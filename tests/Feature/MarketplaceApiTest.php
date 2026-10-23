<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_products_are_accessible_without_login(): void
    {
        $category = Category::create([
            'name' => 'Tecnología',
            'slug' => 'tecnologia',
            'description' => 'Productos tecnológicos',
        ]);

        $seller = User::factory()->create(['role' => 'seller']);

        Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Teclado Mecánico',
            'slug' => 'teclado-mecanico',
            'description' => 'Teclado para trabajo y gaming.',
            'price' => 135.50,
            'transaction_type' => 'sale',
            'stock' => 10,
            'condition' => 'Nuevo',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('data.data.0.title', 'Teclado Mecánico');
    }

    public function test_customer_can_buy_product_after_login(): void
    {
        $category = Category::create([
            'name' => 'Hogar',
            'slug' => 'hogar',
            'description' => 'Artículos del hogar',
        ]);

        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Silla Oficina',
            'slug' => 'silla-oficina',
            'description' => 'Silla ergonómica para oficina.',
            'price' => 120,
            'transaction_type' => 'sale',
            'stock' => 5,
            'condition' => 'Usada',
            'status' => 'active',
        ]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_method' => 'debit_card',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.buyer_id', $customer->id)
            ->assertJsonPath('data.seller_id', $seller->id);
    }

    public function test_admin_dashboard_requires_admin_role(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_admin_dashboard_is_accessible_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard');

        $response->assertOk();
    }
}
