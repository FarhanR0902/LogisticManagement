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
 *
 * Untuk gabungan aktif + storage, isi argumen ketiga true:
 *   new LogistikReportExport($query, 'info', true)
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

    return [
        new LogistikReportSummary($data, $this->filterInfo),
        $data,
    ];
}
}