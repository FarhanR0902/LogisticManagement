<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LogistikPengiriman extends Model
{
    protected $table = 'logistik_pengiriman';

    protected $fillable = [
        'no',
        'tanggal_naik_logistik',
        'rencana_kirim',
        'transport_lead_time',
        'planner',
        'no_shipment',
        'tujuan',
        'area',
        'ketersediaan_unit',
        'mobil',
        'perubahan_mobil',
        'nilai_muatan',
        'biaya_kirim',
        'cr',
        'kategori_ekspedisi',
        'ekspedisi',
        'nama_driver',
        'no_pol',
        'status_pengiriman',
        'tanggal_dpt_unit',
        'planning_loading',
        'tanggal_tiba_gudang',
        'tanggal_keluar_gudang',
        'lama_digudang',
        'status_gudang',
        'sla_loading',
        'keterangan',
        'lama_waktu_pencarian',
        'sla_dapat_mobil',
        'pic_monitoring',
        'status_kendaraan',
        'monitoring_alert',
        'action_required',
        'act_urutan_bongkar',
        'tanggal_tiba',
        'lama_perjalanan',
        'sla_tiba',
        'tanggal_bongkar',
        'overstay_days',
        'sla_bongkar',
        'status_akhir',
        'created_at'
    ];

    public $timestamps = true;

    /* ================= BASIC ================= */

    public static function getAll()
    {
        return self::all();
    }

    public static function getData($limit, $offset)
    {
        return self::orderBy('id', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    public static function countAll()
    {
        return self::count();
    }

    /* ================= FILTER ================= */

    public static function filter($query, $bulan = null, $tahun = null)
    {
        if ($bulan) {
            $query->whereMonth('tanggal_naik_logistik', $bulan);
        }

        if ($tahun) {
            $query->whereYear('tanggal_naik_logistik', $tahun);
        }

        return $query;
    }

    /* ================= KPI ================= */

    public static function totalNilaiMuatan($bulan = null, $tahun = null)
    {
        $q = self::query();
        self::filter($q, $bulan, $tahun);

        return $q->sum('nilai_muatan');
    }

    public static function totalBiaya($bulan = null, $tahun = null)
    {
        $q = self::query();
        self::filter($q, $bulan, $tahun);

        return $q->sum('biaya_kirim');
    }

    /* ================= DASHBOARD ================= */

    public static function dashboardSummary()
    {
        return self::selectRaw("
            COUNT(no_shipment) as total_shipment,

            SUM(CASE WHEN mobil IS NOT NULL AND mobil != '' THEN 1 ELSE 0 END) as sudah_armada,
            SUM(CASE WHEN mobil IS NULL OR mobil = '' THEN 1 ELSE 0 END) as belum_armada,

            SUM(CASE WHEN status_akhir = 'On Time' THEN 1 ELSE 0 END) as ontime,
            SUM(CASE WHEN status_akhir = 'Delay' THEN 1 ELSE 0 END) as delay,

            SUM(nilai_muatan) as total_muatan,
            SUM(biaya_kirim) as total_biaya,

            (SUM(nilai_muatan) / NULLIF(SUM(biaya_kirim),0)) * 100 as cost_ratio
        ")->first();
    }

    /* ================= ARMADA ================= */

    public static function armada($bulan = null, $tahun = null)
    {
        $q = self::query()
            ->selectRaw("
                no_shipment,
                MAX(tanggal_naik_logistik) as tanggal_naik_logistik,
                MAX(ketersediaan_unit) as ketersediaan_unit,
                MAX(area) as area,
                MAX(tujuan) as tujuan,
                MAX(transport_lead_time) as transport_lead_time,
                MAX(rencana_kirim) as rencana_kirim,
                MAX(tanggal_dpt_unit) as tanggal_dpt_unit,
                MAX(tanggal_tiba_gudang) as tanggal_tiba_gudang,
                DATEDIFF(MAX(rencana_kirim), MAX(tanggal_dpt_unit)) AS lama_pencarian
            ")
            ->where('ketersediaan_unit', 'Sudah Dapat')
            ->groupBy('no_shipment');

        self::filter($q, $bulan, $tahun);

        return $q->get();
    }

    /* ================= STATUS ================= */

    public static function ontime($bulan = null, $tahun = null)
    {
        $q = self::where('status_akhir', 'On Time');

        self::filter($q, $bulan, $tahun);

        return $q->count();
    }

    public static function delay($bulan = null, $tahun = null)
    {
        $q = self::where('status_akhir', 'Delay');

        self::filter($q, $bulan, $tahun);

        return $q->count();
    }

    public static function process($bulan = null, $tahun = null)
    {
        $q = self::where(function ($x) {
            $x->where('status_akhir', 'like', '%process%')
              ->orWhere('status_akhir', 'like', '%pending%')
              ->orWhere('status_akhir', 'like', '%loading%');
        });

        self::filter($q, $bulan, $tahun);

        return $q->count();
    }

    /* ================= CHART ================= */

    public static function chartStatus()
    {
        return self::selectRaw('status_akhir as status, COUNT(*) as total')
            ->groupBy('status_akhir')
            ->get();
    }

    public static function chartArea()
    {
        return self::select('area', DB::raw('COUNT(*) as total'))
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    public static function chartKategori()
    {
        return self::select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi')
            ->groupBy('kategori_ekspedisi')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    public static function chartArmada()
    {
        return self::selectRaw("
            CASE 
                WHEN mobil IS NULL OR mobil = '' THEN 'Belum Armada'
                ELSE 'Sudah Armada'
            END as status,
            COUNT(*) as total
        ")
        ->groupBy('status')
        ->get();
    }
}