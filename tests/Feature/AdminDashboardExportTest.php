<?php

namespace Tests\Feature;

use App\Exports\DashboardSnapshotExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AdminDashboardExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_export_the_dashboard(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer, 'sanctum')
            ->get('/api/admin/dashboard/export/pdf?range=today')
            ->assertStatus(403);

        $this->actingAs($customer, 'sanctum')
            ->get('/api/admin/dashboard/export/excel?range=today')
            ->assertStatus(403);
    }

    public function test_invalid_range_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/dashboard/export/pdf?range=last_year')
            ->assertStatus(422);
    }

    public function test_admin_can_export_the_dashboard_as_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/dashboard/export/pdf?range=month');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_admin_can_export_the_dashboard_as_excel_with_the_expected_sheets(): void
    {
        Excel::fake();
        // El nombre del archivo incluye la hora exacta (now()->format('Ymd-His'));
        // se congela el reloj para que esta aserción no dependa de que el
        // segundo no cambie entre la petición y esta comprobación.
        $this->travelTo(now()->startOfMinute());

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/dashboard/export/excel?range=week')
            ->assertOk();

        Excel::assertDownloaded(
            'mercova-resumen-semana-'.now()->format('Ymd-His').'.xlsx',
            function (DashboardSnapshotExport $export) {
                $titles = collect($export->sheets())->map(fn ($sheet) => $sheet->title())->all();

                return $titles === ['Resumen', 'Crecimiento diario'];
            }
        );
    }
}
