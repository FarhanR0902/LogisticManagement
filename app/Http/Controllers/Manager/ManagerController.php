<?php
namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;  
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use App\Models\LogistikPengirimanPasuruan;

class ManagerController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK','BANTEN','JAWA_BARAT','JAWA_TENGAH','JAWA_TIMUR','YOGYAKARTA'],
        'SUMATERA'   => ['ACEH','SUMATERA_UTARA','SUMATERA_BARAT','RIAU','KEP._RIAU','JAMBI','SUMATERA_SELATAN','BENGKULU','LAMPUNG','KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT','KALIMANTAN_TENGAH','KALIMANTAN_SELATAN','KALIMANTAN_TIMUR','KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA','SULAWESI_TENGAH','SULAWESI_SELATAN','SULAWESI_TENGGARA','SULAWESI_BARAT','GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI','NUSA_TENGGARA_BARAT','NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU','PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA','PAPUA_BARAT','PAPUA_BARAT_DAYA','PAPUA_SELATAN','PAPUA_TENGAH'],
    ];

    public function dashboard(Request $request)
    {

        // ================= BASE QUERY =================

        $base = DB::table('logistik_pengiriman');

        $this->applyFilter($base, $request);

        // ================= TOTAL =================

        $total_data = (clone $base)->count();

        // ================= GUDANG =================

        $gudang_ontime = (clone $base)
            ->where(function ($q) {
                $q->whereNotNull('tanggal_tiba_gudang')
                  ->orWhereNotNull('tanggal_tiba_gudang_2')
                  ->orWhereNotNull('tanggal_tiba_gudang_3');
            })
            ->count();

        $gudang_delay = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim')
                  ->orWhere('rencana_kirim', '')
                  ->orWhereNull('tanggal_dpt_unit')
                  ->orWhere('tanggal_dpt_unit', '');
            })
            ->count();

        $gudang_unknown = (clone $base)
            ->where(function ($q) {
                $q->whereNull('sla_loading')
                  ->orWhereRaw("TRIM(sla_loading) = ''")
                  ->orWhereRaw("LOWER(TRIM(sla_loading)) NOT IN (
                      'h+0','h+1','h+2','h>2','on time','ontime','delay','critical delay'
                  )");
            })
            ->count();


        // ================= TUJUAN / CUSTOMER =================

        $customer_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba')
            ->whereRaw("
                DATEDIFF(
                    DATE(tanggal_tiba),
                    DATE(estimasi_tiba)
                ) <= 0
            ")
            ->count();

        $customer_delay = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba')
            ->whereRaw("
                DATEDIFF(
                    DATE(tanggal_tiba),
                    DATE(estimasi_tiba)
                ) > 0
            ")
            ->count();


        // ================= BONGKAR =================

        $bongkar_ontime = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
            ->where(function ($q) {
                $q->whereNull('overstay_days')
                  ->orWhere('overstay_days', 0);
            })
            ->count();

        $bongkar_delay = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
            ->where('overstay_days', '>', 0)
            ->count();


        // ================= ARMADA =================

        $planner_armada = (clone $base)
            ->whereNotNull('rencana_kirim')
            ->whereRaw("TRIM(rencana_kirim) <> ''")
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
            ->count();

        $planner_belum_armada = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim')
                  ->orWhere('rencana_kirim', '')
                  ->orWhereNull('tanggal_dpt_unit')
                  ->orWhere('tanggal_dpt_unit', '');
            })
            ->count();

        $list_dist_channel = (clone $base)
            ->select('dist_channel')
            ->whereNotNull('dist_channel')
            ->distinct()
            ->orderBy('dist_channel')
            ->get();


        // ================= PLANNER =================

        $planner_ontime = (clone $base)
            ->whereNotNull('rencana_kirim')
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw('DATE(tanggal_dpt_unit) <= DATE(rencana_kirim)')
            ->count();

        $planner_delay = (clone $base)
            ->whereNotNull('rencana_kirim')
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw('DATE(tanggal_dpt_unit) > DATE(rencana_kirim)')
            ->count();

        // ================= TOTAL NILAI MUATAN =================

        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan');

        $totalBiayaKirim = (clone $base)
            ->selectRaw("SUM(biaya_kirim) as total")
            ->value('total');


        // ================= SUMMARY AREA =================

        $summary_area = (clone $base)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('COALESCE(SUM(biaya_kirim),0) as total_biaya'),
                DB::raw('COALESCE(SUM(nilai_muatan),0) as total_muatan')
            )
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();


        // ================= SUMMARY TUJUAN =================

        $summary_tujuan = (clone $base)
            ->select(
                'tujuan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('COALESCE(SUM(biaya_kirim),0) as total_biaya'),
                DB::raw('COALESCE(SUM(nilai_muatan),0) as total_muatan')
            )
            ->whereNotNull('tujuan')
            ->groupBy('tujuan')
            ->orderByDesc('total_shipment')
            ->get();


        // ================= SUMMARY PULAU =================

        $summary_pulau = DB::table('logistik_pengiriman')
            ->when($request->filled('bulan'), function ($q) use ($request) {
                $q->whereMonth('tanggal_naik_logistik', $request->bulan);
            })
            ->when($request->filled('tahun'), function ($q) use ($request) {
                $q->whereYear('tanggal_naik_logistik', $request->tahun);
            })
            ->when($request->filled('area'), function ($q) use ($request) {
                $q->where('area', $request->area);
            })
            ->when($request->filled('dist_channel'), function ($q) use ($request) {
                $q->where('dist_channel', $request->dist_channel);
            })
            ->select(
                'pulau',
                DB::raw('COUNT(DISTINCT no_shipment) AS total_shipment'),
                DB::raw('SUM(nilai_muatan) AS total_muatan'),
                DB::raw('SUM(biaya_kirim) AS total_biaya')
            )
            ->whereNotNull('pulau')
            ->whereRaw("TRIM(pulau) <> ''")
            ->groupBy('pulau')
            ->orderByDesc('total_muatan')
            ->get();

        $label_pulau        = $summary_pulau->pluck('pulau');
        $value_muatan_pulau = $summary_pulau->pluck('total_muatan');
        $value_biaya_pulau  = $summary_pulau->pluck('total_biaya');

        // ================= EKSPEDISI =================

        $ekspedisi = (clone $base)
            ->select(
                'kategori_ekspedisi',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('kategori_ekspedisi')
            ->groupBy('kategori_ekspedisi')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi');
        $value = $ekspedisi->pluck('total');


        // ================= RATIO =================

        $total_status = $planner_ontime + $planner_delay;

        $ontime_rate = $total_status > 0
            ? ($planner_ontime / $total_status) * 100
            : 0;

        $delay_rate = $total_status > 0
            ? ($planner_delay / $total_status) * 100
            : 0;

        $total_armada = $planner_armada + $planner_belum_armada;

        $armada_rate = $total_armada > 0
            ? ($planner_armada / $total_armada) * 100
            : 0;

        $pending_rate = $total_armada > 0
            ? ($planner_belum_armada / $total_armada) * 100
            : 0;


        // ================= MONITORING =================

        $summary_monitoring = [
            'tiba_ontime' => $total_data > 0
                ? ($customer_ontime / $total_data) * 100
                : 0,

            'tiba_delay' => $total_data > 0
                ? ($customer_delay / $total_data) * 100
                : 0,

            'bongkar_ontime' => $total_data > 0
                ? ($bongkar_ontime / $total_data) * 100
                : 0,

            'bongkar_delay' => $total_data > 0
                ? ($bongkar_delay / $total_data) * 100
                : 0,
        ];


        // ================= LIST AREA =================

        $list_area = $this->getArea();


        // ================= RETURN =================

        return view('dashboard', compact(
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

            'ekspedisi',

            'label',
            'value',

            'planner_ontime',
            'planner_delay',

            'planner_armada',
            'planner_belum_armada',

            'ontime_rate',
            'delay_rate',

            'armada_rate',
            'pending_rate',

            'summary_monitoring',
            'list_dist_channel',
            'list_area',

            'summary_pulau',
            'label_pulau',
            'value_muatan_pulau',
            'value_biaya_pulau'

        ));
    }

    public function PasuruandataLogistik()
    {
        $logistik = LogistikPengirimanPasuruan::orderByDesc('id')->get();

        $planners = LogistikPengirimanPasuruan::select('planner_pasuruan')
            ->whereNotNull('planner_pasuruan')
            ->where('planner_pasuruan', '!=', '')
            ->distinct()
            ->orderBy('planner_pasuruan')
            ->pluck('planner_pasuruan');

        $areas = LogistikPengirimanPasuruan::select('area_pasuruan')
            ->whereNotNull('area_pasuruan')
            ->where('area_pasuruan', '!=', '')
            ->distinct()
            ->orderBy('area_pasuruan')
            ->pluck('area_pasuruan');

        return view('manager.data_logistik', compact(
            'logistik',
            'planners',
            'areas'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER
    |--------------------------------------------------------------------------
    */

    private function applyFilter($query, $request)
    {

        // AREA

        if ($request->area) {
            $query->where('area', $request->area);
        }

        if ($request->dist_channel) {
            $query->where('dist_channel', $request->dist_channel);
        }

        if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
            $query->whereIn('area', self::PULAU_MAP[$request->pulau]);
        }

        // DATE

        if ($request->date) {
            $query->whereDate(
                'tanggal_naik_logistik',
                $request->date
            );
        }

        // MONTH

        if ($request->month) {
            $query->whereMonth(
                'tanggal_naik_logistik',
                substr($request->month, 5, 2)
            );

            $query->whereYear(
                'tanggal_naik_logistik',
                substr($request->month, 0, 4)
            );
        }

        // YEAR

        if ($request->year) {
            $query->whereYear(
                'tanggal_naik_logistik',
                $request->year
            );
        }

        return $query;
    }


    /*
    |--------------------------------------------------------------------------
    | GET AREA
    |--------------------------------------------------------------------------
    */

    private function getArea()
    {
        return DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();
    }


    /*
    |--------------------------------------------------------------------------
    | HITUNG SLA (dipakai gudangOntime)
    |--------------------------------------------------------------------------
    */
    private function hitungSla($request)
    {
        $data = [
            'lama_waktu_pencarian' => null,
            'sla_dapat_mobil'      => null,
            'status_pengiriman'    => null,

            'lama_digudang'        => null,
            'status_gudang'        => null,
            'sla_loading'          => null,

            'lama_digudang_2'      => null,
            'status_gudang_2'      => null,
            'sla_loading_2'        => null,

            'lama_digudang_3'      => null,
            'status_gudang_3'      => null,
            'sla_loading_3'        => null,
        ];

        $hitungSelisih = function ($start, $end) {

            if (!$start || !$end) return null;

            $awal  = new \DateTime($start);
            $akhir = new \DateTime($end);

            if ($akhir < $awal) {
                return [
                    'text' => '0 Menit',
                    'days' => 0,
                    'hours' => 0,
                    'minutes' => 0,
                    'seconds' => 0
                ];
            }

            $selisih = $akhir->getTimestamp() - $awal->getTimestamp();

            $days    = floor($selisih / 86400);
            $hours   = floor(($selisih % 86400) / 3600);
            $minutes = floor(($selisih % 3600) / 60);

            $text = '';

            if ($days > 0) {
                $text .= $days . ' Hari ';
            }

            if ($hours > 0) {
                $text .= $hours . ' Jam ';
            }

            $text .= $minutes . ' Menit';

            return [
                'text'    => trim($text),
                'days'    => $days,
                'hours'   => $hours,
                'minutes' => $minutes
            ];
        };

        $tibaGudang = collect([
            $request->tanggal_tiba_gudang,
            $request->tanggal_tiba_gudang_2,
            $request->tanggal_tiba_gudang_3,
        ])->filter()->sort()->first();

        // =====================================================
        // 1. SLA DAPAT MOBIL
        // =====================================================

        $start = $request->rencana_kirim
            ? date('Y-m-d H:i:s', strtotime($request->rencana_kirim))
            : null;

        $end = $request->tanggal_dpt_unit
            ? date('Y-m-d H:i:s', strtotime($request->tanggal_dpt_unit))
            : null;

        $diff = $hitungSelisih($start, $end);

        $data['lama_waktu_pencarian'] = $diff['text'] ?? null;

        if ($start && $end) {

            $areaJawa = [
                'JAWA_BARAT',
                'JAWA_TENGAH',
                'JAWA_TIMUR',
                'YOGYAKARTA',
                'BANTEN',
                'JABODEBEK'
            ];

            $area = strtoupper(trim($request->area ?? ''));

            $tanggalRencana = date('Y-m-d', strtotime($start));
            $tanggalDptUnit = date('Y-m-d', strtotime($end));

            if (in_array($area, $areaJawa)) {

                if ($tanggalDptUnit > $tanggalRencana) {
                    $data['sla_dapat_mobil']   = 'Delay';
                    $data['status_pengiriman'] = 'Terlambat';
                } else {
                    $data['sla_dapat_mobil']   = 'On Time';
                    $data['status_pengiriman'] = 'Sudah Dapat';
                }

            } else {

                $selisihHari = floor(
                    (strtotime($tanggalDptUnit) - strtotime($tanggalRencana))
                    / 86400
                );

                if ($selisihHari > 2) {
                    $data['sla_dapat_mobil']   = 'Delay';
                    $data['status_pengiriman'] = 'Terlambat';
                } else {
                    $data['sla_dapat_mobil']   = 'On Time';
                    $data['status_pengiriman'] = 'Sudah Dapat';
                }
            }

        } else {
            $data['sla_dapat_mobil']   = null;
            $data['status_pengiriman'] = null;
        }

        // =====================================================
        // 2. GUDANG 1
        // =====================================================
        if ($request->tanggal_tiba_gudang && $request->tanggal_keluar_gudang) {

            $diff = $hitungSelisih(
                $request->tanggal_tiba_gudang,
                $request->tanggal_keluar_gudang
            );

            if ($diff) {

                $data['lama_digudang'] = $diff['text'];

                if ($diff['days'] > 0) {
                    $data['status_gudang'] = 'Delay';
                    $data['sla_loading']   = 'H+' . $diff['days'];
                } else {
                    $data['status_gudang'] = 'On Time';
                    $data['sla_loading']   = 'Sesuai SLA';
                }
            }
        }

        // =====================================================
        // 3. GUDANG 2
        // =====================================================
        if ($request->tanggal_tiba_gudang_2 && $request->tanggal_keluar_gudang_2) {

            $diff = $hitungSelisih(
                $request->tanggal_tiba_gudang_2,
                $request->tanggal_keluar_gudang_2
            );

            if ($diff) {

                $data['lama_digudang_2'] = $diff['text'];

                if ($diff['days'] > 0) {
                    $data['status_gudang_2'] = 'Delay';
                    $data['sla_loading_2']   = 'H+' . $diff['days'];
                } else {
                    $data['status_gudang_2'] = 'On Time';
                    $data['sla_loading_2']   = 'Sesuai SLA';
                }
            }
        }

        // =====================================================
        // 4. GUDANG 3
        // =====================================================
        if ($request->tanggal_tiba_gudang_3 && $request->tanggal_keluar_gudang_3) {

            $diff = $hitungSelisih(
                $request->tanggal_tiba_gudang_3,
                $request->tanggal_keluar_gudang_3
            );

            if ($diff) {

                $data['lama_digudang_3'] = $diff['text'];

                if ($diff['days'] > 0) {
                    $data['status_gudang_3'] = 'Delay';
                    $data['sla_loading_3']   = 'H+' . $diff['days'];
                } else {
                    $data['status_gudang_3'] = 'On Time';
                    $data['sla_loading_3']   = 'Sesuai SLA';
                }
            }
        }

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | GUDANG DELAY (FIXED: pakai applyFilter, bukan bulan/tahun manual)
    |--------------------------------------------------------------------------
    */
    public function gudangDelay(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereNull('rencana_kirim')
                  ->orWhere('rencana_kirim', '')
                  ->orWhereNull('tanggal_dpt_unit')
                  ->orWhere('tanggal_dpt_unit', '');
            });

        $this->applyFilter($query, $request);

        $logistik = $query
            ->orderBy('tanggal_naik_logistik', 'DESC')
            ->get();

        return view('planner.belum_armada', compact('logistik'));
    }


    // Method lama ini sudah tidak dipakai route manapun — dibiarkan apa
    // adanya (dead code), tidak diubah.
    public function gaudangDelay(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->whereNotNull('rencana_kirim')
            ->whereRaw("TRIM(rencana_kirim) <> ''")
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw("TRIM(tanggal_dpt_unit) <> ''")
            ->where(function ($q) {
                $q->whereNull('tanggal_tiba_gudang')
                  ->orWhere('tanggal_tiba_gudang', '');
            })
            ->where(function ($q) {
                $q->whereNull('tanggal_tiba_gudang_2')
                  ->orWhere('tanggal_tiba_gudang_2', '');
            })
            ->where(function ($q) {
                $q->whereNull('tanggal_tiba_gudang_3')
                  ->orWhere('tanggal_tiba_gudang_3', '');
            });

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        $list = $query->orderBy('tanggal_naik_logistik', 'DESC')
            ->get();

        $list_area = DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();

        return view('manager.sla_delay', [
            'title' => 'SLA DELAY',
            'list' => $list,
            'list_area' => $list_area
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GUDANG ONTIME (FIXED: pakai applyFilter, bukan bulan/tahun manual)
    |--------------------------------------------------------------------------
    */
    public function gudangOntime(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereNotNull('tanggal_tiba_gudang')
                  ->orWhereNotNull('tanggal_tiba_gudang_2')
                  ->orWhereNotNull('tanggal_tiba_gudang_3');
            });

        $this->applyFilter($query, $request);

        $list = $query->get()
          ->map(function ($row) {

        $sla = $this->hitungSla($row);

        $row->lama_waktu_pencarian = $sla['lama_waktu_pencarian'];
        $row->sla_dapat_mobil      = $sla['sla_dapat_mobil'];
        $row->status_pengiriman    = $sla['status_pengiriman'];

        $row->lama_digudang        = $sla['lama_digudang'];
        $row->status_gudang        = $sla['status_gudang'];
        $row->sla_loading          = $sla['sla_loading'];

        $row->lama_digudang_2      = $sla['lama_digudang_2'];
        $row->status_gudang_2      = $sla['status_gudang_2'];
        $row->sla_loading_2        = $sla['sla_loading_2'];

        $row->lama_digudang_3      = $sla['lama_digudang_3'];
        $row->status_gudang_3      = $sla['status_gudang_3'];
        $row->sla_loading_3        = $sla['sla_loading_3'];

        // GUDANG TIBA
        $gudang = [];

        if (!empty($row->tanggal_tiba_gudang)) {
            $gudang[1] = strtotime($row->tanggal_tiba_gudang);
        }

        if (!empty($row->tanggal_tiba_gudang_2)) {
            $gudang[2] = strtotime($row->tanggal_tiba_gudang_2);
        }

        if (!empty($row->tanggal_tiba_gudang_3)) {
            $gudang[3] = strtotime($row->tanggal_tiba_gudang_3);
        }

        $row->gudang_sla = count($gudang)
            ? array_search(min($gudang), $gudang)
            : null;

        // GUDANG KELUAR TERAKHIR
        $keluar = [];

        if (!empty($row->tanggal_keluar_gudang)) {
            $keluar[1] = strtotime($row->tanggal_keluar_gudang);
        }

        if (!empty($row->tanggal_keluar_gudang_2)) {
            $keluar[2] = strtotime($row->tanggal_keluar_gudang_2);
        }

        if (!empty($row->tanggal_keluar_gudang_3)) {
            $keluar[3] = strtotime($row->tanggal_keluar_gudang_3);
        }

        $row->gudang_keluar_terakhir = count($keluar)
            ? array_search(max($keluar), $keluar)
            : null;

        return $row;
    })
            ->values();

        $list_area = DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();

        return view('manager.sla_ontime', compact('list', 'list_area'));
    }

    /*
    |--------------------------------------------------------------------------
    | TUJUAN ONTIME (FIXED: pakai applyFilter, ditambah filter area/dist_channel/pulau)
    |--------------------------------------------------------------------------
    */
    public function tujuanOntime(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->selectRaw("
                logistik_pengiriman.*,

                estimasi_tiba AS tanggal_estimasi,

                CASE
                    WHEN DATEDIFF(
                        DATE(tanggal_tiba),
                        DATE(estimasi_tiba)
                    ) <= 0
                    THEN 'On Time'
                    ELSE 'Delay'
                END AS sla_tiba
            ")
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba');

        $query->havingRaw("
            DATEDIFF(
                DATE(tanggal_tiba),
                DATE(estimasi_tiba)
            ) <= 0
        ");

        $this->applyFilter($query, $request);

        $logistik = $query
            ->orderByDesc('tanggal_tiba')
            ->get();

        return view('manager.tujuan_ontime', compact('logistik'));
    }

    /*
    |--------------------------------------------------------------------------
    | TUJUAN DELAY (FIXED: pakai applyFilter, ditambah filter area/dist_channel/pulau)
    |--------------------------------------------------------------------------
    */
    public function tujuanDelay(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->selectRaw("
                logistik_pengiriman.*,

                estimasi_tiba AS tanggal_estimasi,

                CASE
                    WHEN DATEDIFF(
                        DATE(tanggal_tiba),
                        DATE(estimasi_tiba)
                    ) > 0
                    THEN 'Delay'
                    ELSE 'On Time'
                END AS sla_tiba
            ")
            ->whereNotNull('tanggal_tiba')
            ->whereNotNull('estimasi_tiba')
            ->whereRaw("
                DATEDIFF(
                    DATE(tanggal_tiba),
                    DATE(estimasi_tiba)
                ) > 0
            ");

        $this->applyFilter($query, $request);

        $logistik = $query
            ->orderBy('ship_no')
            ->orderBy('estimasi_tiba')
            ->orderBy('id')
            ->get();

        return view('manager.tujuan_delay', compact('logistik'));
    }

    // Method lama ini sudah tidak dipakai route manapun (route 'manager.customer.delay'
    // memanggil tujuanDelay di atas, bukan tujuanDelaya ini) — dibiarkan apa adanya.
    public function tujuanDelaya(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->whereNotNull('sla_tiba')
            ->whereRaw("LOWER(TRIM(sla_tiba)) IN ('delay','h+1','h+2','h>2','critical delay')");

        $this->applyFilter($query, $request);

        $logistik = $query
            ->orderByDesc('tanggal_tiba')
            ->get();

        $list_area = $this->getArea();

        return view('manager.tujuan_delay', compact(
            'logistik',
            'list_area'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | BONGKAR ONTIME (FIXED: pakai applyFilter, bukan tanggal_bongkar manual)
    |--------------------------------------------------------------------------
    */
    public function bongkarOntime(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->selectRaw("
                *,
                CASE
                    WHEN DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) <= 0
                    THEN 'On Time'
                    ELSE 'Delay'
                END AS sla_bongkar
            ")
            ->whereNotNull('tanggal_bongkar')
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
            ->whereRaw("
                DATEDIFF(
                    DATE(tanggal_bongkar),
                    DATE(tanggal_tiba)
                ) <= 0
            ");

        $this->applyFilter($query, $request);

        $list = $query
            ->orderByDesc('tanggal_bongkar')
            ->get();

        return view('manager.bongkar_ontime', compact('list'));
    }

    /*
    |--------------------------------------------------------------------------
    | BONGKAR DELAY (FIXED: pakai applyFilter, bukan tanggal_bongkar manual)
    |--------------------------------------------------------------------------
    */
    public function bongkarDelay(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereIn('sla_bongkar', ['Delay', 'Critical Delay'])
                  ->orWhere('overstay_days', '>', 0);
            })
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00');

        $this->applyFilter($query, $request);

        $list = $query->orderByDesc('tanggal_bongkar')->get();

        return view('manager.bongkar_delay', compact('list'));
    }

    // Method lama ini sudah tidak dipakai route manapun — dibiarkan apa adanya.
    public function bongkarDelaya(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->whereNotNull('sla_bongkar')
            ->whereRaw("LOWER(TRIM(sla_bongkar)) IN ('delay','critical delay','h+1','h+2','h>2')");

        $this->applyFilter($query, $request);

        $logistik = $query
            ->orderByDesc('tanggal_bongkar')
            ->get();

        return view('manager.bongkar_delay', compact(
            'logistik'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY TOTAL
    |--------------------------------------------------------------------------
    */

    public function summaryTotal(Request $request)
    {
        $query = DB::table('logistik_pengiriman');

        $this->applyFilter($query, $request);

        $logistik = $query->get();

        return view('manager.summary_total', compact(
            'logistik'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | SUMMARY AREA
    |--------------------------------------------------------------------------
    */

    public function summaryArea(Request $request)
    {
        $query = DB::table('logistik_pengiriman');

        $this->applyFilter($query, $request);

        $summary_area = $query
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('COALESCE(SUM(biaya_kirim),0) as total_biaya'),
                DB::raw('COALESCE(SUM(nilai_muatan),0) as total_muatan')
            )
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();

        return view('manager.summary_area', compact(
            'summary_area'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */

    public function planner()
    {
        return redirect()->route('planner.dashboard');
    }


    public function dashboardPasuruan(Request $request)
    {
        $base = DB::table('logistik_pengiriman_pasuruan');

        $this->applyFilterPasuruan($base, $request);

        $total_data = (clone $base)->count();

        $gudang_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba_gudang_pasuruan')
            ->count();

        $gudang_delay = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim_pasuruan')
                  ->orWhere('rencana_kirim_pasuruan', '')
                  ->orWhereNull('tanggal_dpt_unit_pasuruan')
                  ->orWhere('tanggal_dpt_unit_pasuruan', '');
            })
            ->count();

        $customer_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->whereNotNull('estimasi_tiba_pasuruan')
            ->whereRaw("DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) <= 0")
            ->count();

        $customer_delay = (clone $base)
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->whereNotNull('estimasi_tiba_pasuruan')
            ->whereRaw("DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0")
            ->count();

        $bongkar_ontime = (clone $base)
            ->whereNotNull('tanggal_bongkar_pasuruan')
            ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
            ->where(function ($q) {
                $q->whereNull('overstay_days_pasuruan')
                  ->orWhere('overstay_days_pasuruan', 0);
            })
            ->count();

        $bongkar_delay = (clone $base)
            ->whereNotNull('tanggal_bongkar_pasuruan')
            ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
            ->where('overstay_days_pasuruan', '>', 0)
            ->count();

        $planner_armada = (clone $base)
            ->whereNotNull('rencana_kirim_pasuruan')
            ->whereRaw("TRIM(rencana_kirim_pasuruan) <> ''")
            ->whereNotNull('tanggal_dpt_unit_pasuruan')
            ->whereRaw("TRIM(tanggal_dpt_unit_pasuruan) <> ''")
            ->count();

        $planner_belum_armada = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim_pasuruan')
                  ->orWhere('rencana_kirim_pasuruan', '')
                  ->orWhereNull('tanggal_dpt_unit_pasuruan')
                  ->orWhere('tanggal_dpt_unit_pasuruan', '');
            })
            ->count();

        $list_dist_channel = (clone $base)
            ->select('dist_channel_pasuruan')
            ->whereNotNull('dist_channel_pasuruan')
            ->distinct()
            ->orderBy('dist_channel_pasuruan')
            ->get();

        $planner_ontime = (clone $base)
            ->whereNotNull('rencana_kirim_pasuruan')
            ->whereNotNull('tanggal_dpt_unit_pasuruan')
            ->whereRaw('DATE(tanggal_dpt_unit_pasuruan) <= DATE(rencana_kirim_pasuruan)')
            ->count();

        $planner_delay = (clone $base)
            ->whereNotNull('rencana_kirim_pasuruan')
            ->whereNotNull('tanggal_dpt_unit_pasuruan')
            ->whereRaw('DATE(tanggal_dpt_unit_pasuruan) > DATE(rencana_kirim_pasuruan)')
            ->count();

        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan_pasuruan');

        $totalBiayaKirim = (clone $base)
            ->selectRaw("SUM(biaya_kirim_pasuruan) as total")
            ->value('total');

        $summary_area = (clone $base)
            ->select(
                'area_pasuruan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(IFNULL(biaya_kirim_pasuruan, 0)) as total_biaya'),
                DB::raw('SUM(IFNULL(nilai_muatan_pasuruan, 0)) as total_muatan')
            )
            ->whereNotNull('area_pasuruan')
            ->groupBy('area_pasuruan')
            ->orderByDesc('total_shipment')
            ->get();

        $summary_tujuan = (clone $base)
            ->select(
                'tujuan_pasuruan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(IFNULL(biaya_kirim_pasuruan, 0)) as total_biaya'),
                DB::raw('SUM(IFNULL(nilai_muatan_pasuruan, 0)) as total_muatan')
            )
            ->whereNotNull('tujuan_pasuruan')
            ->groupBy('tujuan_pasuruan')
            ->orderByDesc('total_shipment')
            ->get();

        $ekspedisi = (clone $base)
            ->select('kategori_ekspedisi_pasuruan', DB::raw('COUNT(*) as total'))
            ->whereNotNull('kategori_ekspedisi_pasuruan')
            ->groupBy('kategori_ekspedisi_pasuruan')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi_pasuruan');
        $value = $ekspedisi->pluck('total');

        $total_status = $planner_ontime + $planner_delay;
        $ontime_rate = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate  = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;

        $total_armada = $planner_armada + $planner_belum_armada;
        $armada_rate  = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;
        $pending_rate = $total_armada > 0 ? ($planner_belum_armada / $total_armada) * 100 : 0;

        $summary_monitoring = [
            'tiba_ontime'    => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_delay'     => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime' => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay'  => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

        $list_area = $this->getArea();

        return view('manager.dashboard_pasuruan', compact(
            'total_data',
            'gudang_ontime', 'gudang_delay',
            'customer_ontime', 'customer_delay',
            'bongkar_ontime', 'bongkar_delay',
            'summary_area', 'summary_tujuan',
            'totalNilaiMuatan', 'totalBiayaKirim',
            'ekspedisi', 'label', 'value',
            'planner_ontime', 'planner_delay',
            'planner_armada', 'planner_belum_armada',
            'ontime_rate', 'delay_rate',
            'armada_rate', 'pending_rate',
            'summary_monitoring', 'list_dist_channel', 'list_area'
        ));
    }

   
    // public function dataLogistikPasuruan()
    // {
    //     $logistik = LogistikPengirimanPasuruan::orderByDesc('id')->get();

    //     $planners = LogistikPengirimanPasuruan::select('planner_pasuruan')
    //         ->whereNotNull('planner_pasuruan')
    //         ->where('planner_pasuruan', '!=', '')
    //         ->distinct()
    //         ->orderBy('planner_pasuruan')
    //         ->pluck('planner_pasuruan');

    //     $areas = LogistikPengirimanPasuruan::select('area_pasuruan')
    //         ->whereNotNull('area_pasuruan')
    //         ->where('area_pasuruan', '!=', '')
    //         ->distinct()
    //         ->orderBy('area_pasuruan')
    //         ->pluck('area_pasuruan');

    //     return view('manager.data_logistik_pasuruan', compact(
    //         'logistik',
    //         'planners',
    //         'areas'
    //     ));
    // }
public function dataLogistikPasuruan()
{
    $planners = $this->cachedListPasuruanManager('planner_pasuruan');
    $areas    = $this->cachedListPasuruanManager('area_pasuruan');
 
    // 'logistik' SENGAJA tidak di-query lagi di sini — tabel sekarang
    // diisi lewat dataLogistikPasuruanAjax() via AJAX, bukan dari variabel ini.
    return view('manager.data_logistik_pasuruan', compact('planners', 'areas'));
}
 
/* =========================================================
 * DATA LOGISTIK PASURUAN — ENDPOINT AJAX (SERVER-SIDE, READ-ONLY BADGE VIEW)
 * Endpoint BARU, terpisah dari dataAjaxPasuruan() yang sudah ada (itu
 * untuk tabel editable, jangan dipakai ulang untuk view ini).
 * ========================================================= */
public function dataLogistikPasuruanAjax(Request $request)
{
    $query = LogistikPengirimanPasuruan::query();
 
    // ---------- FILTER DARI DROPDOWN CUSTOM ----------
    if ($request->filled('date')) {
        $query->whereDate('tanggal_terima_po_pasuruan', $request->date);
    }
    if ($request->filled('month')) {
        $query->whereMonth('tanggal_terima_po_pasuruan', $request->month);
    }
    if ($request->filled('year')) {
        $query->whereYear('tanggal_terima_po_pasuruan', $request->year);
    }
    if ($request->filled('planner')) {
        $query->where('planner_pasuruan', $request->planner);
    }
    if ($request->filled('area')) {
        $query->where('area_pasuruan', $request->area);
    }
 
    $recordsTotal = (clone $query)->count();
 
    // ---------- SEARCH BOX BAWAAN DATATABLES ----------
    $searchValue = trim((string) $request->input('search.value'));
 
    if ($searchValue !== '') {
        $query->where(function ($q) use ($searchValue) {
            $q->where('no_shipment_pasuruan', 'like', "%{$searchValue}%")
                ->orWhere('tujuan_pasuruan', 'like', "%{$searchValue}%")
                ->orWhere('area_pasuruan', 'like', "%{$searchValue}%")
                ->orWhere('nama_driver_pasuruan', 'like', "%{$searchValue}%")
                ->orWhere('no_pol_pasuruan', 'like', "%{$searchValue}%")
                ->orWhere('planner_pasuruan', 'like', "%{$searchValue}%")
                ->orWhere('mobil_pasuruan', 'like', "%{$searchValue}%")
                ->orWhere('ekspedisi_pasuruan', 'like', "%{$searchValue}%")
                ->orWhere('pic_monitoring_pasuruan', 'like', "%{$searchValue}%");
        });
    }
 
    $recordsFiltered = (clone $query)->count();
 
    // ---------- SORTING ----------
    $orderableColumns = [
        0  => 'tanggal_terima_po_pasuruan',
        1  => 'rencana_kirim_pasuruan',
        2  => 'transport_lead_time_pasuruan',
        3  => 'planner_pasuruan',
        4  => 'no_shipment_pasuruan',
        6  => 'dist_channel_pasuruan',
        7  => 'tujuan_pasuruan',
        8  => 'area_pasuruan',
        10 => 'mobil_pasuruan',
        11 => 'total_do_pasuruan',
        12 => 'nilai_muatan_pasuruan',
        13 => 'biaya_kirim_pasuruan',
        16 => 'ekspedisi_pasuruan',
        17 => 'tanggal_dpt_unit_pasuruan',
        23 => 'pic_monitoring_pasuruan',
        24 => 'nama_kapal_pasuruan',
    ];
 
    $orderColIndex = (int) $request->input('order.0.column', 0);
    $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    $orderColumn = $orderableColumns[$orderColIndex] ?? 'id';
 
    $query->orderBy($orderColumn, $orderDir);
 
    // ---------- PAGINATION ----------
    $start  = max(0, (int) $request->input('start', 0));
    $length = (int) $request->input('length', 25);
    $length = $length > 0 ? min($length, 200) : $recordsFiltered;
 
    $rows = $query->skip($start)->take($length)->get();
 
    $shipmentAgg = Cache::remember(
        'manager_pasuruan_shipment_agg',
        300,
        fn() => $this->shipmentAggregatesPasuruanManager()
    );
 
    $data = $rows->map(function ($r) use ($shipmentAgg) {
        return [
            'tanggal_terima_po_fmt'      => $this->fmtDatePasuruanManager($r->tanggal_terima_po_pasuruan),
            'rencana_kirim_fmt'          => $this->fmtDatePasuruanManager($r->rencana_kirim_pasuruan),
            'transport_lead_time'        => $r->transport_lead_time_pasuruan,
            'planner'                    => $r->planner_pasuruan,
            'no_shipment'                => $r->no_shipment_pasuruan,
            'status_pengiriman_badge'    => $this->badgeStatusPengirimanPasuruanManager($r),
            'dist_channel_badge'         => $this->badgeDistChannelPasuruanManager($r->dist_channel_pasuruan),
            'tujuan'                     => $r->tujuan_pasuruan,
            'area'                       => $r->area_pasuruan,
            'ketersediaan_unit_badge'    => $this->badgeKetersediaanUnitPasuruanManager($r),
            'mobil'                      => $r->mobil_pasuruan,
            'total_do'                   => $r->total_do_pasuruan,
            'nilai_muatan_fmt'           => 'Rp ' . number_format((float) $r->nilai_muatan_pasuruan, 0, ',', '.'),
            'biaya_kirim_fmt'            => 'Rp ' . number_format((float) $r->biaya_kirim_pasuruan, 0, ',', '.'),
            'cr_fmt'                     => $this->formatCRPasuruanManager($this->computeCRPasuruanManager($r, $shipmentAgg)),
            'kategori_ekspedisi_badge'   => $this->badgeKategoriEkspedisiPasuruanManager($r->kategori_ekspedisi_pasuruan),
            'ekspedisi'                  => $r->ekspedisi_pasuruan,
            'tanggal_dpt_unit_fmt'       => $this->fmtDatePasuruanManager($r->tanggal_dpt_unit_pasuruan),
            'lama_waktu_pencarian'       => $this->computeLamaPencarianPasuruanManager($r),
            'sla_dapat_mobil_badge'      => $this->badgeSlaDapatMobilPasuruanManager($r),
            'planning_loading_fmt'       => $this->fmtDatePasuruanManager($r->planning_loading_pasuruan),
            'tanggal_tiba_gudang_fmt'    => $this->fmtDatePasuruanManager($r->tanggal_tiba_gudang_pasuruan),
            'tanggal_keluar_gudang_fmt'  => $this->fmtDatePasuruanManager($r->tanggal_keluar_gudang_pasuruan),
            'pic_monitoring'             => $r->pic_monitoring_pasuruan,
            'nama_kapal'                 => $r->nama_kapal_pasuruan,
            'etd'                        => $r->etd_pasuruan,
            'eta'                        => $r->eta_pasuruan,
            'alert_badge'                => $this->badgeAlertPasuruanManager($r),
            'act_urutan_bongkar'         => $r->act_urutan_bongkar_pasuruan,
            'actual_delivery_quantity'   => $r->actual_delivery_quantity_pasuruan,
            'selisih_qty_badge'          => $this->badgeSelisihQtyPasuruanManager($r),
            'reason_selisih_quantity'    => $r->reason_selisih_quantity_pasuruan,
            'act_pgi_date_fmt'           => $this->fmtDatePasuruanManager($r->act_pgi_date_pasuruan),
            'atd_fmt'                    => $this->fmtDatePasuruanManager($r->atd_pasuruan),
            'ata_fmt'                    => $this->fmtDatePasuruanManager($r->ata_pasuruan),
            'estimasi_tiba_fmt'          => $this->fmtDatePasuruanManager($r->estimasi_tiba_pasuruan),
            'tanggal_tiba_fmt'           => $r->tanggal_tiba_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_tiba_pasuruan)) : '-',
            'lama_perjalanan'            => $r->lama_perjalanan_pasuruan ?? '-',
            'sla_tiba_badge'             => $this->badgeSlaTibaPasuruanManager($r),
            'tanggal_bongkar_fmt'        => $r->tanggal_bongkar_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_bongkar_pasuruan)) : '-',
            'status_bongkar_badge'       => $this->badgeStatusBongkarPasuruanManager($r),
            'overstay_badge'             => $this->badgeOverstayPasuruanManager($r),
            'sla_bongkar_badge'          => $this->badgeSlaBongkarPasuruanManager($r),
            'reason_tiba'                => $r->reason_waktu_tiba_pasuruan,
            'reason_bongkar'             => $r->reason_waktu_bongkar_pasuruan,
            'status_akhir_badge'         => $this->badgeStatusAkhirPasuruanManager($r),
            'status_alert_badge'         => $this->badgeStatusAlertPasuruanManager($r),
            'remarks'                    => $r->remarks_pasuruan,
            'route'                      => $r->route_pasuruan,
            'shipping_point'             => $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-',
            'pulau'                      => $r->pulau_pasuruan,
            'via_kirim'                  => $r->via_kirim_pasuruan,
            'biaya_kuli'                 => $r->biaya_kuli_pasuruan,
            'total_biaya_kuli'           => $r->total_biaya_kuli_pasuruan,
        ];
    });
 
    return response()->json([
        'draw'            => (int) $request->input('draw', 1),
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);
}
 
/* =========================================================================
 *  HELPER / PRESENTER — suffix "Manager" biar TIDAK bentrok nama dengan
 *  method/helper yang mungkin sudah ada di PasuruanController lain kalau
 *  suatu saat di-trait-kan/di-share. Semua private, khusus dipakai
 *  dataLogistikPasuruanAjax() di atas.
 * ========================================================================= */
 
private function cachedListPasuruanManager(string $column)
{
    return Cache::remember("manager_pasuruan_list_{$column}", 3600, function () use ($column) {
        return DB::table('logistik_pengiriman_pasuruan')
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    });
}
 
private function fmtDatePasuruanManager($value, string $format = 'd-m-Y')
{
    if (empty($value) || $value === 'mm/dd/yyyy') {
        return '-';
    }
    return date($format, strtotime($value));
}
 
private function shipmentAggregatesPasuruanManager(): array
{
    return DB::table('logistik_pengiriman_pasuruan')
        ->select(
            'no_shipment_pasuruan',
            DB::raw('SUM(nilai_muatan_pasuruan) as total_muatan'),
            DB::raw('MAX(biaya_kirim_pasuruan) as total_biaya')
        )
        ->whereNotNull('no_shipment_pasuruan')
        ->where('no_shipment_pasuruan', '!=', '')
        ->groupBy('no_shipment_pasuruan')
        ->get()
        ->keyBy('no_shipment_pasuruan')
        ->map(fn($g) => [
            'total_muatan' => (float) $g->total_muatan,
            'total_biaya'  => (float) $g->total_biaya,
        ])
        ->toArray();
}
 
private function computeCRPasuruanManager($r, array $shipmentAgg): float
{
    $noShipment = trim((string) $r->no_shipment_pasuruan);
 
    if ($noShipment === '' || !isset($shipmentAgg[$noShipment])) {
        $muatan = (float) $r->nilai_muatan_pasuruan;
        $biaya  = (float) $r->biaya_kirim_pasuruan;
        return $muatan > 0 ? ($biaya / $muatan) * 100 : 0;
    }
 
    $totalMuatan = $shipmentAgg[$noShipment]['total_muatan'];
    $totalBiaya  = $shipmentAgg[$noShipment]['total_biaya'];
    $nilaiBaris  = (float) $r->nilai_muatan_pasuruan;
 
    if ($totalMuatan <= 0 || $nilaiBaris <= 0) {
        return 0;
    }
 
    $totalCR    = ($totalBiaya / $totalMuatan) * 100;
    $kontribusi = $nilaiBaris / $totalMuatan;
 
    return $kontribusi * $totalCR;
}
 
private function formatCRPasuruanManager(float $cr): string
{
    return $cr > 0
        ? '<span class="cr-value">' . number_format($cr, 4, ',', '.') . '%</span>'
        : '<span class="text-muted">0,0000%</span>';
}
 
private function badgeStatusPengirimanPasuruanManager($r): string
{
    $dpt           = $r->tanggal_dpt_unit_pasuruan;
    $tibaGudang    = $r->tanggal_tiba_gudang_pasuruan;
    $keluarGudang  = $r->tanggal_keluar_gudang_pasuruan;
    $tibaTujuan    = $r->tanggal_tiba_pasuruan;
    $bongkarTujuan = $r->tanggal_bongkar_pasuruan;
 
    if (empty($dpt)) {
        $status = 'MENCARI UNIT'; $badge = 'red';
    } elseif (empty($tibaGudang)) {
        $status = 'PERJALANAN KE GUDANG'; $badge = 'orange';
    } elseif (!empty($tibaGudang) && empty($keluarGudang)) {
        $status = 'DI GUDANG'; $badge = 'blue';
    } elseif (!empty($keluarGudang) && empty($tibaTujuan)) {
        $status = 'PERJALANAN KE TUJUAN'; $badge = 'yellow';
    } elseif (!empty($tibaTujuan) && empty($bongkarTujuan)) {
        $status = 'TIBA DI TUJUAN'; $badge = 'success';
    } elseif (!empty($tibaTujuan) && !empty($bongkarTujuan)) {
        $status = 'SUDAH SELESAI'; $badge = 'green';
    } else {
        $status = '-'; $badge = 'gray';
    }
 
    return '<span class="badge ' . $badge . '">' . e($status) . '</span>';
}
 
private function badgeDistChannelPasuruanManager($channel): string
{
    $channel = trim((string) $channel);
 
    $classes = [
        'badge-green', 'badge-blue', 'badge-orange', 'badge-red',
        'badge-purple', 'badge-pink', 'badge-cyan', 'badge-yellow',
    ];
 
    $badgeClass = $channel
        ? $classes[abs(crc32($channel)) % count($classes)]
        : 'badge-default';
 
    return '<span class="badge ' . $badgeClass . '">' . e($channel ?: '-') . '</span>';
}
 
private function badgeKetersediaanUnitPasuruanManager($r): string
{
    if (!empty($r->tanggal_dpt_unit_pasuruan)) {
        return '<span class="badge-status status-sudah">Sudah Dapat Unit</span>';
    }
    return '<span class="badge-status status-belum">Belum Dapat Unit</span>';
}
 
private function badgeKategoriEkspedisiPasuruanManager($kategori): string
{
    $kategori = $kategori ?? '-';
 
    if (empty($kategori) || $kategori === '-') {
        return '<span class="badge gray">-</span>';
    }
    if (strtolower($kategori) === 'kontrak') {
        return '<span class="badge yellow">Kontrak</span>';
    }
    if (strtolower($kategori) === 'oncall') {
        return '<span class="badge blue">Oncall</span>';
    }
    return '<span class="badge orange">' . e($kategori) . '</span>';
}
 
private function computeLamaPencarianPasuruanManager($r): string
{
    if (empty($r->rencana_kirim_pasuruan) || empty($r->tanggal_dpt_unit_pasuruan)) {
        return '-';
    }
 
    $rencana   = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
    $dapatUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
    $selisih   = floor(($dapatUnit - $rencana) / 86400);
 
    return ($selisih <= 0) ? 'H+0' : 'H+' . $selisih;
}
 
private function badgeSlaDapatMobilPasuruanManager($r): string
{
    if (empty($r->rencana_kirim_pasuruan) || empty($r->tanggal_dpt_unit_pasuruan)) {
        return '<span class="badge gray">-</span>';
    }
 
    $rencana   = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
    $dapatUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
    $selisih   = floor(($dapatUnit - $rencana) / 86400);
 
    return ($selisih <= 0)
        ? '<span class="badge green">On Time</span>'
        : '<span class="badge red">Delay</span>';
}
 
private function badgeAlertPasuruanManager($r): string
{
    if (!empty($r->tanggal_tiba_pasuruan)) {
        return '<span class="badge badge-success">✓ Tiba</span>';
    }
    if (empty($r->estimasi_tiba_pasuruan)) {
        return '<span class="badge badge-secondary">-</span>';
    }
 
    $estimasi = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));
    $today = strtotime(date('Y-m-d'));
    $sisaHari = floor(($estimasi - $today) / 86400);
 
    if ($sisaHari < 0) return '<span class="badge badge-danger">OVERDUE</span>';
    if ($sisaHari == 0) return '<span class="badge badge-danger">H-0</span>';
    if ($sisaHari == 1) return '<span class="badge badge-danger">H-1</span>';
    if ($sisaHari == 2 || $sisaHari == 3) return '<span class="badge badge-warning">H-' . $sisaHari . '</span>';
    if ($sisaHari <= 7) return '<span class="badge badge-info">H-' . $sisaHari . '</span>';
 
    return '<span class="badge badge-success">ON TRACK</span>';
}
 
private function badgeSelisihQtyPasuruanManager($r): string
{
    $totalDo = is_numeric($r->total_do_pasuruan) ? (float) $r->total_do_pasuruan : 0;
    $actualRaw = $r->actual_delivery_quantity_pasuruan;
    $belumDiisi = ($actualRaw === null || $actualRaw === '' || (float) $actualRaw == 0);
 
    if ($belumDiisi) {
        return '<span class="badge badge-secondary">-</span>';
    }
 
    $actualQty = (float) $actualRaw;
    $selisih = $totalDo - $actualQty;
 
    if ($selisih == 0) {
        return '<span class="badge badge-success">Sesuai (0)</span>';
    }
    if ($selisih > 0) {
        return '<span class="badge badge-danger">Berkurang ' . number_format($selisih, 0, ',', '.') . '</span>';
    }
    return '<span class="badge badge-warning">Lebih ' . number_format(abs($selisih), 0, ',', '.') . '</span>';
}
 
private function badgeSlaTibaPasuruanManager($r): string
{
    if (empty($r->tanggal_tiba_pasuruan) || empty($r->estimasi_tiba_pasuruan)) {
        return '<span class="badge gray">-</span>';
    }
 
    $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
    $estimasi = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));
 
    return ($tiba <= $estimasi)
        ? '<span class="badge green">On Time</span>'
        : '<span class="badge red">Delay</span>';
}
 
private function badgeStatusBongkarPasuruanManager($r): string
{
    if (!empty($r->tanggal_bongkar_pasuruan)) {
        return '<span class="badge status-bongkar green">Telah Bongkar</span>';
    }
 
    if (!empty($r->tanggal_tiba_pasuruan)) {
        $tanggalTiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
        $hariIni = strtotime(date('Y-m-d'));
        $selisihHari = max(0, floor(($hariIni - $tanggalTiba) / 86400));
        $class = ($selisihHari == 0) ? 'orange' : 'red';
 
        return '<span class="badge status-bongkar ' . $class . '">H+' . $selisihHari . '</span>';
    }
 
    return '<span class="badge status-bongkar gray">-</span>';
}
 
private function badgeOverstayPasuruanManager($r): string
{
    if (empty($r->tanggal_tiba_pasuruan) || empty($r->tanggal_bongkar_pasuruan)) {
        return '<span class="badge gray">-</span>';
    }
 
    $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
    $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
    $overstay = max(0, floor(($bongkar - $tiba) / 86400));
 
    if ($overstay == 0) {
        return '<span class="badge green">0 Hari</span>';
    }
    return '<span class="badge red">H+' . $overstay . ' Hari</span>';
}
 
private function badgeSlaBongkarPasuruanManager($r): string
{
    if (empty($r->tanggal_tiba_pasuruan) || empty($r->tanggal_bongkar_pasuruan)) {
        return '<span class="badge gray">-</span>';
    }
 
    $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
    $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
    $selisih = floor(($bongkar - $tiba) / 86400);
 
    return ($selisih <= 0)
        ? '<span class="badge green">On Time</span>'
        : '<span class="badge red">Delay</span>';
}
 
private function badgeStatusAkhirPasuruanManager($r): string
{
    $slaTiba = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
    $slaBongkar = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));
 
    if (empty($r->tanggal_tiba_pasuruan)) {
        return '<span class="status-badge status-transit">🚚 Dalam Perjalanan</span>';
    }
    if (!empty($r->tanggal_tiba_pasuruan) && empty($r->tanggal_bongkar_pasuruan)) {
        return '<span class="status-badge status-unloading">📦 Sudah Tiba <br> Dalam Pembongkaran</span>';
    }
    if ($slaTiba === 'ON TIME' && $slaBongkar === 'ON TIME') {
        return '<span class="status-badge status-ontime">✅ Pengiriman On Time</span>';
    }
    return '<span class="status-badge status-delay">🚨 Pengiriman Delay</span>';
}
 
private function badgeStatusAlertPasuruanManager($r): string
{
    $slaTiba = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
    $slaBongkar = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));
 
    if ($slaTiba === 'ON TIME' && $slaBongkar === 'ON TIME') {
        return '<span class="badge badge-success">🟢 Delivered Ontime</span>';
    }
    if ($slaTiba === 'DELAY' && $slaBongkar === 'ON TIME') {
        return '<span class="badge badge-warning">🚚 Delay Perjalanan</span>';
    }
    if ($slaTiba === 'ON TIME' && $slaBongkar === 'DELAY') {
        return '<span class="badge badge-info">📦 Delay Pembongkaran</span>';
    }
    if ($slaTiba === 'DELAY' && $slaBongkar === 'DELAY') {
        return '<span class="badge badge-danger">🔥 Delivered Delay</span>';
    }
    return '<span class="badge badge-secondary">⏳ Belum Selesai</span>';
}
 
    private function applyFilterPasuruan($query, $request)
    {
        if ($request->area) {
            $query->where('area_pasuruan', $request->area);
        }

        if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
            $query->whereIn('area_pasuruan', self::PULAU_MAP[$request->pulau]);
        }

        if ($request->dist_channel) {
            $query->where('dist_channel_pasuruan', $request->dist_channel);
        }

        if ($request->date) {
            $query->whereDate('tanggal_terima_po_pasuruan', $request->date);
        }

        if ($request->month) {
            $query->whereMonth('tanggal_terima_po_pasuruan', substr($request->month, 5, 2));
            $query->whereYear('tanggal_terima_po_pasuruan', substr($request->month, 0, 4));
        }

        if ($request->year) {
            $query->whereYear('tanggal_terima_po_pasuruan', $request->year);
        }

        return $query;
    }

    public function monitoring()
    {
        return redirect()->route('monitoring.dashboard');
    }

     public function dataAjaxPasuruan(Request $request)
{
    $draw   = (int) $request->input('draw', 1);
    $start  = (int) $request->input('start', 0);
    $length = (int) $request->input('length', 25);
    $searchValue = trim((string) $request->input('search.value', ''));

    $baseQuery = LogistikPengirimanPasuruan::query();

    $totalRecords = (clone $baseQuery)->count();

    // ===== FILTER dari dropdown header =====
    if ($request->filled('planner_filter')) {
        $baseQuery->where('planner_pasuruan', $request->input('planner_filter'));
    }
    if ($request->filled('area_filter')) {
        $baseQuery->where('area_pasuruan', $request->input('area_filter'));
    }
    if ($request->filled('create_tgl_filter')) {
        $baseQuery->whereDate('created_at', $request->input('create_tgl_filter'));
    }

    // ===== GLOBAL SEARCH — hanya kolom yang relevan, pakai index =====
    if ($searchValue !== '') {
        $baseQuery->where(function ($q) use ($searchValue) {
            $cols = [
                'planner_pasuruan', 'no_shipment_pasuruan', 'tujuan_pasuruan',
                'route_pasuruan', 'pulau_pasuruan', 'area_pasuruan',
                'via_kirim_pasuruan', 'dist_channel_pasuruan',
                'kategori_ekspedisi_pasuruan', 'ekspedisi_pasuruan',
                'nama_driver_pasuruan', 'no_pol_pasuruan', 'mobil_pasuruan',
            ];
            foreach ($cols as $col) {
                $q->orWhere($col, 'like', "%{$searchValue}%");
            }
        });
    }

    $recordsFiltered = (clone $baseQuery)->count();

    $rows = $baseQuery
        ->orderByDesc('id')
        ->skip($start)
        ->take($length)
        ->get();

    // dropdown reference (untuk render select tiap baris)
    $routeOptions     = DB::table('tarif_pengiriman')->whereNotNull('route')->distinct()->orderBy('route')->pluck('route');
    $mobilOptions     = DB::table('tarif_pengiriman')->whereNotNull('mobil')->distinct()->orderBy('mobil')->pluck('mobil');
    $ekspedisiOptions = DB::table('tarif_pengiriman')->whereNotNull('ekpedisi')->distinct()->orderBy('ekpedisi')->pluck('ekpedisi');

    $reasonTiba = DB::table('akurasi3')->whereNotNull('akurasi_waktu_tiba')->where('akurasi_waktu_tiba', '<>', '')->distinct()->pluck('akurasi_waktu_tiba');
    $reasonBongkar = DB::table('akurasi3')->whereNotNull('akurasi_waktu_bongkar')->where('akurasi_waktu_bongkar', '<>', '')->distinct()->pluck('akurasi_waktu_bongkar');
    $reasonSelisihQty = DB::table('akurasi3')->whereNotNull('remarks_qty')->where('remarks_qty', '<>', '')->distinct()->pluck('remarks_qty');

    $lists = compact('routeOptions', 'mobilOptions', 'ekspedisiOptions', 'reasonTiba', 'reasonBongkar', 'reasonSelisihQty');

    $data = [];
    foreach ($rows as $r) {
        $data[] = $this->renderRowColumnsPasuruan($r, $lists);
    }

    return response()->json([
        'draw'            => $draw,
        'recordsTotal'    => $totalRecords,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);
}

/**
 * Bangun 1 baris kolom untuk tabel Pasuruan.
 * Urutan array HARUS sinkron persis dengan <thead> di view.
 */
private function renderRowColumnsPasuruan($r, array $lists)
{
    $id = $r->id;
    $formAttr = 'form="form-update-' . $id . '"';

    $dateInput = function ($name, $value, $readonly = false) use ($formAttr) {
        $val = $value ? date('Y-m-d', strtotime($value)) : '';
        $ro = $readonly ? 'readonly style="background:#f1f5f9;"' : '';
        return '<input type="date" ' . $formAttr . ' name="' . $name . '" value="' . e($val) . '" ' . $ro . '>';
    };

    $datetimeInput = function ($name, $value) use ($formAttr) {
        $val = $value ? date('Y-m-d\TH:i', strtotime($value)) : '';
        return '<input type="datetime-local" ' . $formAttr . ' name="' . $name . '" value="' . e($val) . '">';
    };

    $textInput = function ($name, $value, $extraClass = '', $readonly = false) use ($formAttr) {
        $ro = $readonly ? 'readonly style="background:#f1f5f9;"' : '';
        return '<input type="text" ' . $formAttr . ' name="' . $name . '" class="' . $extraClass . '" value="' . e($value) . '" ' . $ro . '>';
    };

    $buildSelect = function ($name, $selected, $options, $extraClass = '') use ($formAttr) {
        $html = '<select ' . $formAttr . ' name="' . $name . '" class="' . $extraClass . '">';
        $html .= '<option value="">-- Pilih --</option>';
        foreach ($options as $opt) {
            $sel = ((string) $selected === (string) $opt) ? 'selected' : '';
            $html .= '<option value="' . e($opt) . '" ' . $sel . '>' . e($opt) . '</option>';
        }
        $html .= '</select>';
        return $html;
    };

    $formattedRupiah = function ($angka) {
        if (!$angka) return '';
        $angkaMurni = preg_replace('/[^0-9]/', '', explode('.', (string) $angka)[0]);
        return $angkaMurni ? 'Rp ' . number_format((float) $angkaMurni, 0, ',', '.') : '';
    };

    // Status Mobil
    $statusMobilHtml = !empty($r->tanggal_dpt_unit_pasuruan)
        ? '<span class="badge-status bg-success text-white">SUDAH DAPAT</span>'
        : '<span class="badge-status bg-danger text-white">BELUM DAPAT</span>';

    // SLA Dapat Mobil
    $slaMobilHtml = '<span class="badge-status bg-secondary text-white">-</span>';
    if ($r->rencana_kirim_pasuruan && $r->tanggal_dpt_unit_pasuruan) {
        $area = strtoupper(trim($r->area_pasuruan ?? ''));
        $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
        $dpt = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
        $selisih = floor(($dpt - $rencana) / 86400);
        $batas = ($area == 'JABODEBEK' || $area == 'JABODETABEK') ? 0 : ($area == 'JAWA_BARAT' ? 1 : 2);
        $text = $selisih > $batas ? 'H+' . ($selisih - $batas) : 'Sesuai SLA';
        $slaMobilHtml = '<span class="badge-status ' . (str_contains($text, 'H+') ? 'bg-danger text-white' : 'bg-success text-white') . '">' . $text . '</span>';
    }

    // Status Bongkar
    if (!empty($r->tanggal_bongkar_pasuruan)) {
        $statusBongkarHtml = '<span class="badge green">Telah Bongkar</span>';
    } elseif (!empty($r->tanggal_tiba_pasuruan)) {
        $selisih = max(0, floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)))) / 86400));
        $cls = $selisih == 0 ? 'orange' : 'red';
        $statusBongkarHtml = '<span class="badge ' . $cls . '">H+' . $selisih . '</span>';
    } else {
        $statusBongkarHtml = '<span class="badge gray">-</span>';
    }

    // Estimasi Admin + status
    $estimasiAdmin = null;
    if (!empty($r->rencana_kirim_pasuruan) && !empty($r->transport_lead_time_pasuruan)) {
        $estimasiAdmin = \Carbon\Carbon::parse($r->rencana_kirim_pasuruan)->addDays((int) $r->transport_lead_time_pasuruan);
    }
    $statusEstimasiAdmin = '-';
    if ($estimasiAdmin && !empty($r->tanggal_tiba_pasuruan)) {
        $statusEstimasiAdmin = \Carbon\Carbon::parse($r->tanggal_tiba_pasuruan)->lte($estimasiAdmin) ? 'On Time' : 'Delay';
    } elseif ($estimasiAdmin) {
        $statusEstimasiAdmin = now()->startOfDay()->gt($estimasiAdmin->copy()->startOfDay()) ? 'Delay' : 'Belum Tiba';
    }
    $badgeMap = ['On Time' => 'green', 'Delay' => 'red', 'Belum Tiba' => 'orange'];
    $estimasiAdminBadge = isset($badgeMap[$statusEstimasiAdmin])
        ? '<span class="badge ' . $badgeMap[$statusEstimasiAdmin] . '">' . $statusEstimasiAdmin . '</span>'
        : '<span class="badge gray">-</span>';

    $hiddenForm = '<form class="d-none" id="form-update-' . $id . '" action="'
        . route('pasuruan.update', $id) . '" method="POST">'
        . csrf_field() . method_field('PUT') . '</form>';

    return [
        $hiddenForm . ($r->created_at ? \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') : '-'),
        $textInput('planner_pasuruan', $r->planner_pasuruan),
        $textInput('no_shipment_pasuruan', $r->no_shipment_pasuruan, 'row-no-shipment'),
        $dateInput('tanggal_terima_po_pasuruan', $r->tanggal_terima_po_pasuruan),
        $dateInput('rencana_kirim_pasuruan', $r->rencana_kirim_pasuruan),
        $dateInput('tanggal_dpt_unit_pasuruan', $r->tanggal_dpt_unit_pasuruan),
        $dateInput('planning_loading_pasuruan', $r->planning_loading_pasuruan),
        $dateInput('tanggal_tiba_gudang_pasuruan', $r->tanggal_tiba_gudang_pasuruan),
        $dateInput('tanggal_keluar_gudang_pasuruan', $r->tanggal_keluar_gudang_pasuruan),
        $textInput('tujuan_pasuruan', $r->tujuan_pasuruan),
        $buildSelect('route_pasuruan', $r->route_pasuruan, $lists['routeOptions'], 'row-route select-tarif-row'),
        $textInput('pulau_pasuruan', $r->pulau_pasuruan),
        $textInput('area_pasuruan', $r->area_pasuruan),
        $textInput('via_kirim_pasuruan', $r->via_kirim_pasuruan),
        $textInput('dist_channel_pasuruan', $r->dist_channel_pasuruan),
        $textInput('kategori_ekspedisi_pasuruan', $r->kategori_ekspedisi_pasuruan),
        $buildSelect('ekspedisi_pasuruan', $r->ekspedisi_pasuruan, $lists['ekspedisiOptions'], 'row-ekspedisi select-tarif-row'),
        $textInput('transport_lead_time_pasuruan', $r->transport_lead_time_pasuruan),
        $buildSelect('mobil_pasuruan', $r->mobil_pasuruan, $lists['mobilOptions'], 'row-mobil select-tarif-row'),
        $textInput('nama_driver_pasuruan', $r->nama_driver_pasuruan),
        $textInput('no_pol_pasuruan', $r->no_pol_pasuruan),
        '<input type="number" ' . $formAttr . ' name="total_do_pasuruan" value="' . e($r->total_do_pasuruan) . '">',
        $textInput('nilai_muatan_pasuruan', $formattedRupiah($r->nilai_muatan_pasuruan), 'row-nilai-muatan input-rupiah'),
        $textInput('biaya_kirim_pasuruan', $formattedRupiah($r->biaya_kirim_pasuruan), 'row-biaya-kirim input-rupiah'),
        '<input type="text" ' . $formAttr . ' name="cr_pasuruan" class="row-cr" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;" value="' . e(is_numeric($r->cr_pasuruan) ? number_format((float) $r->cr_pasuruan, 4) . '%' : $r->cr_pasuruan) . '">',
        $statusMobilHtml,
        '<span class="text-primary fw-medium">' . e($r->lama_waktu_pencarian_pasuruan) . '</span>',
        $slaMobilHtml,
        $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-',
        $textInput('pic_monitoring_pasuruan', $r->pic_monitoring_pasuruan),
        $r->created_at ? \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') : '-',
        '<input type="number" ' . $formAttr . ' name="act_urutan_bongkar_pasuruan" value="' . e($r->act_urutan_bongkar_pasuruan) . '">',
        '<input type="number" ' . $formAttr . ' name="selisih_quantity_pasuruan" value="' . e(($r->selisih_quantity_pasuruan === null || (float)$r->selisih_quantity_pasuruan === 0.0) ? '' : $r->selisih_quantity_pasuruan) . '">',
        '<input type="number" ' . $formAttr . ' name="actual_delivery_quantity_pasuruan" value="' . e($r->actual_delivery_quantity_pasuruan) . '" readonly style="background:#f1f5f9;">',
        '<input type="text" ' . $formAttr . ' name="biaya_kuli_pasuruan" class="rupiah-input" value="' . e($r->biaya_kuli_pasuruan ? number_format($r->biaya_kuli_pasuruan, 0, ',', '.') : '') . '">',
        '<input type="text" ' . $formAttr . ' name="total_biaya_kuli_pasuruan" value="Rp ' . number_format($r->total_biaya_kuli_pasuruan ?? 0, 0, ',', '.') . '" readonly>',
        $buildSelect('reason_selisih_quantity_pasuruan', $r->reason_selisih_quantity_pasuruan, $lists['reasonSelisihQty'], 'reason-selisih-select'),
        $dateInput('estimasi_tiba_pasuruan', $r->estimasi_tiba_pasuruan, true),
        $datetimeInput('tanggal_tiba_pasuruan', $r->tanggal_tiba_pasuruan),
        '<input type="number" step="0.01" ' . $formAttr . ' name="lama_perjalanan_pasuruan" value="' . e($r->lama_perjalanan_pasuruan) . '" readonly style="background:#f1f5f9;">',
        $textInput('sla_tiba_pasuruan', $r->sla_tiba_pasuruan, '', true),
        $datetimeInput('tanggal_bongkar_pasuruan', $r->tanggal_bongkar_pasuruan),
        $statusBongkarHtml,
        '<input type="number" step="0.01" ' . $formAttr . ' name="overstay_days_pasuruan" value="' . e($r->overstay_days_pasuruan) . '" readonly style="background:#f1f5f9;">',
        $textInput('sla_bongkar_pasuruan', $r->sla_bongkar_pasuruan, '', true),
        $buildSelect('reason_waktu_tiba_pasuruan', $r->reason_waktu_tiba_pasuruan, $lists['reasonTiba'], 'reason-tiba-select'),
        $buildSelect('reason_waktu_bongkar_pasuruan', $r->reason_waktu_bongkar_pasuruan, $lists['reasonBongkar'], 'reason-bongkar-select'),
        $textInput('remarks_pasuruan', $r->remarks_pasuruan),
        $textInput('nama_kapal_pasuruan', $r->nama_kapal_pasuruan),
        $dateInput('etd_pasuruan', $r->etd_pasuruan),
        $dateInput('eta_pasuruan', $r->eta_pasuruan),
        $dateInput('atd_pasuruan', $r->atd_pasuruan),
        $dateInput('ata_pasuruan', $r->ata_pasuruan),
        $estimasiAdmin ? $estimasiAdmin->format('d-m-Y') : '-',
        $estimasiAdminBadge,
        '<div class="btn-action"><a href="' . route('pasuruan.destroy', $id) . '" class="btn btn-danger btn-sm px-2 d-flex align-items-center gap-1" onclick="return confirm(\'Hapus data ini?\')"><i class="fa-solid fa-trash"></i> Del</a></div>',
    ];
}


}