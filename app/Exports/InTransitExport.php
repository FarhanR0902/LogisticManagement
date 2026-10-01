<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InTransitExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(private Collection $rows) {}

    public function headings(): array
    {
        return [
            'No', 'No Shipment', 'Tujuan', 'Area', 'Dist Channel', 'Ekspedisi',
            'Mobil', 'Nama Driver', 'No Pol', 'PIC Monitoring', 'Keluar Dari',
            'Tanggal Keluar', 'Lama Di Jalan', 'Estimasi Tiba', 'Alert', 'Remarks',
        ];
    }

    public function collection()
    {
        return $this->rows->values()->map(fn($r, $i) => [
            $i + 1,
            $r->no_shipment,
            $r->tujuan,
            $r->area,
            $r->dist_channel,
            $r->ekpedisi,
            $r->mobil,
            $r->nama_driver,
            $r->no_pol,
            $r->pic_monitoring ?: '-',
            $r->gudang_asal,
            $r->keluar_label,
            $r->hari_transit !== null ? $r->hari_transit . ' Hari' : '-',
            $r->estimasi_label,
            $r->alert_label,
            $r->remarks,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}