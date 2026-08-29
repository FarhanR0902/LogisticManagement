<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogistikPengirimanPasuruan;
use Illuminate\Support\Facades\Cache;
use App\Models\LogistikPengiriman;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PasuruanImport;
use App\Exports\PasuruanExport;


class PasuruanController extends Controller
{


    private array $fillableFields = [
        'planner_pasuruan',
        'no_shipment_pasuruan',
        'biaya_kuli_pasuruan',
        'total_biaya_kuli_pasuruan',
        'tanggal_terima_po_pasuruan',
        'rencana_kirim_pasuruan',
        'transport_lead_time_pasuruan',

        'tujuan_pasuruan',
        'area_pasuruan',
        'pulau_pasuruan',

        'ketersediaan_unit_pasuruan',
        'mobil_pasuruan',
        'perubahan_mobil_pasuruan',

        'kategori_pengiriman_pasuruan',

        'nilai_muatan_pasuruan',
        'biaya_kirim_pasuruan',
        // 'cr_pasuruan',

        'kategori_ekspedisi_pasuruan',
        'ekspedisi_pasuruan',

        'no_pol_pasuruan',
        'nama_driver_pasuruan',

        'tanggal_dpt_unit_pasuruan',
        'planning_loading_pasuruan',
        'tanggal_tiba_gudang_pasuruan',
        'tanggal_keluar_gudang_pasuruan',

        'lama_digudang_pasuruan',
        'sla_ketepatan_loading_pasuruan',
        'keterangan_loading_pasuruan',
        'keterangan_loading2_pasuruan',

        'lama_waktu_pencarian_pasuruan',
        'sla_dapat_mobil_pasuruan',

        'sla_ketibaan_gudang_muat_pasuruan',
        'keterangan_ketibaan_gudang_pasuruan',

        'pic_monitoring_pasuruan',
        'status_kendaraan_pasuruan',
        'monitoring_alert_pasuruan',
        'action_required_pasuruan',

        'tanggal_tiba_pasuruan',
        'lama_perjalanan_pasuruan',
        'sla_tiba_pasuruan',
        'keterangan_waktu_tiba_pasuruan',

        'tanggal_bongkar_pasuruan',
        'overstay_days_pasuruan',
        'sla_bongkar_pasuruan',

        'keterangan_waktu_bongkar_pasuruan',
        'reason_waktu_tiba_pasuruan',
        'reason_waktu_bongkar_pasuruan',
        'remarks_pasuruan',

        'keterangan_monitoring_pasuruan',
        'route_pasuruan',
        'via_kirim_pasuruan',
        'ekpedisi_pasuruan', // lihat catatan di atas class, kemungkinan typo dari ekspedisi_pasuruan
        'total_do_pasuruan',
        'dist_channel_pasuruan',

        'act_urutan_bongkar_pasuruan',
        'tanggal_tiba_estimasi_pasuruan',
        'status_akhir_pasuruan',
        'act_pgi_date_pasuruan',

        'nama_kapal_pasuruan',
        'etd_pasuruan',
        'eta_pasuruan',
        'atd_pasuruan',
        'ata_pasuruan',

        'actual_delivery_quantity_pasuruan',
        'selisih_quantity_pasuruan',
        'reason_selisih_quantity_pasuruan',

        'transport_laut_pasuruan',
        'estimasi_tiba_pasuruan',
        'shipping_point_pasuruan',

        'created_by_pasuruan',
        'qty_monitoring_pasuruan',
        'remarks_qty_pasuruan',
        'selisih_qty_pasuruan',
        'create_tgl_pasuruan',
    ];

    private function generateMonitoringPasuruan(array &$data)
    {
        $keluar = !empty($data['tanggal_keluar_gudang_pasuruan'])
            ? strtotime($data['tanggal_keluar_gudang_pasuruan'])
            : null;

        $tiba = !empty($data['tanggal_tiba_pasuruan'])
            ? strtotime($data['tanggal_tiba_pasuruan'])
            : null;

        $bongkar = !empty($data['tanggal_bongkar_pasuruan'])
            ? strtotime($data['tanggal_bongkar_pasuruan'])
            : null;

        $leadtime = isset($data['transport_lead_time_pasuruan'])
            ? (int)$data['transport_lead_time_pasuruan']
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Estimasi Tiba
        |--------------------------------------------------------------------------
        */

        $estimasi = !empty($data['estimasi_tiba_pasuruan'])
            ? strtotime($data['estimasi_tiba_pasuruan'])
            : null;

        if (!$estimasi && $keluar && $leadtime > 0) {
            $estimasi = strtotime("+{$leadtime} days", $keluar);
        }

        /*
        |--------------------------------------------------------------------------
        | Lama Perjalanan
        |--------------------------------------------------------------------------
        */

       if ($keluar && $tiba) {
    $keluarDate = strtotime(date('Y-m-d', $keluar));
    $tibaDate   = strtotime(date('Y-m-d', $tiba));

    $lama = ($tibaDate - $keluarDate) / 86400;

    $data['lama_perjalanan_pasuruan'] = max(0, (int) $lama);
}


        if ($tiba && $estimasi) {


            $tibaDate = strtotime(date('Y-m-d', $tiba));
            $estimasiDate = strtotime(date('Y-m-d', $estimasi));

            if ($tibaDate <= $estimasiDate) {
                $data['sla_tiba_pasuruan'] = 'On Time';
            } else {
                $data['sla_tiba_pasuruan'] = 'Delay';
            }
        }
        /*
        |--------------------------------------------------------------------------
        | Overstay
        |--------------------------------------------------------------------------
        */

        if ($tiba && $bongkar) {

            // FIXED: sama seperti SLA Tiba — bandingkan per tanggal kalender,
            // bukan selisih jam mentah. Kalau tiba jam 14:45 dan bongkar jam
            // 21:45 di HARI YANG SAMA, itu harus dianggap overstay 0 hari
            // (On Time), bukan otomatis "1 hari" gara-gara ceil() dari selisih
            // beberapa jam.
            $tibaDate = strtotime(date('Y-m-d', $tiba));
            $bongkarDate = strtotime(date('Y-m-d', $bongkar));

            $overstay = ($bongkarDate - $tibaDate) / 86400;

            $data['overstay_days_pasuruan'] = max(0, $overstay);

            $data['sla_bongkar_pasuruan'] =
                $overstay <= 0 ? 'On Time' : 'Delay';
        }

        /*
        |--------------------------------------------------------------------------
        | Alert Monitoring
        |--------------------------------------------------------------------------
        */

        if ($bongkar) {

            $data['monitoring_alert_pasuruan'] = 'SELESAI';
        } elseif ($tiba) {

            $data['monitoring_alert_pasuruan'] = 'TIBA DI TUJUAN';
        } elseif ($estimasi) {

            $today = strtotime(date('Y-m-d'));

            $selisih = ceil(($estimasi - $today) / 86400);

            if ($selisih < 0) {

                $data['monitoring_alert_pasuruan'] = 'TERLAMBAT';
            } elseif ($selisih <= 2) {

                $data['monitoring_alert_pasuruan'] = 'WARNING H-2';
            } else {

                $data['monitoring_alert_pasuruan'] = 'AMAN';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Action Required
        |--------------------------------------------------------------------------
        */

        switch ($data['monitoring_alert_pasuruan'] ?? '') {

            case 'TERLAMBAT':
                $data['action_required_pasuruan'] = 'Follow Up Driver';
                break;

            case 'WARNING H-2':
                $data['action_required_pasuruan'] = 'Monitoring';
                break;

            case 'TIBA DI TUJUAN':
                $data['action_required_pasuruan'] = 'Menunggu Bongkar';
                break;

            case 'SELESAI':
                $data['action_required_pasuruan'] = 'Closed';
                break;

            default:
                $data['action_required_pasuruan'] = '-';
                break;
        }
    }


    private function recalculateCr(string $noShipment): float
    {
        $rows = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $totalMuatan = (float) $rows->sum(function ($row) {
            return (float) $row->nilai_muatan_pasuruan;
        });

        $biayaKirim = (float) optional(
            $rows->filter(function ($row) {
                return (float) $row->biaya_kirim_pasuruan > 0;
            })->sortByDesc('id')->first()
        )->biaya_kirim_pasuruan;

        $lastCr = 0;

        if ($totalMuatan > 0 && $biayaKirim > 0) {

            $totalCR = ($biayaKirim / $totalMuatan) * 100;

            foreach ($rows as $row) {

                $nilaiMuatanBaris = (float) $row->nilai_muatan_pasuruan;

                $crBaris = 0;

                if ($nilaiMuatanBaris > 0) {
                    $kontribusi = $nilaiMuatanBaris / $totalMuatan;
                    $crBaris = round($kontribusi * $totalCR, 4);
                }

                LogistikPengirimanPasuruan::where('id', $row->id)
                    ->update(['cr_pasuruan' => $crBaris]);

                $lastCr = $crBaris;
            }
        } else {

            // Kalau tidak ada biaya_kirim / total_muatan valid, semua CR = 0
            LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)
                ->update(['cr_pasuruan' => 0]);
        }

        return $lastCr;
    }
    /*
|--------------------------------------------------------------------------
| GUDANG - SUDAH TIBA (trigger: tanggal_tiba_gudang_pasuruan TERISI)
|--------------------------------------------------------------------------
*/
    public function gudangOntimePasuruan(Request $request)
    {
        $query = DB::table('logistik_pengiriman_pasuruan')
            ->whereNotNull('tanggal_tiba_gudang_pasuruan');

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_tiba_gudang_pasuruan', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_tiba_gudang_pasuruan', $request->tahun);
        }

