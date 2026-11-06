<?php

namespace Tests\Feature;

use App\Exports\MetricsExport;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AdminMetricsExportTest extends TestCase
{
    use RefreshDatabase;

    private function createPaidOrder(User $seller, User $customer, Category $category): Order
    {
        $product = Product::create([
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
        ]);

        $order = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'completed',
            'payment_status' => 'approved',
            'total' => 450000,
            'commission_amount' => 27000,
            'net_amount' => 423000,
            'payment_method' => 'debit_card',
            'payment_reference' => 'ORDER-'.uniqid(),
            'transaction_type' => 'sale',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'type' => 'product',
            'quantity' => 1,
            'unit_price' => $product->price,
            'total' => 450000,
            'commission_rate' => 0.06,
            'commission_amount' => 27000,
            'net_amount' => 423000,
        ]);

        return $order;
    }

    public function test_guest_cannot_export_metrics(): void
    {
        $this->getJson('/api/admin/metrics/export/excel?range=this_month')->assertStatus(401);
    }

    public function test_non_admin_cannot_export_metrics(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer, 'sanctum')
            ->get('/api/admin/metrics/export/excel?range=this_month')
            ->assertStatus(403);

        $this->actingAs($customer, 'sanctum')
            ->get('/api/admin/metrics/export/pdf?range=this_month')
            ->assertStatus(403);
    }

    public function test_custom_range_requires_both_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/metrics/export/pdf?range=custom')
            ->assertStatus(422);
    }

    public function test_admin_can_export_metrics_as_excel_with_the_expected_sheets(): void
    {
        Excel::fake();

        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createPaidOrder($seller, $customer, $category);

        $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/metrics/export/excel?range=this_month')
            ->assertOk();

        Excel::assertDownloaded('mercova-metricas-'.now()->startOfMonth()->format('Ymd').'-'.now()->endOfMonth()->format('Ymd').'.xlsx', function (MetricsExport $export) {
            $titles = collect($export->sheets())->map(fn ($sheet) => $sheet->title())->all();

            return $titles === ['Resumen', 'Transacciones', 'Usuarios'];
        });
    }

    public function test_admin_can_export_metrics_as_pdf(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createPaidOrder($seller, $customer, $category);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/metrics/export/pdf?range=this_month');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_custom_range_is_used_in_the_downloaded_filename(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/metrics/export/pdf?range=custom&from=2026-01-01&to=2026-01-31');

        $response->assertOk();
        $this->assertStringContainsString('mercova-metricas-20260101-20260131.pdf', $response->headers->get('Content-Disposition'));
    }
}
