<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Product;
use App\Models\Rental;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
    }

    private function createProduct(User $seller, array $overrides = []): Product
    {
        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $this->category->id,
            'title' => 'Taladro percutor',
            'slug' => 'taladro-percutor-'.uniqid(),
            'description' => 'Taladro de 800 W.',
            'price' => 30000,
            'price_per_day' => 30000,
            'commission_rate' => 0.06,
            'net_amount' => 28200,
            'transaction_type' => 'rent',
            'stock' => 1,
            'status' => 'active',
            ...$overrides,
        ]);
    }

    private function createService(User $seller, array $overrides = []): Service
    {
        return Service::create([
            'seller_id' => $seller->id,
            'category_id' => $this->category->id,
            'title' => 'Clases de guitarra',
            'slug' => 'clases-guitarra-'.uniqid(),
            'description' => 'Clases personalizadas.',
            'price' => 60000,
            'commission_rate' => 0.06,
            'net_amount' => 56400,
            'duration' => '1 hora',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_two_sellers_can_publish_products_with_the_same_title(): void
    {
        $payload = [
            'category_id' => $this->category->id,
            'title' => 'Silla Ergonómica',
            'description' => 'Silla de oficina.',
            'price' => 450000,
            'transaction_type' => 'sale',
            'stock' => 2,
        ];

        $this->actingAs(User::factory()->create(['role' => 'seller']), 'sanctum')
            ->postJson('/api/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'silla-ergonomica');

        $this->actingAs(User::factory()->create(['role' => 'seller']), 'sanctum')
            ->postJson('/api/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'silla-ergonomica-2');
    }

    public function test_renaming_a_product_to_its_own_title_keeps_its_slug(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $product = $this->createProduct($seller, ['title' => 'Taladro', 'slug' => 'taladro']);

        $this->actingAs($seller, 'sanctum')
            ->putJson("/api/products/{$product->id}", ['title' => 'Taladro'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'taladro');
    }

    public function test_two_sellers_can_publish_services_with_the_same_title(): void
    {
        $this->createService(User::factory()->create(), ['slug' => 'clases-de-guitarra']);

        $this->actingAs(User::factory()->create(['role' => 'seller']), 'sanctum')
            ->postJson('/api/services', [
                'category_id' => $this->category->id,
                'title' => 'Clases de guitarra',
                'description' => 'Clases personalizadas.',
                'price' => 60000,
                'duration' => '1 hora',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'clases-de-guitarra-2');
    }

    public function test_an_archived_service_cannot_be_booked(): void
    {
        $service = $this->createService(User::factory()->create(), ['status' => 'archived']);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/services/{$service->id}/book", ['scheduled_at' => now()->addDay()->toDateTimeString(), 'guest_count' => 1])
            ->assertUnprocessable();

        $this->assertSame(0, Booking::count());
    }

    public function test_seller_cannot_book_their_own_service(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $service = $this->createService($seller);

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/services/{$service->id}/book", ['scheduled_at' => now()->addDay()->toDateTimeString(), 'guest_count' => 1])
            ->assertUnprocessable();

        $this->assertSame(0, Booking::count());
    }

    public function test_seller_cannot_rent_their_own_product(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $product = $this->createProduct($seller);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/rentals', [
                'product_id' => $product->id,
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertUnprocessable();

        $this->assertSame(0, Rental::count());
    }

    public function test_seller_cannot_buy_or_add_to_cart_their_own_product(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $product = $this->createProduct($seller, ['transaction_type' => 'sale', 'stock' => 5]);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/orders', ['product_id' => $product->id, 'quantity' => 1])
            ->assertUnprocessable();
        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id])
            ->assertUnprocessable();

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_deleting_a_rented_product_archives_it_and_keeps_the_renters_history(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $renter = User::factory()->create();
        $product = $this->createProduct($seller);
        $rental = Rental::create([
            'product_id' => $product->id,
            'renter_id' => $renter->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'days' => 2,
            'price_per_day' => 30000,
            'total' => 60000,
            'commission_rate' => 0.06,
            'commission_amount' => 3600,
            'net_amount' => 56400,
            'status' => 'confirmed',
        ]);

        $this->actingAs($seller, 'sanctum')->deleteJson("/api/products/{$product->id}")->assertOk();

        $this->assertSame('archived', $product->fresh()->status);
        $this->assertModelExists($rental);
    }

    public function test_deleting_a_product_without_history_removes_it(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $product = $this->createProduct($seller);

        $this->actingAs($seller, 'sanctum')->deleteJson("/api/products/{$product->id}")->assertOk();

        $this->assertModelMissing($product);
    }

    public function test_deleting_a_booked_service_archives_it_and_keeps_the_booking(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $service = $this->createService($seller);
        $booking = Booking::create([
            'service_id' => $service->id,
            'buyer_id' => User::factory()->create()->id,
            'scheduled_at' => now()->addDay(),
            'guest_count' => 1,
            'total' => 60000,
            'status' => 'pending',
        ]);

        $this->actingAs($seller, 'sanctum')->deleteJson("/api/services/{$service->id}")->assertOk();

        $this->assertSame('archived', $service->fresh()->status);
        $this->assertModelExists($booking);
    }
}
