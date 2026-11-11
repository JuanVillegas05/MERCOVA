<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_public_registration_cannot_create_admin(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Intruso',
            'email' => 'intruso@mercova.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/admin/users/{$other->id}")
            ->assertStatus(403);

        $this->actingAs($customer, 'sanctum')
            ->patchJson("/api/admin/users/{$other->id}/status", ['is_active' => false])
            ->assertStatus(403);
    }

    public function test_admin_can_deactivate_a_user_but_not_another_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$customer->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$otherAdmin->id}/status", ['is_active' => false])
            ->assertStatus(422);
    }

    public function test_deactivating_a_user_revokes_the_tokens_they_already_had(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $customerToken = $customer->createToken('mercova')->plainTextToken;

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$customer->id}/status", ['is_active' => false])
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($customerToken)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_admin_can_update_order_status_with_new_vocabulary(): void
    {
        $category = Category::create([
            'name' => 'Hogar',
            'slug' => 'hogar-admin-test',
            'description' => 'Artículos del hogar',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Mesa de centro',
            'slug' => 'mesa-de-centro-admin-test',
            'description' => 'Mesa de madera.',
            'price' => 60,
            'transaction_type' => 'sale',
            'stock' => 3,
            'status' => 'active',
        ]);

        $order = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->json('data');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order['id']}/status", ['status' => 'processing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_creating_an_order_generates_an_unread_admin_notification(): void
    {
        $category = Category::create([
            'name' => 'Tecnología',
            'slug' => 'tecnologia-admin-test',
            'description' => 'Dispositivos',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Mouse inalámbrico',
            'slug' => 'mouse-inalambrico-admin-test',
            'description' => 'Mouse ergonómico.',
            'price' => 15,
            'transaction_type' => 'sale',
            'stock' => 10,
            'status' => 'active',
        ]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(201);

        $notifications = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications?unread=1')
            ->assertOk()
            ->json('data.data');

        $this->assertNotEmpty($notifications);
        $notificationId = $notifications[0]['id'];

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/notifications/{$notificationId}/read")
            ->assertOk();

        $unreadAfter = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications?unread=1')
            ->json('unread_count');

        $this->assertSame(0, $unreadAfter);
    }
}
