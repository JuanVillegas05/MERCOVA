<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_with_direct_price_stores_commission_breakdown(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);

        $response = $this->actingAs($seller, 'sanctum')->postJson('/api/products', [
            'category_id' => $category->id,
            'title' => 'Lámpara de mesa',
            'description' => 'Lámpara decorativa para sala.',
            'price' => 10638.30,
            'transaction_type' => 'sale',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.price', '10638.30')
            ->assertJsonPath('data.commission_rate', '0.0600');

        $this->assertEqualsWithDelta(10000.0, (float) $response->json('data.net_amount'), 0.01);
    }

    public function test_publishing_with_desired_net_price_computes_published_price(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);

        $response = $this->actingAs($seller, 'sanctum')->postJson('/api/products', [
            'category_id' => $category->id,
            'title' => 'Silla reclinable',
            'description' => 'Silla reclinable en cuero.',
            'desired_net_price' => 10000,
            'transaction_type' => 'sale',
        ]);

        $response->assertStatus(201);
        $this->assertEqualsWithDelta(10638.30, (float) $response->json('data.price'), 0.01);
        $this->assertEqualsWithDelta(10000.0, (float) $response->json('data.net_amount'), 0.01);
    }

    public function test_publishing_without_any_price_field_fails(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);

        $response = $this->actingAs($seller, 'sanctum')->postJson('/api/products', [
            'category_id' => $category->id,
            'title' => 'Mesa de centro',
            'description' => 'Mesa de centro en madera.',
            'transaction_type' => 'sale',
        ]);

        $response->assertStatus(422);
    }

    public function test_commission_preview_endpoint_returns_breakdown_without_creating_anything(): void
    {
        $response = $this->postJson('/api/products/commission-preview', [
            'desired_net_price' => 10000,
        ]);

        $response->assertOk();
        $this->assertEqualsWithDelta(10638.30, (float) $response->json('data.gross_price'), 0.01);
    }

    public function test_switching_a_rent_product_to_sale_without_a_price_fails_instead_of_keeping_stale_price(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Cámara',
            'slug' => 'camara-update-test',
            'description' => 'Cámara para alquilar.',
            'price' => 50000,
            'price_per_day' => 50000,
            'commission_rate' => 0.06,
            'net_amount' => 47000,
            'transaction_type' => 'rent',
            'status' => 'active',
        ]);

        $response = $this->actingAs($seller, 'sanctum')->putJson("/api/products/{$product->id}", [
            'transaction_type' => 'sale',
        ]);

        $response->assertStatus(422);
        $this->assertSame('rent', $product->fresh()->transaction_type);
    }

    public function test_switching_a_rent_product_to_sale_with_a_price_recomputes_commission(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Cámara',
            'slug' => 'camara-update-test-2',
            'description' => 'Cámara que se vende.',
            'price' => 50000,
            'price_per_day' => 50000,
            'commission_rate' => 0.06,
            'net_amount' => 47000,
            'transaction_type' => 'rent',
            'status' => 'active',
        ]);

        $response = $this->actingAs($seller, 'sanctum')->putJson("/api/products/{$product->id}", [
            'transaction_type' => 'sale',
            'price' => 900000,
        ]);

        $response->assertOk();
        $fresh = $product->fresh();
        $this->assertSame('sale', $fresh->transaction_type);
        $this->assertEqualsWithDelta(900000.0, (float) $fresh->price, 0.01);
        $this->assertEqualsWithDelta(846000.0, (float) $fresh->net_amount, 0.01);
    }
}
