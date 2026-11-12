<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledTasksEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_does_not_exist_without_a_configured_secret(): void
    {
        config(['mercova.cron_secret' => null]);

        $this->postJson('/api/cron/run', [], ['Authorization' => 'Bearer cualquier-cosa'])->assertNotFound();
    }

    public function test_endpoint_rejects_a_wrong_secret(): void
    {
        config(['mercova.cron_secret' => 'secreto-correcto']);

        $this->postJson('/api/cron/run')->assertUnauthorized();
        $this->postJson('/api/cron/run', [], ['Authorization' => 'Bearer secreto-falso'])->assertUnauthorized();
    }

    public function test_endpoint_cancels_stale_orders_and_restores_their_stock(): void
    {
        config(['mercova.cron_secret' => 'secreto-correcto']);
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $product = Product::create([
            'seller_id' => User::factory()->create(['role' => 'seller'])->id,
            'category_id' => $category->id,
            'title' => 'Silla',
            'slug' => 'silla',
            'description' => 'Silla de oficina.',
            'price' => 100000,
            'transaction_type' => 'sale',
            'stock' => 5,
            'status' => 'active',
        ]);
        $order = $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/orders', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->json('data');
        $this->assertSame(3, $product->fresh()->stock);

        $this->travel(config('mercova.order_expiry_hours') + 1)->hours();
        $this->postJson('/api/cron/run', [], ['Authorization' => 'Bearer secreto-correcto'])
            ->assertOk()
            ->assertJsonPath('data.orders:cancel-stale.exit_code', 0)
            ->assertJsonPath('data.subscriptions:reconcile-payments.exit_code', 0);

        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'cancelled']);
        $this->assertSame(5, $product->fresh()->stock);
    }
}
