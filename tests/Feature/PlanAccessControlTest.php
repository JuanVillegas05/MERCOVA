<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MembershipPlan;
use App\Models\PlanUsage;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PlanAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_returns_403_once_monthly_listing_limit_is_reached(): void
    {
        MembershipPlan::factory()->free()->create(['monthly_listing_limit' => 1]);
        $seller = User::factory()->create(['role' => 'seller']);
        $category = $this->category();

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/products', $this->productPayload($category))
            ->assertCreated();

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/products', $this->productPayload($category))
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_limit_reached')
            ->assertJsonPath('quota', 'listings');

        $this->assertSame(1, $seller->products()->count());
    }

    public function test_failed_publication_does_not_consume_monthly_quota(): void
    {
        MembershipPlan::factory()->free()->create(['monthly_listing_limit' => 1]);
        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/products', [])
            ->assertUnprocessable();

        $this->assertDatabaseHas('plan_usages', ['user_id' => $seller->id, 'listings_used' => 0]);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/products', $this->productPayload($this->category()))
            ->assertCreated();
    }

    public function test_usage_from_previous_month_does_not_count_against_current_limit(): void
    {
        MembershipPlan::factory()->free()->create(['monthly_listing_limit' => 1]);
        $seller = User::factory()->create(['role' => 'seller']);
        PlanUsage::create([
            'user_id' => $seller->id,
            'period' => now()->subMonthNoOverflow()->format('Y-m'),
            'listings_used' => 1,
        ]);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/products', $this->productPayload($this->category()))
            ->assertCreated();
    }

    public function test_unlimited_plan_and_admin_publish_beyond_free_limit(): void
    {
        MembershipPlan::factory()->free()->create(['monthly_listing_limit' => 0]);
        $premiumSeller = User::factory()->create(['role' => 'seller']);
        Subscription::factory()->for($premiumSeller)->for(MembershipPlan::factory()->premium(), 'plan')->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $category = $this->category();

        $this->actingAs($premiumSeller, 'sanctum')
            ->postJson('/api/products', $this->productPayload($category))
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/products', $this->productPayload($category))
            ->assertCreated();
    }

    public function test_free_plan_returns_403_for_rental_requests_over_monthly_limit(): void
    {
        MembershipPlan::factory()->free()->create(['monthly_request_limit' => 2]);
        $renter = User::factory()->create(['role' => 'customer']);
        PlanUsage::create([
            'user_id' => $renter->id,
            'period' => PlanUsage::currentPeriod(),
            'requests_used' => 2,
        ]);

        $this->actingAs($renter, 'sanctum')
            ->postJson('/api/rentals', [])
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_limit_reached')
            ->assertJsonPath('quota', 'requests');

        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_free_plan_cannot_publish_featured_listings(): void
    {
        MembershipPlan::factory()->free()->create();
        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/products', $this->productPayload($this->category(), ['is_featured' => true]))
            ->assertForbidden()
            ->assertJsonPath('message', 'Tu plan no incluye publicaciones destacadas. Mejora tu plan para destacar productos y servicios.');

        $this->assertSame(0, $seller->products()->count());
    }

    public function test_pro_plan_features_listings_up_to_its_limit(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        Subscription::factory()->for($seller)->for(MembershipPlan::factory()->state(['featured_listings_limit' => 1]), 'plan')->create();
        $category = $this->category();

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/products', $this->productPayload($category, ['is_featured' => true]))
            ->assertCreated()
            ->assertJsonPath('data.is_featured', true);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/products', $this->productPayload($category, ['is_featured' => true]))
            ->assertForbidden()
            ->assertJsonPath('message', 'Alcanzaste el límite de 1 publicaciones destacadas de tu plan.');
    }

    public function test_premium_seller_listing_uses_reduced_plan_commission(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        Subscription::factory()->for($seller)->for(MembershipPlan::factory()->premium(), 'plan')->create();

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/products', $this->productPayload($this->category(), ['price' => 100000]))
            ->assertCreated()
            ->assertJsonPath('data.commission_rate', '0.0400')
            ->assertJsonPath('data.net_amount', '96000.00');
    }

    public function test_plan_feature_middleware_allows_only_plans_with_the_feature(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'plan.feature:advanced_stats'])
            ->get('/api/test-advanced-stats', fn () => ['ok' => true]);

        MembershipPlan::factory()->free()->create();
        $freeUser = User::factory()->create();
        $proUser = User::factory()->create();
        Subscription::factory()->for($proUser)->for(MembershipPlan::factory(), 'plan')->create();

        $this->actingAs($freeUser, 'sanctum')
            ->getJson('/api/test-advanced-stats')
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_feature_required');

        $this->actingAs($proUser, 'sanctum')
            ->getJson('/api/test-advanced-stats')
            ->assertOk();
    }

    private function category(): Category
    {
        return Category::create([
            'name' => 'Hogar',
            'slug' => 'hogar-'.fake()->unique()->numberBetween(1, 99999),
            'description' => 'Artículos para el hogar',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productPayload(Category $category, array $overrides = []): array
    {
        return [
            'category_id' => $category->id,
            'title' => 'Lámpara de mesa '.fake()->unique()->numberBetween(1, 99999),
            'description' => 'Lámpara artesanal en madera.',
            'price' => 50000,
            'transaction_type' => 'sale',
            'stock' => 3,
            ...$overrides,
        ];
    }
}