        if ($request->filled('area')) {
            $query->where('area_pasuruan', $request->area);
        }

        $list = $query->orderByDesc('tanggal_tiba_gudang_pasuruan')->get();

        $list_area = DB::table('logistik_pengiriman_pasuruan')
            ->select('area_pasuruan')
            ->whereNotNull('area_pasuruan')
            ->distinct()
            ->orderBy('area_pasuruan')
            ->get();

        return view('pasuruan.gudang_ontime', compact('list', 'list_area'));
    }
    /*
|--------------------------------------------------------------------------
| GUDANG - BELUM TIBA (trigger: tanggal_tiba_gudang_pasuruan KOSONG)
|--------------------------------------------------------------------------
*/
    public function gudangDelayPasuruan(Request $request)
    {
        $query = DB::table('logistik_pengiriman_pasuruan')
            ->where(function ($q) {
                $q->whereNull('tanggal_tiba_gudang_pasuruan')
                    ->orWhere('tanggal_tiba_gudang_pasuruan', '');
            });

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_terima_po_pasuruan', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_terima_po_pasuruan', $request->tahun);
        }

        if ($request->filled('area')) {
            $query->where('area_pasuruan', $request->area);
        }

        $list = $query->orderByDesc('tanggal_terima_po_pasuruan')->get();

        $list_area = DB::table('logistik_pengiriman_pasuruan')
            ->select('area_pasuruan')
            ->whereNotNull('area_pasuruan')
            ->distinct()
            ->orderBy('area_pasuruan')
            ->get();

        return view('pasuruan.gudang_delay', compact('list', 'list_area'));
    }
    /*
|--------------------------------------------------------------------------
| TUJUAN / CUSTOMER - ONTIME
|--------------------------------------------------------------------------
*/
    public function tujuanOntimePasuruan(Request $request)
    {
        $query = DB::table('logistik_pengiriman_pasuruan')
            ->selectRaw("
            logistik_pengiriman_pasuruan.*,
            estimasi_tiba_pasuruan AS tanggal_estimasi,
            CASE
                WHEN DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) <= 0
                THEN 'On Time' ELSE 'Delay'
            END AS sla_tiba
        ")
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->whereNotNull('estimasi_tiba_pasuruan')
            ->whereRaw("DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) <= 0");

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_tiba_pasuruan', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_tiba_pasuruan', $request->tahun);
        }

        if ($request->filled('area')) {
            $query->where('area_pasuruan', $request->area);
        }

        $list = $query->orderByDesc('tanggal_tiba_pasuruan')->get();
        $list_area = DB::table('logistik_pengiriman_pasuruan')
            ->select('area_pasuruan')
            ->whereNotNull('area_pasuruan')
            ->distinct()
            ->orderBy('area_pasuruan')
            ->get();

        return view('pasuruan.tujuan_ontime', compact('list', 'list_area'));

        return view('pasuruan.tujuan_ontime', compact('list'));
    }

    /*
|--------------------------------------------------------------------------
| TUJUAN / CUSTOMER - DELAY
|--------------------------------------------------------------------------
*/
    public function tujuanDelayPasuruan(Request $request)
    {
        $query = DB::table('logistik_pengiriman_pasuruan')
            ->selectRaw("
            logistik_pengiriman_pasuruan.*,
            estimasi_tiba_pasuruan AS tanggal_estimasi,
            CASE
                WHEN DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0
                THEN 'Delay' ELSE 'On Time'
            END AS sla_tiba
        ")
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->whereNotNull('estimasi_tiba_pasuruan')
            ->whereRaw("DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0");

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_tiba_pasuruan', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_tiba_pasuruan', $request->tahun);
        }

        if ($request->filled('area')) {
            $query->where('area_pasuruan', $request->area);
        }

        $list = $query
            ->orderBy('no_shipment_pasuruan')
            ->orderBy('estimasi_tiba_pasuruan')
            ->orderBy('id')
            ->get();

        return view('pasuruan.tujuan_delay', compact('list'));
    }

    /*
|--------------------------------------------------------------------------
| BONGKAR - ONTIME
|--------------------------------------------------------------------------
*/
    public function bongkarOntimePasuruan(Request $request)
    {
        $query = DB::table('logistik_pengiriman_pasuruan')
            ->selectRaw("
            *,
            CASE
                WHEN DATEDIFF(DATE(tanggal_bongkar_pasuruan), DATE(tanggal_tiba_pasuruan)) <= 0
                THEN 'On Time' ELSE 'Delay'
            END AS sla_bongkar
        ")
            ->whereNotNull('tanggal_bongkar_pasuruan')
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
            ->whereRaw("DATEDIFF(DATE(tanggal_bongkar_pasuruan), DATE(tanggal_tiba_pasuruan)) <= 0");

        if ($request->filled('tanggal_bongkar')) {
            $query->whereDate('tanggal_bongkar_pasuruan', $request->tanggal_bongkar);
        }

        if ($request->filled('area')) {
            $query->where('area_pasuruan', $request->area);
        }

        $list = $query->orderByDesc('tanggal_bongkar_pasuruan')->get();

        return view('pasuruan.bongkar_ontime', compact('list'));
    }

    /*
|--------------------------------------------------------------------------
| BONGKAR - DELAY
|--------------------------------------------------------------------------
*/
    public function bongkarDelayPasuruan(Request $request)
    {
        $query = DB::table('logistik_pengiriman_pasuruan')
            ->where(function ($q) {
                $q->whereIn('sla_bongkar_pasuruan', ['Delay', 'Critical Delay'])
                    ->orWhere('overstay_days_pasuruan', '>', 0);
            })
            ->whereNotNull('tanggal_bongkar_pasuruan')
            ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00');

        if ($request->filled('tanggal_bongkar')) {
            $query->whereDate('tanggal_bongkar_pasuruan', $request->tanggal_bongkar);
        }

        if ($request->filled('area')) {
            $query->where('area_pasuruan', $request->area);
        }

        $list = $query->orderByDesc('tanggal_bongkar_pasuruan')->get();

        return view('pasuruan.bongkar_delay', compact('list'));
    }


    public function updateTransportLaut(Request $request)
    {
        LogistikPengirimanPasuruan::where(
            'no_shipment_pasuruan',
            $request->no_shipment_pasuruan
        )->update([

            'nama_kapal_pasuruan' => $request->nama_kapal_pasuruan,
            'etd_pasuruan'         => $request->etd_pasuruan,
            'eta_pasuruan'         => $request->eta_pasuruan,
            'atd_pasuruan'         => $request->atd_pasuruan,
            'ata_pasuruan'         => $request->ata_pasuruan,

        ]);

        return back()->with('success', 'Transport Laut berhasil diupdate.');
    }

    public function export(Request $request)
    {
        return Excel::download(
            new PasuruanExport(null, [
                'planner' => $request->planner,
                'area'    => $request->area,
                'date'    => $request->date,
                'month'   => $request->month,
                'year'    => $request->year,
            ]),
            'Data_Logistik_Pasuruan.xlsx'
        );
    }

    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK', 'BANTEN', 'JAWA_BARAT', 'JAWA_TENGAH', 'JAWA_TIMUR', 'YOGYAKARTA'],
        'SUMATERA'   => ['ACEH', 'SUMATERA_UTARA', 'SUMATERA_BARAT', 'RIAU', 'KEP._RIAU', 'JAMBI', 'SUMATERA_SELATAN', 'BENGKULU', 'LAMPUNG', 'KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT', 'KALIMANTAN_TENGAH', 'KALIMANTAN_SELATAN', 'KALIMANTAN_TIMUR', 'KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA', 'SULAWESI_TENGAH', 'SULAWESI_SELATAN', 'SULAWESI_TENGGARA', 'SULAWESI_BARAT', 'GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI', 'NUSA_TENGGARA_BARAT', 'NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU', 'PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA', 'PAPUA_BARAT', 'PAPUA_BARAT_DAYA', 'PAPUA_SELATAN', 'PAPUA_TENGAH'],
    ];
    public function dashboard(Request $request)
    {

        // ================= BASE QUERY =================

        $base = DB::table('logistik_pengiriman_pasuruan');

        $this->applyFilter($base, $request);

        // ================= TOTAL =================

        $total_data = (clone $base)->count();

        // ================= GUDANG =================

        $gudang_ontime = (clone $base)
            ->where(function ($q) {
                $q->whereNotNull('tanggal_tiba_gudang_pasuruan');
            })
            ->count();

        $gudang_delay = (clone $base)
            ->where(function ($q) {
                $q->whereNull('rencana_kirim_pasuruan')
                    ->orWhere('rencana_kirim_pasuruan', '')
                    ->orWhereNull('tanggal_dpt_unit_pasuruan')
                    ->orWhere('tanggal_dpt_unit_pasuruan', '');
            })
            ->count();

        // FIXED: 'sla_loading_pasuruan' tidak ada di skema DB.
        // Diganti ke kolom terdekat 'sla_ketepatan_loading_pasuruan'.
        // Silakan koreksi jika maksudnya kolom lain (mis. sla_dapat_mobil_pasuruan).
        $gudang_unknown = (clone $base)
            ->where(function ($q) {
                $q->whereNull('sla_ketepatan_loading_pasuruan')
                    ->orWhereRaw("TRIM(sla_ketepatan_loading_pasuruan) = ''")
                    ->orWhereRaw("LOWER(TRIM(sla_ketepatan_loading_pasuruan)) NOT IN (
                      'h+0','h+1','h+2','h>2','on time','ontime','delay','critical delay'
                  )");
            })
            ->count();


        // ================= TUJUAN / CUSTOMER =================

        $customer_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->whereNotNull('estimasi_tiba_pasuruan')
            ->whereRaw("
                DATEDIFF(
                    DATE(tanggal_tiba_pasuruan),
                    DATE(estimasi_tiba_pasuruan)
                ) <= 0
            ")
            ->count();

        $customer_delay = (clone $base)
            ->whereNotNull('tanggal_tiba_pasuruan')
            ->whereNotNull('estimasi_tiba_pasuruan')
            ->whereRaw("
                DATEDIFF(
                    DATE(tanggal_tiba_pasuruan),
                    DATE(estimasi_tiba_pasuruan)
                ) > 0
            ")
            ->count();


        // ================= BONGKAR =================

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


        // ================= ARMADA =================
        // FIXED: rencana_kirim -> rencana_kirim_pasuruan, tanggal_dpt_unit -> tanggal_dpt_unit_pasuruan

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

        // FIXED: dist_channel -> dist_channel_pasuruan
        $list_dist_channel = (clone $base)
            ->select('dist_channel_pasuruan')
            ->whereNotNull('dist_channel_pasuruan')
            ->distinct()
            ->orderBy('dist_channel_pasuruan')
            ->get();


        // ================= PLANNER =================
        // FIXED: rencana_kirim -> rencana_kirim_pasuruan, tanggal_dpt_unit -> tanggal_dpt_unit_pasuruan

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

        // ================= TOTAL NILAI MUATAN =================
        // FIXED: nilai_muatan -> nilai_muatan_pasuruan, biaya_kirim -> biaya_kirim_pasuruan

        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan_pasuruan');

        $totalBiayaKirim = (clone $base)
            ->selectRaw("SUM(biaya_kirim_pasuruan) as total")
            ->value('total');


        // ================= SUMMARY AREA =================
        // FIXED: area -> area_pasuruan, biaya_kirim -> biaya_kirim_pasuruan, nilai_muatan -> nilai_muatan_pasuruan
        // Catatan: kolom biaya_kirim_pasuruan & nilai_muatan_pasuruan sudah bertipe decimal(18,2),
        // jadi REPLACE(...,',','') tidak lagi diperlukan kecuali datanya memang disimpan sebagai string berformat ribuan.

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


        // ================= SUMMARY TUJUAN =================
        // FIXED: tujuan -> tujuan_pasuruan, biaya_kirim -> biaya_kirim_pasuruan, nilai_muatan -> nilai_muatan_pasuruan

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


        // ================= EKSPEDISI =================
        // FIXED: kategori_ekspedisi -> kategori_ekspedisi_pasuruan

        $ekspedisi = (clone $base)
            ->select(
                'kategori_ekspedisi_pasuruan',
                DB::raw('COUNT(*) as total')
            )
            ->whereNotNull('kategori_ekspedisi_pasuruan')
            ->groupBy('kategori_ekspedisi_pasuruan')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi_pasuruan');
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

        return view('pasuruan.dashboard', compact(
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
            'list_area'
        ));
    }


    private function applyFilter($query, $request)
    {

        // AREA
        // FIXED: area -> area_pasuruan
        if ($request->area) {
            $query->where('area_pasuruan', $request->area);
        }

        if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
            $query->whereIn('area_pasuruan', self::PULAU_MAP[$request->pulau]);
        }

        // DIST CHANNEL
        // FIXED: dist_channel -> dist_channel_pasuruan
        if ($request->dist_channel) {
            $query->where('dist_channel_pasuruan', $request->dist_channel);
        }

        // DATE
        // ⚠️ PERHATIAN: kolom 'tanggal_naik_logistik' TIDAK ADA di skema tabel
        // logistik_pengiriman_pasuruan yang Anda berikan. Sementara diganti ke
        // 'tanggal_terima_po_pasuruan'. Ganti sesuai kolom tanggal yang benar
        // (misalnya planning_loading_pasuruan atau tanggal_dpt_unit_pasuruan)
        // jika asumsi ini salah.
        if ($request->date) {
            $query->whereDate(
                'tanggal_terima_po_pasuruan',
                $request->date
            );
        }

        // MONTH
        if ($request->month) {
            $query->whereMonth(
                'tanggal_terima_po_pasuruan',
                substr($request->month, 5, 2)
            );

            $query->whereYear(
                'tanggal_terima_po_pasuruan',
                substr($request->month, 0, 4)
            );
        }

        // YEAR
        if ($request->year) {
            $query->whereYear(
                'tanggal_terima_po_pasuruan',
                $request->year
            );
        }

        return $query;
    }

    // Catatan: getArea() sengaja TIDAK diubah karena mengambil dari tabel
    // 'logistik_pengiriman' (tanpa suffix _pasuruan) yang tampaknya memang
    // tabel terpisah/master area, bukan tabel logistik_pengiriman_pasuruan.
    private function getArea()
    {
        return DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new PasuruanImport, $request->file('file'));

        return redirect()
            ->route('pasuruan.dataLogistik')
            ->with('success', 'Data Pasuruan berhasil diimport.');
    }

    public function archiveAll()
    {
        LogistikPengirimanPasuruan::truncate();

        return redirect()
            ->route('pasuruan.dataLogistik')
            ->with('success', 'Semua data Pasuruan berhasil dihapus.');
    }

    // public function dataLogistik()
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

    //     // ================= TARIF PENGIRIMAN (untuk dropdown Route/Mobil/Ekspedisi) =================
    //     $tarifPengiriman = DB::table('tarif_pengiriman')
    //         ->select('route', 'mobil', 'ekpedisi', 'biaya_kirim')
    //         ->whereNotNull('route')
    //         ->whereNotNull('mobil')
    //         ->get();

    //     $routeOptions = $tarifPengiriman->pluck('route')->filter()->unique()->sort()->values();
    //     $mobilOptions = $tarifPengiriman->pluck('mobil')->filter()->unique()->sort()->values();
    //     $ekspedisiOptions = $tarifPengiriman->pluck('ekpedisi')->filter()->unique()->sort()->values();

    //     return view('pasuruan.data_logistik', compact(
    //         'logistik',
    //         'planners',
    //         'areas',
    //         'tarifPengiriman',
    //         'routeOptions',
    //         'mobilOptions',
    //         'ekspedisiOptions'
    //     ));
    // }

    public function dataLogistik()
{
    $planners = $this->cachedListPasuruan('planner_pasuruan');
    $areas    = $this->cachedListPasuruan('area_pasuruan');

    return view('pasuruan.data_logistik', compact('planners', 'areas'));
}

