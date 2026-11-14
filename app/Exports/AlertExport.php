<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AlertExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private Collection $rows, private array $gudang) {}

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        $h = ['No', 'Sumber', 'No Shipment', 'Tujuan', 'Area', 'Periode Shipment',
              'Planner', 'PIC Monitoring', 'Ekspedisi', 'Mobil', 'No Pol', 'Driver',
              'Rencana Kirim', 'Tgl Dapat Unit'];

        foreach ($this->gudang as $i => $s) {
            $h[] = 'Tiba Gudang ' . ($i + 1);
            $h[] = 'Keluar Gudang ' . ($i + 1);
        }

        return array_merge($h, ['Estimasi Tiba', 'Tanggal Tiba']);
    }

    public function map($r): array
    {
        static $no = 0;
        $no++;

        $row = [
            $no, $r->sumber, $r->no_shipment, $r->tujuan, $r->area, $r->periode_shipment,
            $r->planner, $r->pic_monitoring, $r->ekpedisi, $r->mobil, $r->no_pol, $r->nama_driver,
            $r->rencana_kirim, $r->tanggal_dpt_unit,
        ];

        foreach ($this->gudang as $s) {
            $row[] = $r->{'tanggal_tiba_gudang' . $s} ?? null;
            $row[] = $r->{'tanggal_keluar_gudang' . $s} ?? null;
        }

        return array_merge($row, [$r->estimasi_tiba, $r->tanggal_tiba]);
    }
}