<?php

namespace App\Exports;

use App\Models\LogistikPengirimanPasuruan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PasuruanExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * Kalau class ini dibuat dengan parameter $rows (misal dari SalesController
     * yang sudah kirim collection hasil query ter-filter), pakai itu apa adanya
     * dan filter di bawah (planner/area/date/month/year) DIABAIKAN, karena
     * data sudah di-filter duluan oleh pemanggilnya.
     *
     * Kalau dibuat TANPA $rows (dipanggil dari LogistikController::export()),
     * data diambil sendiri dari model + filter session dist_channel + filter
     * dari halaman Data Logistik (planner, area, date, month, year).
     */
    protected $rows;
    protected $filters;

    public function __construct($rows = null, array $filters = [])
    {
        $this->rows = $rows;
        $this->filters = $filters;
    }

    /**
     * =========================================================
     * DATA SOURCE
     * =========================================================
     * Export WAJIB ikut scope dist_channel yang lagi login,
     * sama persis kayak filterByDistChannel() di controller,
     * DAN ikut filter aktif dari halaman Data Logistik.
     */
    public function collection()
    {
        if ($this->rows !== null) {
            return collect($this->rows);
        }

        $query = LogistikPengirimanPasuruan::query();

        $channel = session('dist_channel_pasuruan');

        if ($channel) {
            $query->whereRaw('LOWER(TRIM(dist_channel_pasuruan)) = ?', [strtolower(trim($channel))]);
        }

        // ----------------------------------------------------
        // FILTER: Planner (dropdown #filterPlanner)
        // ----------------------------------------------------
        if (!empty($this->filters['planner'])) {
            $query->where('planner_pasuruan', $this->filters['planner']);
        }

        // ----------------------------------------------------
        // FILTER: Area (dropdown #filterArea)
        // ----------------------------------------------------
        if (!empty($this->filters['area'])) {
            $query->where('area_pasuruan', $this->filters['area']);
        }

        // ----------------------------------------------------
        // FILTER: Tanggal spesifik (input #filterDate)
        // Kolom acuan sama dengan kolom pertama tabel:
        // tanggal_terima_po_pasuruan
        // ----------------------------------------------------
        if (!empty($this->filters['date'])) {
            $query->whereDate('tanggal_terima_po_pasuruan', $this->filters['date']);
        }

        // ----------------------------------------------------
        // FILTER: Bulan (dropdown #filterMonth)
        // ----------------------------------------------------
        if (!empty($this->filters['month'])) {
            $query->whereMonth('tanggal_terima_po_pasuruan', $this->filters['month']);
        }

        // ----------------------------------------------------
        // FILTER: Tahun (dropdown #filterYear)
        // ----------------------------------------------------
        if (!empty($this->filters['year'])) {
            $query->whereYear('tanggal_terima_po_pasuruan', $this->filters['year']);
        }

        return $query->orderBy('id')->get();
    }

    /**
     * =========================================================
     * HEADER KOLOM EXCEL
     * =========================================================
     */
    public function headings(): array
    {
        return [
            'Tanggal Naik Logistik_pasuruan',
            'Rencana Kirim_pasuruan',
            'Transport Lead Time_pasuruan',
            'Planner_pasuruan',
            'No Shipment_pasuruan',
            'Status Perjalanan_pasuruan',
            'Distribution Channel_pasuruan',
            'Tujuan_pasuruan',
            'Area_pasuruan',

            'Ketersediaan Unit_pasuruan',
            'Status Mobil_pasuruan',

            'Mobil_pasuruan',
            'Nilai Muatan_pasuruan',
            'Biaya Kirim_pasuruan',
            'CR (%)_pasuruan',
            'Kategori Ekspedisi_pasuruan',
            'Ekspedisi_pasuruan',
            'Tanggal Dpt Unit_pasuruan',
            'Lama Waktu Pencarian_pasuruan',
            'SLA Dapat Mobil_pasuruan',

            // Gudang 1
            'Planning Loading_pasuruan',
            'Tanggal Tiba Gudang_pasuruan',
            'Tanggal Keluar Gudang_pasuruan',
            'Durasi Gudang_pasuruan',
            'Status Gudang_pasuruan',
            'SLA Loading',

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
        $dpt = $r->tanggal_dpt_unit_pasuruan;

        $tibaGudang   = $r->tanggal_tiba_gudang_pasuruan;
        $keluarGudang = $r->tanggal_keluar_gudang_pasuruan;

        $tibaAkhir    = $r->tanggal_tiba_pasuruan;
        $bongkarAkhir = $r->tanggal_bongkar_pasuruan;

        if (empty($dpt)) {
            $statusPerjalanan = 'MENCARI UNIT';
        } elseif (empty($tibaGudang)) {
            $statusPerjalanan = 'PERJALANAN KE GUDANG';
        } elseif (!empty($tibaGudang) && empty($keluarGudang)) {
            $statusPerjalanan = 'DI GUDANG';
        } elseif (!empty($keluarGudang) && empty($tibaAkhir)) {
            $statusPerjalanan = 'PERJALANAN KE TUJUAN';
        } elseif (!empty($tibaAkhir) && empty($bongkarAkhir)) {
            $statusPerjalanan = 'SUDAH TIBA TUJUAN';
        } elseif (!empty($tibaAkhir) && !empty($bongkarAkhir)) {
            $statusPerjalanan = 'SUDAH SELESAI';
        } else {
            $statusPerjalanan = '-';
        }

        // ---------------------------------------------------
        // STATUS MOBIL (SUDAH DAPAT / BELUM DAPAT)
        // ---------------------------------------------------
        $statusMobil = !empty($r->tanggal_dpt_unit_pasuruan)
            ? 'SUDAH DAPAT'
            : 'BELUM DAPAT';

        // ---------------------------------------------------
        // GUDANG 1: DURASI, STATUS ON TIME/DELAY, SLA LOADING
        // ---------------------------------------------------
        $durasiText   = '-';
        $statusGudang = '-';
        $slaLoading   = '-';

        if (!empty($r->planning_loading_pasuruan) &&
            !empty($r->tanggal_tiba_gudang_pasuruan))
        {
            $start = \Carbon\Carbon::parse($r->planning_loading_pasuruan);
            $end   = \Carbon\Carbon::parse($r->tanggal_tiba_gudang_pasuruan);

            $menit = $start->diffInMinutes($end);

            $hari = floor($menit / 1440);
            $jam  = floor(($menit % 1440) / 60);

            if ($hari > 0 && $jam > 0) {
                $durasiText = "{$hari} Hari {$jam} Jam";
            } elseif ($hari > 0) {
                $durasiText = "{$hari} Hari";
            } else {
                $durasiText = "{$jam} Jam";
            }

            if ($end->startOfDay()->gt($start->startOfDay())) {
                $statusGudang = 'Delay';

                $selisih = $start->diffInDays($end);

                $slaLoading = "H+{$selisih}";
            } else {
                $statusGudang = 'On Time';
                $slaLoading = 'Sesuai SLA';
            }
        }

        // ---------------------------------------------------
        // LAMA WAKTU PENCARIAN & SLA DAPAT MOBIL (dihitung live)
        // ---------------------------------------------------
        $lamaWaktuPencarianLive = '-';
        $slaDapatMobilLive      = '-';

        if (!empty($r->rencana_kirim_pasuruan) && !empty($r->tanggal_dpt_unit_pasuruan)) {

            $area = strtoupper(trim($r->area_pasuruan ?? ''));

            $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
            $dptUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));

            $selisihHari = floor(($dptUnit - $rencana) / 86400);

            $lamaWaktuPencarianLive = $selisihHari <= 0 ? 'H+0' : 'H+' . $selisihHari;

            if ($area == 'JABODEBEK' || $area == 'JABODETABEK') {
                $batasHari = 0;
            } elseif ($area == 'JAWA_BARAT') {
                $batasHari = 1;
            } else {
                $batasHari = 2;
            }

            $slaDapatMobilLive = $selisihHari > $batasHari
                ? 'H+' . ($selisihHari - $batasHari)
                : 'Sesuai SLA';
        }

        // ---------------------------------------------------
        // ALERT ESTIMASI TIBA (H-x / OVERDUE / ON TRACK)
        // ---------------------------------------------------
        $estimasi = !empty($r->estimasi_tiba_pasuruan)
            ? strtotime($r->estimasi_tiba_pasuruan)
            : null;

        $estimasiShow = $estimasi
            ? date('d-m-Y', $estimasi)
            : '-';

        $alert = '-';

        if (!$r->tanggal_tiba_pasuruan && $estimasi) {

            $today = strtotime(date('Y-m-d'));

            $hari = floor(($estimasi - $today) / 86400);

            if ($hari < 0) {
                $alert = 'OVERDUE';
            } elseif ($hari == 0) {
                $alert = 'H-0';
            } elseif ($hari <= 7) {
                $alert = 'H-' . $hari;
            } else {
                $alert = 'ON TRACK';
            }

        } elseif ($r->tanggal_tiba_pasuruan) {

            $alert = 'TIBA';

        }

        // ---------------------------------------------------
        // LAMA PERJALANAN (keluar gudang terakhir -> tiba)
        // ---------------------------------------------------
        $keluarTimestamp = !empty($r->tanggal_keluar_gudang_pasuruan)
            ? strtotime($r->tanggal_keluar_gudang_pasuruan)
            : null;

        $tibaTimestamp = !empty($r->tanggal_tiba_pasuruan)
            ? strtotime($r->tanggal_tiba_pasuruan)
            : null;

        $lamaPerjalanan = '-';

        if ($keluarTimestamp && $tibaTimestamp) {

            $lamaPerjalanan =
                floor(($tibaTimestamp - $keluarTimestamp) / 86400);

        }

        // ---------------------------------------------------
        // OVERSTAY BONGKAR (tanggal_bongkar vs tanggal_tiba)
        // ---------------------------------------------------
 if (!is_null($r->overstay_days_pasuruan)) {
    $overBongkar = $r->overstay_days_pasuruan;
} elseif (!empty($r->tanggal_tiba_pasuruan) && !empty($r->tanggal_bongkar_pasuruan)) {
    $tibaC = \Carbon\Carbon::parse($r->tanggal_tiba_pasuruan)->startOfDay();
    $bongkarC = \Carbon\Carbon::parse($r->tanggal_bongkar_pasuruan)->startOfDay();

    $overBongkar = max(0, $tibaC->diffInDays($bongkarC, false));
} else {
    $overBongkar = 0;
}

        // ---------------------------------------------------
        // STATUS PENGIRIMAN (Dalam Perjalanan / Unloading / Ontime / Delay)
        // ---------------------------------------------------
        $slaTiba = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
        $slaBongkar = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));

        if (empty($r->tanggal_tiba_pasuruan)) {

            $statusPengiriman = 'Dalam Perjalanan';

        } elseif (!empty($r->tanggal_tiba_pasuruan)
            && empty($r->tanggal_bongkar_pasuruan)) {

            $statusPengiriman = 'Sudah Tiba - Dalam Pembongkaran';

        } elseif ($slaTiba == 'ON TIME'
            && $slaBongkar == 'ON TIME') {

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
        $routeAwal = $r->route_pasuruan
            ? explode('-', trim($r->route_pasuruan))[0]
            : '-';

        // ---------------------------------------------------
        // EKSPEDISI (fallback ke kolom lama kalau kosong)
        // ---------------------------------------------------
        $ekspedisiFinal = $r->ekspedisi_pasuruan ?: $r->ekpedisi_pasuruan;

        // ---------------------------------------------------
        // HELPER FORMAT TANGGAL
        // ---------------------------------------------------
        $fmt = fn($d) => $d
            ? date('d-m-Y', strtotime($d))
            : '-';

        return [
            $fmt($r->tanggal_terima_po_pasuruan),
            $fmt($r->rencana_kirim_pasuruan),
            $r->transport_lead_time_pasuruan,
            $r->planner_pasuruan,
            $r->no_shipment_pasuruan,

            $statusPerjalanan,

            $r->dist_channel_pasuruan,
            $r->tujuan_pasuruan,
            $r->area_pasuruan,

            $r->ketersediaan_unit_pasuruan,
            $statusMobil,

            $r->mobil_pasuruan,
            $r->nilai_muatan_pasuruan,
            $r->biaya_kirim_pasuruan,
            $r->cr_pasuruan,

            $r->kategori_ekspedisi_pasuruan,
            $ekspedisiFinal,

            $fmt($r->tanggal_dpt_unit_pasuruan),
            $lamaWaktuPencarianLive,
            $slaDapatMobilLive,

            $fmt($r->planning_loading_pasuruan),
            $fmt($r->tanggal_tiba_gudang_pasuruan),
            $fmt($r->tanggal_keluar_gudang_pasuruan),
            $durasiText,
            $statusGudang,
            $slaLoading,

            $r->pic_monitoring_pasuruan,
            $r->nama_kapal_pasuruan,
            $fmt($r->etd_pasuruan),
            $fmt($r->eta_pasuruan),
            $r->status_kendaraan_pasuruan,
            $alert,

            $r->act_urutan_bongkar_pasuruan,
            $r->total_do_pasuruan,
            $r->actual_delivery_quantity_pasuruan,
            $r->selisih_quantity_pasuruan,
            $r->total_biaya_kuli_pasuruan,
            $r->reason_selisih_quantity_pasuruan,
            $fmt($r->act_pgi_date_pasuruan),

            $fmt($r->atd_pasuruan),
            $fmt($r->ata_pasuruan),
            $estimasiShow,
            $fmt($r->tanggal_tiba_pasuruan),
            $lamaPerjalanan,
            $r->sla_tiba_pasuruan,
            $fmt($r->tanggal_bongkar_pasuruan),
            $overBongkar,
            $r->sla_bongkar_pasuruan,
            $r->reason_waktu_tiba_pasuruan,
            $r->reason_waktu_bongkar_pasuruan,

            $statusPengiriman,
            $statusDelivered,

            $r->remarks_pasuruan,
            $r->route_pasuruan,
            $routeAwal,
            $r->pulau_pasuruan,
            $r->via_kirim_pasuruan,
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