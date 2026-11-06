<?php

namespace App\Exports;

use App\Exports\Sheets\SummarySheet;
use App\Exports\Sheets\TransactionsSheet;
use App\Exports\Sheets\UsersSheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MetricsExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly array $report) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new SummarySheet($this->report),
            new TransactionsSheet($this->report['transactions']),
            new UsersSheet($this->report['users']),
        ];
    }
}
