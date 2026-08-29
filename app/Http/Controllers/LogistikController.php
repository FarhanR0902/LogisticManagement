<?php
/**
 * =============================================================================
 * LogistikController.php — REFACTORED (Server-Side Pagination, TANPA package
 * tambahan — pure Eloquent + response()->json(), tidak butuh Yajra sama sekali)
 * =============================================================================
 *
 * PERUBAHAN UTAMA dari versi lama:
 *
 * 1. dataLogistik() lama (fetch SEMUA baris + kalkulasi berat per baris di
 *    Blade + drawCallback yang parsing DOM tiap draw) DIPECAH jadi 2:
 *
 *      - dataLogistik()      -> hanya render halaman (tabel kosong, filter,
 *                                dropdown). TIDAK query data besar di sini.
 *      - dataLogistikAjax()  -> endpoint JSON manual yang mengikuti format
 *                                request/response DataTables serverSide.
 *                                MySQL yang filter/sort/LIMIT-OFFSET, browser
 *                                cuma terima 10-50 baris per request. Badge
 *                                dan status dihitung HANYA untuk baris yang
 *                                sedang ditampilkan di halaman itu — bukan
 *                                seluruh dataset.
 *
 * 2. Semua logic status/badge/SLA/alert/CR yang dulu ada di dalam @php block
 *    Blade (ratusan baris, dieksekusi per-row per-request) dipindah ke
 *    method private di controller ini -> lebih cepat, testable, reusable.
 *
 * 3. Cost Ratio (CR) yang dulu dihitung di JS drawCallback dengan parsing
 *    teks DOM (SANGAT lambat untuk ribuan baris, jalan ulang tiap draw)
 *    sekarang dihitung dari agregasi SQL (SUM(nilai_muatan) & MAX(biaya_kirim)
 *    GROUP BY no_shipment), dan hasil agregasinya di-cache 5 menit supaya
 *    tidak query ulang tiap kali ganti halaman/filter.
 *
 * 4. Dropdown (area, dist_channel, pic) di-cache 1 jam karena jarang berubah.
 *
 * TIDAK ADA DEPENDENCY BARU YANG PERLU DI-INSTALL. Cukup pastikan index
 * database di bawah ini ada supaya query filter/sort tetap cepat di data besar.
 *
 * ROUTE YANG PERLU DITAMBAHKAN (routes/web.php):
 *   Route::get('/datalogistik',        [LogistikController::class, 'dataLogistik'])->name('logistik.page');
 *   Route::match(['get','post'], '/datalogistik/ajax', [LogistikController::class, 'dataLogistikAjax'])->name('logistik.ajax');
 *
 * INDEX DATABASE YANG DISARANKAN (migration baru):
 *   Schema::table('logistik_pengiriman', function (Blueprint $table) {
 *       $table->index('tanggal_naik_logistik');
 *       $table->index('area');
 *       $table->index('no_shipment');
 *       $table->index('status_akhir');
 *       $table->index('dist_channel');
 *       $table->index('pic_monitoring');
 *   });
 * =============================================================================
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogistikPengiriman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\LogistikImport;
use App\Exports\LogistikExport;
use Carbon\Carbon;

class LogistikController extends Controller
{
    /* =========================================================
     * DASHBOARD (tidak diubah — tetap sama seperti sebelumnya)
     * ========================================================= */
    public function dashboard(Request $request)
    {
        $bulan = $request->month ? date('m', strtotime($request->month)) : null;
        $tahun = $request->year;
        $area  = $request->area;
        $date  = $request->date;

        $query = LogistikPengiriman::query();

        if ($date) {
            $query->whereDate('tanggal_naik_logistik', $date);
        }

        if ($bulan && $tahun) {
            $query->whereMonth('tanggal_naik_logistik', $bulan)
                ->whereYear('tanggal_naik_logistik', $tahun);
        }

        if ($area) {
            $query->where('area', $area);
        }

        $total_data = (clone $query)->count();

        $total_loading_ontime = (clone $query)
            ->whereIn('status_akhir', ['On Time', 'Ontime'])
            ->count();

        $total_loading_delay = (clone $query)
            ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
            ->count();

        $armada = (clone $query)
            ->where('ketersediaan_unit', 'Sudah Dapat')
            ->count();

        $belum_armada = (clone $query)
            ->where('ketersediaan_unit', 'Belum Dapat')
            ->count();

        $process = (clone $query)
            ->where('status_pengiriman', 'like', '%process%')
            ->count();

        $totalNilaiMuatan = (clone $query)->sum('nilai_muatan');
        $totalBiayaKirim  = (clone $query)->sum('biaya_kirim');

        $summary_area = (clone $query)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(biaya_kirim) as total_biaya'),
                DB::raw('SUM(nilai_muatan) as total_muatan')
            )
            ->groupBy('area')
            ->orderByDesc('total_shipment')
            ->get();

        $summary_tujuan = (clone $query)
            ->select(
                'tujuan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(biaya_kirim) as total_biaya'),
                DB::raw('SUM(nilai_muatan) as total_muatan')
            )
            ->groupBy('tujuan')
            ->orderByDesc('total_shipment')
            ->get();

        $list_area = $this->cachedList('area');
        $list_dist_channel = $this->cachedList('dist_channel');

        $gudang_ontime = (clone $query)
            ->where(function ($q) {
                $q->where('sla_loading', 'H+0')
                    ->orWhere('status_gudang', 'On Time')
                    ->orWhere('status_gudang', 'ONTIME');
            })
            ->count();

        $gudang_delay = (clone $query)
            ->where(function ($q) {
                $q->where('sla_loading', 'H+1')
                    ->orWhere('sla_loading', 'H+2')
                    ->orWhere('sla_loading', 'H>2')
                    ->orWhere('status_gudang', 'Delay')
                    ->orWhere('status_gudang', 'DELAY');
            })
            ->count();

        $customer_ontime = (clone $query)
            ->where(function ($q) {
                $q->where('sla_tiba', 'H+0')
                    ->orWhere('sla_tiba', 'On Time')
                    ->orWhere('sla_tiba', 'ONTIME');
            })
            ->count();

        $customer_delay = (clone $query)
            ->where(function ($q) {
                $q->where('sla_tiba', 'H+1')
                    ->orWhere('sla_tiba', 'H+2')
                    ->orWhere('sla_tiba', 'H>2')
                    ->orWhere('sla_tiba', 'Delay')
                    ->orWhere('sla_tiba', 'Critical Delay');
            })
            ->count();

        $bongkar_ontime = (clone $query)
            ->where(function ($q) {
                $q->where('sla_bongkar', 'H+0')
                    ->orWhere('sla_bongkar', 'On Time')
                    ->orWhere('sla_bongkar', 'ONTIME');
            })
            ->count();

        $bongkar_delay = (clone $query)
            ->where(function ($q) {
                $q->where('sla_bongkar', 'H+1')
                    ->orWhere('sla_bongkar', 'H+2')
                    ->orWhere('sla_bongkar', 'H>2')
                    ->orWhere('sla_bongkar', 'Delay')
                    ->orWhere('sla_bongkar', 'Critical Delay');
            })
            ->count();

        $total_tiba = $customer_ontime + $customer_delay;
        $total_bongkar = $bongkar_ontime + $bongkar_delay;

        $summary_monitoring = [
            'tiba_ontime'    => $total_tiba ? ($customer_ontime / $total_tiba) * 100 : 0,
            'tiba_delay'     => $total_tiba ? ($customer_delay / $total_tiba) * 100 : 0,
            'bongkar_ontime' => $total_bongkar ? ($bongkar_ontime / $total_bongkar) * 100 : 0,
            'bongkar_delay'  => $total_bongkar ? ($bongkar_delay / $total_bongkar) * 100 : 0,
        ];

        $planner_ontime = $total_loading_ontime;
        $planner_delay  = $total_loading_delay;

        $total_planner = $planner_ontime + $planner_delay;

        $ontime_rate = $total_planner ? ($planner_ontime / $total_planner) * 100 : 0;
        $delay_rate  = $total_planner ? ($planner_delay / $total_planner) * 100 : 0;

        $planner_armada = $armada;
        $planner_belum_armada = $belum_armada;

        $armada_rate = ($armada + $belum_armada)
            ? ($armada / ($armada + $belum_armada)) * 100
            : 0;

        $label = [];
        $value = [];

        return view('dashboard', compact(
            'total_data',
            'total_loading_ontime',
            'total_loading_delay',
            'armada',
            'belum_armada',
            'process',
            'totalNilaiMuatan',
            'totalBiayaKirim',
            'summary_area',
            'summary_tujuan',
            'list_area',
            'gudang_ontime',
            'list_dist_channel',
            'gudang_delay',
            'customer_ontime',
            'customer_delay',
            'bongkar_ontime',
            'bongkar_delay',
            'summary_monitoring',
            'planner_ontime',
            'planner_delay',
            'ontime_rate',
            'delay_rate',
            'planner_armada',
            'planner_belum_armada',
            'armada_rate',
            'label',
            'value'
        ));
    }

    /* =========================================================
     * IMPORT EXCEL
     * ========================================================= */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        Excel::import(new LogistikImport, $request->file('file'));

        // dropdown cache & agregat bisa berubah setelah import -> flush cache
        Cache::forget('list_area');
        Cache::forget('list_dist_channel');
        Cache::forget('list_pic_monitoring');

        return back()->with('success', 'Import berhasil');
    }

    /* =========================================================
     * DIST CHANNEL SESSION FILTER (tetap sama)
     * ========================================================= */
    private function filterByDistChannel($query)
    {
        $channel = session('dist_channel');

        if ($channel) {
            $query->whereRaw('LOWER(TRIM(dist_channel)) = ?', [$channel]);
        }

        return $query;
    }

    /* =========================================================
     * EXPORT EXCEL (tetap sama — tetap query langsung karena export
     * memang butuh semua baris hasil filter, bukan per-page)
     * ========================================================= */
    public function export(Request $request)
    {
        $query = LogistikPengiriman::query();

        $this->filterByDistChannel($query);

        if ($request->date) {
            $query->whereDate('tanggal_naik_logistik', $request->date);
        }

        if ($request->month) {
            $query->whereMonth('tanggal_naik_logistik', $request->month);
        }

        if ($request->year) {
            $query->whereYear('tanggal_naik_logistik', $request->year);
        }

        if ($request->area) {
            $query->where('area', $request->area);
        }

        $logistik = $query->orderBy('id', 'DESC')->get();

        return Excel::download(new LogistikExport($logistik), 'logistik.xlsx');
    }

    /* =========================================================
     * DATA LOGISTIK — HALAMAN (ringan, tanpa query data besar)
     * ========================================================= */
    public function dataLogistik(Request $request)
    {
        $picList  = $this->cachedList('pic_monitoring');
        $areaList = $this->cachedList('area');

        return view('data_logistik', compact('picList', 'areaList'));
    }

    /* =========================================================
     * DATA LOGISTIK — ENDPOINT AJAX (SERVER-SIDE PAGINATION)
     *
     * Tidak pakai package tambahan (tanpa Yajra). Ngikutin format
     * request/response DataTables secara manual:
     *   - Terima: draw, start, length, search[value], order[0][column/dir]
     *   - Balikin: draw, recordsTotal, recordsFiltered, data[]
     *
     * MySQL yang filter/sort/LIMIT-OFFSET. Badge/status/CR cuma
     * dihitung untuk baris yang sedang tampil di halaman itu saja
     * (default 25 baris), bukan seluruh dataset -> makanya cepat
     * walau tabelnya ribuan/puluhan ribu baris.
     * ========================================================= */
    public function dataLogistikAjax(Request $request)
    {
        $query = LogistikPengiriman::query();

        $this->filterByDistChannel($query);

        // ---------- FILTER DARI DROPDOWN CUSTOM ----------
        if ($request->filled('date')) {
            $query->whereDate('tanggal_naik_logistik', $request->date);
        }

        if ($request->filled('month')) {
            $query->whereMonth('tanggal_naik_logistik', $request->month);
        }

        if ($request->filled('year')) {
            $query->whereYear('tanggal_naik_logistik', $request->year);
        }

        if ($request->filled('pic_monitoring')) {
            $query->where('pic_monitoring', $request->pic_monitoring);
        }

        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        // recordsTotal = total setelah filter dropdown custom (belum termasuk search box)
        $recordsTotal = (clone $query)->count();

        // ---------- SEARCH BOX BAWAAN DATATABLES ----------
        $searchValue = trim((string) $request->input('search.value'));

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('no_shipment', 'like', "%{$searchValue}%")
                    ->orWhere('tujuan', 'like', "%{$searchValue}%")
                    ->orWhere('area', 'like', "%{$searchValue}%")
                    ->orWhere('nama_driver', 'like', "%{$searchValue}%")
                    ->orWhere('no_pol', 'like', "%{$searchValue}%")
                    ->orWhere('planner', 'like', "%{$searchValue}%")
                    ->orWhere('mobil', 'like', "%{$searchValue}%")
                    ->orWhere('ekpedisi', 'like', "%{$searchValue}%")
                    ->orWhere('pic_monitoring', 'like', "%{$searchValue}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        // ---------- SORTING ----------
        // Peta index kolom (urutan HARUS sama persis dengan array `columns` di blade JS).
        // null = kolom hasil kalkulasi/badge, tidak bisa di-ORDER BY langsung -> di-skip.
        $orderableColumns = [
            0  => 'tanggal_naik_logistik',
            1  => 'rencana_kirim',
            2  => 'transport_lead_time',
            3  => 'nama_driver',
            4  => 'no_pol',
            5  => 'planner',
            6  => 'no_shipment',
            9  => 'tujuan',
            10 => 'area',
            12 => 'mobil',
            13 => 'total_do_qty_car',
            14 => 'nilai_muatan',
            15 => 'biaya_kirim',
            18 => 'ekpedisi',
            19 => 'tanggal_dpt_unit',
            39 => 'pic_monitoring',
            40 => 'nama_kapal',
        ];

        $orderColIndex = (int) $request->input('order.0.column', 0);
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderColumn = $orderableColumns[$orderColIndex] ?? 'tanggal_naik_logistik';

        $query->orderBy($orderColumn, $orderDir);

        // ---------- PAGINATION (LIMIT/OFFSET) ----------
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 200) : $recordsFiltered; // cap biar gak diminta ambil semua sekaligus

        $rows = $query->skip($start)->take($length)->get();

        // Agregat CR (SUM/MAX per no_shipment) di-cache 5 menit -> tidak query ulang tiap ganti halaman/filter
        $shipmentAgg = Cache::remember('logistik_shipment_agg', 300, fn() => $this->shipmentAggregates());

        // ---------- BANGUN BARIS OUTPUT (hanya untuk baris yang tampil) ----------
        $data = $rows->map(function ($r) use ($shipmentAgg) {
            $estimasi = $this->computeEstimasiDanAlert($r);
            $estimasiAdmin = $this->computeEstimasiAdmin($r);

            return [
                'tanggal_naik_logistik_fmt'   => $this->fmtDate($r->tanggal_naik_logistik),
                'rencana_kirim_fmt'           => $this->fmtDate($r->rencana_kirim),
                'transport_lead_time'         => $r->transport_lead_time,
                'nama_driver'                 => $r->nama_driver,
                'no_pol'                      => $r->no_pol,
                'planner'                     => $r->planner,
                'no_shipment'                 => $r->no_shipment,
                'status_pengiriman_badge'     => $this->badgeStatusPengiriman($r),
                'dist_channel_badge'          => $this->badgeDistChannel($r->dist_channel),
                'tujuan'                      => $r->tujuan,
                'area'                        => $r->area,
                'ketersediaan_unit_badge'     => $this->badgeKetersediaanUnit($r),
                'mobil'                       => $r->mobil,
                'total_do_qty_car'            => $r->total_do_qty_car,
                'nilai_muatan_fmt'            => 'Rp ' . number_format((float) $r->nilai_muatan, 0, ',', '.'),
                'biaya_kirim_fmt'             => 'Rp ' . number_format((float) $r->biaya_kirim, 0, ',', '.'),
                'cr_fmt'                      => $this->formatCR($this->computeCR($r, $shipmentAgg)),
                'kategori_ekspedisi_badge'    => $this->badgeKategoriEkspedisi($r->kategori_ekspedisi),
                'ekpedisi'                    => $r->ekpedisi,
                'tanggal_dpt_unit_fmt'        => $this->fmtDate($r->tanggal_dpt_unit),
                'lama_waktu_pencarian'        => $r->lama_waktu_pencarian ?? '-',
                'sla_dapat_mobil_badge'       => $this->badgeSlaDapatMobil($r->sla_dapat_mobil),

                // GUDANG 1 (KACS)
                'planning_loading_fmt'        => $this->fmtDate($r->planning_loading),
                'tanggal_tiba_gudang_fmt'     => $this->fmtDate($r->tanggal_tiba_gudang),
                'tanggal_keluar_gudang_fmt'   => $this->fmtDate($r->tanggal_keluar_gudang),
                'durasi_gudang1_fmt'          => $this->computeDurasiGudang($r->planning_loading, $r->tanggal_tiba_gudang),
                'status_gudang1_badge'        => $this->badgeStatusOnTimeDelayByDate($r->planning_loading, $r->tanggal_tiba_gudang),
                'sla_loading1_badge'          => $this->badgeSlaLoadingClean($r->planning_loading, $r->tanggal_tiba_gudang),

                // GUDANG 2 (SENTUL)
                'planning_loading_2'          => $this->fmtDate($r->planning_loading_2 ?? null),
                'tanggal_tiba_gudang_2'       => $this->fmtDate($r->tanggal_tiba_gudang_2 ?? null),
                'tanggal_keluar_gudang_2'     => $this->fmtDate($r->tanggal_keluar_gudang_2 ?? null),
                'lama_digudang_2'             => $r->lama_digudang_2 ?? '-',
                'sla_loading2_badge'          => $this->badgeSLA($r->sla_loading_2 ?? null),
                'status_gudang2_badge'        => $this->badgeStatusGudangRaw($r->status_gudang_2 ?? null),

                // GUDANG 3 (CCIE)
                'planning_loading_3'          => $this->fmtDate($r->planning_loading_3 ?? null),
                'tanggal_tiba_gudang_3'       => $this->fmtDate($r->tanggal_tiba_gudang_3 ?? null),
                'tanggal_keluar_gudang_3'     => $this->fmtDate($r->tanggal_keluar_gudang_3 ?? null),
                'lama_digudang_3'             => $r->lama_digudang_3 ?? '-',
                'sla_loading3_badge'          => $this->badgeSLA($r->sla_loading_3 ?? null),
                'status_gudang3_badge'        => $this->badgeStatusGudangRaw($r->status_gudang_3 ?? null),

                'pic_monitoring'              => $r->pic_monitoring,
                'nama_kapal'                  => $r->nama_kapal,
                'etd'                         => $r->etd,
                'eta'                         => $r->eta,
                'status_kendaraan_badge'      => $this->badgeStatusKendaraan($r->status_kendaraan ?? null),
                'alert_badge'                 => $estimasi['alert_html'],

                'act_urutan_bongkar'          => $r->act_urutan_bongkar,
                'qty_monitoring'              => $r->qty_monitoring,
                'biaya_kuli'                  => $r->biaya_kuli ? 'Rp ' . number_format($r->biaya_kuli, 0, ',', '.') : '',
                'total_biaya_kuli'            => $r->total_biaya_kuli ? 'Rp ' . number_format($r->total_biaya_kuli, 0, ',', '.') : '',
                'selisih_qty'                 => $r->selisih_qty,
                'remarks_qty'                 => $r->remarks_qty,
                'create_tgl'                  => $r->create_tgl ? Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-',

                'atd'                         => $r->atd,
                'ata'                         => $r->ata,
                'estimasi_tiba_fmt'           => $estimasi['estimasi_show'],
                'tanggal_tiba_fmt'            => $r->tanggal_tiba ? date('d-m-Y h:i A', strtotime($r->tanggal_tiba)) : '-',
'lama_perjalanan' => $this->computeLamaPerjalananSimple($r),// ✅ pakai method yang sudah ada

                'sla_tiba_badge'              => $this->badgeOnTimeDelay($r->sla_tiba),
                'tanggal_bongkar_fmt'         => $r->tanggal_bongkar ? date('d-m-Y h:i A', strtotime($r->tanggal_bongkar)) : '-',
                'status_bongkar_badge'        => $this->badgeStatusBongkar($r),
              'overstay_days'               => $this->formatHari($r->overstay_days),
                'sla_bongkar_badge'           => $this->badgeOnTimeDelay($r->sla_bongkar),
                'reason_tiba'                 => $r->reason_tiba,
                'reason_bongkar'              => $r->reason_bongkar,
                'status_akhir_badge'          => $this->badgeStatusAkhir($r),
                'status_alert_badge'          => $this->badgeStatusAlert($r),

                'remarks'                     => $r->remarks,
                'route'                       => $r->route,
                'route_awal'                  => $r->route ? explode('-', trim($r->route))[0] : '-',
                'pulau'                       => $r->pulau,
                'via_kirim'                   => $r->via_kirim,
                'estimasi_admin_fmt'          => $estimasiAdmin ? $estimasiAdmin->format('d-m-Y') : '-',
                'estimasi_admin_status_badge' => $this->badgeEstimasiAdmin($r),
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    private function formatHari($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    return $value . ' Hari';
}

    

    /* =========================================================
     * ARCHIVE / DELETE ALL (tetap sama)
     * ========================================================= */
    public function archiveAll()
    {
        $data = DB::table('logistik_pengiriman')->get();

        foreach ($data as $row) {
            DB::table('logistik_storage')->insert([
                'no_shipment' => $row->no_shipment ?? null,
                'tanggal_naik_logistik' => $row->tanggal_naik_logistik ?? null,
                'rencana_kirim' => $row->rencana_kirim ?? null,
                'dist_channel' => $row->dist_channel ?? null,
                'tujuan' => $row->tujuan ?? null,
                'area' => $row->area ?? null,
                'nilai_muatan' => $row->nilai_muatan ?? 0,
                'biaya_kirim' => $row->biaya_kirim ?? 0,
                'kategori_ekspedisi' => $row->kategori_ekspedisi ?? null,
                'ekspedisi' => $row->ekspedisi ?? null,
                'status_pengiriman' => $row->status_pengiriman ?? null,
                'status_gudang' => $row->status_gudang ?? null,
                'status_akhir' => $row->status_akhir ?? null,
                'sla_tiba' => $row->sla_tiba ?? null,
                'sla_bongkar' => $row->sla_bongkar ?? null,
                'total_do_qty_car' => $row->total_do_qty_car ?? 0,
                'overstay_days' => $row->overstay_days ?? 0,
                'tanggal_tiba_gudang' => $row->tanggal_tiba_gudang ?? null,
                'tanggal_keluar_gudang' => $row->tanggal_keluar_gudang ?? null,
                'tanggal_tiba' => $row->tanggal_tiba ?? null,
                'tanggal_bongkar' => $row->tanggal_bongkar ?? null,
                'remarks' => $row->remarks ?? null,
                'reason_tiba' => $row->reason_tiba ?? null,
                'reason_bongkar' => $row->reason_bongkar ?? null,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }

        DB::table('logistik_pengiriman')->delete();

        return back()->with('success', 'Data berhasil dipindahkan ke Storage');
    }

    public function deleteAll()
    {
        DB::table('logistik_pengiriman')->delete();

        return back()->with('success', 'Semua data berhasil dihapus');
    }

    public function monitoring()
    {
        $logistik = LogistikPengiriman::latest()->get();
        $areaList = $this->cachedList('area');

        return view('monitoring.data_monitoring', compact('logistik', 'areaList'));
    }

    /* =========================================================
     * ARMADA (tetap sama — dataset biasanya lebih kecil karena
     * sudah difilter rencana_kirim/tanggal_dpt_unit NOT NULL).
     * Kalau tabel ini juga sudah ribuan baris, terapkan pola
     * server-side DataTables yang sama seperti dataLogistikAjax().
     * ========================================================= */
    public function armada(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->whereNotNull('rencana_kirim')
            ->whereRaw("TRIM(rencana_kirim) <> ''")
            ->whereNotNull('tanggal_dpt_unit')
            ->whereRaw("TRIM(tanggal_dpt_unit) <> ''");

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        $logistik = $query
            ->orderBy('tanggal_naik_logistik', 'DESC')
            ->get()
            ->map(function ($row) {
                $tibaGudang = $this->getTibaGudangTerdekat($row);

                if ($row->tanggal_dpt_unit && $tibaGudang) {
                    $awal = new \DateTime(date('Y-m-d H:i:s', strtotime($row->tanggal_dpt_unit)));
                    $akhir = new \DateTime(date('Y-m-d H:i:s', strtotime($tibaGudang)));

                    $awalCek  = (clone $awal)->setTime(0, 0, 0);
                    $akhirCek = (clone $akhir)->setTime(0, 0, 0);

                    if ($akhir >= $awal) {
                        $diff = $awal->diff($akhir);

                        $row->lama_waktu_pencarian = $diff->days > 0
                            ? "{$diff->days} Hari {$diff->h} Jam {$diff->i} Menit"
                            : "{$diff->h} Jam {$diff->i} Menit";

                        $row->sla_dapat_mobil   = $akhirCek > $awalCek ? 'Delay' : 'On Time';
                        $row->status_pengiriman = $akhirCek > $awalCek ? 'Terlambat' : 'Sudah Dapat';
                    } else {
                        $row->lama_waktu_pencarian = "0 Jam 0 Menit";
                        $row->sla_dapat_mobil      = 'On Time';
                        $row->status_pengiriman    = 'Sudah Dapat';
                    }
                } else {
                    $row->lama_waktu_pencarian = '-';
                    $row->sla_dapat_mobil      = '-';
                    $row->status_pengiriman    = '-';
                }

                return $row;
            });

        return view('planner.armada', compact('logistik'));
    }

    public function edit($id)
    {
        $data['logistik'] = LogistikPengiriman::findOrFail($id);
        return view('edit', $data);
    }

    public function update(Request $request, $id)
    {
        $logistik = LogistikPengiriman::findOrFail($id);
        $logistik->update($request->all());

        return redirect('/logistik')->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        LogistikPengiriman::findOrFail($id)->delete();
        return redirect('/logistik')->with('success', 'Data berhasil dihapus');
    }

    public function belumArmada(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereNull('rencana_kirim')
                    ->orWhere('rencana_kirim', '')
                    ->orWhereNull('tanggal_dpt_unit')
                    ->orWhere('tanggal_dpt_unit', '');
            });

        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        $logistik = $query->orderBy('tanggal_naik_logistik', 'DESC')->get();

        return view('planner.belum_armada', compact('logistik'));
    }

    public function store(Request $request)
    {
        LogistikPengiriman::create($request->all());
        return back()->with('success', 'Data berhasil ditambah');
    }

    public function delete($id)
    {
        LogistikPengiriman::findOrFail($id)->delete();
        return back()->with('success', 'Data dihapus');
    }

    public function chartStatus()
    {
        return LogistikPengiriman::select('status_akhir', DB::raw('COUNT(*) as total'))
            ->groupBy('status_akhir')
            ->get();
    }

    public function slaOntime(Request $request)
    {
        $query = LogistikPengiriman::query();

        if ($request->bulan) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        if ($request->area) {
            $query->where('area', $request->area);
        }

        $list = $query->where('status_akhir', 'On Time')->get();
        $list_area = $this->cachedList('area');
        $title = "SLA ONTIME";

        return view('sla_ontime', compact('list', 'list_area', 'title'));
    }

    public function index()
    {
        $total = LogistikPengiriman::count();

        $ontime = LogistikPengiriman::whereIn('status_akhir', ['On Time', 'Ontime'])->count();
        $delay  = LogistikPengiriman::whereIn('status_akhir', ['Delay', 'Critical Delay'])->count();

        $summary_area = LogistikPengiriman::select('area', DB::raw('COUNT(*) as total'))
            ->groupBy('area')
            ->orderByDesc('total')
            ->get();

        return view('dashboard', compact('total', 'ontime', 'delay', 'summary_area'));
    }

    public function slaDelay(Request $request)
    {
        $query = LogistikPengiriman::query();

        if ($request->bulan) {
            $query->whereMonth('tanggal_naik_logistik', $request->bulan);
        }

        if ($request->tahun) {
            $query->whereYear('tanggal_naik_logistik', $request->tahun);
        }

        if ($request->area) {
            $query->where('area', $request->area);
        }

        $list = $query->whereIn('status_akhir', ['Delay', 'Critical Delay'])->get();
        $list_area = $this->cachedList('area');
        $title = "SLA DELAY";

        return view('sla_delay', compact('list', 'list_area', 'title'));
    }

    public function dashboardPlanner()
    {
        $total = DB::table('logistik_pengiriman')->count();

        $ontime = DB::table('logistik_pengiriman')
            ->whereIn('status_akhir', ['On Time', 'Ontime'])
            ->count();

        $delay = DB::table('logistik_pengiriman')
            ->whereIn('status_akhir', ['Delay', 'Critical Delay'])
            ->count();

        return view('planner.dashboard', compact('total', 'ontime', 'delay'));
    }

    public function ontimeCustomer(Request $request)
    {
        $bulan = $request->bulan;
        $tahun = $request->tahun;

        $query = DB::table('logistik_pengiriman_new');

        if ($bulan) {
            $query->whereMonth('tanggal_tiba', $bulan);
        }

        if ($tahun) {
            $query->whereYear('tanggal_tiba', $tahun);
        }

        $logistik = $query->get();

        foreach ($logistik as $row) {
            $row->status_customer = 'DELAY';

            if (!empty($row->rencana_kirim) && !empty($row->tanggal_tiba)) {
                if (strtotime($row->tanggal_tiba) <= strtotime($row->rencana_kirim)) {
                    $row->status_customer = 'ONTIME';
                }
            }
        }

        return view('logistik.ontime_customer', compact('logistik'));
    }

    public static function kpi($query)
    {
        return [
            'total_data' => (clone $query)->count(),

            'gudang_ontime' => (clone $query)->where(function ($q) {
                $q->where('sla_loading', 'H+0')->orWhere('status', 'On Time');
            })->count(),

            'gudang_delay' => (clone $query)->where(function ($q) {
                $q->where('sla_loading', 'H+1')->orWhere('status', 'Delay');
            })->count(),
        ];
    }

    public static function summaryArea($query)
    {
        return (clone $query)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw('SUM(biaya_kirim) as total_biaya'),
                DB::raw('SUM(nilai_muatan) as total_muatan')
            )
            ->groupBy('area')
            ->get();
    }

    public function dashboardSpv()
    {
        $data = LogistikPengiriman::all();
        return view('spv.dashboard', compact('data'));
    }

    public function dashboardManager(Request $request)
    {
        $date  = $request->date;
        $month = $request->month;
        $year  = $request->year;

        $base = DB::table('logistik_pengiriman');

        if ($date) {
            $base->whereDate('created_at', $date);
        }

        if ($month) {
            $base->whereMonth('created_at', substr($month, 5, 2))
                ->whereYear('created_at', substr($month, 0, 4));
        }

        if ($year) {
            $base->whereYear('created_at', $year);
        }

        $total_data = (clone $base)->count();

        $gudang_ontime = (clone $base)->whereRaw("LOWER(status) LIKE '%on%'")->count();
        $gudang_delay  = (clone $base)->whereRaw("LOWER(status) LIKE '%delay%'")->count();

        $customer_ontime = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_tiba', '!=', '')
            ->whereIn('status_akhir', ['On Time', 'ONTIME'])
            ->count();

        $customer_delay = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_tiba', '!=', '')
            ->whereIn('status_akhir', ['Delay', 'DELAY', 'Critical Delay'])
            ->count();

        $bongkar_ontime = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '')
            ->where('sla_bongkar', 'H+0')
            ->count();

        $bongkar_delay = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '')
            ->whereRaw("
                LOWER(sla_bongkar) LIKE '%delay%'
                OR LOWER(sla_bongkar) LIKE '%h+1%'
                OR LOWER(sla_bongkar) LIKE '%h+2%'
                OR LOWER(sla_bongkar) LIKE '%h>2%'
            ")
            ->count();

        $summary_area = (clone $base)
            ->select(
                'area',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw("SUM(CASE WHEN biaya_kirim IS NULL THEN 0 ELSE biaya_kirim END) as total_biaya"),
                DB::raw("SUM(CASE WHEN nilai_muatan IS NULL THEN 0 ELSE nilai_muatan END) as total_muatan")
            )
            ->groupBy('area')
            ->get();

        $summary_tujuan = (clone $base)
            ->select(
                'tujuan',
                DB::raw('COUNT(*) as total_shipment'),
                DB::raw("SUM(biaya_kirim) as total_biaya"),
                DB::raw("SUM(nilai_muatan) as total_muatan")
            )
            ->groupBy('tujuan')
            ->get();

        $ekspedisi = (clone $base)
            ->select('kategori_ekspedisi', DB::raw('COUNT(*) as total'))
            ->groupBy('kategori_ekspedisi')
            ->get();

        $label = $ekspedisi->pluck('kategori_ekspedisi');
        $value = $ekspedisi->pluck('total');

        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan');
        $totalBiayaKirim  = (clone $base)->sum('biaya_kirim');

        $planner_ontime = $gudang_ontime;
        $planner_delay  = $gudang_delay;
        $planner_armada = (clone $base)->whereNotNull('mobil')->count();
        $planner_belum_armada = (clone $base)->whereNull('mobil')->count();

        $total_tiba = (clone $base)
            ->whereNotNull('tanggal_tiba')
            ->where('tanggal_tiba', '!=', '')
            ->count();

        $total_bongkar = (clone $base)
            ->whereNotNull('tanggal_bongkar')
            ->where('tanggal_bongkar', '!=', '')
            ->count();

        $summary_monitoring = [
            'tiba_ontime' => $total_tiba ? round(($customer_ontime / $total_tiba) * 100, 2) : 0,
            'tiba_delay' => $total_tiba ? round(($customer_delay / $total_tiba) * 100, 2) : 0,
            'bongkar_ontime' => $total_bongkar ? round(($bongkar_ontime / $total_bongkar) * 100, 2) : 0,
            'bongkar_delay' => $total_bongkar ? round(($bongkar_delay / $total_bongkar) * 100, 2) : 0,
        ];

        return view('manager.dashboard', compact(
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
            'label',
            'value',
            'planner_ontime',
            'planner_delay',
            'planner_armada',
            'planner_belum_armada',
            'summary_monitoring'
        ));
    }

    private function dashboardData()
    {
        return [
            'total' => DB::table('logistik_pengiriman')->count(),
            'ontime' => DB::table('logistik_pengiriman')
                ->whereIn('status_akhir', ['On Time', 'Ontime'])->count(),
            'delay' => DB::table('logistik_pengiriman')
                ->whereIn('status_akhir', ['Delay', 'Critical Delay'])->count(),
        ];
    }

    /* =========================================================================
     * =========================================================================
     *  HELPER / PRESENTER METHODS
     *  (dipindahkan dari @php block Blade -> lebih cepat, testable, reusable)
     * =========================================================================
     * ========================================================================= */

    /** Cache dropdown list 1 jam — dulu di-query ulang tiap load halaman */
    private function cachedList(string $column)
    {
        return Cache::remember("list_{$column}", 3600, function () use ($column) {
            return DB::table('logistik_pengiriman')
                ->select($column)
                ->whereNotNull($column)
                ->distinct()
                ->orderBy($column)
                ->pluck($column);
        });
    }

    private function fmtDate($value, string $format = 'd-m-Y')
    {
        if (empty($value) || $value === 'mm/dd/yyyy') {
            return '-';
        }
        return date($format, strtotime($value));
    }

    /**
     * Agregat per no_shipment (dipakai untuk hitung Cost Ratio).
     * Dihitung SEKALI per request via SQL GROUP BY, bukan di JS per baris.
     * Return: ['SHIPMENT001' => ['total_muatan' => x, 'total_biaya' => y], ...]
     */
    private function shipmentAggregates(): array
    {
        return DB::table('logistik_pengiriman')
            ->select(
                'no_shipment',
                DB::raw('SUM(nilai_muatan) as total_muatan'),
                DB::raw('MAX(biaya_kirim) as total_biaya')
            )
            ->whereNotNull('no_shipment')
            ->where('no_shipment', '!=', '')
            ->groupBy('no_shipment')
            ->get()
            ->keyBy('no_shipment')
            ->map(fn($g) => [
                'total_muatan' => (float) $g->total_muatan,
                'total_biaya'  => (float) $g->total_biaya,
            ])
            ->toArray();
    }

    /** Replikasi persis logic CR yang dulu ada di drawCallback JS */
    private function computeCR($r, array $shipmentAgg): float
    {
        $noShipment = trim((string) $r->no_shipment);

        if ($noShipment === '' || !isset($shipmentAgg[$noShipment])) {
            $muatan = (float) $r->nilai_muatan;
            $biaya  = (float) $r->biaya_kirim;
            return $muatan > 0 ? ($biaya / $muatan) * 100 : 0;
        }

        $totalMuatan = $shipmentAgg[$noShipment]['total_muatan'];
        $totalBiaya  = $shipmentAgg[$noShipment]['total_biaya'];
        $nilaiBaris  = (float) $r->nilai_muatan;

        if ($totalMuatan <= 0 || $nilaiBaris <= 0) {
            return 0;
        }

        $totalCR    = ($totalBiaya / $totalMuatan) * 100;
        $kontribusi = $nilaiBaris / $totalMuatan;

        return $kontribusi * $totalCR;
    }

    private function formatCR(float $cr): string
    {
        return $cr > 0
            ? '<span class="text-primary font-weight-bold" style="color:#0056b3;font-weight:bold;">' . number_format($cr, 4, ',', '.') . '%</span>'
            : '<span class="text-muted" style="color:#9e9e9e;font-size:11px;">0,0000%</span>';
    }

    /** Badge generik untuk kolom SLA seperti "Sesuai SLA / H+1 / H+2" */
    private function badgeSLA($sla): string
    {
        $sla = trim((string) $sla);

        if ($sla === '' || $sla === '-' || $sla === 'null') {
            return '<span class="badge badge-gray">-</span>';
        }

        $slaLower = strtolower($sla);

        if (in_array($slaLower, ['sesuai sla', 'on time', 'ontime', 'h+0'])) {
            return '<span class="badge badge-green">' . e($sla) . '</span>';
        }

        if (preg_match('/^h\+1$/i', $sla)) {
            return '<span class="badge badge-orange">' . e($sla) . '</span>';
        }

        if (preg_match('/^h\+\d+$/i', $sla)) {
            return '<span class="badge badge-red">' . e($sla) . '</span>';
        }

        return '<span class="badge badge-gray">' . e($sla) . '</span>';
    }

    /** Badge On Time / Delay generik (dipakai untuk sla_tiba & sla_bongkar) */
    private function badgeOnTimeDelay($value): string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return '-';
        }

        if (strtolower($value) === 'on time') {
            return '<span class="badge green">On Time</span>';
        }

        return '<span class="badge red">' . e($value) . '</span>';
    }

    private function badgeSlaDapatMobil($sla): string
    {
        $sla = trim((string) $sla);

        if (empty($sla)) {
            return '<span class="badge gray">-</span>';
        }

        if (strtolower($sla) === 'on time' || strtoupper($sla) === 'H+0') {
            return '<span class="badge green">' . e($sla) . '</span>';
        }

        if (strtolower($sla) === 'delay') {
            return '<span class="badge red">Delay</span>';
        }

        if (preg_match('/h\+1/i', $sla)) {
            return '<span class="badge orange">' . e($sla) . '</span>';
        }

        if (preg_match('/h\+/i', $sla)) {
            return '<span class="badge red">' . e($sla) . '</span>';
        }

        return '<span class="badge gray">' . e($sla) . '</span>';
    }

    private function badgeDistChannel($channel): string
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

    private function badgeKetersediaanUnit($r): string
    {
        if (empty($r->rencana_kirim) || empty($r->tanggal_dpt_unit)) {
            return '<span class="badge-status status-belum">BELUM DAPAT</span>';
        }

        return '<span class="badge-status status-sudah">SUDAH DAPAT</span>';
    }

    private function badgeKategoriEkspedisi($kategori): string
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

    /**
     * Status pengiriman (badge "MENCARI UNIT / PERJALANAN KE GUDANG X /
     * DI GUDANG X / PERJALANAN KE TUJUAN / SUDAH TIBA TUJUAN / SUDAH SELESAI").
     * Replikasi persis logic panjang yang dulu ada di dalam @foreach Blade.
     */
    private function badgeStatusPengiriman($r): string
    {
        $dpt = $r->tanggal_dpt_unit;
        $tibaAkhir = $r->tanggal_tiba;
        $bongkarAkhir = $r->tanggal_bongkar;

        $gudang = collect([
            ['nama' => 'KACS',   'planning' => $r->planning_loading,   'tiba' => $r->tanggal_tiba_gudang,   'keluar' => $r->tanggal_keluar_gudang],
            ['nama' => 'SENTUL', 'planning' => $r->planning_loading_2, 'tiba' => $r->tanggal_tiba_gudang_2, 'keluar' => $r->tanggal_keluar_gudang_2],
            ['nama' => 'CCIE',   'planning' => $r->planning_loading_3, 'tiba' => $r->tanggal_tiba_gudang_3, 'keluar' => $r->tanggal_keluar_gudang_3],
        ])
            ->filter(fn($g) => !empty($g['planning']))
            ->sortBy(fn($g) => strtotime($g['planning']))
            ->values();

        $adaPlanningGudang = $gudang->count() > 0;
        $statusGudang = null;

        foreach ($gudang as $g) {
            if (empty($g['tiba'])) {
                $statusGudang = ['status' => 'PERJALANAN KE ' . $g['nama'], 'badge' => 'yellow'];
                break;
            }

            if (!empty($g['tiba']) && empty($g['keluar'])) {
                $statusGudang = ['status' => 'DI GUDANG ' . $g['nama'], 'badge' => 'blue'];
                break;
            }
        }

        if (empty($dpt)) {
            $status = 'MENCARI UNIT';
            $badge = 'red';
        } elseif (!$adaPlanningGudang && empty($tibaAkhir)) {
            $status = 'PERJALANAN KE GUDANG';
            $badge = 'orange';
        } elseif ($statusGudang) {
            $status = $statusGudang['status'];
            $badge = $statusGudang['badge'];
        } elseif (empty($tibaAkhir)) {
            $status = 'PERJALANAN KE TUJUAN';
            $badge = 'yellow';
        } elseif (!empty($tibaAkhir) && !empty($bongkarAkhir)) {
            $status = 'SUDAH SELESAI';
            $badge = 'green';
        } elseif (!empty($tibaAkhir)) {
            $status = 'SUDAH TIBA TUJUAN';
            $badge = 'success';
        } else {
            $status = '-';
            $badge = 'gray';
        }

        return '<span class="badge ' . $badge . '">' . e($status) . '</span>';
    }

    /** Cari status gudang aktif (dipakai internal buat menentukan estimasi/alert) */
    private function getStatusGudangAktif($r): ?array
    {
        $gudang = collect([
            ['nama' => 'KACS',   'planning' => $r->planning_loading,   'tiba' => $r->tanggal_tiba_gudang],
            ['nama' => 'SENTUL', 'planning' => $r->planning_loading_2, 'tiba' => $r->tanggal_tiba_gudang_2],
            ['nama' => 'CCIE',   'planning' => $r->planning_loading_3, 'tiba' => $r->tanggal_tiba_gudang_3],
        ])
            ->filter(fn($g) => !empty($g['planning']))
            ->sortBy(fn($g) => strtotime($g['planning']))
            ->values();

        foreach ($gudang as $g) {
            if (empty($g['tiba'])) {
                return ['status' => 'PERJALANAN KE ' . $g['nama']];
            }
        }

        return null;
    }

    /**
     * Estimasi tiba + alert (H-1/H-2/.../TERLAMBAT/ON TRACK).
     * Return array ['estimasi_show' => string, 'alert_html' => string]
     */
    private function computeEstimasiDanAlert($r): array
    {
        $statusGudang = $this->getStatusGudangAktif($r);

        if ($statusGudang) {
            return [
                'estimasi_show' => $statusGudang['status'],
                'alert_html' => '<span class="badge gray">-</span>',
            ];
        }

        $estimasi = !empty($r->estimasi_tiba) ? strtotime($r->estimasi_tiba) : null;
        $estimasiShow = $estimasi ? date('d-m-Y', $estimasi) : '-';

        if ($r->tanggal_tiba) {
            return [
                'estimasi_show' => $estimasiShow,
                'alert_html' => '<span class="badge green">✅ TIBA</span>',
            ];
        }

        if (!$estimasi) {
            return [
                'estimasi_show' => $estimasiShow,
                'alert_html' => '<span class="badge gray">-</span>',
            ];
        }

        $today = strtotime(date('Y-m-d'));
        $hariSisa = floor(($estimasi - $today) / 86400);

        if ($hariSisa < 0) {
            $alertText = 'Pending Tiba H+' . abs($hariSisa);
            $alertClass = 'red';
        } elseif ($hariSisa == 0) {
            $alertText = 'H-0';
            $alertClass = 'red';
        } elseif ($hariSisa == 1) {
            $alertText = 'H-1';
            $alertClass = 'red';
        } elseif ($hariSisa == 2 || $hariSisa == 3) {
            $alertText = 'H-' . $hariSisa;
            $alertClass = 'orange';
        } elseif ($hariSisa <= 7) {
            $alertText = 'H-' . $hariSisa;
            $alertClass = 'blue';
        } else {
            $alertText = 'ON TRACK';
            $alertClass = 'green';
        }

        return [
            'estimasi_show' => $estimasiShow,
            'alert_html' => '<span class="badge ' . $alertClass . '">' . e($alertText) . '</span>',
        ];
    }

    private function badgeStatusBongkar($r): string
    {
        if ($r->tanggal_bongkar) {
            return '<span class="badge green">Sudah Bongkar</span>';
        }

        if ($r->tanggal_tiba) {
            $tanggalTiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)));
            $today = strtotime(date('Y-m-d'));
            $hariBongkar = max(0, floor(($today - $tanggalTiba) / 86400));

            $class = $hariBongkar == 0 ? 'orange' : 'red';

            return '<span class="badge ' . $class . '">Pending Bongkar H+' . $hariBongkar . '</span>';
        }

        return '-';
    }

    private function badgeStatusAkhir($r): string
    {
        $slaTiba = strtoupper(trim($r->sla_tiba ?? ''));
        $slaBongkar = strtoupper(trim($r->sla_bongkar ?? ''));

        if (empty($r->tanggal_tiba)) {
            return '<span class="status-badge status-transit">🚚 Dalam Perjalanan</span>';
        }

        if (!empty($r->tanggal_tiba) && empty($r->tanggal_bongkar)) {
            return '<span class="status-badge status-unloading">📦 Sudah Tiba<br>Dalam Pembongkaran</span>';
        }

        if ($slaTiba === 'ON TIME' && $slaBongkar === 'ON TIME') {
            return '<span class="status-badge status-ontime">✅ Pengiriman On Time</span>';
        }

        return '<span class="status-badge status-delay">🚨 Pengiriman Delay</span>';
    }

    private function badgeStatusAlert($r): string
    {
        $slaTiba = strtoupper(trim($r->sla_tiba ?? ''));
        $slaBongkar = strtoupper(trim($r->sla_bongkar ?? ''));

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

    /** Estimasi tiba versi admin (rencana_kirim + lead time, +1 hari khusus Jawa Barat) */
    private function computeEstimasiAdmin($r): ?Carbon
    {
        if (empty($r->rencana_kirim)) {
            return null;
        }

        $estimasiAdmin = Carbon::parse($r->rencana_kirim)
            ->addDays((int) ($r->transport_lead_time ?? 0));

        if (strtolower(trim($r->area ?? '')) === 'jawa barat') {
            $estimasiAdmin->addDay();
        }

        return $estimasiAdmin;
    }

    private function badgeEstimasiAdmin($r): string
    {
        $estimasiAdmin = $this->computeEstimasiAdmin($r);

        if (!$estimasiAdmin) {
            return '<span class="badge gray">-</span>';
        }

        if (!empty($r->tanggal_tiba)) {
            $tanggalTiba = Carbon::parse($r->tanggal_tiba);
            return $tanggalTiba->lte($estimasiAdmin)
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">Delay</span>';
        }

        $isLate = now()->startOfDay()->gt($estimasiAdmin->copy()->startOfDay());

        return $isLate
            ? '<span class="badge red">Delay</span>'
            : '<span class="badge orange">Belum Tiba</span>';
    }
   
private function formatLamaPerjalananRaw($value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '-';
    }
    return (string) round((float) $value);
}
private function computeLamaPerjalananSimple($r): string
{
    if (empty($r->tanggal_tiba)) {
        return '-';
    }

    $keluarCandidates = array_filter([
        $r->tanggal_keluar_gudang ?? null,
        $r->tanggal_keluar_gudang_2 ?? null,
        $r->tanggal_keluar_gudang_3 ?? null,
    ]);

    if (empty($keluarCandidates)) {
        return '-';
    }

    $keluar = max(array_map('strtotime', $keluarCandidates));
    $tiba = strtotime($r->tanggal_tiba);

    $hari = max(0, floor(($tiba - $keluar) / 86400));

    return $hari . ' Hari';
}

    /** Durasi antara planning loading & tanggal tiba gudang, format "X Hari Y Jam" */
    private function computeDurasiGudang($planning, $tiba): string
    {
        if (empty($planning) || empty($tiba)) {
            return '-';
        }

        $start = Carbon::parse($planning);
        $end = Carbon::parse($tiba);

        $totalMenit = $start->diffInMinutes($end);
        $desimalHari = $totalMenit / 1440;

        $hari = floor($desimalHari);
        $jam = round(($desimalHari - $hari) * 24);

        if ($jam == 24) {
            $jam = 0;
            $hari += 1;
        }

        if ($hari > 0 && $jam > 0) return "{$hari} Hari {$jam} Jam";
        if ($hari > 0) return "{$hari} Hari";
        if ($jam > 0) return "{$jam} Jam";
        return "0 Jam";
    }

    /** Badge On Time / Delay dibanding 2 tanggal (planning vs realisasi), per-hari */
    private function badgeStatusOnTimeDelayByDate($planning, $tiba): string
    {
        if (empty($planning) || empty($tiba)) {
            return '<span class="badge gray">-</span>';
        }

        $startDay = Carbon::parse($planning)->startOfDay();
        $endDay = Carbon::parse($tiba)->startOfDay();

        return $endDay->gt($startDay)
            ? '<span class="badge red">Delay</span>'
            : '<span class="badge green">On Time</span>';
    }

    /** Badge "Sesuai SLA" / "H+n" berdasarkan selisih hari planning vs realisasi */
    private function badgeSlaLoadingClean($planning, $tiba): string
    {
        if (empty($planning) || empty($tiba)) {
            return '<span class="badge bg-secondary">-</span>';
        }

        $start = Carbon::parse($planning)->startOfDay();
        $end = Carbon::parse($tiba)->startOfDay();

        if ($end->gt($start)) {
            $selisihHari = $start->diffInDays($end);
            return '<span class="badge bg-warning text-dark">H+' . $selisihHari . '</span>';
        }

        return '<span class="badge bg-success">Sesuai SLA</span>';
    }

    /** Badge status gudang mentah (On Time / Delay / On Site) dari kolom status_gudang_2/3 */
    private function badgeStatusGudangRaw($status): string
    {
        if (empty($status)) {
            return '<span class="badge gray">-</span>';
        }

        $s = strtolower($status);

        if ($s === 'on time') return '<span class="badge green">On Time</span>';
        if ($s === 'delay') return '<span class="badge red">Delay</span>';
        if ($s === 'on site') return '<span class="badge orange">On Site</span>';

        return '<span class="badge gray">' . e($status) . '</span>';
    }

    private function badgeStatusKendaraan($status): string
    {
        $status = trim((string) $status);

        if ($status === 'On Track') return '<span class="badge green">🟢 On Track</span>';
        if ($status === 'Potential Delay') return '<span class="badge red">🔴 Potential Delay</span>';

        return '<span class="badge gray">-</span>';
    }

    /** Dipakai oleh armada() — cari tanggal tiba gudang paling awal yang terisi */
    private function getTibaGudangTerdekat($row)
    {
        foreach ([$row->tanggal_tiba_gudang ?? null, $row->tanggal_tiba_gudang_2 ?? null, $row->tanggal_tiba_gudang_3 ?? null] as $t) {
            if (!empty($t)) {
                return $t;
            }
        }
        return null;
    }
}