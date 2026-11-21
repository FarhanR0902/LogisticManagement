<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Laporan lengkap = sheet "Dashboard" + sheet "Ringkasan" + sheet "Data" (Excel Table).
 *
 *   return Excel::download(
 *       new LogistikReportExport($query, 'Filter: Area=Jawa Barat'),
 *       'Laporan_Logistik_' . date('Ymd_His') . '.xlsx'
 *   );
 */
class LogistikReportExport implements WithMultipleSheets
{
    public function __construct(
        private $builder,
        private string $filterInfo = '',
        private ?string $tabel = null
    ) {
    }

    public function sheets(): array
    {
        $data = new LogistikReportData($this->builder);
        $data->tabel = $this->tabel;

        $summary = new LogistikReportSummary($data, $this->filterInfo);

        return [
            new LogistikReportDashboard($summary, $this->filterInfo),  // sheet 1
            $summary,                                                  // sheet 2
            $data,                                                     // sheet 3
        ];
    }
}