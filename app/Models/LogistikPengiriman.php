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
        'dist_channel',
        'tujuan',
        'area',
        'ketersediaan_unit',
        'mobil',
        'perubahan_mobil',
        'nilai_muatan',
        'biaya_kirim',
        'cr',
        'kategori_ekspedisi',
        'ekpedisi',
      
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
        'remarks',
        'act_pgi_date',
        'total_do_qty_car',

    'tanggal_tiba_gudang_2',
    'tanggal_keluar_gudang_2',
    'lama_digudang_2',
    'status_gudang_2',
    'sla_loading_2',

    'tanggal_tiba_gudang_3',
    'tanggal_keluar_gudang_3',
    'lama_digudang_3',
    'status_gudang_3',
    'sla_loading_3',
    'nama_kapal',
    'etd',
    'eta',
    'atd',
    'ata',
    'transport_laut',
    'route',
    'pulau',
    'via_kirim',
    'shipping_point',
        'created_by',
        'create_tgl'
    ];

    public $timestamps = true;

    /* ================= FILTER ================= */
    public static function scopeFilter($query, $bulan = null, $tahun = null)
    {
        if ($bulan) {
            $query->whereMonth('tanggal_naik_logistik', $bulan);
        }

        if ($tahun) {
            $query->whereYear('tanggal_naik_logistik', $tahun);
        }

        return $query;
    }


    // Lama perjalanan = keluar gudang → tiba
    public function getLamaPerjalananAttribute()
    {
        return $this->tanggal_keluar_gudang && $this->tanggal_tiba
            ? \Carbon\Carbon::parse($this->tanggal_keluar_gudang)
            ->diffInDays($this->tanggal_tiba)
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
        $keluar = strtotime($this->tanggal_keluar_gudang);
        $leadtime = (int) $this->transport_lead_time;

        return date('d-m-Y', strtotime("+$leadtime days", $keluar));
    }

    public function getEstimasiTibaFinalAttribute()
    {
        return $this->tanggal_tiba_estimasi
            ? $this->safeDate($this->tanggal_tiba_estimasi)
            : $this->safeDate($this->rencana_kirim);
    }
    protected $casts = [
        'tanggal_keluar_gudang' => 'datetime',
        'tanggal_tiba' => 'datetime',
        'tanggal_tiba_estimasi' => 'datetime',
        'tanggal_bongkar' => 'datetime',
        'rencana_kirim' => 'datetime',
    ];

    // Overstay Tiba = estimasi → tiba
    public function getOverstayTibaAttribute()
    {
        $est  = $this->estimasi_tiba_final;
        $tiba = $this->safeDate($this->tanggal_tiba);

        if (!$est || !$tiba) return 0;

        return max(0, $est->diffInDays($tiba));
    }

    // Overstay Bongkar = tiba → bongkar
    public function getOverstayBongkarAttribute()
    {
        $tiba    = $this->safeDate($this->tanggal_tiba);
        $bongkar = $this->safeDate($this->tanggal_bongkar);

        if (!$tiba || !$bongkar) return 0;

        return max(0, $tiba->diffInDays($bongkar));
    }

    public static function monitoringTiba()
    {
        $tiba_ontime = self::whereColumn('tanggal_tiba', '<=', 'tanggal_tiba_estimasi')->count();

        $tiba_delay = self::whereColumn('tanggal_tiba', '>', 'tanggal_tiba_estimasi')->count();

        $total = $tiba_ontime + $tiba_delay;

        return [
            'tiba_ontime' => $tiba_ontime,
            'tiba_delay' => $tiba_delay,
            'tiba_ontime_rate' => $total ? round(($tiba_ontime / $total) * 100, 2) : 0,
            'tiba_delay_rate' => $total ? round(($tiba_delay / $total) * 100, 2) : 0,
        ];
    }
    public static function monitoringBongkar()
    {
        $bongkar_ontime = self::whereColumn('tanggal_bongkar', '<=', 'tanggal_tiba_estimasi')->count();

        $bongkar_delay = self::whereColumn('tanggal_bongkar', '>', 'tanggal_tiba_estimasi')->count();

        $total = $bongkar_ontime + $bongkar_delay;

        return [
            'bongkar_ontime' => $bongkar_ontime,
            'bongkar_delay' => $bongkar_delay,
            'bongkar_ontime_rate' => $total ? round(($bongkar_ontime / $total) * 100, 2) : 0,
            'bongkar_delay_rate' => $total ? round(($bongkar_delay / $total) * 100, 2) : 0,
        ];
    }

    /* ================= BASIC ================= */

    public static function totalShipment()
    {
        return self::count();
    }

    public static function totalNilaiMuatan()
    {
        return self::sum('nilai_muatan');
    }

    public static function totalBiaya()
    {
        return self::sum('biaya_kirim');
    }

    /* ================= STATUS ================= */

    public static function ontime()
    {
        return self::where('status_akhir', 'On Time')->count();
    }

    public static function delay()
    {
        return self::where('status_akhir', 'Delay')->count();
    }

    public function armada(Request $request)
    {
        $query = LogistikPengiriman::where(
            'ketersediaan_unit',
            'Sudah Dapat'
        );

        // FILTER BULAN
        if ($request->bulan) {

            $query->whereMonth(
                'tanggal_naik_logistik',
                $request->bulan
            );
        }

        // FILTER TAHUN
        if ($request->tahun) {

            $query->whereYear(
                'tanggal_naik_logistik',
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
