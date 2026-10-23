<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRentalsTest extends TestCase
{
    use RefreshDatabase;

    private function createRental(array $overrides = []): Rental
    {
        $category = Category::firstOrCreate(['slug' => 'tecnologia'], ['name' => 'Tecnología']);
        $seller = User::factory()->create(['role' => 'seller']);
        $renter = User::factory()->create(['role' => 'customer']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Cámara Mirrorless 4K',
            'slug' => 'camara-mirrorless-'.uniqid(),
            'description' => 'Cámara profesional para eventos.',
            'price' => 180000,
            'price_per_day' => 180000,
            'commission_rate' => 0.06,
            'net_amount' => 169200,
            'transaction_type' => 'rent',
            'stock' => 1,
            'status' => 'active',
        ]);

        return Rental::create(array_merge([
            'product_id' => $product->id,
            'renter_id' => $renter->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'days' => 3,
            'price_per_day' => 180000,
            'total' => 540000,
            'commission_rate' => 0.06,
            'commission_amount' => 32400,
            'net_amount' => 507600,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_non_admin_cannot_list_all_rentals(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/rentals')->assertStatus(403);
    }

    public function test_admin_sees_rentals_from_any_owner_or_renter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createRental();
        $this->createRental(['status' => 'finished']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/rentals');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_filter_rentals_by_active_window(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createRental(['status' => 'confirmed']);
        $this->createRental(['status' => 'finished']);
        $this->createRental(['status' => 'cancelled']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/rentals?window=active');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('confirmed', $response->json('data.0.status'));
    }
}
