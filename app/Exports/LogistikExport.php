<?php

namespace App\Exports;

use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LogistikExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * Kalau class ini dibuat dengan parameter (misal dari SalesController
     * yang sudah kirim collection hasil query ter-filter), pakai itu.
     * Kalau dibuat tanpa parameter (misal dari LogistikController::export()),
     * ambil sendiri dari model + filter session dist_channel.
     */
    protected $rows;

    public function __construct($rows = null)
    {
        $this->rows = $rows;
    }

    

    /**
     * =========================================================
     * DATA SOURCE
     * =========================================================
     * Export WAJIB ikut scope dist_channel yang lagi login,
     * sama persis kayak filterByDistChannel() di controller.
     * Jadi user role sales cuma bisa export data channel dia sendiri.
     */
    public function collection()
    {
        if ($this->rows !== null) {
            return collect($this->rows);
        }

        $query = LogistikPengiriman::query();

        $channel = session('dist_channel');

        if ($channel) {
            $query->whereRaw('LOWER(TRIM(dist_channel)) = ?', [strtolower(trim($channel))]);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    /**
     * =========================================================
     * HEADER KOLOM EXCEL
     * =========================================================
     */
    public function headings(): array
    {
        return [
            'Tanggal Naik Logistik',
            'Rencana Kirim',
            'Transport Lead Time',
            'Planner',
            'No Shipment',
            'Status Perjalanan',
            'Distribution Channel',
            'Tujuan',
            'Area',
            'Status Mobil',
            'Mobil',
            'Nilai Muatan',
            'Biaya Kirim',
            'CR (%)',
            'Kategori Ekspedisi',
            'Ekspedisi',
            'Tanggal Dpt Unit',
            'Lama Waktu Pencarian',
            'SLA Dapat Mobil',

            // Gudang 1
            'Planning Loading',
            'Tanggal Tiba Gudang',
            'Tanggal Keluar Gudang',
            'Durasi Gudang',
            'Status Gudang',
            'SLA Loading',

            // Gudang 2
            'Planning Loading 2',
            'Tanggal Tiba Gudang 2',
            'Tanggal Keluar Gudang 2',
            'Lama di Gudang 2',
            'SLA Loading 2',
            'Status Gudang 2',

            // Gudang 3
            'Planning Loading 3',
            'Tanggal Tiba Gudang 3',
            'Tanggal Keluar Gudang 3',
            'Lama di Gudang 3',
            'SLA Loading 3',
            'Status Gudang 3',

            'PIC Monitoring',
            'Nama Kapal',
            'ETD',
            'ETA',
            'Status Kendaraan',
            'Alert Estimasi Tiba',

            'Act Urutan Bongkar',
            'Total DO Qty Car',
            'Qty Monitoring',
            
            'Selisih Qty',
            'Biaya Kuli',
            'Remarks Qty',
            'Act PGI Date',

            'ATD',
            'ATA',
            'Estimasi Tiba',
            'Tanggal Tiba',
            'Lama Perjalanan (Hari)',
            'SLA Tiba',
            'Tanggal Bongkar',
            'Overstay Bongkar (Hari)',
            'SLA Bongkar',
            'Reason Tiba',
            'Reason Bongkar',

            'Status Pengiriman',
            'Status Delivered',

            'Remarks',
            'Route',
            'Asal (Route)',
            'Pulau',
            'Via Kirim',
        ];
    }

    /**
     * =========================================================
     * MAPPING TIAP BARIS
     * =========================================================
     */
    public function map($r): array
    {
        // ---------------------------------------------------
        // STATUS PERJALANAN UTAMA (badge di kolom awal tabel)
        // ---------------------------------------------------
        $dpt = $r->tanggal_dpt_unit;

        $tibaG1 = $r->tanggal_tiba_gudang;
        $tibaG2 = $r->tanggal_tiba_gudang_2;
        $tibaG3 = $r->tanggal_tiba_gudang_3;

        $tibaG = !empty($tibaG1) || !empty($tibaG2) || !empty($tibaG3);

        $keluarG = !empty($r->tanggal_keluar_gudang)
            || !empty($r->tanggal_keluar_gudang_2)
            || !empty($r->tanggal_keluar_gudang_3);

        $tibaAkhir    = $r->tanggal_tiba;
        $bongkarAkhir = $r->tanggal_bongkar;

        if (empty($dpt)) {
            $statusPerjalanan = 'MENCARI UNIT';
        } elseif (!empty($dpt) && !$tibaG) {
            $statusPerjalanan = 'PERJALANAN KE GUDANG';
        } elseif ($tibaG && empty($keluarG)) {
            $statusPerjalanan = 'DI GUDANG';
        } elseif (!empty($keluarG) && empty($tibaAkhir)) {
            $statusPerjalanan = 'PERJALANAN KE TUJUAN';
        } elseif (!empty($tibaAkhir) && !empty($bongkarAkhir)) {
            $statusPerjalanan = 'SUDAH SELESAI';
        } elseif (!empty($tibaAkhir)) {
            $statusPerjalanan = 'SUDAH TIBA TUJUAN';
        } else {
            $statusPerjalanan = '-';
        }

        // ---------------------------------------------------
        // STATUS MOBIL (SUDAH DAPAT / BELUM DAPAT)
        // ---------------------------------------------------
        $statusMobil = (empty($r->rencana_kirim) || empty($r->tanggal_dpt_unit))
            ? 'BELUM DAPAT'
            : 'SUDAH DAPAT';

        // ---------------------------------------------------
        // GUDANG 1: DURASI, STATUS ON TIME/DELAY, SLA LOADING
        // ---------------------------------------------------
        $durasiText   = '-';
        $statusGudang = '-';
        $slaLoading   = '-';

        if (!empty($r->planning_loading) && !empty($r->tanggal_tiba_gudang)) {
            $start = \Carbon\Carbon::parse($r->planning_loading);
            $end   = \Carbon\Carbon::parse($r->tanggal_tiba_gudang);

            $totalMenit  = $start->diffInMinutes($end);
            $desimalHari = $totalMenit / 1440;

            $hari = floor($desimalHari);
            $jam  = round(($desimalHari - $hari) * 24);

            if ($jam == 24) {
                $jam = 0;
                $hari += 1;
            }

            if ($hari > 0 && $jam > 0) {
                $durasiText = "{$hari} Hari {$jam} Jam";
            } elseif ($hari > 0) {
                $durasiText = "{$hari} Hari";
            } elseif ($jam > 0) {
                $durasiText = "{$jam} Jam";
            } else {
                $durasiText = "0 Jam";
            }

            $startDay = $start->copy()->startOfDay();
            $endDay   = $end->copy()->startOfDay();

            $statusGudang = $endDay->gt($startDay) ? 'Delay' : 'On Time';

            if ($endDay->gt($startDay)) {
                $selisihHari = $startDay->diffInDays($endDay);
                $slaLoading  = "H+{$selisihHari}";
            } else {
                $slaLoading = 'Sesuai SLA';
            }
        }

        // ---------------------------------------------------
        // ALERT ESTIMASI TIBA (H-x / OVERDUE / ON TRACK)
        // ---------------------------------------------------
        $estimasi     = !empty($r->estimasi_tiba) ? strtotime($r->estimasi_tiba) : null;
        $estimasiShow = $estimasi ? date('d-m-Y', $estimasi) : '-';
        $alert        = '-';

        if (!$r->tanggal_tiba && $estimasi) {
            $today    = strtotime(date('Y-m-d'));
            $hariSisa = floor(($estimasi - $today) / 86400);

            if ($hariSisa < 0) {
                $alert = 'OVERDUE';
            } elseif ($hariSisa == 0) {
                $alert = 'H-0';
            } elseif ($hariSisa == 1) {
                $alert = 'H-1';
            } elseif ($hariSisa == 2) {
                $alert = 'H-2';
            } elseif ($hariSisa == 3) {
                $alert = 'H-3';
            } elseif ($hariSisa <= 7) {
                $alert = 'H-' . $hariSisa;
            } else {
                $alert = 'ON TRACK';
            }
        } elseif ($r->tanggal_tiba) {
            $alert = 'TIBA';
        }

        // ---------------------------------------------------
        // LAMA PERJALANAN (keluar gudang terakhir -> tiba)
        // ---------------------------------------------------
        $keluarTimestamp = collect([
            $r->tanggal_keluar_gudang,
            $r->tanggal_keluar_gudang_2,
            $r->tanggal_keluar_gudang_3,
        ])
            ->filter()
            ->map(fn($d) => strtotime($d))
            ->max();

        $tibaTs = $r->tanggal_tiba ? strtotime($r->tanggal_tiba) : null;

        $lamaPerjalanan = '-';
        if ($tibaTs && $keluarTimestamp) {
            $lamaPerjalanan = floor(($tibaTs - $keluarTimestamp) / 86400);
        }

  // ---------------------------------------------------
// OVERSTAY BONGKAR
// Ambil langsung dari database agar sama dengan tampilan aplikasi
// ---------------------------------------------------
if (!is_null($r->overstay_days)) {
    $overBongkar = $r->overstay_days;
} elseif (!empty($r->tanggal_tiba) && !empty($r->tanggal_bongkar)) {
    $tibaC    = \Carbon\Carbon::parse($r->tanggal_tiba)->startOfDay();
    $bongkarC = \Carbon\Carbon::parse($r->tanggal_bongkar)->startOfDay();

    $overBongkar = $tibaC->diffInDays($bongkarC);
} else {
    $overBongkar = '-';
}
        // ---------------------------------------------------
        // STATUS PENGIRIMAN (Dalam Perjalanan / Unloading / Ontime / Delay)
        // ---------------------------------------------------
        $slaTiba    = strtoupper(trim($r->sla_tiba ?? ''));
        $slaBongkar = strtoupper(trim($r->sla_bongkar ?? ''));

        if (empty($r->tanggal_tiba)) {
            $statusPengiriman = 'Dalam Perjalanan';
        } elseif (!empty($r->tanggal_tiba) && empty($r->tanggal_bongkar)) {
            $statusPengiriman = 'Sudah Tiba - Dalam Pembongkaran';
        } elseif ($slaTiba == 'ON TIME' && $slaBongkar == 'ON TIME') {
            $statusPengiriman = 'Pengiriman On Time';
        } else {
            $statusPengiriman = 'Pengiriman Delay';
        }

        // ---------------------------------------------------
        // STATUS DELIVERED (kombinasi SLA tiba & bongkar)
        // ---------------------------------------------------
        if ($slaTiba == 'ON TIME' && $slaBongkar == 'ON TIME') {
            $statusDelivered = 'Delivered Ontime';
        } elseif ($slaTiba == 'DELAY' && $slaBongkar == 'ON TIME') {
            $statusDelivered = 'Delay Perjalanan';
        } elseif ($slaTiba == 'ON TIME' && $slaBongkar == 'DELAY') {
            $statusDelivered = 'Delay Pembongkaran';
        } elseif ($slaTiba == 'DELAY' && $slaBongkar == 'DELAY') {
            $statusDelivered = 'Delivered Delay';
        } else {
            $statusDelivered = 'Belum Selesai';
        }

        // ---------------------------------------------------
        // ROUTE AWAL (bagian pertama sebelum tanda '-')
        // ---------------------------------------------------
        $routeAwal = $r->route ? explode('-', trim($r->route))[0] : '-';

        // ---------------------------------------------------
        // HELPER FORMAT TANGGAL
        // ---------------------------------------------------
        $fmt = fn($d) => $d ? date('d-m-Y', strtotime($d)) : '-';

        return [
            $fmt($r->tanggal_naik_logistik),
            $fmt($r->rencana_kirim),
            $r->transport_lead_time,
            $r->planner,
            $r->no_shipment,
            $statusPerjalanan,
            $r->dist_channel,
            $r->tujuan,
            $r->area,
            $statusMobil,
            $r->mobil,
           
            $r->nilai_muatan,
            $r->biaya_kirim,
            $r->cr,
            $r->kategori_ekspedisi,
            $r->ekpedisi,
            $fmt($r->tanggal_dpt_unit),
            $r->lama_waktu_pencarian,
            $r->sla_dapat_mobil,

            // Gudang 1
            $fmt($r->planning_loading),
            $fmt($r->tanggal_tiba_gudang),
            $fmt($r->tanggal_keluar_gudang),
            $durasiText,
            $statusGudang,
            $slaLoading,

            // Gudang 2
            $fmt($r->planning_loading_2),
            $fmt($r->tanggal_tiba_gudang_2),
            $fmt($r->tanggal_keluar_gudang_2),
            $r->lama_digudang_2,
            $r->sla_loading_2,
            $r->status_gudang_2,

            // Gudang 3
            $fmt($r->planning_loading_3),
            $fmt($r->tanggal_tiba_gudang_3),
            $fmt($r->tanggal_keluar_gudang_3),
            $r->lama_digudang_3,
            $r->sla_loading_3,
            $r->status_gudang_3,

            $r->pic_monitoring,
            $r->nama_kapal,
            $r->etd,
            $r->eta,
            $r->status_kendaraan,
            $alert,

            $r->act_urutan_bongkar,
            $r->total_do_qty_car,
            $r->qty_monitoring,
            
            $r->selisih_qty,
             $r->total_biaya_kuli,
            $r->remarks_qty,
            $fmt($r->act_pgi_date),

            $r->atd,
            $r->ata,
            $estimasiShow,
            $fmt($r->tanggal_tiba),
            $lamaPerjalanan,
            $r->sla_tiba,
            $fmt($r->tanggal_bongkar),
            $overBongkar,
            $r->sla_bongkar,
            $r->reason_tiba,
            $r->reason_bongkar,

            $statusPengiriman,
            $statusDelivered,

            $r->remarks,
            $r->route,
            $routeAwal,
            $r->pulau,
            $r->via_kirim,
        ];
    }

    /**
     * =========================================================
     * STYLE HEADER (bold)
     * =========================================================
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
