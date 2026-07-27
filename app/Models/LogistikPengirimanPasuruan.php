<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LogistikPengirimanPasuruan extends Model
{
     protected $table = 'logistik_pengiriman_pasuruan';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    // protected $fillable = [
    //     'no',
    //     'tanggal_terima_po_pasuruan',
    //     'rencana_kirim_pasuruan',
    //     'transport_lead_time_pasuruan',
    //     'planner_pasuruan',
    //     'no_shipment_pasuruan',
    //     'dist_channel_pasuruan',
    //     'tujuan_pasuruan',
    //     'area_pasuruan',
    //     'ketersediaan_unit_pasuruan',
    //     'mobil_pasuruan',
    //     'perubahan_mobil_pasuruan',
    //     'nilai_muatan_pasuruan',
    //     'biaya_kirim_pasuruan',
    //     'cr_pasuruan',
    //     'kategori_ekspedisi_pasuruan',
    //     'ekpedisi_pasuruan',
      
    //     'status_pengiriman_pasuruan',
    //     'tanggal_dpt_unit_pasuruan',
    //     'planning_loading_pasuruan',
    //     'tanggal_tiba_gudang_pasuruan',
    //     'tanggal_keluar_gudang_pasuruan',
    //     'lama_digudang_pasuruan',
    //     'status_gudang_pasuruan',
    //     'sla_loading_pasuruan',
    //     'keterangan_pasuruan',
    //     'lama_waktu_pencarian_pasuruan',
    //     'sla_dapat_mobil_pasuruan',
    //    'actual_delivery_quantity_pasuruan',
    //    'selisih_delivery_quantity_pasuruan',
    //    'reason_selisih_quantity_pasuruan',
    //    'act_urutan_bongkar_pasuruan',
    //    'act_pgi_date_pasuruan'
    // ];

    protected $fillable = [

    'no',

    'tanggal_terima_po_pasuruan',
    'rencana_kirim_pasuruan',
    'transport_lead_time_pasuruan',

    'planner_pasuruan',
    'no_shipment_pasuruan',

    'dist_channel_pasuruan',
    'tujuan_pasuruan',
    'area_pasuruan',
    'pulau_pasuruan',

    'route_pasuruan',
    'via_kirim_pasuruan',
    'shipping_point_pasuruan',
    'total_do_pasuruan',

    'ketersediaan_unit_pasuruan',
    'mobil_pasuruan',
    'perubahan_mobil_pasuruan',

    'nilai_muatan_pasuruan',
    'biaya_kirim_pasuruan',
    'cr_pasuruan',

    'kategori_ekspedisi_pasuruan',
    'ekspedisi_pasuruan',

    'tanggal_dpt_unit_pasuruan',
    'planning_loading_pasuruan',
    'tanggal_tiba_gudang_pasuruan',
    'tanggal_keluar_gudang_pasuruan',

    'lama_digudang_pasuruan',
    'status_gudang_pasuruan',
    'sla_loading_pasuruan',

    'lama_waktu_pencarian_pasuruan',
    'sla_dapat_mobil_pasuruan',

    'status_kendaraan_pasuruan',
    'monitoring_alert_pasuruan',
    'action_required_pasuruan',

    'estimasi_tiba_pasuruan',
    'tanggal_tiba_pasuruan',
    'lama_perjalanan_pasuruan',
    'sla_tiba_pasuruan',

    'tanggal_bongkar_pasuruan',
    'overstay_days_pasuruan',
    'sla_bongkar_pasuruan',

    'status_akhir_pasuruan',

    'nama_kapal_pasuruan',
    'etd_pasuruan',
    'eta_pasuruan',
    'atd_pasuruan',
    'ata_pasuruan',
    'transport_laut_pasuruan',

    'actual_delivery_quantity_pasuruan',
    'selisih_quantity_pasuruan',
    'reason_selisih_quantity_pasuruan',
    'reason_waktu_tiba_pasuruan',
    'qty_monitoring_pasuruan',
    'remarks_qty_pasuruan',
    'selisih_qty_pasuruan',
    'reason_waktu_bongkar_pasuruan',
    'remarks_pasuruan',


    'create_tgl_pasuruan',
    'act_pgi_date_pasuruan',
    'pic_monitoring_pasuruan',
    'act_urutan_bongkar_pasuruan',
];
    public $timestamps = true;

    /* ================= FILTER ================= */
    public static function scopeFilter($query, $bulan = null, $tahun = null)
    {
        if ($bulan) {
            $query->whereMonth('tanggal_naik_logistik_pasuruan', $bulan);
        }

        if ($tahun) {
            $query->whereYear('tanggal_naik_logistik_pasuruan', $tahun);
        }

        return $query;
    }


    // Lama perjalanan = keluar gudang → tiba
    public function getLamaPerjalananAttribute()
    {
        return $this->tanggal_keluar_gudang_pasuruan && $this->tanggal_tiba_pasuruan
            ? \Carbon\Carbon::parse($this->tanggal_keluar_gudang_pasuruan)
            ->diffInDays($this->tanggal_tiba_pasuruan)
            : 0;
    }

    public function safeDate($value)
    {
        if (!$value || $value == 'mm/dd/yyyy') return null;

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getEstimasiAttribute()
    {
        $keluar = strtotime($this->tanggal_keluar_gudang_pasuruan);
        $leadtime = (int) $this->transport_lead_time_pasuruan;

        return date('d-m-Y', strtotime("+$leadtime days", $keluar));
    }

    public function getEstimasiTibaFinalAttribute()
    {
        return $this->tanggal_tiba_estimasi_pasuruan
            ? $this->safeDate($this->tanggal_tiba_estimasi_pasuruan)
            : $this->safeDate($this->rencana_kirim_pasuruan);
    }
    protected $casts = [
        'tanggal_keluar_gudang_pasuruan' => 'datetime',
        'tanggal_tiba_pasuruan' => 'datetime',
        'tanggal_tiba_estimasi_pasuruan' => 'datetime',
        'tanggal_bongkar_pasuruan' => 'datetime',
        'rencana_kirim_pasuruan' => 'datetime',
    ];

    // Overstay Tiba = estimasi → tiba
    public function getOverstayTibaAttribute()
    {
        $est  = $this->estimasi_tiba_final;
        $tiba = $this->safeDate($this->tanggal_tiba_pasuruan);

        if (!$est || !$tiba) return 0;

        return max(0, $est->diffInDays($tiba));
    }

    // Overstay Bongkar = tiba → bongkar
    public function getOverstayBongkarAttribute()
    {
        $tiba    = $this->safeDate($this->tanggal_tiba_pasuruan);
        $bongkar = $this->safeDate($this->tanggal_bongkar_pasuruan);

        if (!$tiba || !$bongkar) return 0;

        return max(0, $tiba->diffInDays($bongkar));
    }

    public static function monitoringTiba()
    {
        $tiba_ontime = self::whereColumn('tanggal_tiba_pasuruan', '<=', 'tanggal_tiba_estimasi_pasuruan')->count();

        $tiba_delay = self::whereColumn('tanggal_tiba_pasuruan', '>', 'tanggal_tiba_estimasi_pasuruan')->count();

        $total = $tiba_ontime + $tiba_delay;

        return [
            'tiba_ontime_pasuruan' => $tiba_ontime,
            'tiba_delay_pasuruan' => $tiba_delay,
            'tiba_ontime_rate_pasuruan' => $total ? round(($tiba_ontime / $total) * 100, 2) : 0,
            'tiba_delay_rate_pasuruan' => $total ? round(($tiba_delay / $total) * 100, 2) : 0,
        ];
    }
    public static function monitoringBongkar()
    {
        $bongkar_ontime = self::whereColumn('tanggal_bongkar_pasuruan', '<=', 'tanggal_tiba_estimasi_pasuruan')->count();

        $bongkar_delay = self::whereColumn('tanggal_bongkar_pasuruan', '>', 'tanggal_tiba_estimasi_pasuruan')->count();

        $total = $bongkar_ontime + $bongkar_delay;

        return [
            'bongkar_ontime_pasuruan' => $bongkar_ontime,
            'bongkar_delay_pasuruan' => $bongkar_delay,
            'bongkar_ontime_rate_pasuruan' => $total ? round(($bongkar_ontime / $total) * 100, 2) : 0,
            'bongkar_delay_rate_pasuruan' => $total ? round(($bongkar_delay / $total) * 100, 2) : 0,
        ];
    }

    /* ================= BASIC ================= */

    public static function totalShipment()
    {
        return self::count();
    }

    public static function totalNilaiMuatan()
    {
        return self::sum('nilai_muatan_pasuruan');
    }

    public static function totalBiaya()
    {
        return self::sum('biaya_kirim_pasuruan');
    }

    /* ================= STATUS ================= */

    public static function ontime()
    {
        return self::where('status_akhir_pasuruan', 'On Time')->count();
    }

    public static function delay()
    {
        return self::where('status_akhir_pasuruan', 'Delay')->count();
    }

    public function armada(Request $request)
    {
        $query = LogistikPengiriman::where(
            'ketersediaan_unit_pasuruan',
            'Sudah Dapat'
        );

        // FILTER BULAN
        if ($request->bulan) {

            $query->whereMonth(
                'tanggal_naik_logistik_pasuruan',
                $request->bulan
            );
        }

        // FILTER TAHUN
        if ($request->tahun) {

            $query->whereYear(
                'tanggal_naik_logistik_pasuruan',
                $request->tahun
            );
        }

        $logistik = $query
            ->orderBy('id', 'DESC')
            ->get();

        return view('armada', compact('logistik'));
    }

    public static function process()
    {
        return self::where(function ($q) {
            $q->where('status_akhir', 'like', '%process%')
                ->orWhere('status_akhir', 'like', '%pending%')
                ->orWhere('status_akhir', 'like', '%loading%');
        })->count();
    }

    /* ================= ARMADA ================= */

    public static function armadaReady()
    {
        return self::where('ketersediaan_unit', 'Sudah Dapat')->count();
    }

    public static function armadaPending()
    {
        return self::where(function ($q) {
            $q->whereNull('ketersediaan_unit')
                ->orWhere('ketersediaan_unit', '')
                ->orWhere('ketersediaan_unit', 'Belum Dapat');
        })->count();
    }

    /* ================= DASHBOARD ================= */

    public function dashboard(Request $request)
    {
        $baseQuery = DB::table('logistik_pengiriman');

        // ================= FILTER =================
        if ($request->filled('month')) {
            $month = date('m', strtotime($request->month));
            $baseQuery->whereMonth('tanggal_naik_logistik', $month);
        }

        if ($request->filled('year')) {
            $baseQuery->whereYear('tanggal_naik_logistik', $request->year);
        }

        if ($request->filled('area')) {
            $baseQuery->where('area', $request->area);
        }

        // clone base
        $query = clone $baseQuery;

        // ================= TOTAL =================
        $total_data = (clone $query)->count();
        $totalNilaiMuatan = (clone $query)->sum('nilai_muatan');
        $totalBiayaKirim = (clone $query)->sum('biaya_kirim');

        // ================= GUDANG =================
        $gudang_ontime = (clone $query)
            ->where('status', 'like', '%On%Time%')
            ->count();

        $gudang_delay = (clone $query)
            ->where('status', 'like', '%Delay%')
            ->count();

        // ================= CUSTOMER =================
        $customer_ontime = (clone $query)
            ->whereIn('status_akhir', ['On Time', 'OnTime', 'ONTIME'])
            ->count();

        $customer_delay = (clone $query)
            ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
            ->count();

        // ================= BONGKAR (FIXED SAFE) =================
        $bongkar_ontime = (clone $query)
            ->where(function ($q) {
                $q->where('sla_bongkar', 'H+0')
                    ->orWhere('sla_bongkar', 'On Time')
                    ->orWhere('sla_bongkar', 'ONTIME')
                    ->orWhere('overstay_days', '<=', 0);
            })
            ->count();

        $bongkar_delay = (clone $query)
            ->where(function ($q) {
                $q->where('sla_bongkar', 'Delay')
                    ->orWhere('sla_bongkar', 'Critical Delay')
                    ->orWhere('overstay_days', '>', 0);
            })
            ->count();

        // ================= SUMMARY AREA =================
        $summary_area = (clone $query)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(COALESCE(biaya_kirim,0)) as total_biaya'),
                DB::raw('SUM(COALESCE(nilai_muatan,0)) as total_muatan')
            )
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();

        // ================= SUMMARY TUJUAN (FIXED) =================
        $summary_tujuan = (clone $query)
            ->select(
                'tujuan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(COALESCE(biaya_kirim,0)) as total_biaya'),
                DB::raw('SUM(COALESCE(nilai_muatan,0)) as total_muatan')
            )
            ->groupBy('tujuan')
            ->orderByDesc('total_shipment')
            ->get();

        // ================= MONITORING =================
        $summary_monitoring = [
            'tiba_ontime' => $total_data ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_delay' => $total_data ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime' => $total_data ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay' => $total_data ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

        // ================= PLANNER =================
        $planner_ontime = (clone $query)->whereRaw("LOWER(status) LIKE '%on%'")->count();
        $planner_delay = (clone $query)->whereRaw("LOWER(status) LIKE '%delay%'")->count();

        $planner_armada = (clone $query)->whereNotNull('mobil')->count();
        $planner_belum_armada = (clone $query)->whereNull('mobil')->count();

        $total_status = $planner_ontime + $planner_delay;

        $ontime_rate = $total_status ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate = $total_status ? ($planner_delay / $total_status) * 100 : 0;

        $total_armada = $planner_armada + $planner_belum_armada;
        $armada_rate = $total_armada ? ($planner_armada / $total_armada) * 100 : 0;

        // ================= EKSPEDISI CHART =================
        $exp = (clone $query)
            ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi')
            ->groupBy('kategori_ekspedisi')
            ->get();

        $label = $exp->pluck('kategori_ekspedisi');
        $value = $exp->pluck('total');

        // ================= AREA LIST =================
        $list_area = DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->get();

        return view('sales.dashboard', compact(
            'total_data',
            'gudang_ontime',
            'gudang_delay',
            'customer_ontime',
            'customer_delay',
            'bongkar_ontime',
            'bongkar_delay',
            'summary_area',
            'summary_tujuan',
            'totalNilaiMuatan',
            'totalBiayaKirim',
            'summary_monitoring',
            'planner_ontime',
            'planner_delay',
            'planner_armada',
            'planner_belum_armada',
            'ontime_rate',
            'delay_rate',
            'armada_rate',
            'label',
            'value',
            'list_area'
        ));
    }

    /* ================= CHART ================= */

    public static function chartStatus()
    {
        return self::select('status_akhir', DB::raw('COUNT(*) as total'))
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

    // rumus dari tanggal tiba digudang sampai ke sla loading//
    public function getLamaDigudangAttribute()
    {
        if (!$this->tanggal_tiba_gudang || !$this->tanggal_keluar_gudang) {
            return 0;
        }

        return Carbon::parse($this->tanggal_tiba_gudang)
            ->diffInDays(Carbon::parse($this->tanggal_keluar_gudang));
    }

    public function getStatusGudangAttribute()
    {
        $lama = $this->lama_digudang;

        return match (true) {
            $lama == 0 => 'ONTIME',
            $lama == 1 => 'H+1',
            $lama == 2 => 'H+2',
            default => 'DELAY',
        };
    }
    public function getSlaLoadingAttribute()
    {
        if (!$this->tanggal_tiba_gudang || !$this->tanggal_keluar_gudang) {
            return null;
        }

        $diff = Carbon::parse($this->tanggal_tiba_gudang)
            ->diffInDays(Carbon::parse($this->tanggal_keluar_gudang));

        return "H+$diff";
    }

    /* ================= TOP ================= */

    public static function topPlanner()
    {
        return self::select('planner', DB::raw('COUNT(*) as total'))
            ->groupBy('planner')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    public static function topAreaDelay()
    {
        return self::select('area', DB::raw('COUNT(*) as total_delay'))
            ->where('status_akhir', 'Delay')
            ->groupBy('area')
            ->orderByDesc('total_delay')
            ->limit(5)
            ->get();
    }



    public function parseDate($value)
    {
        if (!$value || $value == 'mm/dd/yyyy') return null;

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }
    function formatTanggal($date)
    {
        return !empty($date)
            ? date('d-m-Y', strtotime($date))
            : '-';
    }
}