private function cachedListPasuruan(string $column)
{
    return Cache::remember("pasuruan_list_{$column}", 3600, function () use ($column) {
        return DB::table('logistik_pengiriman_pasuruan')
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    });
}

/* =========================================================
 * DATA LOGISTIK — ENDPOINT AJAX (SERVER-SIDE, READ-ONLY DISPLAY)
 * Terpisah dari dataAjaxPasuruan() (yg buat admin editable grid)
 * ========================================================= */
public function dataLogistikAjaxPasuruan(Request $request)
{
    $query = LogistikPengirimanPasuruan::query();

    if ($request->filled('planner')) {
        $query->where('planner_pasuruan', $request->planner);
    }
    if ($request->filled('area')) {
        $query->where('area_pasuruan', $request->area);
    }
    if ($request->filled('date')) {
        $query->whereDate('tanggal_terima_po_pasuruan', $request->date);
    }
    if ($request->filled('month')) {
        $query->whereMonth('tanggal_terima_po_pasuruan', $request->month);
    }
    if ($request->filled('year')) {
        $query->whereYear('tanggal_terima_po_pasuruan', $request->year);
    }

    $recordsTotal = (clone $query)->count();

    $searchValue = trim((string) $request->input('search.value'));

    if ($searchValue !== '') {
        $query->where(function ($q) use ($searchValue) {
            $cols = [
                'planner_pasuruan', 'no_shipment_pasuruan', 'tujuan_pasuruan',
                'route_pasuruan', 'pulau_pasuruan', 'area_pasuruan',
                'via_kirim_pasuruan', 'dist_channel_pasuruan',
                'kategori_ekspedisi_pasuruan', 'ekspedisi_pasuruan', 'mobil_pasuruan',
            ];
            foreach ($cols as $col) {
                $q->orWhere($col, 'like', "%{$searchValue}%");
            }
        });
    }

    $recordsFiltered = (clone $query)->count();

    // index kolom HARUS sinkron sama array `columns` di JS blade
    $orderableColumns = [
        0 => 'tanggal_terima_po_pasuruan',
        1 => 'rencana_kirim_pasuruan',
        2 => 'transport_lead_time_pasuruan',
        3 => 'planner_pasuruan',
        4 => 'no_shipment_pasuruan',
        7 => 'tujuan_pasuruan',
        8 => 'area_pasuruan',
        10 => 'mobil_pasuruan',
        11 => 'total_do_pasuruan',
        12 => 'nilai_muatan_pasuruan',
        13 => 'biaya_kirim_pasuruan',
        16 => 'ekspedisi_pasuruan',
        17 => 'tanggal_dpt_unit_pasuruan',
        20 => 'planning_loading_pasuruan',
        21 => 'tanggal_tiba_gudang_pasuruan',
        22 => 'tanggal_keluar_gudang_pasuruan',
        23 => 'pic_monitoring_pasuruan',
        24 => 'nama_kapal_pasuruan',
        25 => 'etd_pasuruan',
        26 => 'eta_pasuruan',
        28 => 'act_urutan_bongkar_pasuruan',
        29 => 'actual_delivery_quantity_pasuruan',
        32 => 'act_pgi_date_pasuruan',
        33 => 'atd_pasuruan',
        34 => 'ata_pasuruan',
        35 => 'estimasi_tiba_pasuruan',
        36 => 'tanggal_tiba_pasuruan',
        37 => 'lama_perjalanan_pasuruan',
        39 => 'tanggal_bongkar_pasuruan',
        47 => 'remarks_pasuruan',
        48 => 'route_pasuruan',
        50 => 'pulau_pasuruan',
        51 => 'via_kirim_pasuruan',
    ];

    $orderColIndex = (int) $request->input('order.0.column', 0);
    $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    $orderColumn = $orderableColumns[$orderColIndex] ?? 'id';

    $query->orderBy($orderColumn, $orderDir);

    $start  = max(0, (int) $request->input('start', 0));
    $length = (int) $request->input('length', 25);
    $length = $length > 0 ? min($length, 200) : $recordsFiltered;

    $rows = $query->skip($start)->take($length)->get();

    // agregat CR di-cache 5 menit, gak query ulang tiap ganti halaman
    $agg = Cache::remember('pasuruan_shipment_agg', 300, fn() => $this->shipmentAggregatesPasuruan());

    $data = $rows->map(function ($r) use ($agg) {
        $lamaSla = $this->computeLamaPencarianDanSla($r);

        return [
            'tanggal_naik_fmt'         => $r->tanggal_terima_po_pasuruan ? date('d-m-Y', strtotime($r->tanggal_terima_po_pasuruan)) : '-',
            'rencana_kirim_fmt'        => $r->rencana_kirim_pasuruan ? date('d-m-Y', strtotime($r->rencana_kirim_pasuruan)) : '-',
            'lead_time'                => $r->transport_lead_time_pasuruan,
            'planner'                  => $r->planner_pasuruan,
            'no_shipment'              => $r->no_shipment_pasuruan,
            'posisi_mobil_badge'       => $this->badgeStatusPosisiMobilPasuruan($r),
            'dist_channel_badge'       => $this->badgeDistChannelPasuruan($r->dist_channel_pasuruan),
            'tujuan'                   => $r->tujuan_pasuruan,
            'area'                     => $r->area_pasuruan,
            'ketersediaan_badge'       => $this->badgeKetersediaanUnitPasuruan($r),
            'mobil'                    => $r->mobil_pasuruan,
            'delivery_qty'             => $r->total_do_pasuruan,
            'nilai_muatan_fmt'         => 'Rp ' . number_format((float) $r->nilai_muatan_pasuruan, 0, ',', '.'),
            'biaya_kirim_fmt'          => 'Rp ' . number_format((float) $r->biaya_kirim_pasuruan, 0, ',', '.'),
            'cr_fmt'                   => $this->formatCR($this->computeCRPasuruan($r, $agg)),
            'kategori_ekspedisi_badge' => $this->badgeKategoriEkspedisiPasuruan($r->kategori_ekspedisi_pasuruan),
            'ekspedisi'                => $r->ekspedisi_pasuruan,
            'tanggal_dpt_fmt'          => $r->tanggal_dpt_unit_pasuruan ? date('d-m-Y', strtotime($r->tanggal_dpt_unit_pasuruan)) : '-',
            'lama_pencarian'           => $lamaSla['lama'],
            'sla_dapat_mobil_badge'    => $lamaSla['sla_badge'],
            'planning_loading_fmt'     => $r->planning_loading_pasuruan ? date('d-m-Y', strtotime($r->planning_loading_pasuruan)) : '-',
            'tiba_gudang_fmt'          => $r->tanggal_tiba_gudang_pasuruan ? date('d-m-Y', strtotime($r->tanggal_tiba_gudang_pasuruan)) : '-',
            'keluar_gudang_fmt'        => $r->tanggal_keluar_gudang_pasuruan ? date('d-m-Y', strtotime($r->tanggal_keluar_gudang_pasuruan)) : '-',
            'pic_monitoring'           => $r->pic_monitoring_pasuruan,
            'nama_kapal'               => $r->nama_kapal_pasuruan,
            'etd'                      => $r->etd_pasuruan,
            'eta'                      => $r->eta_pasuruan,
            'alert_badge'              => $this->badgeAlertPasuruan($r),
            'urutan_bongkar'           => $r->act_urutan_bongkar_pasuruan,
            'actual_delivery_qty'      => $r->actual_delivery_quantity_pasuruan,
            'selisih_qty_badge'        => $this->badgeSelisihQtyPasuruan($r),
            'reason_selisih_qty'       => $r->reason_selisih_quantity_pasuruan,
            'act_pgi_fmt'              => $r->act_pgi_date_pasuruan ? date('d-m-Y', strtotime($r->act_pgi_date_pasuruan)) : '-',
            'atd_fmt'                  => $r->atd_pasuruan ? date('d-m-Y', strtotime($r->atd_pasuruan)) : '-',
            'ata_fmt'                  => $r->ata_pasuruan ? date('d-m-Y', strtotime($r->ata_pasuruan)) : '-',
            'estimasi_fmt'             => $r->estimasi_tiba_pasuruan ? date('d-m-Y', strtotime($r->estimasi_tiba_pasuruan)) : '-',
            'tiba_fmt'                 => $r->tanggal_tiba_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_tiba_pasuruan)) : '-',
            'lama_perjalanan'          => $r->lama_perjalanan_pasuruan ?? '-',
            'sla_tiba_badge'           => $this->badgeSlaGeneric($r->sla_tiba_pasuruan),
            'bongkar_fmt'              => $r->tanggal_bongkar_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_bongkar_pasuruan)) : '-',
            'status_bongkar_badge'     => $this->badgeStatusBongkarPasuruan($r),
            'overstay_badge'           => $this->badgeOverstayPasuruan($r),
            'sla_bongkar_badge'        => $this->badgeSlaBongkarPasuruan($r),
            'reason_tiba'              => $r->reason_waktu_tiba_pasuruan,
            'reason_bongkar'           => $r->reason_waktu_bongkar_pasuruan,
            'status_akhir_badge'       => $this->badgeStatusAkhirPasuruan($r),
            'status_alert_badge'       => $this->badgeStatusAlertPasuruan($r),
            'remarks'                  => $r->remarks_pasuruan,
            'route'                    => $r->route_pasuruan,
            'shipping_point'           => $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-',
            'pulau'                    => $r->pulau_pasuruan,
            'via_kirim'                => $r->via_kirim_pasuruan,
        ];
    });

    return response()->json([
        'draw'            => (int) $request->input('draw', 1),
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);
}

