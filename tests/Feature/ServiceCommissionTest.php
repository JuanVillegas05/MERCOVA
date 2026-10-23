<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_a_service_with_direct_price_stores_commission_breakdown(): void
    {
        $category = Category::create(['name' => 'Servicios', 'slug' => 'servicios']);
        $seller = User::factory()->create(['role' => 'seller']);

        $response = $this->actingAs($seller, 'sanctum')->postJson('/api/services', [
            'category_id' => $category->id,
            'title' => 'Clases de guitarra',
            'description' => 'Clases personalizadas.',
            'price' => 10638.30,
            'duration' => '1 hora',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.price', '10638.30')
            ->assertJsonPath('data.commission_rate', '0.0600');

        $this->assertEqualsWithDelta(10000.0, (float) $response->json('data.net_amount'), 0.01);
    }

    public function test_publishing_a_service_with_desired_net_price_computes_published_price(): void
    {
        $category = Category::create(['name' => 'Servicios', 'slug' => 'servicios']);
        $seller = User::factory()->create(['role' => 'seller']);

        $response = $this->actingAs($seller, 'sanctum')->postJson('/api/services', [
            'category_id' => $category->id,
            'title' => 'Reparación de electrodomésticos',
            'description' => 'Diagnóstico y mantenimiento.',
            'desired_net_price' => 10000,
            'duration' => '2 horas',
        ]);

        $response->assertStatus(201);
        $this->assertEqualsWithDelta(10638.30, (float) $response->json('data.price'), 0.01);
        $this->assertEqualsWithDelta(10000.0, (float) $response->json('data.net_amount'), 0.01);
    }

    public function test_publishing_a_service_without_any_price_field_fails(): void
    {
        $category = Category::create(['name' => 'Servicios', 'slug' => 'servicios']);
        $seller = User::factory()->create(['role' => 'seller']);

        $response = $this->actingAs($seller, 'sanctum')->postJson('/api/services', [
            'category_id' => $category->id,
            'title' => 'Paseo de mascotas',
            'description' => 'Paseos diarios.',
            'duration' => '45 minutos',
        ]);

        $response->assertStatus(422);
    }

    public function test_booking_a_service_stores_commission_breakdown_on_the_booking(): void
    {
        $category = Category::create(['name' => 'Servicios', 'slug' => 'servicios']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);

        $service = Service::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Diseño de logo',
            'slug' => 'diseno-de-logo-test',
            'description' => 'Branding para pequeñas empresas.',
            'price' => 250000,
            'commission_rate' => 0.06,
            'net_amount' => 235000,
            'duration' => '3 horas',
            'status' => 'active',
        ]);

        $response = $this->actingAs($customer, 'sanctum')->postJson("/api/services/{$service->id}/book", [
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            'guest_count' => 2,
        ]);

        $response->assertStatus(201);
        $this->assertEqualsWithDelta(500000.0, (float) $response->json('data.total'), 0.01);
        $this->assertEqualsWithDelta(30000.0, (float) $response->json('data.commission_amount'), 0.01);
        $this->assertEqualsWithDelta(470000.0, (float) $response->json('data.net_amount'), 0.01);
    }
}
