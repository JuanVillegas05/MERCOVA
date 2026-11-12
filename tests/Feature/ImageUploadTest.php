<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_is_uploaded_to_cloudinary_with_a_signed_request(): void
    {
        $this->freezeTime();
        config(['services.cloudinary.url' => 'cloudinary://123456:api-secret@demo-cloud']);
        Http::preventStrayRequests();
        Http::fake(['api.cloudinary.com/*' => Http::response([
            'secure_url' => 'https://res.cloudinary.com/demo-cloud/image/upload/v1/mercova/listings/foto.jpg',
        ])]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->post('/api/uploads/image', ['image' => UploadedFile::fake()->image('foto.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('url', 'https://res.cloudinary.com/demo-cloud/image/upload/v1/mercova/listings/foto.jpg');

        $timestamp = (string) now()->timestamp;
        $expectedSignature = sha1("folder=mercova/listings&timestamp={$timestamp}&transformation=c_limit,w_1600,h_1600,q_auto".'api-secret');

        Http::assertSent(function (Request $request) use ($expectedSignature) {
            $fields = collect($request->data())->pluck('contents', 'name');

            return $request->url() === 'https://api.cloudinary.com/v1_1/demo-cloud/image/upload'
                && $fields['api_key'] === '123456'
                && $fields['signature'] === $expectedSignature
                && ! str_contains((string) $request->body(), 'api-secret');
        });
    }

    public function test_image_falls_back_to_the_public_disk_without_cloudinary(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();

        $response = $this->actingAs(User::factory()->create(), 'sanctum')
            ->post('/api/uploads/image', ['image' => UploadedFile::fake()->image('foto.png')], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertCount(1, Storage::disk('public')->files('listings'));
        $this->assertStringContainsString('/storage/listings/', $response->json('url'));
    }

    public function test_upload_rejects_files_that_are_not_images(): void
    {
        config(['services.cloudinary.url' => 'cloudinary://123456:api-secret@demo-cloud']);
        Http::preventStrayRequests();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->post('/api/uploads/image', ['image' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_upload_requires_authentication(): void
    {
        $this->postJson('/api/uploads/image')->assertUnauthorized();
    }
}