/* =========================================================
 * HELPER — CR AGGREGATION
 * ========================================================= */
private function shipmentAggregatesPasuruan(): array
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

private function computeCRPasuruan($r, array $agg): float
{
    $shipment = trim((string) $r->no_shipment_pasuruan);

    if ($shipment === '' || !isset($agg[$shipment])) {
        $muatan = (float) $r->nilai_muatan_pasuruan;
        $biaya  = (float) $r->biaya_kirim_pasuruan;
        return $muatan > 0 ? ($biaya / $muatan) * 100 : 0;
    }

    $totalMuatan = $agg[$shipment]['total_muatan'];
    $totalBiaya  = $agg[$shipment]['total_biaya'];
    $nilaiBaris  = (float) $r->nilai_muatan_pasuruan;

    if ($totalMuatan <= 0 || $nilaiBaris <= 0) return 0;

    $totalCR    = ($totalBiaya / $totalMuatan) * 100;
    $kontribusi = $nilaiBaris / $totalMuatan;

    return $kontribusi * $totalCR;
}

private function formatCR(float $cr): string
{
    return $cr > 0
        ? '<span class="cr-value">' . number_format($cr, 4, ',', '.') . '%</span>'
        : '<span class="text-muted">0,0000%</span>';
}

/* =========================================================
 * HELPER — BADGE (dipindah dari @php block Blade)
 * ========================================================= */
