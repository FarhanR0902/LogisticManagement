<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogistikPengirimanPasuruan;

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
            $lama = ceil(($tiba - $keluar) / 86400);

            $data['lama_perjalanan_pasuruan'] = max(0, $lama);
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

    public function dataLogistik()
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

        // ================= TARIF PENGIRIMAN (untuk dropdown Route/Mobil/Ekspedisi) =================
        $tarifPengiriman = DB::table('tarif_pengiriman')
            ->select('route', 'mobil', 'ekpedisi', 'biaya_kirim')
            ->whereNotNull('route')
            ->whereNotNull('mobil')
            ->get();

        $routeOptions = $tarifPengiriman->pluck('route')->filter()->unique()->sort()->values();
        $mobilOptions = $tarifPengiriman->pluck('mobil')->filter()->unique()->sort()->values();
        $ekspedisiOptions = $tarifPengiriman->pluck('ekpedisi')->filter()->unique()->sort()->values();

        return view('pasuruan.data_logistik', compact(
            'logistik',
            'planners',
            'areas',
            'tarifPengiriman',
            'routeOptions',
            'mobilOptions',
            'ekspedisiOptions'
        ));
    }
    public function index()
    {
        $logistik = LogistikPengirimanPasuruan::orderBy('id', 'desc')->get();

        return view('pasuruan.index', compact('logistik'));
    }

    public function admin(Request $request)
    {
        $logistik = LogistikPengirimanPasuruan::orderBy('id', 'desc')->get();

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

        // TAMBAHAN: dropdown Reason Selisih Qty dari akurasi3.remarks_qty
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
            'logistik',
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
} // <-- penutup class PasuruanController, ditaruh paling akhir