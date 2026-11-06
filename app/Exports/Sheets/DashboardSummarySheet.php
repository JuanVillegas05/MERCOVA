<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DashboardSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly array $snapshot) {}

    public function array(): array
    {
        $summary = $this->snapshot['summary'];

        $rows = [
            ['Dinero ingresado (COP)', $summary['revenue']],
            ['Clientes activos', $summary['customers']],
            ['Pedidos', $summary['orders_count']],
            ['Promedio por pedido (COP)', $summary['average_order_value']],
        ];

        foreach ($this->snapshot['order_status_breakdown'] as $status) {
            $rows[] = ["Pedidos · {$status['label']}", $status['count']];
        }

        foreach ($this->snapshot['request_origin_breakdown'] as $origin) {
            $rows[] = ["Origen · {$origin['label']}", $origin['count']];
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
