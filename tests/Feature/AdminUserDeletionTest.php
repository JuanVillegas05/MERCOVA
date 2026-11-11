<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\UserAccountDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(User $seller, int $stock = 5): Product
    {
        $category = Category::firstOrCreate(['slug' => 'hogar'], ['name' => 'Hogar']);

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Silla Ergonómica',
            'slug' => 'silla-ergonomica-'.uniqid(),
            'description' => 'Silla de oficina con soporte lumbar.',
            'price' => 450000,
            'commission_rate' => 0.06,
            'net_amount' => 423000,
            'transaction_type' => 'sale',
            'stock' => $stock,
            'status' => 'active',
        ]);
    }

    private function createOrder(User $buyer, User $seller, array $attributes = []): Order
    {
        return Order::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 450000,
            'payment_method' => 'pse',
            'payment_reference' => 'ORDER-'.uniqid(),
            'transaction_type' => 'sale',
            ...$attributes,
        ]);
    }

    public function test_admin_deletes_a_user_revoking_access_and_anonymizing_personal_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer', 'email' => 'borrar@mercova.test', 'phone' => '3001234567']);
        $customerToken = $customer->createToken('mercova')->plainTextToken;

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$customer->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Usuario eliminado correctamente']);

        $deleted = User::withTrashed()->findOrFail($customer->id);
        $this->assertSoftDeleted($deleted);
        $this->assertSame(UserAccountDeletion::DELETED_NAME, $deleted->name);
        $this->assertStringEndsWith('@deleted.invalid', $deleted->email);
        $this->assertNull($deleted->phone);
        $this->assertSame(0, $deleted->tokens()->count());
        $this->assertTrue(AdminNotification::query()->where('type', 'user.deleted')->exists());
        $this->assertFalse(AdminNotification::query()->where('message', 'like', '%borrar@mercova.test%')->exists());

        $this->app['auth']->forgetGuards();
        $this->withToken($customerToken)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_deleted_user_cannot_log_in_and_the_email_can_be_registered_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer', 'email' => 'vuelve@mercova.test', 'password' => bcrypt('password123')]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/users/{$customer->id}")->assertOk();

        $this->postJson('/api/login', ['email' => 'vuelve@mercova.test', 'password' => 'password123'])->assertUnauthorized();
        $this->postJson('/api/register', [
            'name' => 'Cuenta Nueva',
            'email' => 'vuelve@mercova.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();
    }

    public function test_deleted_user_disappears_from_the_admin_list_but_their_orders_stay_with_the_other_party(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['role' => 'seller']);
        $buyer = User::factory()->create(['role' => 'customer']);
        $order = $this->createOrder($buyer, $seller, ['status' => 'completed', 'payment_status' => 'approved']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/users/{$seller->id}")->assertOk();

        $listedIds = collect($this->actingAs($admin, 'sanctum')->getJson('/api/admin/users')->json('data'))->pluck('id');
        $this->assertNotContains($seller->id, $listedIds);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->actingAs($buyer, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.seller.name', UserAccountDeletion::DELETED_NAME);
    }

    public function test_deleting_a_seller_archives_listings_and_cancels_unpaid_orders_restoring_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['role' => 'seller']);
        $buyer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, stock: 3);
        $order = $this->createOrder($buyer, $seller);
        $order->items()->create(['product_id' => $product->id, 'type' => 'product', 'quantity' => 2, 'unit_price' => 225000, 'total' => 450000]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/users/{$seller->id}")->assertOk();

        $this->assertSame('archived', $product->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_user_with_a_paid_order_in_progress_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['role' => 'seller']);
        $this->createOrder(User::factory()->create(), $seller, ['status' => 'processing', 'payment_status' => 'approved']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$seller->id}")
            ->assertUnprocessable();

        $this->assertNotSoftDeleted($seller);
    }

    public function test_admin_accounts_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/users/{$otherAdmin->id}")->assertUnprocessable();
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/users/{$admin->id}")->assertUnprocessable();

        $this->assertNotSoftDeleted($otherAdmin);
        $this->assertNotSoftDeleted($admin);
    }

    public function test_only_admins_can_delete_users(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $victim = User::factory()->create(['role' => 'customer']);

        $this->deleteJson("/api/admin/users/{$victim->id}")->assertUnauthorized();
        $this->actingAs($customer, 'sanctum')->deleteJson("/api/admin/users/{$victim->id}")->assertForbidden();

        $this->assertNotSoftDeleted($victim);
    }

    public function test_deleting_an_already_deleted_user_returns_not_found(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/users/{$customer->id}")->assertOk();
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/users/{$customer->id}")->assertNotFound();
    }
}
