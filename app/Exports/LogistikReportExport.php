<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Laporan lengkap = sheet "Dashboard" + sheet "Ringkasan" + sheet "Data" (Excel Table).
 *
 * $builder = query data TERFILTER per DO (JANGAN dipotong jadi baris wakil di controller):
 *   - LogistikReportData sendiri yang menjumlahkan per grup (no_shipment + tujuan) dari semua DO,
 *     lalu hanya menulis 1 baris wakil per grup ke sheet Data.
 *
 *   Excel::store(
 *       new LogistikReportExport($query, 'Filter: Area=Jawa Barat', $tabel),
 *       'exports/laporan_logistik.xlsx',
 *       'local'
 *   );
 */
class LogistikReportExport implements WithMultipleSheets
{
    public function __construct(
        private $builder,
        private string $filterInfo = '',
        private ?string $tabel = null,
        private bool $ringan = false
    ) {
    }

    public function sheets(): array
    {
        // satu instance Data dipakai bersama oleh Ringkasan & Dashboard (daftar grup di-cache di dalamnya)
        $data = new LogistikReportData($this->builder);
        $data->tabel  = $this->tabel;
        $data->ringan = $this->ringan;

        $summary = new LogistikReportSummary($data, $this->filterInfo);

        return [
            new LogistikReportDashboard($summary, $this->filterInfo),  // sheet 1
            $summary,                                                  // sheet 2
            $data,                                                     // sheet 3
        ];
    }
}