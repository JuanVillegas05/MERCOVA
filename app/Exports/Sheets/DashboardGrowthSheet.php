<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class DashboardGrowthSheet implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{date: string, label: string, new_users: int, new_orders: int}>  $growth
     */
    public function __construct(private readonly array $growth) {}

    public function array(): array
    {
        return array_map(
            fn (array $day) => [$day['date'], $day['new_users'], $day['new_orders']],
            $this->growth
        );
    }

    public function headings(): array
    {
        return ['Fecha', 'Usuarios nuevos', 'Pedidos nuevos'];
    }

    public function title(): string
    {
        return 'Crecimiento diario';
    }
}
