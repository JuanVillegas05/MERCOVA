<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_endpoint_includes_business_and_review_stats(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $business = Business::create([
            'user_id' => $seller->id,
            'name' => 'Taller Creativo',
            'slug' => 'taller-creativo',
            'status' => 'active',
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'business_id' => $business->id,
            'category_id' => $category->id,
            'title' => 'Mesa de centro',
            'slug' => 'mesa-de-centro-detalle',
            'description' => 'Mesa de centro en madera.',
            'price' => 350000,
            'commission_rate' => 0.06,
            'net_amount' => 329000,
            'transaction_type' => 'sale',
            'stock' => 2,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/products/mesa-de-centro-detalle');

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.business.name', 'Taller Creativo')
            ->assertJsonPath('data.reviews_count', 0);
    }

    public function test_product_detail_page_renders_for_a_valid_slug(): void
    {
        $response = $this->get('/producto/cualquier-slug');

        $response->assertOk();
    }
}
