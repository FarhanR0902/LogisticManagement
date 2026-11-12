<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Laporan lengkap = sheet "Ringkasan" (KPI + tabel per dimensi + grafik) + sheet "Data" (Excel Table).
 *
 * Pemakaian di controller (query JANGAN di-get()):
 *   return Excel::download(
 *       new LogistikReportExport($query, 'Filter: Area=Jawa Barat'),
 *       'Laporan_Logistik_' . date('Ymd_His') . '.xlsx'
 *   );
 */
class LogistikReportExport implements WithMultipleSheets
{
    public function __construct(private $builder, private string $filterInfo = '')
    {
    }

    public function sheets(): array
    {
        $data = new LogistikReportData($this->builder);

        return [
            new LogistikReportSummary($data, $this->filterInfo),
            $data,
        ];
    }
}