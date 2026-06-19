<?php

namespace App\Models;

use Carbon\Carbon;
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
        'status',
        'sla_loading',
        'pic_monitoring',
        'status_kendaraan',
        'monitoring_alert',
        'act_urutan_bongkar',
        'tanggal_tiba_estimasi',
        'tanggal_tiba',
        'lama_perjalanan',
        'sla_tiba',
        'tanggal_bongkar',
        'overstay_days',
        'sla_bongkar',
        'reason_tiba',
        'reason_bongkar',
        'status_akhir',
        'remarks'
    ];

    public $timestamps = true;

    /* =========================================================
     * SAFE DATE
     * ========================================================= */
    public function safeDate($value)
    {
        if (!$value || $value == 'mm/dd/yyyy') return null;

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /* =========================================================
     * LAMA DI GUDANG (AUTO)
     * ========================================================= */
    public function getLamaDigudangFixAttribute()
    {
        $tiba = $this->safeDate($this->tanggal_tiba_gudang);
        $keluar = $this->safeDate($this->tanggal_keluar_gudang);

        if (!$tiba || !$keluar) return 0;

        return max(0, $tiba->diffInDays($keluar));
    }

    /* =========================================================
     * SLA LOADING (AUTO)
     * ========================================================= */
    public function getSlaLoadingFixAttribute()
    {
        $planning = $this->safeDate($this->planning_loading);
        $keluar = $this->safeDate($this->tanggal_keluar_gudang);

        if (!$planning || !$keluar) return '-';

        return $keluar->lessThanOrEqualTo($planning)
            ? 'On Time'
            : 'Delay';
    }

    /* =========================================================
     * STATUS GUDANG (AUTO TURUNAN SLA)
     * ========================================================= */
    public function getStatusGudangFixAttribute()
    {
        $sla = $this->sla_loading_fix;

        if ($sla === 'On Time') return 'Ontime';
        if ($sla === 'Delay') return 'Delay';

        return '-';
    }

    /* =========================================================
     * LAMA PERJALANAN
     * ========================================================= */
    public function getLamaPerjalananAttribute()
    {
        $keluar = $this->safeDate($this->tanggal_keluar_gudang);
        $tiba = $this->safeDate($this->tanggal_tiba);

        if (!$keluar || !$tiba) return 0;

        return $keluar->diffInDays($tiba);
    }

    /* =========================================================
     * ESTIMASI TIBA
     * ========================================================= */
    public function getEstimasiAttribute()
    {
        $keluar = $this->safeDate($this->tanggal_keluar_gudang);
        $leadtime = (int) $this->transport_lead_time;

        if (!$keluar) return null;

        return $keluar->copy()->addDays($leadtime)->format('Y-m-d');
    }

    /* =========================================================
     * OVERSTAY
     * ========================================================= */
    public function getOverstayBongkarAttribute()
    {
        $tiba = $this->safeDate($this->tanggal_tiba);
        $bongkar = $this->safeDate($this->tanggal_bongkar);

        if (!$tiba || !$bongkar) return 0;

        return max(0, $tiba->diffInDays($bongkar));
    }

    /* =========================================================
     * SCOPES FILTER
     * ========================================================= */
    public function scopeFilter($query, $bulan = null, $tahun = null)
    {
        if ($bulan) {
            $query->whereMonth('tanggal_naik_logistik', $bulan);
        }

        if ($tahun) {
            $query->whereYear('tanggal_naik_logistik', $tahun);
        }

        return $query;
    }
}