<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pestaña "Resumen": todas las métricas de App\Services\MetricsReportService
 * aplanadas en filas "métrica / valor", para que se pueda abrir en Excel sin
 * necesitar ninguna fórmula ni referencia cruzada entre pestañas.
 */
class SummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly array $report) {}

    public function array(): array
    {
        $summary = $this->report['summary'];
        $financial = $this->report['financial'];
        $performance = $this->report['performance'];

        $rows = [
            ['Rango del reporte', $this->report['range']['from']->toDateString().' a '.$this->report['range']['to']->toDateString()],
            ['Usuarios registrados', $summary['total_users']],
            ['Emprendimientos activos', $summary['active_businesses']],
            ['Publicaciones en venta', $summary['listings_sale']],
            ['Publicaciones en alquiler', $summary['listings_rent']],
            ['Servicios publicados', $summary['services']],
            ['Transacciones en el rango', $summary['transactions_in_range']],
            ['Ingresos totales (COP)', $financial['total_revenue']],
            ['Comisiones de Mercova (COP)', $financial['total_commission']],
            ['Monto neto a vendedores (COP)', $financial['net_amount']],
            ['Tasa de ocupación de alquiler (%)', $performance['rental_occupancy_rate']],
        ];

        foreach ($performance['order_status_breakdown'] as $status) {
            $rows[] = ["Pedidos · {$status['label']}", $status['count']];
        }

        foreach ($performance['top_categories'] as $category) {
            $rows[] = ["Categoría top · {$category['name']} (COP)", $category['revenue']];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Métrica', 'Valor'];
    }

    public function title(): string
    {
        return 'Resumen';
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
