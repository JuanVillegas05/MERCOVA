<?php

namespace App\Http\Controllers\Api;

use App\Exports\DashboardSnapshotExport;
use App\Http\Controllers\Controller;
use App\Services\DashboardSnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Exportación de la pantalla "Resumen y crecimiento" del panel admin tal
 * cual se ve en pantalla: mismo selector de rango (hoy/semana/mes/todo) y
 * mismas cifras que AdminController::dashboard(), vía
 * App\Services\DashboardSnapshotService. Restringido por el middleware
 * "admin" del grupo de rutas (ver routes/api.php).
 */
class AdminDashboardExportController extends Controller
{
    public function __construct(private readonly DashboardSnapshotService $dashboardSnapshot) {}

    public function pdf(Request $request)
    {
        $snapshot = $this->resolveSnapshot($request);

        $pdf = Pdf::loadView('admin.exports.dashboard-pdf', [
            'snapshot' => $snapshot,
            'generatedAt' => now(),
        ])->setPaper('letter');

        return $pdf->download($this->filename($snapshot['range'], 'pdf'));
    }

    public function excel(Request $request)
    {
        $snapshot = $this->resolveSnapshot($request);

        return Excel::download(new DashboardSnapshotExport($snapshot), $this->filename($snapshot['range'], 'xlsx'));
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSnapshot(Request $request): array
    {
        $validated = $request->validate([
            'range' => ['required', 'string', 'in:today,week,month,all'],
        ]);

        return $this->dashboardSnapshot->build($validated['range']);
    }

    private function filename(string $range, string $extension): string
    {
        $rangeLabels = ['today' => 'hoy', 'week' => 'semana', 'month' => 'mes', 'all' => 'todo'];

        return 'mercova-resumen-'.($rangeLabels[$range] ?? $range).'-'.now()->format('Ymd-His').".{$extension}";
    }
}
