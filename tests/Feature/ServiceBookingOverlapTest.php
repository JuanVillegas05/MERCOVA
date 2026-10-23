<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceBookingOverlapTest extends TestCase
{
    use RefreshDatabase;

    private function createService(User $seller, Category $category, array $overrides = []): Service
    {
        return Service::create(array_merge([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Clases de guitarra',
            'slug' => 'clases-guitarra-'.uniqid(),
            'description' => 'Clases personalizadas.',
            'price' => 60000,
            'commission_rate' => 0.06,
            'net_amount' => 56400,
            'duration' => '1 hora',
            'status' => 'active',
        ], $overrides));
    }

    public function test_duration_parser_reads_hours_and_minutes(): void
    {
        $category = Category::create(['name' => 'Servicios', 'slug' => 'servicios']);
        $seller = User::factory()->create(['role' => 'seller']);

        $this->assertSame(60, $this->createService($seller, $category, ['duration' => '1 hora'])->durationInMinutes());
        $this->assertSame(180, $this->createService($seller, $category, ['duration' => '3 horas'])->durationInMinutes());
        $this->assertSame(45, $this->createService($seller, $category, ['duration' => '45 minutos'])->durationInMinutes());
        $this->assertSame(60, $this->createService($seller, $category, ['duration' => 'a domicilio'])->durationInMinutes());
    }

    public function test_cannot_book_an_overlapping_time_slot(): void
    {
        $category = Category::create(['name' => 'Servicios', 'slug' => 'servicios']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);
        $service = $this->createService($seller, $category, ['duration' => '2 horas']);

        $this->actingAs($customerA, 'sanctum')->postJson("/api/services/{$service->id}/book", [
            'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'guest_count' => 1,
        ])->assertStatus(201);

        $this->actingAs($customerB, 'sanctum')->postJson("/api/services/{$service->id}/book", [
            'scheduled_at' => now()->addDays(2)->setTime(11, 0)->toDateTimeString(),
            'guest_count' => 1,
        ])->assertStatus(422);
    }

    public function test_can_book_a_non_overlapping_time_slot(): void
    {
        $category = Category::create(['name' => 'Servicios', 'slug' => 'servicios']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);
        $service = $this->createService($seller, $category, ['duration' => '1 hora']);

        $this->actingAs($customerA, 'sanctum')->postJson("/api/services/{$service->id}/book", [
            'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'guest_count' => 1,
        ])->assertStatus(201);

        $this->actingAs($customerB, 'sanctum')->postJson("/api/services/{$service->id}/book", [
            'scheduled_at' => now()->addDays(2)->setTime(11, 0)->toDateTimeString(),
            'guest_count' => 1,
        ])->assertStatus(201);
    }

    public function test_cancelled_booking_frees_up_the_time_slot(): void
    {
        $category = Category::create(['name' => 'Servicios', 'slug' => 'servicios']);
        $seller = User::factory()->create(['role' => 'seller']);
        $admin = User::factory()->create(['role' => 'admin']);
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);
        $service = $this->createService($seller, $category, ['duration' => '1 hora']);

        $bookingId = $this->actingAs($customerA, 'sanctum')->postJson("/api/services/{$service->id}/book", [
            'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'guest_count' => 1,
        ])->json('data.id');

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/bookings/{$bookingId}/status", [
            'status' => 'cancelled',
        ])->assertOk();

        $this->actingAs($customerB, 'sanctum')->postJson("/api/services/{$service->id}/book", [
            'scheduled_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'guest_count' => 1,
        ])->assertStatus(201);
    }
}
