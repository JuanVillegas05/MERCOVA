<?php

namespace App\Http\Controllers\Api;

use App\Exports\MetricsExport;
use App\Http\Controllers\Controller;
use App\Services\MetricsReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Exportación de las métricas del panel de administración. Restringido por
 * el middleware "admin" del grupo de rutas (ver routes/api.php): llegar
 * hasta aquí ya implica que el usuario autenticado es admin.
 */
class AdminMetricsExportController extends Controller
{
    public function __construct(private readonly MetricsReportService $metrics) {}

    public function excel(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);
        $report = $this->metrics->build($from, $to);

        return Excel::download(new MetricsExport($report), $this->filename($from, $to, 'xlsx'));
    }

    public function pdf(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);
        $report = $this->metrics->build($from, $to);

        $pdf = Pdf::loadView('admin.exports.metrics-pdf', [
            'report' => $report,
            'generatedAt' => now(),
        ])->setPaper('letter');

        return $pdf->download($this->filename($from, $to, 'pdf'));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $validated = $request->validate([
            'range' => ['required', 'string', 'in:this_month,last_quarter,custom'],
            'from' => ['required_if:range,custom', 'date'],
            'to' => ['required_if:range,custom', 'date', 'after_or_equal:from'],
        ]);

        return match ($validated['range']) {
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_quarter' => $this->lastQuarterRange(),
            'custom' => [Carbon::parse($validated['from'])->startOfDay(), Carbon::parse($validated['to'])->endOfDay()],
        };
    }

    /**
     * El trimestre calendario inmediatamente anterior al actual (no "los
     * últimos 3 meses"): si hoy cae en Q1 (ene-mar), el trimestre anterior es
     * el Q4 del año pasado.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function lastQuarterRange(): array
    {
        $currentQuarterStartMonth = ((int) floor((now()->month - 1) / 3)) * 3 + 1;
        $currentQuarterStart = now()->setDay(1)->setMonth($currentQuarterStartMonth)->startOfDay();

        $lastQuarterEnd = $currentQuarterStart->copy()->subDay()->endOfDay();
        $lastQuarterStart = $lastQuarterEnd->copy()->startOfMonth()->subMonthsNoOverflow(2)->startOfMonth();

        return [$lastQuarterStart, $lastQuarterEnd];
    }

    private function filename(Carbon $from, Carbon $to, string $extension): string
    {
        return "mercova-metricas-{$from->format('Ymd')}-{$to->format('Ymd')}.{$extension}";
    }
}
