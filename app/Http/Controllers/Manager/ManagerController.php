<?php
namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

    public function dataLogistikPasuruan()
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

        return view('manager.data_logistik_pasuruan', compact(
            'logistik',
            'planners',
            'areas'
        ));
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

}