private function badgeStatusPosisiMobilPasuruan($r): string
{
    $dpt = $r->tanggal_dpt_unit_pasuruan;
    $tibaGudang = $r->tanggal_tiba_gudang_pasuruan;
    $keluarGudang = $r->tanggal_keluar_gudang_pasuruan;
    $tibaTujuan = $r->tanggal_tiba_pasuruan;
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

private function badgeDistChannelPasuruan($channel): string
{
    $channel = trim((string) $channel);
    $classes = ['badge-green','badge-blue','badge-orange','badge-red','badge-purple','badge-pink','badge-cyan','badge-yellow'];
    $badgeClass = $channel ? $classes[abs(crc32($channel)) % count($classes)] : 'badge-default';

    return '<span class="badge ' . $badgeClass . '">' . e($channel ?: '-') . '</span>';
}

private function badgeKetersediaanUnitPasuruan($r): string
{
    return !empty($r->tanggal_dpt_unit_pasuruan)
        ? '<span class="badge-status status-sudah">Sudah Dapat Unit</span>'
        : '<span class="badge-status status-belum">Belum Dapat Unit</span>';
}

private function badgeKategoriEkspedisiPasuruan($kategori): string
{
    $kategori = $kategori ?? '-';

    if (empty($kategori) || $kategori === '-') return '<span class="badge gray">-</span>';
    if (strtolower($kategori) === 'kontrak') return '<span class="badge yellow">Kontrak</span>';
    if (strtolower($kategori) === 'oncall') return '<span class="badge blue">Oncall</span>';

    return '<span class="badge orange">' . e($kategori) . '</span>';
}

private function computeLamaPencarianDanSla($r): array
{
    $lama = '-';
    $sla = null;

    if (!empty($r->rencana_kirim_pasuruan) && !empty($r->tanggal_dpt_unit_pasuruan)) {
        $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
        $dapat   = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
        $selisih = floor(($dapat - $rencana) / 86400);

        $lama = $selisih <= 0 ? 'H+0' : 'H+' . $selisih;
        $sla  = $selisih <= 0 ? 'On Time' : 'Delay';
    }

    $slaBadge = $sla === 'On Time'
        ? '<span class="badge green">On Time</span>'
        : ($sla === 'Delay' ? '<span class="badge red">Delay</span>' : '<span class="badge gray">-</span>');

    return ['lama' => $lama, 'sla_badge' => $slaBadge];
}

private function badgeAlertPasuruan($r): string
{
    if (!empty($r->tanggal_tiba_pasuruan)) {
        return '<span class="badge badge-success">✓ Tiba</span>';
    }

    if (empty($r->estimasi_tiba_pasuruan)) {
        return '<span class="badge badge-secondary">-</span>';
    }

    $estimasi = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));
    $today = strtotime(date('Y-m-d'));
    $sisa = floor(($estimasi - $today) / 86400);

    if ($sisa < 0) return '<span class="badge badge-danger">OVERDUE</span>';
    if ($sisa == 0) return '<span class="badge badge-danger">H-0</span>';
    if ($sisa == 1) return '<span class="badge badge-danger">H-1</span>';
    if ($sisa == 2 || $sisa == 3) return '<span class="badge badge-warning">H-' . $sisa . '</span>';
    if ($sisa <= 7) return '<span class="badge badge-info">H-' . $sisa . '</span>';

    return '<span class="badge badge-success">ON TRACK</span>';
}

private function badgeSelisihQtyPasuruan($r): string
{
    $totalDo = is_numeric($r->total_do_pasuruan) ? (float) $r->total_do_pasuruan : 0;
    $actualRaw = $r->actual_delivery_quantity_pasuruan;
    $belumDiisi = ($actualRaw === null || $actualRaw === '' || (float) $actualRaw == 0);

    if ($belumDiisi) {
        return '<span class="badge badge-secondary">-</span>';
    }

    $actual = (float) $actualRaw;
    $selisih = $totalDo - $actual;

    if ($selisih == 0) return '<span class="badge badge-success">Sesuai (0)</span>';
    if ($selisih > 0) return '<span class="badge badge-danger">Berkurang ' . number_format($selisih, 0, ',', '.') . '</span>';

    return '<span class="badge badge-warning">Lebih ' . number_format(abs($selisih), 0, ',', '.') . '</span>';
}

