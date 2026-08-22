<?php

namespace App\Exports;

use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MonitoringExport implements FromCollection, WithHeadings
{
    protected $picMonitoring;
    protected $area;

    /**
     * Terima filter dari controller (nilai dari request GET).
     */
    public function __construct($picMonitoring = null, $area = null)
    {
        $this->picMonitoring = $picMonitoring;
        $this->area          = $area;
    }

    public function collection()
    {
        $query = LogistikPengiriman::query();

        if (!empty($this->picMonitoring)) {
            $query->where('pic_monitoring', $this->picMonitoring);
        }

        if (!empty($this->area)) {
            $query->where('area', $this->area);
        }

        return $query->get()->map(function ($r) {

            /*
            |--------------------------------------------------------------------------
            | Keluar Gudang Terakhir
            |--------------------------------------------------------------------------
            */
            $keluar = collect([
                $r->tanggal_keluar_gudang,
                $r->tanggal_keluar_gudang_2,
                $r->tanggal_keluar_gudang_3,
            ])
                ->filter()
                ->map(fn($d) => strtotime($d))
                ->max();

            /*
            |--------------------------------------------------------------------------
            | Lama Perjalanan
            |--------------------------------------------------------------------------
            */
            $tiba = $r->tanggal_tiba ? strtotime($r->tanggal_tiba) : null;

            $lamaPerjalanan = '-';
            if ($keluar && $tiba) {
                $lamaPerjalanan = floor(($tiba - $keluar) / 86400);
            }

            /*
            |--------------------------------------------------------------------------
            | Alert / Status Tiba (persis logika di Blade: H-0, H-1, H-2, H-3, dst)
            |--------------------------------------------------------------------------
            */
            $estimasi = !empty($r->estimasi_tiba) ? strtotime($r->estimasi_tiba) : null;
            $alertText = '-';

            if (!$r->tanggal_tiba && $estimasi) {

                $today = strtotime(date('Y-m-d'));
                $hariSisa = floor(($estimasi - $today) / 86400);

                if ($hariSisa < 0) {
                    $alertText = 'OVERDUE';
                } elseif ($hariSisa == 0) {
                    $alertText = 'H-0';
                } elseif ($hariSisa == 1) {
                    $alertText = 'H-1';
                } elseif ($hariSisa == 2) {
                    $alertText = 'H-2';
                } elseif ($hariSisa == 3) {
                    $alertText = 'H-3';
                } elseif ($hariSisa <= 7) {
                    $alertText = 'H-' . $hariSisa;
                } else {
                    $alertText = 'ON TRACK';
                }
            }

            $statusTibaColumn = $r->tanggal_tiba ? 'TIBA' : $alertText;

            return [

                // Tanggal Keluar Gudang (tampilan tanggal saja, kolom pertama)
                $keluar ? date('d-m-Y', $keluar) : '-',

                $r->act_pgi_date,
                $r->dist_channel,
                $r->area,
                $r->no_shipment,
                $r->tujuan,
                $r->ekpedisi,

                $r->pic_monitoring,
                $r->status_kendaraan,
                $statusTibaColumn,

                $r->total_do_qty_car,
                $r->qty_monitoring,
                $r->selisih_qty,
                $r->biaya_kuli,
                $r->total_biaya_kuli,
                $r->remarks_qty,
                $r->act_urutan_bongkar,

                // Keluar (versi lengkap dengan jam)
                $keluar ? date('d-m-Y H:i', $keluar) : '-',

                $r->estimasi_tiba ? date('d-m-Y', strtotime($r->estimasi_tiba)) : '-',
                $r->tanggal_tiba,
                $lamaPerjalanan,
                $r->sla_tiba,

                $r->tanggal_bongkar,
                $r->overstay_days,
                $r->sla_bongkar,

                $r->reason_tiba,
                $r->reason_bongkar,
                $r->remarks,

                $r->nama_kapal,
                $r->etd,
                $r->eta,
                $r->atd,
                $r->ata,
            ];
        });
    }

    public function headings(): array
    {
        return [

            'Tanggal Keluar Gudang',
            'Act PGI Date',
            'Dist Channel',
            'Area',
            'No Shipment',
            'Tujuan',
            'Ekspedisi',

            'PIC Monitoring',
            'Status Kendaraan',
            'Alert / Status Tiba',

            'Total DO Qty',
            'Total DO Qty Actual',
            'Selisih Qty',
            'biaya_kuli',
            'total_biaya_kuli',
            'Reason Qty',
            'Urutan Bongkar',

            'Keluar Gudang (Detail)',
            'Estimasi Tiba',
            'Tanggal Tiba',
            'Lama Perjalanan',
            'SLA Tiba',

            'Tanggal Bongkar',
            'Overstay',
            'SLA Bongkar',

            'Reason Tiba',
            'Reason Bongkar',
            'Remarks',

            'Nama Kapal',
            'ETD',
            'ETA',
            'ATD',
            'ATA',
        ];
    }
}