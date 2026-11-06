<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(User $seller, Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Silla Ergonómica',
            'slug' => 'silla-ergonomica-'.uniqid(),
            'description' => 'Silla de oficina con soporte lumbar.',
            'price' => 450000,
            'commission_rate' => 0.06,
            'net_amount' => 423000,
            'transaction_type' => 'sale',
            'stock' => 5,
            'status' => 'active',
        ], $overrides));
    }

    public function test_seller_is_notified_when_a_new_order_is_created(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_method' => 'debit_card',
        ])->assertStatus(201);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $seller->id,
            'type' => 'order.created',
        ]);
    }

    public function test_buyer_is_notified_when_order_status_changes(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $orderId = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->json('data.id');

        // "pending" solo puede pasar a "processing" (pago confirmado) o
        // "cancelled"; no puede saltar directo a "completed" sin pagar.
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'processing',
        ])->assertOk();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/orders/{$orderId}/status", [
            'status' => 'completed',
        ])->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'type' => 'order.status_changed',
        ]);
    }

    public function test_owner_is_notified_of_new_rental_request_and_renter_of_status_changes(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category, [
            'transaction_type' => 'rent',
            'price_per_day' => 50000,
        ]);

        $rentalId = $this->actingAs($customer, 'sanctum')->postJson('/api/rentals', [
            'product_id' => $product->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ])->json('data.id');

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $seller->id,
            'type' => 'rental.created',
        ]);

        $this->actingAs($seller, 'sanctum')->patchJson("/api/rentals/{$rentalId}/status", [
            'status' => 'confirmed',
        ])->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'type' => 'rental.status_changed',
        ]);
    }

    public function test_user_can_list_and_mark_notifications_as_read(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $notification = UserNotification::record($user->id, 'order.created', 'Título', 'Mensaje');

        $this->actingAs($user, 'sanctum')->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($user, 'sanctum')->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $this->actingAs($user, 'sanctum')->getJson('/api/notifications')
            ->assertJsonPath('unread_count', 0);
    }

    public function test_user_cannot_mark_someone_elses_notification_as_read(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $intruder = User::factory()->create(['role' => 'customer']);
        $notification = UserNotification::record($owner->id, 'order.created', 'Título');

        $this->actingAs($intruder, 'sanctum')->postJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(403);
    }

    public function test_checkout_persists_delivery_information_on_the_order(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/checkout', [
            'recipient_name' => 'Cliente Mercova',
            'recipient_phone' => '+573001234567',
            'address' => 'Calle 10 # 5-23',
            'city' => 'Bogotá',
            'department' => 'Cundinamarca',
            'payment_method' => 'pse',
        ])->assertStatus(201);

        $this->assertDatabaseHas('orders', [
            'buyer_id' => $customer->id,
            'recipient_name' => 'Cliente Mercova',
            'city' => 'Bogotá',
            'department' => 'Cundinamarca',
        ]);
    }
}
