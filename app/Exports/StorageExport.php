<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StorageExport implements FromCollection, WithHeadings
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = DB::table('logistik_storage');

        // FILTER SAMA PERSIS DENGAN DASHBOARD
        if ($this->request->year) {
            $query->whereYear('tanggal_naik_logistik', $this->request->year);
        }

        if ($this->request->month) {
            $query->whereMonth('tanggal_naik_logistik', $this->request->month);
        }

        if ($this->request->area) {
            $query->where('area', $this->request->area);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'No Shipment',
            'Tanggal Naik',
            'Rencana Kirim',
            'Tujuan',
            'Area',
            'Nilai Muatan',
            'Biaya Kirim',
            'Kategori Ekspedisi',
            'Ekspedisi',
            'Driver',
            'No Pol',
            'Status Pengiriman',
            'Status',
            'Status Akhir',
            'SLA Tiba',
            'SLA Bongkar',
            'Overstay Days',
            'Tanggal Tiba Gudang',
            'Tanggal Keluar Gudang',
            'Tanggal Tiba',
            'Tanggal Bongkar',
            'Remarks',
            'Reason Tiba',
            'Reason Bongkar',
            'Created At',
            'Updated At'
        ];
    }
}