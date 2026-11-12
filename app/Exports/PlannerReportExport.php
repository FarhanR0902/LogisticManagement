<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PlannerReportExport implements WithMultipleSheets
{
    public function __construct(private array $filters, private string $filterInfo = '') {}

    public function sheets(): array
    {
        $data = new PlannerReportData($this->filters);
        return [new PlannerReportSummary($data, $this->filterInfo), $data];
    }
}