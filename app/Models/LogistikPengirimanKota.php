<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LogistikPengirimanKota extends Model
{
    protected $table = 'logistik_pengiriman_kota';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    /**
     * Kolom ini persis sesuai struktur tabel `logistik_pengiriman_kota`
     * (kolom 2 s/d 91, di luar id/created_at/updated_at yang di-handle otomatis).
     */
    protected $fillable = [
        'no_kota',
        'tanggal_naik_logistik_kota',
        'rencana_kirim_kota',
        'transport_lead_time_kota',
        'estimasi_admin_kota',
        'planner_kota',
        'no_shipment_kota',
        'tujuan_kota',
        'area_kota',
        'ketersediaan_unit_kota',
        'mobil_kota',
        'perubahan_mobil_kota',
        'nilai_muatan_kota',
        'biaya_kirim_kota',
        'biaya_kuli_kota',
        'total_biaya_kuli_kota',
        'cr_kota',
        'kategori_ekspedisi_kota',
        'ekpedisi_kota',
        'nama_kapal_kota',
        'etd_kota',
        'eta_kota',
        'atd_kota',
        'ata_kota',
        'nama_driver_kota',
        'no_pol_kota',
        'status_pengiriman_kota',
        'tanggal_dpt_unit_kota',
        'planning_loading_kota',
        'tanggal_tiba_gudang_kota',
        'tanggal_keluar_gudang_kota',
        'lama_digudang_kota',
        'status_gudang_kota',
        'sla_loading_kota',
        'keterangan_kota',
        'lama_waktu_pencarian_kota',
        'sla_dapat_mobil_kota',
        'pic_monitoring_kota',
        'status_kendaraan_kota',
        'monitoring_alert_kota',
        'action_required_kota',
        'act_urutan_bongkar_kota',
        'tanggal_tiba_kota',
        'lama_perjalanan_kota',
        'sla_tiba_kota',
        'tanggal_bongkar_kota',
        'overstay_days_kota',
        'sla_bongkar_kota',
        'reason_tiba_kota',
        'reason_bongkar_kota',
        'status_akhir_kota',
        'tanggal_tiba_estimasi_kota',
        'remarks_kota',
        'dist_channel_kota',
        'transportasi_kota',
        'transport_laut_kota',
        'tanggal_tiba_gudang_2_kota',
        'planning_loading_2_kota',
        'tanggal_keluar_gudang_2_kota',
        'lama_digudang_2_kota',
        'status_gudang_2_kota',
        'sla_loading_2_kota',
        'tanggal_tiba_gudang_3_kota',
        'planning_loading_3_kota',
        'tanggal_keluar_gudang_3_kota',
        'lama_digudang_3_kota',
        'status_gudang_3_kota',
        'sla_loading_3_kota',
        'cust_grp_5_desc_kota',
        'cust_grp_3_desc_kota',
        'ship_no_kota',
        'cust_desc_kota',
        'addt_text_4_kota',
        'service_agent_kota',
        'urutan_bongkar_kota',
        'act_pgi_date_kota',
        'created_by_kota',
        'total_do_qty_car_kota',
        'route_kota',
        'shipping_point_kota',
        'kubikasi_kota',
        'pulau_kota',
        'via_kirim_kota',
        'estimasi_tiba_kota',
        'create_tgl_kota',
        'qty_monitoring_kota',
        'remarks_qty_kota',
        'selisih_qty_kota',
    ];

    /**
     * Cast tipe kolom sesuai definisi tabel DB.
     */
    protected $casts = [
        // ===== datetime =====
        'tanggal_naik_logistik_kota'    => 'datetime',
        'rencana_kirim_kota'            => 'datetime',
        'tanggal_dpt_unit_kota'         => 'datetime',
        'planning_loading_kota'         => 'datetime',
        'tanggal_tiba_gudang_kota'      => 'datetime',
        'tanggal_keluar_gudang_kota'    => 'datetime',
        'tanggal_tiba_kota'             => 'datetime',
        'tanggal_bongkar_kota'          => 'datetime',
        'tanggal_tiba_gudang_2_kota'    => 'datetime',
        'planning_loading_2_kota'       => 'datetime',
        'tanggal_keluar_gudang_2_kota'  => 'datetime',
        'tanggal_tiba_gudang_3_kota'    => 'datetime',
        'planning_loading_3_kota'       => 'datetime',
        'tanggal_keluar_gudang_3_kota'  => 'datetime',
        'estimasi_tiba_kota'            => 'datetime',
        'create_tgl_kota'               => 'datetime',

        // ===== date =====
        'etd_kota'                      => 'date',
        'eta_kota'                      => 'date',
        'atd_kota'                      => 'date',
        'ata_kota'                      => 'date',
        'tanggal_tiba_estimasi_kota'    => 'date',
        'act_pgi_date_kota'             => 'date',

        // ===== numeric =====
        'nilai_muatan_kota'             => 'decimal:2',
        'biaya_kirim_kota'              => 'decimal:2',
        'biaya_kuli_kota'               => 'integer',
        'total_biaya_kuli_kota'         => 'integer',
        'estimasi_admin_kota'           => 'integer',
        'urutan_bongkar_kota'           => 'integer',
        'total_do_qty_car_kota'         => 'integer',
        'qty_monitoring_kota'           => 'integer',
    ];

    /* ================= FILTER ================= */
    public function scopeFilter($query, $bulan = null, $tahun = null)
    {
        if ($bulan) {
            $query->whereMonth('tanggal_naik_logistik_kota', $bulan);
        }

        if ($tahun) {
            $query->whereYear('tanggal_naik_logistik_kota', $tahun);
        }

        return $query;
    }

    /* ================= HELPERS TANGGAL ================= */
    public function safeDate($value)
    {
        if (!$value || $value == 'mm/dd/yyyy') return null;

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function formatTanggal($date)
    {
        return !empty($date)
            ? date('d-m-Y', strtotime($date))
            : '-';
    }

    /* ================= ACCESSOR (dihitung, bukan kolom DB) ================= */

    // Lama perjalanan dihitung ulang dari keluar gudang -> tiba
    public function getLamaPerjalananHitungAttribute()
    {
        return $this->tanggal_keluar_gudang_kota && $this->tanggal_tiba_kota
            ? Carbon::parse($this->tanggal_keluar_gudang_kota)
                ->diffInDays($this->tanggal_tiba_kota)
            : 0;
    }

    public function getEstimasiHitungAttribute()
    {
        if (!$this->tanggal_keluar_gudang_kota) return null;

        $keluar   = strtotime($this->tanggal_keluar_gudang_kota);
        $leadtime = (int) $this->transport_lead_time_kota;

        return date('d-m-Y', strtotime("+$leadtime days", $keluar));
    }

    public function getEstimasiTibaFinalAttribute()
    {
        return $this->tanggal_tiba_estimasi_kota
            ? $this->safeDate($this->tanggal_tiba_estimasi_kota)
            : $this->safeDate($this->rencana_kirim_kota);
    }

    // Overstay Tiba = estimasi -> tiba
    public function getOverstayTibaHitungAttribute()
    {
        $est  = $this->estimasi_tiba_final;
        $tiba = $this->safeDate($this->tanggal_tiba_kota);

        if (!$est || !$tiba) return 0;

        return max(0, $est->diffInDays($tiba));
    }

    // Overstay Bongkar = tiba -> bongkar
    public function getOverstayBongkarHitungAttribute()
    {
        $tiba    = $this->safeDate($this->tanggal_tiba_kota);
        $bongkar = $this->safeDate($this->tanggal_bongkar_kota);

        if (!$tiba || !$bongkar) return 0;

        return max(0, $tiba->diffInDays($bongkar));
    }

    // Lama & status gudang siklus 1 (kolom lama_digudang_kota/status_gudang_kota
    // sudah ada di DB dan diisi manual/otomatis oleh controller, jadi accessor
    // ini hanya untuk kalkulasi bantu, namanya sengaja dibedakan agar tidak bentrok)
    public function getLamaDigudangHitungAttribute()
    {
        if (!$this->tanggal_tiba_gudang_kota || !$this->tanggal_keluar_gudang_kota) {
            return 0;
        }

        return Carbon::parse($this->tanggal_tiba_gudang_kota)
            ->diffInDays(Carbon::parse($this->tanggal_keluar_gudang_kota));
    }

    public function getStatusGudangHitungAttribute()
    {
        $lama = $this->lama_digudang_hitung;

        return match (true) {
            $lama == 0 => 'ONTIME',
            $lama == 1 => 'H+1',
            $lama == 2 => 'H+2',
            default    => 'DELAY',
        };
    }

    public function getSlaLoadingHitungAttribute()
    {
        if (!$this->tanggal_tiba_gudang_kota || !$this->tanggal_keluar_gudang_kota) {
            return null;
        }

        $diff = Carbon::parse($this->tanggal_tiba_gudang_kota)
            ->diffInDays(Carbon::parse($this->tanggal_keluar_gudang_kota));

        return "H+$diff";
    }

    /* ================= MONITORING ================= */

    public static function monitoringTiba()
    {
        $tiba_ontime = self::whereColumn('tanggal_tiba_kota', '<=', 'tanggal_tiba_estimasi_kota')->count();
        $tiba_delay  = self::whereColumn('tanggal_tiba_kota', '>', 'tanggal_tiba_estimasi_kota')->count();

        $total = $tiba_ontime + $tiba_delay;

        return [
            'tiba_ontime_kota'      => $tiba_ontime,
            'tiba_delay_kota'       => $tiba_delay,
            'tiba_ontime_rate_kota' => $total ? round(($tiba_ontime / $total) * 100, 2) : 0,
            'tiba_delay_rate_kota'  => $total ? round(($tiba_delay / $total) * 100, 2) : 0,
        ];
    }

    public static function monitoringBongkar()
    {
        $bongkar_ontime = self::whereColumn('tanggal_bongkar_kota', '<=', 'tanggal_tiba_estimasi_kota')->count();
        $bongkar_delay  = self::whereColumn('tanggal_bongkar_kota', '>', 'tanggal_tiba_estimasi_kota')->count();

        $total = $bongkar_ontime + $bongkar_delay;

        return [
            'bongkar_ontime_kota'      => $bongkar_ontime,
            'bongkar_delay_kota'       => $bongkar_delay,
            'bongkar_ontime_rate_kota' => $total ? round(($bongkar_ontime / $total) * 100, 2) : 0,
            'bongkar_delay_rate_kota'  => $total ? round(($bongkar_delay / $total) * 100, 2) : 0,
        ];
    }

    /* ================= BASIC ================= */

    public static function totalShipment()
    {
        return self::count();
    }

    public static function totalNilaiMuatan()
    {
        return self::sum('nilai_muatan_kota');
    }

    public static function totalBiaya()
    {
        return self::sum('biaya_kirim_kota');
    }

    /* ================= STATUS ================= */

    public static function ontime()
    {
        return self::where('status_akhir_kota', 'On Time Total')->count();
    }

    public static function delay()
    {
        return self::where('status_akhir_kota', 'like', 'Delay%')->count();
    }

    public static function process()
    {
        return self::where(function ($q) {
            $q->where('status_akhir_kota', 'like', '%process%')
                ->orWhere('status_akhir_kota', 'like', '%pending%')
                ->orWhere('status_akhir_kota', 'like', '%loading%');
        })->count();
    }

    /* ================= ARMADA ================= */

    public static function armadaReady()
    {
        return self::where('ketersediaan_unit_kota', 'Sudah Dapat')->count();
    }

    public static function armadaPending()
    {
        return self::where(function ($q) {
            $q->whereNull('ketersediaan_unit_kota')
                ->orWhere('ketersediaan_unit_kota', '')
                ->orWhere('ketersediaan_unit_kota', 'Belum Dapat');
        })->count();
    }

    /* ================= CHART ================= */

    public static function chartStatus()
    {
        return self::select('status_akhir_kota', DB::raw('COUNT(*) as total'))
            ->groupBy('status_akhir_kota')
            ->get();
    }

    public static function chartArea()
    {
        return self::select('area_kota', DB::raw('COUNT(*) as total'))
            ->whereNotNull('area_kota')
            ->groupBy('area_kota')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    public static function chartKategori()
    {
        return self::select('kategori_ekspedisi_kota', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi_kota')
            ->groupBy('kategori_ekspedisi_kota')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    public static function chartArmada()
    {
        return self::selectRaw("
            CASE
                WHEN mobil_kota IS NULL OR mobil_kota = '' THEN 'Belum Armada'
                ELSE 'Sudah Armada'
            END as status,
            COUNT(*) as total
        ")
            ->groupBy('status')
            ->get();
    }

    /* ================= TOP ================= */

    public static function topPlanner()
    {
        return self::select('planner_kota', DB::raw('COUNT(*) as total'))
            ->groupBy('planner_kota')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    public static function topAreaDelay()
    {
        return self::select('area_kota', DB::raw('COUNT(*) as total_delay'))
            ->where('status_akhir_kota', 'like', 'Delay%')
            ->groupBy('area_kota')
            ->orderByDesc('total_delay')
            ->limit(5)
            ->get();
    }
}