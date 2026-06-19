<?php

namespace App\Http\Controllers\spv;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;

class SpvMonitoringController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */
    //    public function dashboard()
    // {
    //     $total_data = DB::table('logistik_pengiriman')->count();

    //     $total_tiba_ontime = DB::table('logistik_pengiriman')
    //         ->whereIn('status_akhir', ['On Time','ONTIME','OnTime'])
    //         ->count();

    //     $total_tiba_delay = DB::table('logistik_pengiriman')
    //         ->whereIn('status_akhir', ['Delay','DELAY','Critical Delay'])
    //         ->count();

    //     $total_final_delay = DB::table('logistik_pengiriman')
    //         ->whereIn('status_akhir', ['Delay','Critical Delay'])
    //         ->count();

    //     $total_bongkar_ontime = DB::table('logistik_pengiriman')
    //         ->where(function ($q) {
    //             $q->whereIn('sla_bongkar', ['H+0','On Time','ONTIME'])
    //               ->orWhere('overstay_days','<=',0);
    //         })
    //         ->count();

    //     $total_bongkar_delay = DB::table('logistik_pengiriman')
    //         ->where(function ($q) {
    //             $q->whereIn('sla_bongkar', ['Delay','Critical Delay'])
    //               ->orWhere('overstay_days','>',0);
    //         })
    //         ->count();

    //     $summary_area = DB::table('logistik_pengiriman')
    //         ->select('area', DB::raw('COUNT(*) as total'))
    //         ->whereNotNull('area')
    //         ->groupBy('area')
    //         ->orderByDesc('total')
    //         ->get();

    //     return view('spvmonitoring.dashboard', compact(
    //         'total_data',
    //         'total_tiba_ontime',
    //         'total_tiba_delay',
    //         'total_final_delay',
    //         'total_bongkar_ontime',
    //         'total_bongkar_delay',
    //         'summary_area'
    //     ));
    // }

    public function dashboard(Request $request)
    {
        // ================= FILTER =================
        $date  = $request->date;
        $month = $request->month;
        $year  = $request->year;
        $area  = $request->area;

        // ================= BASE QUERY =================
        $base = DB::table('logistik_pengiriman');

        // FILTER DATE
        if ($date) {

            $base->whereDate(
                'tanggal_tiba',
                $date
            );
        }

        // FILTER MONTH
        if ($month) {

            $base->whereMonth(
                'tanggal_tiba',
                substr($month, 5, 2)
            );

            $base->whereYear(
                'tanggal_tiba',
                substr($month, 0, 4)
            );
        }

        // FILTER YEAR
        if ($year) {

            $base->whereYear(
                'tanggal_tiba',
                $year
            );
        }

        // FILTER AREA
        if ($area) {

            $base->where(
                'area',
                $area
            );
        }
        if ($request->filled('dist_channel')) {
            $base->where('dist_channel', $request->dist_channel);
        }

        // ================= TOTAL =================
        $total_data = (clone $base)->count();

        // ================= ONTIME =================
        // $total_tiba_ontime = (clone $base)
        //     ->where(function ($q) {

        //         $q->whereRaw("LOWER(sla_tiba) = 'on time'")
        //             ->orWhere('sla_tiba', 'ONTIME')
        //             ->orWhere('sla_tiba', 'H+0');
        //     })
        //     ->count();

        // ================= TIBA ONTIME =================
        $total_tiba_ontime = (clone $base)
            ->where(function ($q) {

                $q->where('sla_tiba', 'On Time')
                    ->orWhere('sla_tiba', 'ONTIME')
                    ->orWhere('sla_tiba', 'H+0');
            })
            ->count();


        // ================= TIBA DELAY =================
        $total_tiba_delay = (clone $base)
            ->where(function ($q) {

                $q->where('sla_tiba', 'Delay')
                    ->orWhere('sla_tiba', 'Critical Delay')
                    ->orWhere('sla_tiba', 'H+1')
                    ->orWhere('sla_tiba', 'H+2')
                    ->orWhere('sla_tiba', 'H>2');
            })
            ->count();
        // ================= FINAL DELAY =================
        $total_final_delay = (clone $base)
            ->where(function ($q) {

                $q->whereRaw("LOWER(sla_tiba) = 'delay'")
                    ->orWhereRaw("LOWER(sla_tiba) = 'critical delay'")
                    ->orWhere('sla_tiba', 'H+1')
                    ->orWhere('sla_tiba', 'H+2')
                    ->orWhere('sla_tiba', 'H>2');
            })
            ->count();
        // ================= BONGKAR ONTIME =================
        // ================= BONGKAR ONTIME =================
        $total_bongkar_ontime = (clone $base)
            ->where(function ($q) {

                $q->where('sla_bongkar', 'On Time')
                    ->orWhere('sla_bongkar', 'ONTIME')
                    ->orWhere('sla_bongkar', 'H+0');
            })
            ->count();
        $list_dist_channel = DB::table('logistik_pengiriman')
            ->select('dist_channel')
            ->whereNotNull('dist_channel')
            ->distinct()
            ->orderBy('dist_channel')
            ->get();


        // ================= BONGKAR DELAY =================
        $total_bongkar_delay = (clone $base)
            ->where(function ($q) {

                $q->where('sla_bongkar', 'Delay')
                    ->orWhere('sla_bongkar', 'Critical Delay')
                    ->orWhere('sla_bongkar', 'H+1')
                    ->orWhere('sla_bongkar', 'H+2')
                    ->orWhere('sla_bongkar', 'H>2');
            })
            ->count();
        // ================= FINANCE =================
        $totalNilaiMuatan = (clone $base)
            ->selectRaw("
        SUM(
            CASE
                WHEN nilai_muatan IS NULL THEN 0
                ELSE CAST(REPLACE(REPLACE(nilai_muatan, '.', ''), ',', '') AS UNSIGNED)
            END
        ) as total
    ")
            ->value('total');

        $totalBiayaKirim = (clone $base)
            ->selectRaw("
        SUM(
            CASE
                WHEN biaya_kirim IS NULL THEN 0
                ELSE CAST(REPLACE(REPLACE(biaya_kirim, '.', ''), ',', '') AS UNSIGNED)
            END
        ) as total
    ")
            ->value('total');

        // ================= SUMMARY AREA =================
        $summary_area = (clone $base)
            ->select(
                'area',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        // ================= DROPDOWN AREA =================
        $list_area = DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->get();

        return view('spvmonitoring.dashboard', compact(
            'total_data',
            'total_tiba_ontime',
            'total_tiba_delay',
            'total_final_delay',
            'total_bongkar_ontime',
            'list_dist_channel',
            'total_bongkar_delay',
            'summary_area',
            'list_area'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | DATA LOGISTIK
    |--------------------------------------------------------------------------
    */
    public function dataLogistik(Request $request)
    {
        $query = LogistikPengiriman::query();

        // ================= FILTER AREA =================
        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        // ================= FILTER PIC MONITORING (BARU) =================
        if ($request->filled('pic_monitoring')) {
            $query->where('pic_monitoring', $request->pic_monitoring);
        }

        // ================= FILTER BULAN =================
        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_tiba', $request->bulan);
        }

        // ================= FILTER TAHUN =================
        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_tiba', $request->tahun);
        }

        $logistik = $query->latest()->get();

        // dropdown PIC
        $picList = DB::table('logistik_pengiriman')
            ->select('pic_monitoring')
            ->whereNotNull('pic_monitoring')
            ->distinct()
            ->orderBy('pic_monitoring')
            ->pluck('pic_monitoring');

        // dropdown area (biar konsisten pakai ini juga)
        $areaList = DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        // dropdown akurasi
        $akurasiTiba = DB::table('akurasi3')
            ->distinct()
            ->pluck('akurasi_waktu_tiba');

        $akurasiBongkar = DB::table('akurasi3')
            ->distinct()
            ->pluck('akurasi_waktu_bongkar');

        return view('spvmonitoring.data_monitoring', compact(
            'logistik',
            'picList',
            'areaList',
            'akurasiTiba',
            'akurasiBongkar'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | SLA ONTIME
    |--------------------------------------------------------------------------
    */
    public function slaOntime()
    {
        $list = DB::table('logistik_pengiriman')
            ->where(function ($q) {

                $q->whereRaw("LOWER(sla_tiba) = 'on time'")
                    ->orWhere('sla_tiba', 'ONTIME')
                    ->orWhere('sla_tiba', 'H+0');
            })
            ->orderByDesc('tanggal_tiba')
            ->get();

        $list_area = DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();

        return view(
            'spvmonitoring.sla_ontime',
            compact('list', 'list_area')
        );
    }

    public function slaDelay(Request $request)
    {
        $list = DB::table('logistik_pengiriman')
            ->where(function ($q) {

                $q->whereRaw("LOWER(sla_tiba) = 'delay'")
                    ->orWhereRaw("LOWER(sla_tiba) = 'critical delay'")
                    ->orWhere('sla_tiba', 'H+1')
                    ->orWhere('sla_tiba', 'H+2')
                    ->orWhere('sla_tiba', 'H>2');
            })
            ->orderByDesc('tanggal_tiba')
            ->get();

        return view(
            'spvmonitoring.sla_delay',
            compact('list')
        );
    }
    private function applyFilter($query, $request, $field = 'tanggal_tiba')
    {
        if ($request->area) {
            $query->where('area', $request->area);
        }

        if ($request->date) {
            $query->whereDate($field, $request->date);
        }

        if ($request->month) {

            $query->whereMonth(
                $field,
                substr($request->month, 5, 2)
            );

            $query->whereYear(
                $field,
                substr($request->month, 0, 4)
            );
        }

        if ($request->year) {
            $query->whereYear(
                $field,
                $request->year
            );
        }

        return $query;
    }

    public function fullDashboard(Request $request)
    {

        // ================= BASE QUERY =================

        $base = DB::table('logistik_pengiriman');

        $this->applyFilter($base, $request);

        // ================= TOTAL =================

        $total_data = (clone $base)->count();

        // ================= GUDANG =================

        $gudang_ontime = (clone $base)

            ->where(function ($q) {

                $q->where('sla_loading', 'H+0')
                    ->orWhere('sla_loading', 'On Time')
                    ->orWhere('sla_loading', 'ONTIME');
            })

            ->count();
        $list_area = DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->get();


        $gudang_delay = (clone $base)

            ->where(function ($q) {

                $q->where('sla_loading', 'H+1')
                    ->orWhere('sla_loading', 'H+2')
                    ->orWhere('sla_loading', 'H>2')
                    ->orWhere('sla_loading', 'Delay')
                    ->orWhere('sla_loading', 'Critical Delay');
            })

            ->count();
        $list_dist_channel = DB::table('logistik_pengiriman')
            ->select('dist_channel')
            ->whereNotNull('dist_channel')
            ->distinct()
            ->orderBy('dist_channel')
            ->get();


        // ================= TUJUAN / CUSTOMER =================

        $customer_ontime = (clone $base)

            ->whereNotNull('tanggal_tiba')

            ->whereRaw("
                DATEDIFF(
                    tanggal_tiba,
                    DATE_ADD(tanggal_keluar_gudang, INTERVAL transport_lead_time DAY)
                ) <= 0
            ")

            ->count();


        $customer_delay = (clone $base)

            ->whereNotNull('tanggal_tiba')

            ->whereRaw("
                DATEDIFF(
                    tanggal_tiba,
                    DATE_ADD(tanggal_keluar_gudang, INTERVAL transport_lead_time DAY)
                ) > 0
            ")

            ->count();


        // ================= BONGKAR =================

        $bongkar_ontime = (clone $base)

            ->whereNotNull('sla_bongkar')

            ->where(function ($q) {

                $q->whereRaw("LOWER(sla_bongkar) = 'on time'")
                    ->orWhere('sla_bongkar', 'ONTIME')
                    ->orWhere('sla_bongkar', 'H+0');
            })

            ->count();


        $bongkar_delay = (clone $base)

            ->whereNotNull('sla_bongkar')

            ->where(function ($q) {

                $q->whereRaw("LOWER(sla_bongkar) = 'delay'")
                    ->orWhereRaw("LOWER(sla_bongkar) = 'critical delay'")
                    ->orWhere('sla_bongkar', 'H+1')
                    ->orWhere('sla_bongkar', 'H+2')
                    ->orWhere('sla_bongkar', 'H>2');
            })

            ->count();


        // ================= ARMADA =================

        $planner_armada = (clone $base)

            ->where('ketersediaan_unit', 'Sudah Dapat')

            ->count();


        $planner_belum_armada = (clone $base)

            ->where('ketersediaan_unit', 'Belum Dapat')

            ->count();


        // ================= PLANNER =================

        $planner_ontime = $gudang_ontime;

        $planner_delay = $gudang_delay;


        // ================= TOTAL NILAI MUATAN =================

        $totalNilaiMuatan = (clone $base)

            ->selectRaw("
                SUM(
                    CASE
                        WHEN nilai_muatan IS NULL THEN 0
                        ELSE CAST(
                            REPLACE(
                                REPLACE(nilai_muatan,'.',''),
                            ',','') AS UNSIGNED
                        )
                    END
                ) as total
            ")

            ->value('total');


        // ================= TOTAL BIAYA =================

        $totalBiayaKirim = (clone $base)

            ->selectRaw("
                SUM(
                    CASE
                        WHEN biaya_kirim IS NULL THEN 0
                        ELSE CAST(
                            REPLACE(
                                REPLACE(biaya_kirim,'.',''),
                            ',','') AS UNSIGNED
                        )
                    END
                ) as total
            ")

            ->value('total');


        // ================= SUMMARY AREA =================

        $summary_area = (clone $base)

            ->select(
                'area',

                DB::raw('COUNT(*) as total_shipment'),

                DB::raw("
                    SUM(
                        CASE
                            WHEN biaya_kirim IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(biaya_kirim,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_biaya
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN nilai_muatan IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(nilai_muatan,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_muatan
                ")
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

                DB::raw("
                    SUM(
                        CASE
                            WHEN biaya_kirim IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(biaya_kirim,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_biaya
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN nilai_muatan IS NULL THEN 0
                            ELSE CAST(
                                REPLACE(
                                    REPLACE(nilai_muatan,'.',''),
                                ',','') AS UNSIGNED
                            )
                        END
                    ) as total_muatan
                ")
            )

            ->whereNotNull('tujuan')

            ->groupBy('tujuan')

            ->orderByDesc('total_shipment')

            ->get();


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
            'list_dist_channel',
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

            'list_area'
        ));
    }

    private function getArea()
    {
        return DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->get();
    }
    /*
    |--------------------------------------------------------------------------
    | SLA DELAY
    |--------------------------------------------------------------------------
    */
    // public function slaDelay(Request $request)
    // {
    //     $query = DB::table('logistik_pengiriman')
    //         ->whereIn('status_akhir', [
    //             'Delay',
    //             'DELAY',
    //             'Critical Delay',
    //             'delay'
    //         ]);

    //     // 🔥 AUTO FILTER
    //     $this->applyFilter(
    //         $query,
    //         $request,
    //         'tanggal_tiba'
    //     );

    //     $list = $query
    //         ->orderBy('tanggal_tiba', 'DESC')
    //         ->get();

    //     return view(
    //         'spvmonitoring.sla_delay',
    //         compact('list')
    //     );
    // }
    /*
    |--------------------------------------------------------------------------
    | BONGKAR ONTIME
    |--------------------------------------------------------------------------
    */
public function bongkarOntime(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {

            $q->whereIn('sla_bongkar', ['On Time', 'ONTIME', 'H+0'])
              ->orWhere(function ($q2) {
                  $q2->whereNull('overstay_days')
                     ->orWhere('overstay_days', '<=', 0);
              });
        });

    $this->applyFilter($query, $request, 'tanggal_bongkar');

    $list = $query
        ->orderBy('tanggal_bongkar', 'DESC')
        ->get();

    return view('spvmonitoring.bongkar_ontime', compact('list'));
}
    /*
    |--------------------------------------------------------------------------
    | BONGKAR DELAY
    |--------------------------------------------------------------------------
    */
public function bongkarDelay(Request $request)
{
    $query = DB::table('logistik_pengiriman')
        ->where(function ($q) {
            $q->whereRaw("LOWER(sla_bongkar) IN ('delay', 'critical delay')")
              ->orWhereIn('sla_bongkar', ['H+1', 'H+2', 'H>2']);
        });

    // FILTER
    if ($request->filled('tanggal_tiba')) {
        $query->whereDate('tanggal_tiba', $request->tanggal_tiba);
    }

    if ($request->filled('pic_monitoring')) {
        $query->where('pic_monitoring', $request->pic_monitoring);
    }

    if ($request->filled('area')) {
        $query->where('area', $request->area);
    }

    $list = $query
        ->orderBy('tanggal_bongkar', 'DESC')
        ->get();

    return view('spvmonitoring.bongkar_delay', compact('list'));
}
    /*
    |--------------------------------------------------------------------------
    | SUMMARY AREA
    |--------------------------------------------------------------------------
    */
    public function summaryArea(Request $request)
    {
        $query = DB::table('logistik_pengiriman');

        // 🔥 AUTO FILTER
        $this->applyFilter(
            $query,
            $request,
            'tanggal_tiba'
        );

        $summary_area = $query
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(COALESCE(biaya_kirim,0)) as total_biaya'),
                DB::raw('SUM(COALESCE(nilai_muatan,0)) as total_muatan')
            )
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();

        return view(
            'spvmonitoring.summary_area',
            compact('summary_area')
        );
    }

//     public function updateMonitoring(Request $request, $id)
//     {
        

//         $logistik = LogistikPengiriman::findOrFail($id);

//         $keluar = $logistik->tanggal_keluar_gudang
//             ? strtotime(date('Y-m-d', strtotime($logistik->tanggal_keluar_gudang)))
//             : null;

//         $tiba = $request->tanggal_tiba
//             ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)))
//             : null;

//         $bongkar = $request->tanggal_bongkar
//             ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)))
//             : null;

//         $leadtime = (int)($logistik->transport_lead_time ?? 0);

//         $estimasi = $keluar
//             ? strtotime("+{$leadtime} days", $keluar)
//             : null;

//         $lama_perjalanan = ($keluar && $tiba)
//             ? max(0, floor(($tiba - $keluar) / 86400))
//             : null;

//         $sla_tiba = ($tiba && $estimasi)
//             ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
//             : '-';

//         $overstay = ($tiba && $bongkar)
//             ? max(0, floor(($bongkar - $tiba) / 86400))
//             : null;

//         $sla_bongkar = ($tiba && $bongkar)
//             ? (($overstay <= 0) ? 'On Time' : 'Delay')
//             : '-';

//         $logistik->pic_monitoring   = $request->pic_monitoring;
//         $logistik->status_kendaraan = $request->status_kendaraan;
//         $logistik->action_required  = $request->action_required;
//         $logistik->monitoring_alert = $request->monitoring_alert;

//         $logistik->act_urutan_bongkar = $request->act_urutan_bongkar;

//         $logistik->tanggal_tiba    = $request->tanggal_tiba;
//         $logistik->tanggal_bongkar  = $request->tanggal_bongkar;

//         $logistik->sla_tiba        = $sla_tiba;
//         $logistik->sla_bongkar     = $sla_bongkar;

//         $logistik->overstay_days   = $overstay;
//         $logistik->lama_perjalanan = $lama_perjalanan;

//         $logistik->reason_tiba     = $request->reason_tiba;
//         $logistik->reason_bongkar  = $request->reason_bongkar;

//         $logistik->remarks         = $request->remarks;

//         $logistik->act_pgi_date = $request->input('act_pgi_date');
// $logistik->created_by   = $request->input('created_by');
// $logistik->total_do_qty_car = $request->input('total_do_qty_car');


//         $logistik->save();

//         return response()->json([
//             'success' => true,
//             'message' => 'Monitoring berhasil diupdate'
//         ]);
//     }
// dari monitoring update
public function update(Request $request, $id)
{

     $logistik = LogistikPengiriman::findOrFail($id);
    $keluar = $logistik->tanggal_keluar_gudang
        ? strtotime(date('Y-m-d', strtotime($logistik->tanggal_keluar_gudang)))
        : null;

    $tiba = $request->tanggal_tiba
        ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)))
        : null;

    $bongkar = $request->tanggal_bongkar
        ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)))
        : null;

    $leadtime = (int)($logistik->transport_lead_time ?? 0);

    $estimasi = $keluar
        ? strtotime("+{$leadtime} days", $keluar)
        : null;

    $lama_perjalanan = ($keluar && $tiba)
        ? max(0, floor(($tiba - $keluar) / 86400))
        : null;

    $sla_tiba = ($tiba && $estimasi)
        ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
        : '-';

    $overstay = ($tiba && $bongkar)
        ? max(0, floor(($bongkar - $tiba) / 86400))
        : null;

    $sla_bongkar = ($tiba && $bongkar)
        ? (($overstay <= 0) ? 'On Time' : 'Delay')
        : '-';

    // =========================
    // AUTO STATUS + ALERT
    // =========================
    $logic = $this->generateStatusAlert($sla_tiba, $sla_bongkar);

    $logistik->status_akhir = $logic['status_akhir'];
    $logistik->monitoring_alert = $logic['alert'];

    // =========================
    // FIELD UPDATE
    // =========================
    $logistik->pic_monitoring   = $request->pic_monitoring;
    $logistik->status_kendaraan = $request->status_kendaraan;
    $logistik->action_required  = $request->action_required;

    $logistik->act_urutan_bongkar = $request->act_urutan_bongkar;

    $logistik->tanggal_tiba    = $request->tanggal_tiba;
    $logistik->tanggal_bongkar = $request->tanggal_bongkar;

    $logistik->overstay_days   = $overstay;
    $logistik->lama_perjalanan = $lama_perjalanan;

    $logistik->reason_tiba    = $request->reason_tiba;
    $logistik->reason_bongkar = $request->reason_bongkar;

    $logistik->remarks        = $request->remarks;

    $logistik->act_pgi_date      = $request->input('act_pgi_date');
    $logistik->created_by        = $request->input('created_by');
    $logistik->total_do_qty_car  = $request->input('total_do_qty_car');

    // =========================
// TRANSPORT LAUT
// =========================
$logistik->transport_laut = $request->transport_laut ?? 0;

if ($logistik->transport_laut == 1) {

    $logistik->nama_kapal = $request->nama_kapal;

    $logistik->etd = $request->etd;
    $logistik->eta = $request->eta;
    $logistik->atd = $request->atd;
    $logistik->ata = $request->ata;

} else {

    $logistik->nama_kapal = null;
    $logistik->etd = null;
    $logistik->eta = null;
    $logistik->atd = null;
    $logistik->ata = null;
}

    $logistik->save();

    return response()->json([
        'success' => true,
        'message' => 'Monitoring berhasil diupdate',
        'status_akhir' => $logic['status_akhir'],
        'alert' => $logic['alert']
    ]);
}

    private function generateStatusAlert($sla_tiba, $sla_bongkar)
{
    // default
    $status_akhir = 'In Transit';
    $alert = 'Menunggu update';

    // ON TIME + ON TIME
    if ($sla_tiba == 'On Time' && $sla_bongkar == 'On Time') {
        $status_akhir = 'Delivered On Time';
        $alert = 'Aman';
    }

    // ON TIME + DELAY
    elseif ($sla_tiba == 'On Time' && $sla_bongkar != 'On Time') {
        $status_akhir = 'Delivered Delay';
        $alert = 'Delay di Pembongkaran';
    }

    // DELAY + ON TIME
    elseif ($sla_tiba != 'On Time' && $sla_bongkar == 'On Time') {
        $status_akhir = 'Delay';
        $alert = 'Delay di Perjalanan';
    }

    // DELAY + DELAY
    elseif ($sla_tiba != 'On Time' && $sla_bongkar != 'On Time') {
        $status_akhir = 'Critical Delay';
        $alert = 'Delay Total (Perjalanan + Pembongkaran)';
    }

    return [
        'status_akhir' => $status_akhir,
        'alert' => $alert
    ];
}
    public function fullDassshboard(Request $request)
    {
        // ================= FILTER =================
        $date  = $request->date;
        $month = $request->month;
        $year  = $request->year;
        $area  = $request->area;

        $base = DB::table('logistik_pengiriman');

        // ================= FILTER DATE =================
        if ($date) {

            $base->whereDate(
                'tanggal_naik_logistik',
                $date
            );
        }

        // ================= FILTER MONTH =================
        if ($month) {

            $base->whereMonth(
                'tanggal_naik_logistik',
                substr($month, 5, 2)
            );

            $base->whereYear(
                'tanggal_naik_logistik',
                substr($month, 0, 4)
            );
        }

        // ================= FILTER YEAR =================
        if ($year) {

            $base->whereYear(
                'tanggal_naik_logistik',
                $year
            );
        }

        // ================= FILTER AREA =================
        if ($area) {

            $base->where(
                'area',
                $area
            );
        }

        // ================= TOTAL =================
        $total_data = (clone $base)->count();

        // ================= GUDANG =================
        $gudang_ontime = (clone $base)
            ->whereRaw("LOWER(status) LIKE '%on%'")
            ->count();

        $gudang_delay = (clone $base)
            ->whereRaw("LOWER(status) LIKE '%delay%'")
            ->count();

        // ================= CUSTOMER =================
        $customer_ontime = (clone $base)
            ->whereRaw("LOWER(status_akhir) LIKE '%on%'")
            ->count();

        $customer_delay = (clone $base)
            ->whereRaw("LOWER(status_akhir) LIKE '%delay%'")
            ->count();

        // ================= BONGKAR =================
        $bongkar_ontime = (clone $base)
            ->where(function ($q) {

                $q->where('sla_bongkar', 'H+0')
                    ->orWhereRaw("LOWER(sla_bongkar) LIKE '%on%'")
                    ->orWhere('overstay_days', '<=', 0);
            })
            ->count();

        $bongkar_delay = (clone $base)
            ->where(function ($q) {

                $q->whereRaw("LOWER(sla_bongkar) LIKE '%delay%'")
                    ->orWhere('overstay_days', '>', 0);
            })
            ->count();

        // ================= FINANCE =================
        $totalNilaiMuatan = (clone $base)
            ->selectRaw("
            SUM(
                CASE 
                    WHEN nilai_muatan IS NULL THEN 0
                    ELSE CAST(REPLACE(REPLACE(nilai_muatan, '.', ''), ',', '') AS UNSIGNED)
                END
            ) as total
        ")
            ->value('total');

        $totalBiayaKirim = (clone $base)
            ->selectRaw("
            SUM(
                CASE 
                    WHEN biaya_kirim IS NULL THEN 0
                    ELSE CAST(REPLACE(REPLACE(biaya_kirim, '.', ''), ',', '') AS UNSIGNED)
                END
            ) as total
        ")
            ->value('total');

        // ================= AREA =================
        $summary_area = (clone $base)
            ->select(
                'area',

                DB::raw('COUNT(*) as total_shipment'),

                DB::raw("
                SUM(
                    CASE 
                        WHEN biaya_kirim IS NULL THEN 0
                        ELSE CAST(REPLACE(REPLACE(biaya_kirim,'.',''),',','') AS UNSIGNED)
                    END
                ) as total_biaya
            "),

                DB::raw("
                SUM(
                    CASE 
                        WHEN nilai_muatan IS NULL THEN 0
                        ELSE CAST(REPLACE(REPLACE(nilai_muatan,'.',''),',','') AS UNSIGNED)
                    END
                ) as total_muatan
            ")
            )
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();

        // ================= TUJUAN =================
        $summary_tujuan = (clone $base)
            ->select(
                'tujuan',

                DB::raw('COUNT(*) as total_shipment'),

                DB::raw("
                SUM(
                    CASE 
                        WHEN biaya_kirim IS NULL THEN 0
                        ELSE CAST(REPLACE(REPLACE(biaya_kirim,'.',''),',','') AS UNSIGNED)
                    END
                ) as total_biaya
            "),

                DB::raw("
                SUM(
                    CASE 
                        WHEN nilai_muatan IS NULL THEN 0
                        ELSE CAST(REPLACE(REPLACE(nilai_muatan,'.',''),',','') AS UNSIGNED)
                    END
                ) as total_muatan
            ")
            )
            ->whereNotNull('tujuan')
            ->groupBy('tujuan')
            ->orderByDesc('total_shipment')
            ->get();

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

        // ================= PLANNER =================
        $planner_ontime = (clone $base)
            ->whereRaw("LOWER(status) LIKE '%on%'")
            ->count();

        $planner_delay = (clone $base)
            ->whereRaw("LOWER(status) LIKE '%delay%'")
            ->count();

        $planner_armada = (clone $base)
            ->whereNotNull('mobil')
            ->count();

        $planner_belum_armada = (clone $base)
            ->whereNull('mobil')
            ->count();

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
        $list_area = DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->distinct()
            ->orderBy('area')
            ->get();

        // ================= VIEW =================
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


            'list_area'
        ));
    }
    public function fullDataLogistik(Request $request)
    {
        $query = DB::table('logistik_pengiriman');

        // FILTER
        if ($request->filled('date')) {
            $query->whereDate('tanggal_naik_logistik', $request->date);
        }

        if ($request->filled('month')) {
            $query->whereMonth(
                'tanggal_naik_logistik',
                substr($request->month, 5, 2)
            )->whereYear(
                'tanggal_naik_logistik',
                substr($request->month, 0, 4)
            );
        }

        if ($request->filled('year')) {
            $query->whereYear('tanggal_naik_logistik', $request->year);
        }

        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        $logistik = $query
            ->orderByDesc('tanggal_naik_logistik')
            ->get();

        // ================= AREA FILTER =================
        $areaList = LogistikPengiriman::select('area')
            ->distinct()
            ->whereNotNull('area')
            ->orderBy('area')
            ->pluck('area');

        // ================= ESTIMASI =================
        $estimasiData = $logistik->map(function ($item) {
            return [
                'tujuan'   => $item->tujuan,
                'estimasi' => $item->rencana_kirim ?? null,
            ];
        });

        return view('data_logistik', compact(
            'logistik',
            'areaList',
            'estimasiData'
        ));
    }
}