private function badgeStatusBongkarPasuruan($r): string
{
    if (!empty($r->tanggal_bongkar_pasuruan)) {
        return '<span class="badge status-bongkar green">Telah Bongkar</span>';
    }

    if (!empty($r->tanggal_tiba_pasuruan)) {
        $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
        $today = strtotime(date('Y-m-d'));
        $selisih = max(0, floor(($today - $tiba) / 86400));
        $class = $selisih == 0 ? 'orange' : 'red';

        return '<span class="badge status-bongkar ' . $class . '">H+' . $selisih . '</span>';
    }

    return '<span class="badge status-bongkar gray">-</span>';
}

private function badgeOverstayPasuruan($r): string
{
    if (empty($r->tanggal_tiba_pasuruan) || empty($r->tanggal_bongkar_pasuruan)) {
        return '<span class="badge gray">-</span>';
    }

    $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
    $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
    $overstay = max(0, floor(($bongkar - $tiba) / 86400));
    $text = $overstay == 0 ? '0 Hari' : 'H+' . $overstay . ' Hari';
    $class = $overstay == 0 ? 'green' : 'red';

    return '<span class="badge ' . $class . '">' . $text . '</span>';
}

private function badgeSlaBongkarPasuruan($r): string
{
    if (empty($r->tanggal_tiba_pasuruan) || empty($r->tanggal_bongkar_pasuruan)) {
        return '<span class="badge gray">-</span>';
    }

    $tiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
    $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
    $selisih = floor(($bongkar - $tiba) / 86400);

    return $selisih <= 0
        ? '<span class="badge green">On Time</span>'
        : '<span class="badge red">Delay</span>';
}

private function badgeSlaGeneric($value): string
{
    $value = trim((string) $value);

    if ($value === '') return '<span class="badge gray">-</span>';
    if (strtolower($value) === 'on time') return '<span class="badge green">' . e($value) . '</span>';
    if (strtolower($value) === 'delay') return '<span class="badge red">' . e($value) . '</span>';

    return '<span class="badge gray">' . e($value) . '</span>';
}

private function badgeStatusAkhirPasuruan($r): string
{
    $slaTiba = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
    $slaBongkar = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));

    if (empty($r->tanggal_tiba_pasuruan)) {
        return '<span class="status-badge status-transit">🚚 Dalam Perjalanan</span>';
    }

    if (!empty($r->tanggal_tiba_pasuruan) && empty($r->tanggal_bongkar_pasuruan)) {
        return '<span class="status-badge status-unloading">📦 Sudah Tiba<br>Dalam Pembongkaran</span>';
    }

    if ($slaTiba === 'ON TIME' && $slaBongkar === 'ON TIME') {
        return '<span class="status-badge status-ontime">✅ Pengiriman On Time</span>';
    }

    return '<span class="status-badge status-delay">🚨 Pengiriman Delay</span>';
}

