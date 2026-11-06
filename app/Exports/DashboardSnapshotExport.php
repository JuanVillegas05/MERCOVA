<?php

namespace App\Exports;

use App\Exports\Sheets\DashboardGrowthSheet;
use App\Exports\Sheets\DashboardSummarySheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DashboardSnapshotExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly array $snapshot) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new DashboardSummarySheet($this->snapshot),
            new DashboardGrowthSheet($this->snapshot['growth']),
        ];
    }
}
