<?php

namespace App\Exports;

use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlannerExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return LogistikPengiriman::select(
            'create_tgl',
            'planner',
            'no_shipment',
            'tanggal_naik_logistik',
            'rencana_kirim',
            'tanggal_dpt_unit',
            'tanggal_tiba_gudang',
            'tanggal_keluar_gudang',
            'tanggal_tiba_gudang_2',
            'tanggal_keluar_gudang_2',
            'tanggal_tiba_gudang_3',
            'tanggal_keluar_gudang_3',
            'tujuan',
            'route',
            'area',
            'dist_channel',
            'kategori_ekspedisi',
            'ekpedisi',
            'transport_lead_time',
            'mobil',
            'nilai_muatan',
            'biaya_kirim',
            'cr'
        )->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal Import',
            'Planner',
            'No Shipment',
            'Tanggal Terima Admin',
            'Rencana Kirim',
            'Tanggal Dapat Unit',
            'Tanggal Tiba KACS',
            'Tanggal Keluar KACS',
            'Tanggal Tiba Sentul',
            'Tanggal Keluar Sentul',
            'Tanggal Tiba CCIE',
            'Tanggal Keluar CCIE',
            'Tujuan',
            'Route',
            'Area',
            'Dist Channel',
            'Kategori Ekspedisi',
            'Ekspedisi',
            'Lead Time',
            'Mobil',
            'Nilai Muatan',
            'Biaya Kirim',
            'CR (%)'
        ];
    }
}