private function badgeStatusAlertPasuruan($r): string
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
    public function index()
    {
        $logistik = LogistikPengirimanPasuruan::orderBy('id', 'desc')->get();

        return view('pasuruan.index', compact('logistik'));
    }

    public function admin(Request $request)
{
    $reasonTiba = DB::table('akurasi3')
        ->whereNotNull('akurasi_waktu_tiba')
        ->where('akurasi_waktu_tiba', '<>', '')
        ->distinct()
        ->orderBy('akurasi_waktu_tiba')
        ->pluck('akurasi_waktu_tiba');

    $reasonBongkar = DB::table('akurasi3')
        ->whereNotNull('akurasi_waktu_bongkar')
        ->where('akurasi_waktu_bongkar', '<>', '')
        ->distinct()
        ->orderBy('akurasi_waktu_bongkar')
        ->pluck('akurasi_waktu_bongkar');

    $reasonSelisihQty = DB::table('akurasi3')
        ->whereNotNull('remarks_qty')
        ->where('remarks_qty', '<>', '')
        ->distinct()
        ->orderBy('remarks_qty')
        ->pluck('remarks_qty');

    $planners = LogistikPengirimanPasuruan::whereNotNull('planner_pasuruan')
        ->distinct()
        ->orderBy('planner_pasuruan')
        ->pluck('planner_pasuruan');

    $areas = LogistikPengirimanPasuruan::whereNotNull('area_pasuruan')
        ->distinct()
        ->orderBy('area_pasuruan')
        ->pluck('area_pasuruan');

    $tujuans = LogistikPengirimanPasuruan::whereNotNull('tujuan_pasuruan')
        ->distinct()
        ->orderBy('tujuan_pasuruan')
        ->pluck('tujuan_pasuruan');

    $tarifPengiriman = DB::table('tarif_pengiriman')
        ->select('route', 'mobil', 'ekpedisi', 'biaya_kirim')
        ->whereNotNull('route')
        ->whereNotNull('mobil')
        ->get();

    $routeOptions = $tarifPengiriman->pluck('route')->filter()->unique()->sort()->values();
    $mobilOptions = $tarifPengiriman->pluck('mobil')->filter()->unique()->sort()->values();
    $ekspedisiOptions = $tarifPengiriman->pluck('ekpedisi')->filter()->unique()->sort()->values();

    return view('pasuruan.data_admin', compact(
        'reasonTiba',
        'reasonBongkar',
        'reasonSelisihQty',
        'planners',
        'areas',
        'tujuans',
        'tarifPengiriman',
        'routeOptions',
        'mobilOptions',
        'ekspedisiOptions'
    ));
}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'planner_pasuruan'              => 'nullable|string|max:100',
            'no_shipment_pasuruan'          => 'nullable|string|max:50',
            'dist_channel_pasuruan'         => 'nullable|string|max:100',
            'transport_lead_time_pasuruan'  => 'nullable|numeric',
            'tujuan_pasuruan'               => 'nullable|string|max:150',
            'area_pasuruan'                 => 'nullable|string|max:100',
            'mobil_pasuruan'                => 'nullable|string|max:100',
            'nilai_muatan_pasuruan'         => 'nullable|string',
            'biaya_kirim_pasuruan'          => 'nullable|string',
            'kategori_ekspedisi_pasuruan'   => 'nullable|string|max:100',
            'ekspedisi_pasuruan'             => 'nullable|string|max:100',
            'tanggal_terima_po_pasuruan'    => 'nullable|date',
            'rencana_kirim_pasuruan'        => 'nullable|date',
            'tanggal_dpt_unit_pasuruan'     => 'nullable|date',
            'biaya_kuli_pasuruan'                => 'nullable|string',


            'tanggal_tiba_gudang_pasuruan'  => 'nullable|date',
            'planning_loading_pasuruan'     => 'nullable|date',
            'tanggal_keluar_gudang_pasuruan' => 'nullable|date',
            // CATATAN: 'keterangan_pasuruan' bukan nama kolom yang ada di
            // $fillableFields / model. Kemungkinan besar ini typo dari salah
            // satu kolom lain (keterangan_monitoring_pasuruan,
            // keterangan_loading_pasuruan, dll). Dibiarkan nullable supaya
            // form lama tidak error validasi, TAPI sengaja di-unset sebelum
            // create() di bawah supaya tidak menyebabkan SQL error kalau
            // memang bukan nama kolom yang valid. Mohon dicek & disesuaikan
            // ke nama kolom yang benar.
            'keterangan_pasuruan'           => 'nullable|string',
            // FIXED: sebelumnya 'create_tgl' (tidak match nama kolom asli).
            // Diperbaiki jadi 'create_tgl_pasuruan' sesuai $fillableFields.
            'create_tgl_pasuruan'            => 'nullable|date',
        ]);

        // Bersihkan format "Rp 1.000.000" jadi angka murni sebelum disimpan
        // Bersihkan format "Rp 1.000.000" jadi angka murni sebelum disimpan
        $validated['nilai_muatan_pasuruan'] = $this->parseRupiah($validated['nilai_muatan_pasuruan'] ?? null);
        $validated['biaya_kuli_pasuruan']   = $this->parseRupiah($validated['biaya_kuli_pasuruan'] ?? null);

        // BIAYA KIRIM OTOMATIS (Route + Mobil + Ekspedisi), fallback ke input manual
        $autoBiayaKirim = $this->cariBiayaKirimOtomatisPasuruan(
            $validated['route_pasuruan'] ?? null,
            $validated['mobil_pasuruan'] ?? null,
            $validated['ekspedisi_pasuruan'] ?? null
        );

        $validated['biaya_kirim_pasuruan'] = $autoBiayaKirim !== null
            ? $this->parseRupiah($autoBiayaKirim)
            : $this->parseRupiah($validated['biaya_kirim_pasuruan'] ?? null);

        $validated['total_biaya_kuli_pasuruan'] =
            ((float) ($validated['actual_delivery_quantity_pasuruan'] ?? 0))
            * ((float) $validated['biaya_kuli_pasuruan']);
        // FIXED: field yang belum tentu kolom asli tidak usah dikirim ke create()
        unset($validated['keterangan_pasuruan']);

        // FIXED: cr_pasuruan TIDAK dihitung manual di sini lagi. CR baru bisa
        // dihitung benar SETELAH baris ini tersimpan, karena kalau
        // no_shipment_pasuruan-nya ternyata duplicate (sudah ada baris lain
        // dengan no shipment yang sama), nilai_muatan harus dijumlah dulu
        // dengan baris-baris lain baru dibagi biaya_kirim.
        $logistik = LogistikPengirimanPasuruan::create($validated);

        if (!empty($logistik->no_shipment_pasuruan)) {
            $this->recalculateCr($logistik->no_shipment_pasuruan);
        }

        return redirect()
            ->route('pasuruan.admin')
            ->with('success', 'Data berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        return view('pasuruan.edit', compact('logistik'));
    }

    public function update(Request $request, $id)
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        // Ambil cuma field yang memang ada di request & termasuk kolom yang diizinkan
        $data = $request->only($this->fillableFields);

        // ============================================================
        // SELISIH QTY (manual, dari user) -> ACTUAL QTY (otomatis)
        // FIXED: dibalik dari sebelumnya. Dulu actual_delivery_quantity_pasuruan
        // input manual dan selisih_quantity_pasuruan dihitung otomatis.
        // Sekarang selisih_quantity_pasuruan yang diinput manual, dan
        // actual_delivery_quantity_pasuruan dihitung: total_do - selisih.
        // ============================================================
        $totalDo    = $data['total_do_pasuruan'] ?? $logistik->total_do_pasuruan;
        $selisihQty = $data['selisih_quantity_pasuruan'] ?? $logistik->selisih_quantity_pasuruan;

        if ($totalDo !== null && $totalDo !== '' && $selisihQty !== null && $selisihQty !== '') {
            $data['actual_delivery_quantity_pasuruan'] = (float) $totalDo - (float) $selisihQty;
        } else {
            $data['actual_delivery_quantity_pasuruan'] = $logistik->actual_delivery_quantity_pasuruan;
        }

        // ============================================================
        // BIAYA KULI & TOTAL BIAYA KULI
        // Pakai actual_delivery_quantity_pasuruan hasil hitungan di atas.
        // ============================================================
        $actualQtyForKuli = $data['actual_delivery_quantity_pasuruan'];
        $biayaKuli        = $data['biaya_kuli_pasuruan'] ?? $logistik->biaya_kuli_pasuruan;

        $data['biaya_kuli_pasuruan'] = $this->parseRupiah($biayaKuli);
        $data['total_biaya_kuli_pasuruan'] = ((float) $actualQtyForKuli) * ((float) $data['biaya_kuli_pasuruan']);

        if (array_key_exists('nilai_muatan_pasuruan', $data)) {
            $data['nilai_muatan_pasuruan'] = $this->parseRupiah($data['nilai_muatan_pasuruan']);
        }

        // ============================================================
        // BIAYA KIRIM OTOMATIS (Route + Mobil + Ekspedisi)
        // Kalau kombinasinya ketemu di tabel tarif_pengiriman, override
        // input manual biaya_kirim_pasuruan dengan hasil lookup.
        // ============================================================
        $routeForTarif     = $data['route_pasuruan'] ?? $logistik->route_pasuruan;
        $mobilForTarif     = $data['mobil_pasuruan'] ?? $logistik->mobil_pasuruan;
        $ekspedisiForTarif = $data['ekspedisi_pasuruan'] ?? $logistik->ekspedisi_pasuruan;

        $autoBiayaKirim = $this->cariBiayaKirimOtomatisPasuruan(
            $routeForTarif,
            $mobilForTarif,
            $ekspedisiForTarif
        );

        if ($autoBiayaKirim !== null) {
            $data['biaya_kirim_pasuruan'] = $this->parseRupiah($autoBiayaKirim);
        } elseif (array_key_exists('biaya_kirim_pasuruan', $data)) {
            $data['biaya_kirim_pasuruan'] = $this->parseRupiah($data['biaya_kirim_pasuruan']);
        }

        $oldShipmentNo = $logistik->no_shipment_pasuruan;

        $this->generateMonitoringPasuruan($data);

        /* ============================================================
        | FIELD YANG HARUS SAMA UNTUK SEMUA NO SHIPMENT
        ============================================================ */

        $shipmentFields = [
            'planner_pasuruan',
            'tanggal_terima_po_pasuruan',
            'rencana_kirim_pasuruan',
            'transport_lead_time_pasuruan',
            'route_pasuruan',
            'pulau_pasuruan',
            'area_pasuruan',
            'via_kirim_pasuruan',
            'pic_monitoring_pasuruan',

            'ketersediaan_unit_pasuruan',
            'mobil_pasuruan',
            'perubahan_mobil_pasuruan',

            'kategori_pengiriman_pasuruan',
            'kategori_ekspedisi_pasuruan',
            'ekspedisi_pasuruan',

            'no_pol_pasuruan',
            'nama_driver_pasuruan',

            'tanggal_dpt_unit_pasuruan',
            'planning_loading_pasuruan',
            'tanggal_tiba_gudang_pasuruan',
            'tanggal_keluar_gudang_pasuruan',

            'nama_kapal_pasuruan',
            'etd_pasuruan',
            'eta_pasuruan',
            'atd_pasuruan',
            'ata_pasuruan',
        ];

        /*
        | Ambil hanya field yang ada di atas
        */

        $shipmentData = array_intersect_key(
            $data,
            array_flip($shipmentFields)
        );

        /*
        | Kalau ada perubahan, update semua row dengan shipment yang sama
        */

        if (!empty($shipmentData)) {

            LogistikPengirimanPasuruan::where(
                'no_shipment_pasuruan',
                $oldShipmentNo
            )->update($shipmentData);
        }


        $logistik->update($data);
        $logistik->refresh();

        // ============================================================
        // HITUNG ULANG CR
        // FIXED: sebelumnya CR dihitung manual di tengah-tengah fungsi ini
        // (dengan bug: query biaya_kirim tanpa orderBy = hasil bisa acak,
        // lalu di-update 2x secara redundant). Sekarang cukup panggil
        // recalculateCr() yang membaca data TERBARU dari DB (setelah
        // $logistik->update($data) di atas) dan otomatis menjumlahkan
        // nilai_muatan seluruh baris duplicate untuk no_shipment ini.
        // ============================================================
        $cr = 0;

        if (!empty($logistik->no_shipment_pasuruan)) {
            $cr = $this->recalculateCr($logistik->no_shipment_pasuruan);
        }

        // FIXED: kalau no_shipment_pasuruan baris ini berubah ke nomor lain,
        // shipment yang LAMA juga perlu dihitung ulang CR-nya karena baris
        // ini sudah tidak ikut lagi di grouping lama (sebelumnya tidak
        // ditangani sama sekali).
        if ($oldShipmentNo && $oldShipmentNo !== $logistik->no_shipment_pasuruan) {
            $this->recalculateCr($oldShipmentNo);
        }

        $shipment = LogistikPengirimanPasuruan::where(
            'no_shipment_pasuruan',
            $logistik->no_shipment_pasuruan
        )->get();

        // estimasi awal
        $keluar = optional($shipment->first())->tanggal_keluar_gudang_pasuruan;
        $leadtime = (int) optional($shipment->first())->transport_lead_time_pasuruan;

        $baseEstimasi = $keluar
            ? date('Y-m-d', strtotime($keluar . " +{$leadtime} days"))
            : null;

        // cari tanggal bongkar TERAKHIR yang sudah ada
        $lastBongkar = $shipment
            ->whereNotNull('tanggal_bongkar_pasuruan')
            ->max('tanggal_bongkar_pasuruan');

        $nextEstimasi = $lastBongkar
            ? date('Y-m-d', strtotime($lastBongkar . ' +1 day'))
            : $baseEstimasi;

        // update semua yang BELUM bongkar
        foreach ($shipment as $row) {

            if (!empty($row->tanggal_bongkar_pasuruan)) {
                continue; // yang sudah bongkar dikunci
            }

            $row->estimasi_tiba_pasuruan = $nextEstimasi;
            $row->save();
        }

        // Kalau dipanggil lewat AJAX (autosave form-update-{id}), balikin JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'message' => 'Data berhasil diupdate.',
                'cr'      => $cr,
            ]);
        }

        return redirect()->route('pasuruan.admin')
            ->with('success', 'Data berhasil diupdate.');
    }

    /**
     * Endpoint untuk auto-save per baris (dipanggil dari JS saveRow()
     * ke URL /planner/autosave-row/{id}).
     */
    public function autosaveRow(Request $request, $id)
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        $data = $request->only($this->fillableFields);

        // ============================================================
        // SELISIH QTY (manual) -> ACTUAL QTY (otomatis)
        // ============================================================
        $totalDo    = $data['total_do_pasuruan'] ?? $logistik->total_do_pasuruan;
        $selisihQty = $data['selisih_quantity_pasuruan'] ?? $logistik->selisih_quantity_pasuruan;

        if ($totalDo !== null && $totalDo !== '' && $selisihQty !== null && $selisihQty !== '') {
            $data['actual_delivery_quantity_pasuruan'] = (float) $totalDo - (float) $selisihQty;
        } else {
            $data['actual_delivery_quantity_pasuruan'] = $logistik->actual_delivery_quantity_pasuruan;
        }

        // ============================================================
        // BIAYA KULI & TOTAL BIAYA KULI
        // ============================================================
        $actualQtyForKuli = $data['actual_delivery_quantity_pasuruan'];
        $biayaKuli        = $data['biaya_kuli_pasuruan'] ?? $logistik->biaya_kuli_pasuruan;

        $data['biaya_kuli_pasuruan'] = $this->parseRupiah($biayaKuli);
        $data['total_biaya_kuli_pasuruan'] = ((float) $actualQtyForKuli) * ((float) $data['biaya_kuli_pasuruan']);

        if (array_key_exists('nilai_muatan_pasuruan', $data)) {
            $data['nilai_muatan_pasuruan'] = $this->parseRupiah($data['nilai_muatan_pasuruan']);
        }

        // ============================================================
        // BIAYA KIRIM OTOMATIS (Route + Mobil + Ekspedisi)
        // ============================================================
        $routeForTarif     = $data['route_pasuruan'] ?? $logistik->route_pasuruan;
        $mobilForTarif     = $data['mobil_pasuruan'] ?? $logistik->mobil_pasuruan;
        $ekspedisiForTarif = $data['ekspedisi_pasuruan'] ?? $logistik->ekspedisi_pasuruan;

        $autoBiayaKirim = $this->cariBiayaKirimOtomatisPasuruan(
            $routeForTarif,
            $mobilForTarif,
            $ekspedisiForTarif
        );

        if ($autoBiayaKirim !== null) {
            $data['biaya_kirim_pasuruan'] = $this->parseRupiah($autoBiayaKirim);
        } elseif (array_key_exists('biaya_kirim_pasuruan', $data)) {
            $data['biaya_kirim_pasuruan'] = $this->parseRupiah($data['biaya_kirim_pasuruan']);
        }

        $oldShipmentNo = $logistik->no_shipment_pasuruan;

        $this->generateMonitoringPasuruan($data);

        $logistik->update($data);
        $logistik->refresh();

        // ============================================================
        // HITUNG ULANG CR
        // FIXED: sebelumnya autosaveRow() TIDAK menghitung CR berdasarkan
        // duplicate shipment sama sekali (langsung hitungCr($nilaiMuatan,
        // $biayaKirim) dari baris itu sendiri saja). Sekarang konsisten
        // dengan store()/update() lewat recalculateCr().
        // ============================================================
        $cr = 0;

        if (!empty($logistik->no_shipment_pasuruan)) {
            $cr = $this->recalculateCr($logistik->no_shipment_pasuruan);
        }

        if ($oldShipmentNo && $oldShipmentNo !== $logistik->no_shipment_pasuruan) {
            $this->recalculateCr($oldShipmentNo);
        }

        return response()->json([
            'message' => 'Auto-save berhasil.',
            'id'      => $logistik->id,
            'cr'      => $cr,
        ]);
    }

    public function destroy($id)
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        $noShipment = $logistik->no_shipment_pasuruan;

        $logistik->delete();

        // FIXED: kalau baris yang dihapus adalah bagian dari shipment
        // duplicate, CR baris-baris yang tersisa harus dihitung ulang
        // (total nilai_muatan berkurang). Sebelumnya tidak ditangani sama
        // sekali (CR baris lain jadi basi/salah setelah delete).
        if (!empty($noShipment)) {
            $this->recalculateCr($noShipment);
        }

        return redirect()->route('pasuruan.admin')
            ->with('success', 'Data berhasil dihapus.');
    }

    /**
     * Ubah "Rp 1.000.000" / "1.000.000" jadi angka murni (float).
     * Kalau kosong, balikin null biar kolom numeric di DB nggak error.
     */
    private function parseRupiah($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9]/', '', (string) $value);

        return $clean === '' ? null : (float) $clean;
    }

    /**
     * CR (%) = biaya_kirim / nilai_muatan * 100
     */
    private function hitungCr($nilaiMuatan, $biayaKirim): float
    {
        $nilaiMuatan = (float) $nilaiMuatan;
        $biayaKirim  = (float) $biayaKirim;

        if ($nilaiMuatan <= 0) {
            return 0;
        }

        return round(($biayaKirim / $nilaiMuatan) * 100, 4);
    }

    /**
     * Cari biaya_kirim otomatis dari tabel tarif_pengiriman berdasarkan
     * kombinasi Route + Mobil + Ekspedisi. Sama persis pola dengan
     * PlannerController::cariBiayaKirimOtomatis().
     */
    private function cariBiayaKirimOtomatisPasuruan($route, $mobil, $ekspedisi = null)
    {
        if (!$route || !$mobil) {
            return null;
        }

        $normalize = function ($v) {
            if (!$v) return '';
            $v = str_replace("\xc2\xa0", ' ', $v);
            $v = preg_replace('/\s*-\s*/', '-', $v);
            $v = preg_replace('/\s+/', ' ', trim($v));
            return mb_strtolower($v);
        };

        $routeKey     = $normalize($route);
        $mobilKey     = $normalize($mobil);
        $ekspedisiKey = $ekspedisi ? $normalize($ekspedisi) : '';

        $candidates = DB::table('tarif_pengiriman')
            ->whereNotNull('route')
            ->whereNotNull('mobil')
            ->get()
            ->filter(fn($t) => $normalize($t->route) === $routeKey);

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($ekspedisiKey !== '') {
            $strict = $candidates->first(function ($t) use ($normalize, $ekspedisiKey, $mobilKey) {
                return $normalize($t->ekpedisi) === $ekspedisiKey
                    && str_starts_with($normalize($t->mobil), $mobilKey);
            });

            if ($strict) {
                return $strict->biaya_kirim;
            }
        }

        $fallback = $candidates->first(fn($t) => str_starts_with($normalize($t->mobil), $mobilKey));

        return $fallback->biaya_kirim ?? null;
    }


    /**
 * Daftar No Shipment unik untuk dropdown modal Transport Laut.
 * Dipisah dari dataAjaxPasuruan() karena modal butuh SEMUA no_shipment
 * yang pernah ada (bukan cuma yang ada di halaman aktif tabel).
 */
public function listNoShipmentPasuruan()
{
    $list = LogistikPengirimanPasuruan::select('no_shipment_pasuruan', 'tujuan_pasuruan')
        ->whereNotNull('no_shipment_pasuruan')
        ->where('no_shipment_pasuruan', '!=', '')
        ->distinct()
        ->orderBy('no_shipment_pasuruan')
        ->get();

    return response()->json($list);
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