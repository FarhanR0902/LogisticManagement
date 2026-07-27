<?php

namespace App\Exports;

use App\Models\LogistikPengiriman;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PlannerExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * Ambil SEMUA data, tidak dibatasi select() supaya semua kolom
     * yang dibutuhkan (termasuk yang dipakai untuk kalkulasi) tersedia.
     */
   protected $planner;
protected $area;

public function __construct($planner = null, $area = null)
{
    $this->planner = $planner;
    $this->area = $area;
}

public function collection()
{
    $query = LogistikPengiriman::query();

    if (!empty($this->planner)) {
        $query->where('planner', $this->planner);
    }

    if (!empty($this->area)) {
        $query->where('area', $this->area);
    }

    return $query->get();
}

    public function headings(): array
    {
        return [
            'Tanggal Import',
            'Nama Planner',
            'No Shipment',

            'Tanggal Terima Dari Admin',
            'Rencana Kirim',
            'Tanggal Dapat Unit',

            'Planning Loading KACS',
            'Tanggal Tiba KACS',
            'Tanggal Keluar KACS',

            'Planning Loading Sentul',
            'Tanggal Tiba Sentul',
            'Tanggal Keluar Sentul',

            'Planning Loading CCIE',
            'Tanggal Tiba CCIE',
            'Tanggal Keluar CCIE',

            'Tujuan',
            'Route',
            'Pulau',
            'Area',
            'Via Kirim',

            'Dist Channel',
            'Kategori Ekspedisi',
            'Ekspedisi',

            'Lead Time',
            'Nama Driver',
            'No Pol',
            'Mobil',

            'Total Qty',
            'Nilai Muatan',
            'Biaya Kirim',
            'CR (%)',

            'Status Mobil',
            'Lama Waktu Pencarian',
            'SLA Dapat Mobil',

            'Lama Di KACS',
            'Status KACS',
            'SLA Loading KACS',

            'Lama Di Sentul',
            'Status Sentul',
            'SLA Loading Sentul',

            'Lama Di CCIE',
            'Status CCIE',
            'SLA Loading CCIE',

            'Shipping Point',
        ];
    }

    /**
     * Susun 1 baris export persis dengan urutan & logika di tampilan tabel Blade.
     */
    public function map($r): array
    {
        return [
            $r->create_tgl ? Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-',
            $r->planner,
            $r->no_shipment,

            $this->fmtDate($r->tanggal_naik_logistik),
            $this->fmtDate($r->rencana_kirim),
            $this->fmtDate($r->tanggal_dpt_unit),

            $this->fmtDate($r->planning_loading),
            $this->fmtDate($r->tanggal_tiba_gudang),
            $this->fmtDate($r->tanggal_keluar_gudang),

            $this->fmtDate($r->planning_loading_2),
            $this->fmtDate($r->tanggal_tiba_gudang_2),
            $this->fmtDate($r->tanggal_keluar_gudang_2),

            $this->fmtDate($r->planning_loading_3),
            $this->fmtDate($r->tanggal_tiba_gudang_3),
            $this->fmtDate($r->tanggal_keluar_gudang_3),

            $r->tujuan,
            $r->route,
            $r->pulau,
            $r->area,
            $r->via_kirim,

            $r->dist_channel,
            $r->kategori_ekspedisi,
            $r->ekpedisi,

            $r->transport_lead_time,
            $r->nama_driver,
            $r->no_pol,
            $r->mobil,

            $r->total_do_qty_car,
            $r->nilai_muatan,
            $r->biaya_kirim,
            is_numeric($r->cr) ? number_format((float) $r->cr, 4) . '%' : $r->cr,

            $this->statusMobil($r),
            $r->lama_waktu_pencarian,
            $this->slaDapatMobil($r),

            $this->durasi($r->planning_loading, $r->tanggal_tiba_gudang),
            $this->statusGudang($r->planning_loading, $r->tanggal_tiba_gudang),
            $this->slaLoading($r->planning_loading, $r->tanggal_tiba_gudang),

            $this->durasi($r->planning_loading_2, $r->tanggal_tiba_gudang_2),
            $this->statusGudang($r->planning_loading_2, $r->tanggal_tiba_gudang_2),
            $this->slaLoading($r->planning_loading_2, $r->tanggal_tiba_gudang_2),

            $this->durasi($r->planning_loading_3, $r->tanggal_tiba_gudang_3),
            $this->statusGudang($r->planning_loading_3, $r->tanggal_tiba_gudang_3),
            $this->slaLoading($r->planning_loading_3, $r->tanggal_tiba_gudang_3),

            $r->route ? explode('-', trim($r->route))[0] : '-',
        ];
    }

    /* ============================================================
     |  HELPER: format tanggal Y-m-d (biar rapi di Excel)
     * ============================================================ */
    private function fmtDate($value): string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : '';
    }

    /* ============================================================
     |  HELPER: Status Mobil (SUDAH DAPAT / BELUM DAPAT)
     * ============================================================ */
    private function statusMobil($r): string
    {
        return !empty($r->tanggal_dpt_unit) ? 'SUDAH DAPAT' : 'BELUM DAPAT';
    }

    /* ============================================================
     |  HELPER: SLA Dapat Mobil (H+ / Sesuai SLA), tergantung area
     * ============================================================ */
    private function slaDapatMobil($r): string
    {
        if (!$r->rencana_kirim || !$r->tanggal_dpt_unit) {
            return '-';
        }

        $area = strtoupper(trim($r->area));

        $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim)));
        $dptUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit)));

        $selisihHari = floor(($dptUnit - $rencana) / 86400);

        if ($area == 'JABODEBEK' || $area == 'JABODETABEK') {
            $batasHari = 0;
        } elseif ($area == 'JAWA_BARAT') {
            $batasHari = 1;
        } else {
            $batasHari = 2;
        }

        return $selisihHari > $batasHari
            ? 'H+' . ($selisihHari - $batasHari)
            : 'Sesuai SLA';
    }

    /* ============================================================
     |  HELPER: Durasi antara 2 tanggal/jam (mis. "1 Hari 5 Jam")
     * ============================================================ */
    private function durasi($start, $end): string
    {
        if (empty($start) || empty($end)) {
            return '-';
        }

        $startC = Carbon::parse($start);
        $endC = Carbon::parse($end);

        $totalMenit = $startC->diffInMinutes($endC);
        $desimalHari = $totalMenit / 1440;

        $hari = floor($desimalHari);
        $jam = round(($desimalHari - $hari) * 24);

        if ($jam == 24) {
            $jam = 0;
            $hari += 1;
        }

        if ($hari > 0 && $jam > 0) {
            return "{$hari} Hari {$jam} Jam";
        } elseif ($hari > 0) {
            return "{$hari} Hari";
        } elseif ($jam > 0) {
            return "{$jam} Jam";
        }

        return "0 Jam";
    }

    /* ============================================================
     |  HELPER: Status Gudang (On Time / Delay), dibanding per hari
     * ============================================================ */
    private function statusGudang($planning, $tiba): string
    {
        if (empty($planning) || empty($tiba)) {
            return '-';
        }

        $startDay = Carbon::parse($planning)->startOfDay();
        $endDay = Carbon::parse($tiba)->startOfDay();

        return $endDay->gt($startDay) ? 'Delay' : 'On Time';
    }

    /* ============================================================
     |  HELPER: SLA Loading (H+ / Sesuai SLA), dibanding per hari
     * ============================================================ */
    private function slaLoading($planning, $tiba): string
    {
        if (empty($planning) || empty($tiba)) {
            return '-';
        }

        $start = Carbon::parse($planning)->startOfDay();
        $end = Carbon::parse($tiba)->startOfDay();

        if ($end->gt($start)) {
            $selisihHari = $start->diffInDays($end);
            return "H+{$selisihHari}";
        }

        return 'Sesuai SLA';
    }
}