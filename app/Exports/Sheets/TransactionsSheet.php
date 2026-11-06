<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TransactionsSheet implements FromCollection, WithColumnFormatting, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly Collection $orders) {}

    public function collection(): Collection
    {
        return $this->orders;
    }

    public function headings(): array
    {
        return ['ID', 'Fecha', 'Comprador', 'Vendedor', 'Total', 'Comisión', 'Neto', 'Estado', 'Estado de pago'];
    }

    public function map($order): array
    {
        return [
            $order->id,
            $order->created_at->format('Y-m-d H:i'),
            $order->buyer?->name ?? '—',
            $order->seller?->name ?? '—',
            (float) $order->total,
            (float) $order->commission_amount,
            (float) $order->net_amount,
            $order->status,
            $order->payment_status ?? '—',
        ];
    }

    public function title(): string
    {
        return 'Transacciones';
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }
}
