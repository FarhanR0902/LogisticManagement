<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MonitoringReportExport implements WithMultipleSheets
{
    public function __construct(private array $filters, private string $filterInfo = '') {}

    public function sheets(): array
    {
        $data = new MonitoringReportData($this->filters);
        return [new MonitoringReportSummary($data, $this->filterInfo), $data];
    }
}