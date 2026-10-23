<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use RefreshDatabase;

    private function createRentalProduct(User $seller, Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
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
        ], $overrides));
    }

    public function test_customer_can_request_a_rental_and_commission_is_applied(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createRentalProduct($seller, $category);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'payment_method' => 'pse',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.days', 3);

        $this->assertEqualsWithDelta(540000, (float) $response->json('data.total'), 0.01);
        $this->assertEqualsWithDelta(507600, (float) $response->json('data.net_amount'), 0.01);
    }

    public function test_cannot_request_overlapping_dates_for_the_same_product(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);
        $product = $this->createRentalProduct($seller, $category);

        $this->actingAs($customerA, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ])->assertStatus(201);

        $this->actingAs($customerB, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ])->assertStatus(422);
    }

    public function test_non_overlapping_dates_are_allowed(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);
        $product = $this->createRentalProduct($seller, $category);

        $this->actingAs($customerA, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ])->assertStatus(201);

        $this->actingAs($customerB, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(6)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
        ])->assertStatus(201);
    }

    public function test_cannot_rent_a_sale_product(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createRentalProduct($seller, $category, ['transaction_type' => 'sale']);

        $this->actingAs($customer, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ])->assertStatus(422);
    }

    public function test_owner_can_confirm_then_activate_then_finish_a_rental(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createRentalProduct($seller, $category);

        $rental = $this->actingAs($customer, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ])->json('data');

        $this->actingAs($seller, 'sanctum')->patchJson("/api/rentals/{$rental['id']}/status", [
            'status' => 'confirmed',
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->actingAs($seller, 'sanctum')->patchJson("/api/rentals/{$rental['id']}/status", [
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.status', 'active');

        $this->actingAs($seller, 'sanctum')->patchJson("/api/rentals/{$rental['id']}/status", [
            'status' => 'finished',
        ])->assertOk()->assertJsonPath('data.status', 'finished');
    }

    public function test_renter_cannot_confirm_their_own_rental(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createRentalProduct($seller, $category);

        $rental = $this->actingAs($customer, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ])->json('data');

        $this->actingAs($customer, 'sanctum')->patchJson("/api/rentals/{$rental['id']}/status", [
            'status' => 'confirmed',
        ])->assertStatus(403);
    }

    public function test_renter_can_cancel_their_own_pending_request(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createRentalProduct($seller, $category);

        $rental = $this->actingAs($customer, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ])->json('data');

        $this->actingAs($customer, 'sanctum')->patchJson("/api/rentals/{$rental['id']}/status", [
            'status' => 'cancelled',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cannot_skip_states(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createRentalProduct($seller, $category);

        $rental = $this->actingAs($customer, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ])->json('data');

        $this->actingAs($seller, 'sanctum')->patchJson("/api/rentals/{$rental['id']}/status", [
            'status' => 'active',
        ])->assertStatus(422);
    }

    public function test_availability_endpoint_reflects_confirmed_overlap(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createRentalProduct($seller, $category);

        $this->actingAs($customer, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->getJson("/api/products/{$product->id}/availability?start_date=".now()->addDays(4)->toDateString().'&end_date='.now()->addDays(6)->toDateString())
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->getJson("/api/products/{$product->id}/availability?start_date=".now()->addDays(10)->toDateString().'&end_date='.now()->addDays(12)->toDateString())
            ->assertOk()
            ->assertJsonPath('data.available', true);
    }
}
