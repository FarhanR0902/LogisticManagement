<?php

namespace App\Http\Controllers;

use App\Exports\PasuruanExportFull;
use App\Exports\PasuruanExport;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Imports\LogistikPasuruanImport;
use Illuminate\Http\Request;
use App\Models\LogistikPengirimanPasuruan;
use App\Models\ShipmentPasuruanArchive;
use Illuminate\Support\Facades\Cache;
use App\Imports\UpdateQtyPgiPasuruanImport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PasuruanImport;

class PasuruanController extends Controller
{
    private array $fillableFields = [
        'planner_pasuruan',
          'customer_reference_pasuruan', 'sales_document_pasuruan', 'contract_end_date_pasuruan',
  'no_do_pasuruan', 'no_delivery_pasuruan', 'kirim_kontribusi_pasuruan',
  'reason_pengiriman_optimal_customer_pasuruan', 
        'no_shipment_pasuruan',
        'biaya_kuli_pasuruan',
        'total_biaya_kuli_pasuruan',
          'reason_pengiriman_optimal_pasuruan',
    'reason_pengiriman_optimal_customer_pasuruan',
    'pengiriman_optimal_customer_pasuruan',
    'hasil_kubik_pasuruan',
    'hasil_tonase_pasuruan',
    'pengiriman_optimal_pasuruan',

        // kapasitas & muatan (hasil_* / pengiriman_optimal dihitung per shipment
        // lewat recalculateHasilKubikTonase(), BUKAN dari input grid)
        'kubikasi_pasuruan',
        'tonase_pasuruan',
        'total_kubik_pasuruan',
        'total_tonase_pasuruan',

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

    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK', 'BANTEN', 'JAWA_BARAT', 'JAWA_TENGAH', 'JAWA_TIMUR', 'YOGYAKARTA'],
        'SUMATERA'   => ['ACEH', 'SUMATERA_UTARA', 'SUMATERA_BARAT', 'RIAU', 'KEP._RIAU', 'JAMBI', 'SUMATERA_SELATAN', 'BENGKULU', 'LAMPUNG', 'KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT', 'KALIMANTAN_TENGAH', 'KALIMANTAN_SELATAN', 'KALIMANTAN_TIMUR', 'KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA', 'SULAWESI_TENGAH', 'SULAWESI_SELATAN', 'SULAWESI_TENGGARA', 'SULAWESI_BARAT', 'GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI', 'NUSA_TENGGARA_BARAT', 'NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU', 'PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA', 'PAPUA_BARAT', 'PAPUA_BARAT_DAYA', 'PAPUA_SELATAN', 'PAPUA_TENGAH'],
    ];

    // =====================================================
    // MONITORING (dipakai update & autosave)
    // =====================================================
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
            ? (int) $data['transport_lead_time_pasuruan']
            : 0;

        // ---- Estimasi Tiba ----
        $estimasi = !empty($data['estimasi_tiba_pasuruan'])
            ? strtotime($data['estimasi_tiba_pasuruan'])
            : null;

        if (!$estimasi && $keluar && $leadtime > 0) {
            $estimasi = strtotime("+{$leadtime} days", $keluar);
        }

        // ---- Lama Perjalanan ----
        if ($keluar && $tiba) {
            $keluarDate = strtotime(date('Y-m-d', $keluar));
            $tibaDate   = strtotime(date('Y-m-d', $tiba));

            $lama = ($tibaDate - $keluarDate) / 86400;

            $data['lama_perjalanan_pasuruan'] = max(0, (int) $lama);
        }

        // ---- SLA Tiba ----
        if ($tiba && $estimasi) {
            $tibaDate     = strtotime(date('Y-m-d', $tiba));
            $estimasiDate = strtotime(date('Y-m-d', $estimasi));

            $data['sla_tiba_pasuruan'] = ($tibaDate <= $estimasiDate) ? 'On Time' : 'Delay';
        }

        // ---- Overstay (bandingkan per tanggal kalender) ----
        if ($tiba && $bongkar) {
            $tibaDate    = strtotime(date('Y-m-d', $tiba));
            $bongkarDate = strtotime(date('Y-m-d', $bongkar));

            $overstay = ($bongkarDate - $tibaDate) / 86400;

            $data['overstay_days_pasuruan'] = max(0, $overstay);
            $data['sla_bongkar_pasuruan']   = $overstay <= 0 ? 'On Time' : 'Delay';
        }

        // ---- Alert Monitoring ----
        if ($bongkar) {
            $data['monitoring_alert_pasuruan'] = 'SELESAI';
        } elseif ($tiba) {
            $data['monitoring_alert_pasuruan'] = 'TIBA DI TUJUAN';
        } elseif ($estimasi) {
            $today   = strtotime(date('Y-m-d'));
            $selisih = ceil(($estimasi - $today) / 86400);

            if ($selisih < 0) {
                $data['monitoring_alert_pasuruan'] = 'TERLAMBAT';
            } elseif ($selisih <= 2) {
                $data['monitoring_alert_pasuruan'] = 'WARNING H-2';
            } else {
                $data['monitoring_alert_pasuruan'] = 'AMAN';
            }
        }

        // ---- Action Required ----
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

    // =====================================================
    // IN TRANSIT (PASURUAN): sudah keluar gudang, belum ada tanggal_tiba
    // =====================================================
    private function inTransitEstimasiSqlPasuruan(): string
    {
        return "COALESCE(estimasi_tiba_pasuruan, DATE_ADD(
            COALESCE(tanggal_keluar_gudang_pasuruan, '1900-01-01'),
            INTERVAL CAST(COALESCE(NULLIF(TRIM(transport_lead_time_pasuruan),''),0) AS UNSIGNED) DAY
        ))";
    }

    public function inTransit(Request $request)
    {
        $today   = date('Y-m-d');
        $todayTs = strtotime($today);
        $soon    = date('Y-m-d', strtotime('+3 days'));
        $est     = $this->inTransitEstimasiSqlPasuruan();

        // duplicate request supaya $request asli tidak ke-mutate dengan status=in_transit
        $reqInTransit = $request->duplicate();
        $reqInTransit->merge(['status' => 'in_transit']);

        $base = DB::table('logistik_pengiriman_pasuruan');

        $this->applyFilter($base, $reqInTransit);

        if ($request->filled('pic_monitoring')) {
            $base->where('pic_monitoring_pasuruan', $request->input('pic_monitoring'));
        }
        if ($request->filled('q')) {
            $s = trim($request->input('q'));
            $base->where(function ($q) use ($s) {
                foreach ([
                    'no_shipment_pasuruan', 'tujuan_pasuruan', 'ekspedisi_pasuruan',
                    'nama_driver_pasuruan', 'no_pol_pasuruan', 'mobil_pasuruan',
                ] as $col) {
                    $q->orWhere($col, 'like', "%{$s}%");
                }
            });
        }

        // ===== ringkasan =====
        $sum = (clone $base)->selectRaw("
            COUNT(*) AS total,
            SUM(CASE WHEN DATE({$est}) < ? THEN 1 ELSE 0 END) AS overdue,
            SUM(CASE WHEN DATE({$est}) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS soon,
            SUM(CASE WHEN DATE({$est}) > ? THEN 1 ELSE 0 END) AS ontrack
        ", [$today, $today, $soon, $soon])->first();

        $summary = [
            'total'   => (int) ($sum->total ?? 0),
            'overdue' => (int) ($sum->overdue ?? 0),
            'soon'    => (int) ($sum->soon ?? 0),
            'ontrack' => (int) ($sum->ontrack ?? 0),
        ];

        // ===== data tabel =====
        $list = (clone $base)
            ->orderByRaw("DATE({$est}) ASC")
            ->orderBy('no_shipment_pasuruan')
            ->paginate(50)
            ->withQueryString();

        // mapping nama kolom Pasuruan -> properti generik yang dipakai
        // view monitoring/in_transit.blade.php
        $list->getCollection()->transform(function ($r) use ($todayTs) {
            $keluar = !empty($r->tanggal_keluar_gudang_pasuruan)
                ? strtotime($r->tanggal_keluar_gudang_pasuruan)
                : null;

            $lead     = (int) ($r->transport_lead_time_pasuruan ?? 0);
            $keluarD  = $keluar ? strtotime(date('Y-m-d', $keluar)) : null;
            $estimasi = !empty($r->estimasi_tiba_pasuruan)
                ? strtotime($r->estimasi_tiba_pasuruan)
                : ($keluarD ? strtotime("+{$lead} days", $keluarD) : null);

            $alert = '-';
            $cls   = 'gray';
            if ($estimasi) {
                $sisa = floor(($estimasi - $todayTs) / 86400);
                if     ($sisa < 0)  { $alert = 'Pending Tiba H+' . abs($sisa); $cls = 'red'; }
                elseif ($sisa <= 1) { $alert = 'H-' . $sisa;                    $cls = 'red'; }
                elseif ($sisa <= 3) { $alert = 'H-' . $sisa;                    $cls = 'orange'; }
                elseif ($sisa <= 7) { $alert = 'H-' . $sisa;                    $cls = 'blue'; }
                else                { $alert = 'ON TRACK';                      $cls = 'green'; }
            }

            return (object) [
                'no_shipment'    => $r->no_shipment_pasuruan,
               'tujuan'      => $r->tujuan_pasuruan,
'tujuan_html' => $r->no_shipment_pasuruan
    ? '<a href="#" class="btn-tujuan" data-shipment="' . e($r->no_shipment_pasuruan)
        . '" data-tujuan="' . e($r->tujuan_pasuruan) . '">▶ '
        . e($r->tujuan_pasuruan) . '</a>'
    : e($r->tujuan_pasuruan),
                'area'           => $r->area_pasuruan,
                'dist_channel'   => $r->dist_channel_pasuruan,
                'ekpedisi'       => $r->ekspedisi_pasuruan,
                'mobil'          => $r->mobil_pasuruan,
                'nama_driver'    => $r->nama_driver_pasuruan,
                'no_pol'         => $r->no_pol_pasuruan,
                'pic_monitoring' => $r->pic_monitoring_pasuruan,
                'remarks'        => $r->remarks_pasuruan,
                'gudang_asal'    => 'GUDANG',
                'keluar_label'   => $keluar ? date('d-m-Y', $keluar) : '-',
                'hari_transit'   => $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null,
                'estimasi_label' => $estimasi ? date('d-m-Y', $estimasi) : '-',
                'alert_label'    => $alert,
                'alert_class'    => $cls,
            ];
        });

        $areaList = DB::table('logistik_pengiriman_pasuruan')
            ->whereNotNull('area_pasuruan')->distinct()->orderBy('area_pasuruan')->pluck('area_pasuruan');

        $picList = DB::table('logistik_pengiriman_pasuruan')
            ->whereNotNull('pic_monitoring_pasuruan')->distinct()->orderBy('pic_monitoring_pasuruan')->pluck('pic_monitoring_pasuruan');

        $formRoute = route('pasuruan.intransit');

        return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList', 'formRoute'));
    }

    public function exportFull(Request $request)
{
    set_time_limit(0);
    ini_set('memory_limit', '1G');

    // JANGAN di-get(), kirim builder-nya saja
    $query = DB::table('logistik_pengiriman_pasuruan');
    $this->applyFilter($query, $request);   // planner, area, pulau, dist_channel, tanggal, bulan, tahun, search

    return Excel::download(
        new PasuruanExportFull($query),
        'Data_Pasuruan_Full_' . date('Ymd_His') . '.xlsx'
    );
}

    // =====================================================
    // IMPORT
    // =====================================================
    public function updateQtyPgi(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $import = new UpdateQtyPgiPasuruanImport();
        Excel::import($import, $request->file('file'));

        Cache::forget('pasuruan_shipment_agg');

        $message = "Update selesai. "
            . "Total DO: {$import->getQtyUpdated()} baris diupdate. "
            . "Act PGI Date: {$import->getPgiUpdated()} baris diupdate. "
            . "Kubikasi/Tonase: {$import->getKubikTonaseUpdated()} baris diupdate.";

        $notFound = array_merge(
            array_map(fn($x) => "Total DO tidak ketemu: {$x}", $import->getQtyNotFound()),
            array_map(fn($x) => "Total DO ambigu (lebih dari 1 baris): {$x}", $import->getQtyAmbiguous()),
            array_map(fn($x) => "PGI tidak ketemu: {$x}", $import->getPgiNotFound()),
            array_map(fn($x) => "Kubik/Tonase tidak ketemu: {$x}", $import->getKubikTonaseNotFound())
        );

        return redirect()->back()
            ->with('success', $message)
            ->with('not_found_list', $notFound);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $import = new LogistikPasuruanImport;
        Excel::import($import, $request->file('file'));

        $this->invalidatePlannerAreaCachePasuruan();
        Cache::forget('pasuruan_shipment_agg');

        $msg = "Data Pasuruan berhasil diimport. "
            . "Baru: {$import->getInsertedCount()}, "
            . "Diupdate: {$import->getUpdatedCount()}, "
            . "Dilewati: {$import->getSkippedCount()}, "
            . "Gagal: {$import->getFailedCount()}.";

        $redirect = redirect()
            ->route('pasuruan.dataLogistik')
            ->with('success', $msg);

       $redirect = redirect()
    ->route('pasuruan.dataLogistik')
    ->with('success', $msg);

$notes = [];

if ($import->getFailedCount() > 0) {
    foreach ($import->getFailedList() as $f) {
        $notes[] = "Gagal: {$f['no_shipment']} - {$f['tujuan']} ({$f['error']})";
    }
}

foreach ($import->getNoLeadTime() as $x) {
    $notes[] = "Estimasi tiba kosong (lead time tidak ketemu): {$x}";
}

foreach ($import->getUnmatchedTujuan() as $t) {
    $notes[] = "Tujuan tidak ada di master Pasuruan: {$t}";
}

if ($notes) {
    $redirect->with('not_found_list', $notes);
}

return $redirect;
    }

    // ============================================================
    // FILTER TERPUSAT
    // ============================================================
  private function applyPeriod($query, Request $request, string $col = 'tanggal_terima_po_pasuruan')
{
    if ($request->filled('date')) {
        $query->whereDate($col, $request->date);
    }

    $month = (string) $request->input('month', '');
    $year  = (string) $request->input('year', '');

    if (preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {      // dari <input type="month">
        $query->whereYear($col, $m[1])->whereMonth($col, $m[2]);
    } else {                                                   // angka 1-12 dari form lama
        if (ctype_digit($month) && (int) $month >= 1 && (int) $month <= 12) {
            $query->whereMonth($col, (int) $month);
        }
        if (ctype_digit($year)) {
            $query->whereYear($col, (int) $year);
        }
    }

    return $query;
}

// filter dasar: dipakai dashboard, halaman list KPI, dan AJAX tabel data
private function applyBaseFilter($query, Request $request)
{
    if ($request->filled('planner')) {
        $query->where('planner_pasuruan', $request->planner);
    }
    if ($request->filled('area')) {
        $query->where('area_pasuruan', $request->area);
    }
    if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
        $query->whereIn('area_pasuruan', self::PULAU_MAP[$request->pulau]);
    }
    if ($request->filled('dist_channel')) {
        $query->where('dist_channel_pasuruan', $request->dist_channel);
    }

    return $this->applyPeriod($query, $request);
}

private function applyFilter($query, Request $request)
{
    $this->applyBaseFilter($query, $request);

    if ($request->input('status') === 'in_transit') {
        $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $query->whereRaw($empty('tanggal_tiba_pasuruan'))
              ->whereRaw($filled('tanggal_keluar_gudang_pasuruan'));
    }

    $search = $request->input('search');
    if (is_string($search) && trim($search) !== '') {
        $searchValue = trim($search);
        $query->where(function ($q) use ($searchValue) {
            foreach ([
                'planner_pasuruan', 'no_shipment_pasuruan', 'tujuan_pasuruan', 'route_pasuruan',
                'pulau_pasuruan', 'area_pasuruan', 'via_kirim_pasuruan', 'dist_channel_pasuruan',
                'kategori_ekspedisi_pasuruan', 'ekspedisi_pasuruan', 'mobil_pasuruan',
            ] as $col) {
                $q->orWhere($col, 'like', "%{$searchValue}%");
            }
        });
    }

    return $query;
}

// kondisi tiap KPI: SATU definisi, dipakai dashboard (count) dan halaman list
private function applyKpi($q, string $kpi)
{
    switch ($kpi) {
        case 'gudang-ontime':
            $q->whereNotNull('tanggal_tiba_gudang_pasuruan');
            break;

        case 'gudang-delay':   // belum tiba gudang (mirror belumTibaGudangQuery versi Jakarta)
            $q->whereNotNull('rencana_kirim_pasuruan')
              ->whereRaw("TRIM(rencana_kirim_pasuruan) <> ''")
              ->whereNotNull('tanggal_dpt_unit_pasuruan')
              ->whereRaw("TRIM(tanggal_dpt_unit_pasuruan) <> ''")
              ->where(fn ($x) => $x->whereNull('tanggal_tiba_gudang_pasuruan')
                                   ->orWhereRaw("TRIM(tanggal_tiba_gudang_pasuruan) = ''"));
            break;

        case 'customer-ontime':
            $q->whereNotNull('tanggal_tiba_pasuruan')
              ->whereNotNull('estimasi_tiba_pasuruan')
              ->whereRaw('DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) <= 0');
            break;

        case 'customer-delay':
            $q->whereNotNull('tanggal_tiba_pasuruan')
              ->whereNotNull('estimasi_tiba_pasuruan')
              ->whereRaw('DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0');
            break;

        case 'bongkar-ontime':
            $q->whereNotNull('tanggal_bongkar_pasuruan')
              ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
              ->where(fn ($x) => $x->whereNull('overstay_days_pasuruan')
                                   ->orWhere('overstay_days_pasuruan', 0));
            break;

        case 'bongkar-delay':
            $q->whereNotNull('tanggal_bongkar_pasuruan')
              ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00')
              ->where('overstay_days_pasuruan', '>', 0);
            break;
    }

    return $q;
}

private function kpiLinksPasuruan(Request $request): array
{
    $f = array_filter(
        $request->only(['date', 'month', 'year', 'area', 'dist_channel', 'pulau']),
        fn ($v) => $v !== null && $v !== ''
    );

    return [
        'total'           => route('pasuruan.dataLogistik', $f),
        'in_transit'      => route('pasuruan.intransit', $f),
        'gudang_ontime'   => route('pasuruan.gudang.ontime', $f),
        'gudang_delay'    => route('pasuruan.gudang.delay', $f),
        'customer_ontime' => route('pasuruan.tujuan.ontime', $f),
        'customer_delay'  => route('pasuruan.tujuan.delay', $f),
        'bongkar_ontime'  => route('pasuruan.bongkar.ontime', $f),
        'bongkar_delay'   => route('pasuruan.bongkar.delay', $f),
    ];
}

    private function invalidatePlannerAreaCachePasuruan(): void
    {
        Cache::forget('pasuruan_list_planner_pasuruan');
        Cache::forget('pasuruan_list_area_pasuruan');
        Cache::forget('pasuruan_list_dist_channel_pasuruan');
    }

    public function archiveFiltered(Request $request)
    {
        $rows = $this->applyFilter(
            LogistikPengirimanPasuruan::query(),
            $request
        )->get();

        foreach ($rows as $row) {
            ShipmentPasuruanArchive::create($row->toArray());
            $row->delete();
        }

        Cache::forget('pasuruan_shipment_agg');

        return redirect()->back()->with(
            'success',
            $rows->count() . ' data berhasil diarsipkan.'
        );
    }

    public function deleteFiltered(Request $request)
    {
        $query = $this->applyFilter(
            LogistikPengirimanPasuruan::query(),
            $request
        );

        $count = $query->count();
        $query->delete();

        Cache::forget('pasuruan_shipment_agg');

        return redirect()->back()->with(
            'success',
            $count . ' data berhasil dihapus permanen.'
        );
    }

    // ============================================================
    // RECALC PER SHIPMENT
    // ============================================================

    /**
     * Hitung ulang CR (per baris, sesuai kontribusi nilai_muatan) dan
     * hasil kubik/tonase/pengiriman optimal (per shipment) untuk 1 no_shipment.
     * Return: CR baris terakhir.
     */
    private function refreshShipmentCalc(?string $noShipment): float
    {
        if (empty($noShipment)) {
            return 0;
        }

      $cr = $this->recalculateCr($noShipment);
$this->recalculateHasilKubikTonase($noShipment);
$this->recalculateOptimalCustomerPasuruan($noShipment);   // BARU

        Cache::forget('pasuruan_shipment_agg');

        return $cr;
    }

    // =====================================================
    // LOCK SHIPMENT (ANTI DEADLOCK)
    // Request lain untuk shipment yang sama antre, urutan lock
    // selalu sama (shipment ASC, id ASC) => tidak saling kunci.
    // Harus dipanggil DI DALAM DB::transaction().
    // =====================================================
    private function lockShipments(array $noShipments): void
    {
        $keys = array_values(array_unique(array_filter(
            array_map(fn($v) => trim((string) $v), $noShipments),
            fn($v) => $v !== ''
        )));
        sort($keys);

        foreach ($keys as $ns) {
            DB::table('logistik_pengiriman_pasuruan')
                ->where('no_shipment_pasuruan', $ns)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
        }
    }

    /** Subquery: 1 id wakil (MIN id) per shipment+tujuan dari query yang sudah terfilter */
private function groupIdsQueryPasuruan($query)
{
    return (clone $query)
        ->select(DB::raw('MIN(id) as id'))
        ->groupByRaw("COALESCE(NULLIF(TRIM(no_shipment_pasuruan),''), CONCAT('id', id)), COALESCE(tujuan_pasuruan,'')");
}
private function adminGroupAggPasuruan($rows): array
{
    $n = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";

    $shipments = $rows->pluck('no_shipment_pasuruan')
        ->filter(fn($v) => trim((string) $v) !== '')->unique()->values();

    if ($shipments->isEmpty()) return [];

    return LogistikPengirimanPasuruan::query()
        ->whereIn('no_shipment_pasuruan', $shipments)
        ->select(
            'no_shipment_pasuruan',
            DB::raw("COALESCE(tujuan_pasuruan,'') as tujuan_key"),
            DB::raw('COUNT(*) as jml_do'),
            DB::raw('SUM(' . $n('nilai_muatan_pasuruan') . ') as sum_nilai'),
            DB::raw('SUM(' . $n('total_kubik_pasuruan') . ') as sum_kubik'),
            DB::raw('SUM(' . $n('total_tonase_pasuruan') . ') as sum_tonase'),
            DB::raw('SUM(' . $n('total_do_pasuruan') . ') as sum_qty'),
            DB::raw('SUM(' . $n('kirim_kontribusi_pasuruan') . ') as sum_kontribusi'),

            // selisih / biaya kuli per grup (shipment + tujuan)
            DB::raw('SUM(' . $n('selisih_quantity_pasuruan') . ') as sum_selisih'),
            DB::raw("SUM(CASE WHEN NULLIF(TRIM(selisih_quantity_pasuruan),'') IS NOT NULL THEN 1 ELSE 0 END) as jml_selisih"),
            DB::raw('MAX(' . $n('biaya_kuli_pasuruan') . ') as max_kuli'),

            DB::raw('IF(SUM(' . $n('kirim_kontribusi_pasuruan') . ') > 0,
                        SUM(' . $n('biaya_kirim_pasuruan') . '),
                        MAX(' . $n('biaya_kirim_pasuruan') . ')) as sum_biaya')
        )
        ->groupBy('no_shipment_pasuruan', 'tujuan_key')
        ->get()
        ->keyBy(fn($g) => $g->no_shipment_pasuruan . '|' . $g->tujuan_key)
        ->all();
}

/**
 * Selisih, actual, dan biaya kuli dihitung per grup (shipment + tujuan):
 *   actual = total semua DO - total selisih
 * Hanya menimpa objek di memori (tidak disimpan ke DB).
 */
private function applyGroupQtyKuliPasuruan($r, $g): void
{
    $totalDo = (float) $g->sum_qty;
    $kuli    = (float) $g->max_kuli;

    $r->biaya_kuli_pasuruan = $kuli;

    if ((int) $g->jml_selisih > 0) {
        $sel    = (float) $g->sum_selisih;
        $actual = $totalDo - $sel;

        $r->selisih_quantity_pasuruan         = $sel != 0 ? $sel : null;
        $r->actual_delivery_quantity_pasuruan = floor($actual) == $actual ? (int) $actual : $actual;
        $r->total_biaya_kuli_pasuruan         = $actual * $kuli;
    } else {
        $r->selisih_quantity_pasuruan         = null;
        $r->actual_delivery_quantity_pasuruan = null;
        $r->total_biaya_kuli_pasuruan         = 0;
    }
}

private function countGroupsPasuruan($query): int
{
    return DB::query()->fromSub($this->groupIdsQueryPasuruan($query), 'g')->count();
}

/** Endpoint rincian DO per shipment + tujuan (dipanggil saat klik tujuan) */
public function dataLogistikDetailPasuruan(Request $request)
{
    $request->validate([
        'no_shipment' => 'required|string',
        'tujuan'      => 'nullable|string',
    ]);

    $q = LogistikPengirimanPasuruan::query()
        ->where('no_shipment_pasuruan', $request->no_shipment);

    $tujuan = (string) $request->tujuan;
    $tujuan !== ''
        ? $q->where('tujuan_pasuruan', $tujuan)
        : $q->where(fn($x) => $x->whereNull('tujuan_pasuruan')->orWhere('tujuan_pasuruan', ''));

    $fmtDate = fn($v) => $v ? date('d-m-Y', strtotime($v)) : '-';

    $data = $q->orderBy('sales_document_pasuruan')->orderBy('id')->get()->map(fn($r) => [
        'sales_document'       => $r->sales_document_pasuruan ?: '-',
        'customer_reference'   => $r->customer_reference_pasuruan ?: '-',
        'contract_end_date'    => $fmtDate($r->contract_end_date_pasuruan),
        'no_do'                => $r->no_do_pasuruan ?: '-',
        'no_delivery' => $r->no_delivery_pasuruan ?: '-',
        'qty'                  => $r->total_do_pasuruan,
       
        'nilai_muatan'         => 'Rp ' . number_format((float) $r->nilai_muatan_pasuruan, 0, ',', '.'),
        'kirim_kontribusi'     => $r->kirim_kontribusi_pasuruan !== null
            ? 'Rp ' . number_format((float) $r->kirim_kontribusi_pasuruan, 0, ',', '.') : '-',
        'kirim_kontribusi_raw' => (float) $r->kirim_kontribusi_pasuruan,
        'total_kubik'          => $this->formatDesimalPasuruan($r->total_kubik_pasuruan),
        'total_tonase'         => $this->formatDesimalPasuruan($r->total_tonase_pasuruan),
    ]);

    return response()->json(['data' => $data]);
}

    // =====================================================
    // CR per baris: SATU query UPDATE (bukan satu per baris)
    // =====================================================
    private function recalculateCr(string $noShipment): float
    {
        $rows = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $totalMuatan = (float) $rows->sum(function ($row) {
            return (float) $row->nilai_muatan_pasuruan;
        });

      $adaKontribusi = $rows->sum(fn($r) => (float) $r->kirim_kontribusi_pasuruan) > 0;

$biayaKirim = $adaKontribusi
    ? (float) $rows->sum(fn($r) => (float) $r->biaya_kirim_pasuruan)
    : (float) optional(
        $rows->filter(fn($row) => (float) $row->biaya_kirim_pasuruan > 0)
             ->sortByDesc('id')->first()
      )->biaya_kirim_pasuruan;
        $lastCr = 0;

        if ($totalMuatan > 0 && $biayaKirim > 0) {
            $totalCR = ($biayaKirim / $totalMuatan) * 100;

            // sprintf supaya angka tidak jadi notasi ilmiah (1.0E+6) di SQL
            $muatanSql = sprintf('%.4F', $totalMuatan);
            $crSql     = sprintf('%.8F', $totalCR);

            LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)
                ->update([
                    'cr_pasuruan' => DB::raw(
                        "ROUND(COALESCE(nilai_muatan_pasuruan, 0) / {$muatanSql} * {$crSql}, 4)"
                    ),
                ]);

            // CR baris terakhir (nilai balik untuk response)
            $last      = $rows->sortBy('id')->last();
            $nilaiLast = (float) $last->nilai_muatan_pasuruan;
            $lastCr    = $nilaiLast > 0 ? round(($nilaiLast / $totalMuatan) * $totalCR, 4) : 0;
        } else {
            LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)
                ->update(['cr_pasuruan' => 0]);
        }

        return $lastCr;
    }

    /**
     * hasil_kubik   = SUM(total_kubik per no_shipment)  / kubikasi * 100
     * hasil_tonase  = SUM(total_tonase per no_shipment) / tonase   * 100
     * optimal       = salah satu >= 85
     * Semua baris dalam satu no_shipment mendapat nilai yang sama.
     */
private function recalculateHasilKubikTonase(?string $noShipment): void
{
    if (empty($noShipment)) {
        return;
    }

    $n  = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";
    $tk = $n('total_kubik_pasuruan');
    $tt = $n('total_tonase_pasuruan');

    DB::update("
        UPDATE logistik_pengiriman_pasuruan lp
        JOIN (
            SELECT no_shipment_pasuruan AS ns,
                   SUM({$tk}) AS sum_kubik,
                   SUM({$tt}) AS sum_tonase,
                   MAX(CAST(NULLIF(TRIM(kubikasi_pasuruan), '') AS DECIMAL(18,4))) AS kubikasi,
                   MAX(CAST(NULLIF(TRIM(tonase_pasuruan), '')   AS DECIMAL(18,4))) AS tonase
            FROM logistik_pengiriman_pasuruan
            WHERE no_shipment_pasuruan = ?
            GROUP BY no_shipment_pasuruan
        ) s ON lp.no_shipment_pasuruan = s.ns
        JOIN (
            SELECT no_shipment_pasuruan AS ns,
                   COALESCE(tujuan_pasuruan, '') AS tj,
                   SUM({$tk}) AS t_kubik,
                   SUM({$tt}) AS t_tonase
            FROM logistik_pengiriman_pasuruan
            WHERE no_shipment_pasuruan = ?
            GROUP BY no_shipment_pasuruan, COALESCE(tujuan_pasuruan, '')
        ) t ON lp.no_shipment_pasuruan = t.ns
           AND COALESCE(lp.tujuan_pasuruan, '') = t.tj
        SET
            lp.hasil_kubik_pasuruan = CASE WHEN s.kubikasi > 0
                THEN ROUND(t.t_kubik / s.kubikasi * 100, 2) ELSE NULL END,
            lp.hasil_tonase_pasuruan = CASE WHEN s.tonase > 0
                THEN ROUND(t.t_tonase / s.tonase * 100, 2) ELSE NULL END,
            lp.pengiriman_optimal_pasuruan = CASE
                WHEN (s.kubikasi > 0 AND (s.sum_kubik  / s.kubikasi * 100) >= 85)
                  OR (s.tonase   > 0 AND (s.sum_tonase / s.tonase   * 100) >= 85) THEN 'OPTIMAL'
                WHEN s.kubikasi > 0 OR s.tonase > 0 THEN 'TIDAK OPTIMAL'
                ELSE NULL END
        WHERE lp.no_shipment_pasuruan = ?
    ", [$noShipment, $noShipment, $noShipment]);
}
    // =====================================================
  
    // =====================================================
// HALAMAN LIST KPI (nama method & route tetap)
// =====================================================
public function gudangOntimePasuruan(Request $r)  { return $this->kpiList($r, 'gudang-ontime',   'pasuruan.gudang_ontime'); }
public function gudangDelayPasuruan(Request $r)   { return $this->kpiList($r, 'gudang-delay',    'pasuruan.gudang_delay'); }
public function tujuanOntimePasuruan(Request $r)  { return $this->kpiList($r, 'customer-ontime', 'pasuruan.tujuan_ontime'); }
public function tujuanDelayPasuruan(Request $r)   { return $this->kpiList($r, 'customer-delay',  'pasuruan.tujuan_delay'); }
public function bongkarOntimePasuruan(Request $r) { return $this->kpiList($r, 'bongkar-ontime',  'pasuruan.bongkar_ontime'); }
public function bongkarDelayPasuruan(Request $r)  { return $this->kpiList($r, 'bongkar-delay',   'pasuruan.bongkar_delay'); }

private function kpiList(Request $request, string $kpi, string $view)
{
    // form lama di halaman list masih kirim bulan/tahun
    if ($request->filled('bulan') && !$request->filled('month')) {
        $request->merge(['month' => $request->bulan]);
    }
    if ($request->filled('tahun') && !$request->filled('year')) {
        $request->merge(['year' => $request->tahun]);
    }

    $query = DB::table('logistik_pengiriman_pasuruan');

    if (in_array($kpi, ['customer-ontime', 'customer-delay'], true)) {
        $query->selectRaw("
            logistik_pengiriman_pasuruan.*,
            estimasi_tiba_pasuruan AS tanggal_estimasi,
            CASE WHEN DATEDIFF(DATE(tanggal_tiba_pasuruan), DATE(estimasi_tiba_pasuruan)) > 0
                 THEN 'Delay' ELSE 'On Time' END AS sla_tiba
        ");
    } elseif (in_array($kpi, ['bongkar-ontime', 'bongkar-delay'], true)) {
        $query->selectRaw("
            logistik_pengiriman_pasuruan.*,
            CASE WHEN COALESCE(overstay_days_pasuruan, 0) > 0
                 THEN 'Delay' ELSE 'On Time' END AS sla_bongkar
        ");
    }

    $this->applyBaseFilter($query, $request);   // filter SAMA dengan dashboard
    $this->applyKpi($query, $kpi);              // kondisi SAMA dengan dashboard

    if ($request->filled('tanggal_bongkar')) {
        $query->whereDate('tanggal_bongkar_pasuruan', $request->tanggal_bongkar);
    }

    if ($kpi === 'customer-delay') {
        $query->orderBy('no_shipment_pasuruan')->orderBy('estimasi_tiba_pasuruan')->orderBy('id');
    } else {
        $query->orderByDesc(match ($kpi) {
            'gudang-ontime'   => 'tanggal_tiba_gudang_pasuruan',
            'gudang-delay'    => 'tanggal_terima_po_pasuruan',
            'customer-ontime' => 'tanggal_tiba_pasuruan',
            default           => 'tanggal_bongkar_pasuruan',
        });
    }

    $list = $query->get();

    $list_area = DB::table('logistik_pengiriman_pasuruan')
        ->select('area_pasuruan')
        ->whereNotNull('area_pasuruan')
        ->distinct()
        ->orderBy('area_pasuruan')
        ->get();

    return view($view, compact('list', 'list_area'));
}

    // public function 2ongkarDelayPasuruan(Request $request)
    // {
    //     $query = DB::table('logistik_pengiriman_pasuruan')
    //         ->where(function ($q) {
    //             $q->whereIn('sla_bongkar_pasuruan', ['Delay', 'Critical Delay'])
    //                 ->orWhere('overstay_days_pasuruan', '>', 0);
    //         })
    //         ->whereNotNull('tanggal_bongkar_pasuruan')
    //         ->where('tanggal_bongkar_pasuruan', '!=', '1899-12-31 00:00:00');

    //     if ($request->filled('tanggal_bongkar')) {
    //         $query->whereDate('tanggal_bongkar_pasuruan', $request->tanggal_bongkar);
    //     }

    //     if ($request->filled('area')) {
    //         $query->where('area_pasuruan', $request->area);
    //     }

    //     $list = $query->orderByDesc('tanggal_bongkar_pasuruan')->get();

    //     return view('pasuruan.bongkar_delay', compact('list'));
    // }

    public function updateTransportLaut(Request $request)
    {
        LogistikPengirimanPasuruan::where(
            'no_shipment_pasuruan',
            $request->no_shipment_pasuruan
        )->update([
            'nama_kapal_pasuruan' => $request->nama_kapal_pasuruan,
            'etd_pasuruan'        => $request->etd_pasuruan,
            'eta_pasuruan'        => $request->eta_pasuruan,
            'atd_pasuruan'        => $request->atd_pasuruan,
            'ata_pasuruan'        => $request->ata_pasuruan,
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

    // =====================================================
    // DASHBOARD
    // =====================================================
    public function dashboard(Request $request)
    {
      $base = DB::table('logistik_pengiriman_pasuruan');
$this->applyBaseFilter($base, $request);

$total_data = (clone $base)->count();     // jumlah baris DO, tetap jadi penyebut persentase
$total_shipment = $this->countGroupsPasuruan(
    $this->applyBaseFilter(LogistikPengirimanPasuruan::query(), $request)
);
$cnt = fn (string $kpi) => $this->applyKpi(clone $base, $kpi)->count();

$gudang_ontime   = $cnt('gudang-ontime');
$gudang_delay    = $cnt('gudang-delay');
$customer_ontime = $cnt('customer-ontime');
$customer_delay  = $cnt('customer-delay');
$bongkar_ontime  = $cnt('bongkar-ontime');
$bongkar_delay   = $cnt('bongkar-delay');
        // ================= ARMADA =================
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

       $list_dist_channel = DB::table('logistik_pengiriman_pasuruan')
    ->select('dist_channel_pasuruan')
    ->whereNotNull('dist_channel_pasuruan')
    ->where('dist_channel_pasuruan', '!=', '')
    ->distinct()
    ->orderBy('dist_channel_pasuruan')
    ->get();


        // ================= PLANNER =================
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
        $totalNilaiMuatan = (clone $base)->sum('nilai_muatan_pasuruan');

        $totalBiayaKirim = (clone $base)
            ->selectRaw("SUM(biaya_kirim_pasuruan) as total")
            ->value('total');

        // ================= SUMMARY AREA =================
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

        $ontime_rate = $total_status > 0 ? ($planner_ontime / $total_status) * 100 : 0;
        $delay_rate  = $total_status > 0 ? ($planner_delay / $total_status) * 100 : 0;

        $total_armada = $planner_armada + $planner_belum_armada;

        $armada_rate   = $total_armada > 0 ? ($planner_armada / $total_armada) * 100 : 0;
        $pending_rate  = $total_armada > 0 ? ($planner_belum_armada / $total_armada) * 100 : 0;

        // ================= MONITORING =================
        $summary_monitoring = [
            'tiba_ontime'    => $total_data > 0 ? ($customer_ontime / $total_data) * 100 : 0,
            'tiba_delay'     => $total_data > 0 ? ($customer_delay / $total_data) * 100 : 0,
            'bongkar_ontime' => $total_data > 0 ? ($bongkar_ontime / $total_data) * 100 : 0,
            'bongkar_delay'  => $total_data > 0 ? ($bongkar_delay / $total_data) * 100 : 0,
        ];

      $list_area = DB::table('logistik_pengiriman_pasuruan')
    ->select('area_pasuruan as area')
    ->whereNotNull('area_pasuruan')
    ->where('area_pasuruan', '!=', '')
    ->groupBy('area_pasuruan')
    ->orderBy('area_pasuruan')
    ->get();
    $kpiLinks = $this->kpiLinksPasuruan($request);

        $reqInTransit = $request->duplicate();
        $reqInTransit->merge(['status' => 'in_transit']);

        $total_in_transit = $this->applyFilter(
            DB::table('logistik_pengiriman_pasuruan'),
            $reqInTransit
        )->count();

        return view('pasuruan.dashboard', compact(
            'total_data',

            'gudang_ontime',
            'gudang_delay',
            'total_shipment',      // BARU
    'kpiLinks',     
            'total_in_transit',

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

    // getArea() sengaja mengambil dari tabel 'logistik_pengiriman'
    // (master area, bukan tabel _pasuruan).
    private function getArea()
    {
        return DB::table('logistik_pengiriman')
            ->select('area')
            ->whereNotNull('area')
            ->groupBy('area')
            ->orderBy('area')
            ->get();
    }

    public function archiveAll()
    {
        LogistikPengirimanPasuruan::truncate();

        Cache::forget('pasuruan_shipment_agg');

        return redirect()
            ->route('pasuruan.dataLogistik')
            ->with('success', 'Semua data Pasuruan berhasil dihapus.');
    }

    // =====================================================
    // DATA LOGISTIK (READ-ONLY DISPLAY)
    // =====================================================
    public function dataLogistik()
    {
        $planners     = $this->cachedListPasuruan('planner_pasuruan');
        $areas        = $this->cachedListPasuruan('area_pasuruan');
        $distChannels = $this->cachedListPasuruan('dist_channel_pasuruan');

        $activeFilters = [
            'area'         => request('area'),
            'pulau'        => request('pulau'),
            'dist_channel' => request('dist_channel'),
            'date'         => request('date'),
            'month'        => request('month'),
            'year'         => request('year'),
        ];

        return view('pasuruan.data_logistik', compact('planners', 'areas', 'distChannels', 'activeFilters'));
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
     * DATA LOGISTIK — ENDPOINT AJAX (SERVER-SIDE, READ-ONLY)
     * ========================================================= */
 public function dataLogistikAjaxPasuruan(Request $request)
{
    $query = LogistikPengirimanPasuruan::query();
    $this->applyBaseFilter($query, $request);

    // jumlah GRUP (shipment + tujuan), bukan jumlah DO
    $recordsTotal = $this->countGroupsPasuruan($query);
    $searchValue  = trim((string) $request->input('search.value'));

    if ($searchValue !== '') {
        $query->where(function ($q) use ($searchValue) {
            foreach ([
                'planner_pasuruan', 'no_shipment_pasuruan', 'tujuan_pasuruan', 'route_pasuruan',
                'pulau_pasuruan', 'area_pasuruan', 'via_kirim_pasuruan', 'dist_channel_pasuruan',
                'kategori_ekspedisi_pasuruan', 'ekspedisi_pasuruan', 'customer_reference_pasuruan',
                'sales_document_pasuruan', 'no_do_pasuruan', 'no_delivery_pasuruan', 'mobil_pasuruan',
            ] as $col) {
                $q->orWhere($col, 'like', "%{$searchValue}%");
            }
        });
    }

    $recordsFiltered = $this->countGroupsPasuruan($query);

    // INI YANG MENGGABUNGKAN: 1 baris wakil per shipment + tujuan
    $query = LogistikPengirimanPasuruan::query()
        ->whereIn('id', $this->groupIdsQueryPasuruan($query));

    $orderableColumns = [
        0  => 'tanggal_terima_po_pasuruan',
        1  => 'rencana_kirim_pasuruan',
        2  => 'transport_lead_time_pasuruan',
        3  => 'planner_pasuruan',
        4  => 'no_shipment_pasuruan',
        7  => 'tujuan_pasuruan',
        8  => 'area_pasuruan',
        10 => 'mobil_pasuruan',
        11 => 'total_do_pasuruan',
        12 => 'nilai_muatan_pasuruan',
        13 => 'biaya_kirim_pasuruan',
    ];

    $orderColIndex = (int) $request->input('order.0.column', 4);
    $orderDir      = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
    $orderColumn   = $orderableColumns[$orderColIndex] ?? 'no_shipment_pasuruan';

    if ($orderColumn === 'no_shipment_pasuruan') {
        $query->orderByRaw("CAST(no_shipment_pasuruan AS UNSIGNED) {$orderDir}")
              ->orderBy('no_shipment_pasuruan', $orderDir);
    } else {
        $query->orderBy($orderColumn, $orderDir)
              ->orderByRaw('CAST(no_shipment_pasuruan AS UNSIGNED) ASC')
              ->orderBy('no_shipment_pasuruan');
    }
    $query->orderBy('tujuan_pasuruan')->orderBy('id');

    $start  = max(0, (int) $request->input('start', 0));
    $length = (int) $request->input('length', 25);
    $length = $length > 0 ? min($length, 200) : $recordsFiltered;

    $rows = $query->skip($start)->take($length)->get();

    // penjumlahan per shipment + tujuan (nilai, kontribusi, kubik, tonase, qty)
    $groupAgg = $this->adminGroupAggPasuruan($rows);
    $agg      = Cache::remember('pasuruan_shipment_agg', 300, fn() => $this->shipmentAggregatesPasuruan());

    $data = $rows->map(function ($r) use ($agg, $groupAgg) {

        $g = $groupAgg[$r->no_shipment_pasuruan . '|' . ($r->tujuan_pasuruan ?? '')] ?? null;

        if ($g && trim((string) $r->no_shipment_pasuruan) !== '') {
            $r->nilai_muatan_pasuruan     = $g->sum_nilai;
            $r->total_kubik_pasuruan      = $g->sum_kubik;
            $r->total_tonase_pasuruan     = $g->sum_tonase;
            $r->kirim_kontribusi_pasuruan = $g->sum_kontribusi;
            $r->biaya_kirim_pasuruan      = (float) $g->sum_kontribusi > 0 ? $g->sum_kontribusi : $g->sum_biaya;

            $q = (float) $g->sum_qty;
            $r->total_do_pasuruan = floor($q) == $q ? (int) $q : $q;

            // actual / selisih / biaya kuli per grup (shipment + tujuan)
            $this->applyGroupQtyKuliPasuruan($r, $g);
        }

        $lamaSla = $this->computeLamaPencarianDanSla($r);

        return [
            'tanggal_naik_fmt'  => $r->tanggal_terima_po_pasuruan ? date('d-m-Y', strtotime($r->tanggal_terima_po_pasuruan)) : '-',
            'rencana_kirim_fmt' => $r->rencana_kirim_pasuruan ? date('d-m-Y', strtotime($r->rencana_kirim_pasuruan)) : '-',
            'lead_time'         => $r->transport_lead_time_pasuruan,
            'planner'           => $r->planner_pasuruan,
            'kubikasi_pasuruan' => $r->kubikasi_pasuruan !== null ? number_format((float) $r->kubikasi_pasuruan, 2, ',', '.') . '%' : '-',
            'tonase_pasuruan'   => $r->tonase_pasuruan !== null ? number_format((float) $r->tonase_pasuruan, 2, ',', '.') . '%' : '-',
            'total_kubik_fmt'   => $this->formatDesimalPasuruan($r->total_kubik_pasuruan ?? null),
            'total_tonase_fmt'  => $this->formatDesimalPasuruan($r->total_tonase_pasuruan ?? null),
            'hasil_kubik_fmt'   => $this->formatHasilPersenPasuruan($r->hasil_kubik_pasuruan !== null ? (float) $r->hasil_kubik_pasuruan : null),
            'hasil_tonase_fmt'  => $this->formatHasilPersenPasuruan($r->hasil_tonase_pasuruan !== null ? (float) $r->hasil_tonase_pasuruan : null),
            'pengiriman_optimal_badge' => $this->badgePengirimanOptimalPasuruan($r),
            'reason_optimal'    => $r->reason_pengiriman_optimal_pasuruan,

            'no_shipment'       => $r->no_shipment_pasuruan,
            'tujuan'            => $r->tujuan_pasuruan,
            'tujuan_html'       => $r->no_shipment_pasuruan
                ? '<a href="#" class="btn-tujuan" data-shipment="' . e($r->no_shipment_pasuruan)
                    . '" data-tujuan="' . e($r->tujuan_pasuruan) . '">▶ '
                    . e($r->tujuan_pasuruan)
                    . ($g && $g->jml_do > 1 ? ' <span class="badge gray">' . $g->jml_do . ' DO</span>' : '')
                    . '</a>'
                : e($r->tujuan_pasuruan),
            'posisi_mobil_badge' => $this->badgeStatusPosisiMobilPasuruan($r),
            'dist_channel_badge' => $this->badgeDistChannelPasuruan($r->dist_channel_pasuruan),
            'area'               => $r->area_pasuruan,
            'ketersediaan_badge' => $this->badgeKetersediaanUnitPasuruan($r),
            'mobil'              => $r->mobil_pasuruan,
            'delivery_qty'       => $r->total_do_pasuruan,
            'nilai_muatan_fmt'   => 'Rp ' . number_format((float) $r->nilai_muatan_pasuruan, 0, ',', '.'),
            'biaya_kirim_fmt'    => 'Rp ' . number_format((float) $r->biaya_kirim_pasuruan, 0, ',', '.'),
            'cr_fmt'             => $this->formatCR(
                (float) $r->nilai_muatan_pasuruan > 0
                    ? ((float) $r->biaya_kirim_pasuruan / (float) $r->nilai_muatan_pasuruan) * 100
                    : 0
            ),
            'kategori_ekspedisi_badge' => $this->badgeKategoriEkspedisiPasuruan($r->kategori_ekspedisi_pasuruan),
            'ekspedisi'          => $r->ekspedisi_pasuruan,
            'tanggal_dpt_fmt'    => $r->tanggal_dpt_unit_pasuruan ? date('d-m-Y', strtotime($r->tanggal_dpt_unit_pasuruan)) : '-',
            'lama_pencarian'     => $lamaSla['lama'],
            'sla_dapat_mobil_badge' => $lamaSla['sla_badge'],
            'planning_loading_fmt'  => $r->planning_loading_pasuruan ? date('d-m-Y', strtotime($r->planning_loading_pasuruan)) : '-',
            'tiba_gudang_fmt'       => $r->tanggal_tiba_gudang_pasuruan ? date('d-m-Y', strtotime($r->tanggal_tiba_gudang_pasuruan)) : '-',
            'keluar_gudang_fmt'     => $r->tanggal_keluar_gudang_pasuruan ? date('d-m-Y', strtotime($r->tanggal_keluar_gudang_pasuruan)) : '-',
            'pic_monitoring'        => $r->pic_monitoring_pasuruan,
            'nama_kapal'            => $r->nama_kapal_pasuruan,
            'etd'                   => $r->etd_pasuruan,
            'eta'                   => $r->eta_pasuruan,
            'alert_badge'           => $this->badgeAlertPasuruan($r),
            'urutan_bongkar'        => $r->act_urutan_bongkar_pasuruan,
            'actual_delivery_qty'   => $r->actual_delivery_quantity_pasuruan,
            'selisih_qty_badge'     => $this->badgeSelisihQtyPasuruan($r),
            'reason_selisih_qty'    => $r->reason_selisih_quantity_pasuruan,
            'act_pgi_fmt'           => $r->act_pgi_date_pasuruan ? date('d-m-Y', strtotime($r->act_pgi_date_pasuruan)) : '-',
            'atd_fmt'               => $r->atd_pasuruan ? date('d-m-Y', strtotime($r->atd_pasuruan)) : '-',
            'ata_fmt'               => $r->ata_pasuruan ? date('d-m-Y', strtotime($r->ata_pasuruan)) : '-',
            'estimasi_fmt'          => $r->estimasi_tiba_pasuruan ? date('d-m-Y', strtotime($r->estimasi_tiba_pasuruan)) : '-',
            'tiba_fmt'              => $r->tanggal_tiba_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_tiba_pasuruan)) : '-',
            'lama_perjalanan'       => $r->lama_perjalanan_pasuruan ?? '-',
            'sla_tiba_badge'        => $this->badgeSlaGeneric($r->sla_tiba_pasuruan),
            'bongkar_fmt'           => $r->tanggal_bongkar_pasuruan ? date('d-m-Y h:i A', strtotime($r->tanggal_bongkar_pasuruan)) : '-',
            'status_bongkar_badge'  => $this->badgeStatusBongkarPasuruan($r),
            'overstay_badge'        => $this->badgeOverstayPasuruan($r),
            'sla_bongkar_badge'     => $this->badgeSlaBongkarPasuruan($r),
            'reason_tiba'           => $r->reason_waktu_tiba_pasuruan,
            'reason_bongkar'        => $r->reason_waktu_bongkar_pasuruan,
            'status_akhir_badge'    => $this->badgeStatusAkhirPasuruan($r),
            'status_alert_badge'    => $this->badgeStatusAlertPasuruan($r),
            'remarks'               => $r->remarks_pasuruan,
            'route'                 => $r->route_pasuruan,
            'shipping_point'        => $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-',
            'pulau'                 => $r->pulau_pasuruan,
            'via_kirim'             => $r->via_kirim_pasuruan,
            'kirim_kontribusi_fmt'  => $r->kirim_kontribusi_pasuruan !== null
                ? 'Rp ' . number_format((float) $r->kirim_kontribusi_pasuruan, 0, ',', '.') : '-',
            'pengiriman_optimal_customer_badge' => $this->badgePengirimanOptimalCustomerPasuruan($r),
            'reason_optimal_customer'           => $r->reason_pengiriman_optimal_customer_pasuruan ?: '-',
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
DB::raw("IF(SUM(COALESCE(CAST(NULLIF(TRIM(kirim_kontribusi_pasuruan),'') AS DECIMAL(18,4)),0)) > 0,
            SUM(COALESCE(CAST(NULLIF(TRIM(biaya_kirim_pasuruan),'') AS DECIMAL(18,4)),0)),
            MAX(biaya_kirim_pasuruan)) as total_biaya")
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

    /**
 * Optimal PER CUSTOMER (per baris): baca kolom pengiriman_optimal_customer_pasuruan
 * (dihitung oleh recalculateOptimalCustomerPasuruan).
 */
private function badgePengirimanOptimalCustomerPasuruan($r): string
{
    $v = strtoupper(trim((string) ($r->pengiriman_optimal_customer_pasuruan ?? '')));

    if ($v === '') return '<span class="badge gray">-</span>';

    return $v === 'OPTIMAL'
        ? '<span class="badge green">✅ Optimal</span>'
        : '<span class="badge orange">⚠️ Tidak Optimal</span>';
}

/**
 * Optimal per customer (per baris): total_kubik / kubikasi * 100 >= 85
 * ATAU total_tonase / tonase * 100 >= 85. Tidak digabung dengan baris lain.
 */
private function recalculateOptimalCustomerPasuruan(?string $noShipment): void
{
    if (empty($noShipment)) {
        return;
    }

    $n   = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";
    $kub = $n('kubikasi_pasuruan');
    $ton = $n('tonase_pasuruan');
    $tk  = $n('total_kubik_pasuruan');
    $tt  = $n('total_tonase_pasuruan');

   DB::update("
    UPDATE logistik_pengiriman_pasuruan lp
    JOIN (
        SELECT no_shipment_pasuruan AS ns,
               COALESCE(tujuan_pasuruan, '') AS tj,
               SUM({$tk})  AS sum_kubik,
               SUM({$tt})  AS sum_tonase,
               MAX({$kub}) AS kubikasi,
               MAX({$ton}) AS tonase
        FROM logistik_pengiriman_pasuruan
        WHERE no_shipment_pasuruan = ?
        GROUP BY no_shipment_pasuruan, COALESCE(tujuan_pasuruan, '')
    ) x ON lp.no_shipment_pasuruan = x.ns
       AND COALESCE(lp.tujuan_pasuruan, '') = x.tj
    SET lp.pengiriman_optimal_customer_pasuruan = CASE
        WHEN x.kubikasi <= 0 AND x.tonase <= 0 THEN NULL
        WHEN (x.kubikasi > 0 AND x.sum_kubik  / x.kubikasi * 100 >= 85)
          OR (x.tonase   > 0 AND x.sum_tonase / x.tonase   * 100 >= 85) THEN 'OPTIMAL'
        ELSE 'TIDAK OPTIMAL'
    END
    WHERE lp.no_shipment_pasuruan = ?
", [$noShipment, $noShipment]);
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
     * HELPER — FORMAT & BADGE
     * ========================================================= */
private function formatDesimalPasuruan($value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '-';
    }
    return number_format((float) $value, 2, ',', '.');
}

    private function formatHasilPersenPasuruan(?float $value): string
    {
        if ($value === null) {
            return '-';
        }
        return number_format($value, 2, ',', '.') . '%';
    }

    /**
     * Badge Pengiriman Optimal: baca kolom pengiriman_optimal_pasuruan
     * (dihitung per shipment oleh recalculateHasilKubikTonase / import).
     */
    private function badgePengirimanOptimalPasuruan($r): string
    {
        $v = strtoupper(trim((string) ($r->pengiriman_optimal_pasuruan ?? '')));

        if ($v === '') return '<span class="badge gray">-</span>';

        return $v === 'OPTIMAL'
            ? '<span class="badge green">✅ Optimal</span>'
            : '<span class="badge orange">⚠️ Belum Optimal</span>';
    }

    private function badgeStatusPosisiMobilPasuruan($r): string
    {
        $dpt           = $r->tanggal_dpt_unit_pasuruan;
        $tibaGudang    = $r->tanggal_tiba_gudang_pasuruan;
        $keluarGudang  = $r->tanggal_keluar_gudang_pasuruan;
        $tibaTujuan    = $r->tanggal_tiba_pasuruan;
        $bongkarTujuan = $r->tanggal_bongkar_pasuruan;

        if (empty($dpt)) {
            $status = 'MENCARI UNIT';
            $badge  = 'red';
        } elseif (empty($tibaGudang)) {
            $status = 'PERJALANAN KE GUDANG';
            $badge  = 'orange';
        } elseif (!empty($tibaGudang) && empty($keluarGudang)) {
            $status = 'DI GUDANG';
            $badge  = 'blue';
        } elseif (!empty($keluarGudang) && empty($tibaTujuan)) {
            $status = 'PERJALANAN KE TUJUAN';
            $badge  = 'yellow';
        } elseif (!empty($tibaTujuan) && empty($bongkarTujuan)) {
            $status = 'TIBA DI TUJUAN';
            $badge  = 'success';
        } elseif (!empty($tibaTujuan) && !empty($bongkarTujuan)) {
            $status = 'SUDAH SELESAI';
            $badge  = 'green';
        } else {
            $status = '-';
            $badge  = 'gray';
        }

        return '<span class="badge ' . $badge . '">' . e($status) . '</span>';
    }

    private function badgeDistChannelPasuruan($channel): string
    {
        $channel    = trim((string) $channel);
        $classes    = ['badge-green', 'badge-blue', 'badge-orange', 'badge-red', 'badge-purple', 'badge-pink', 'badge-cyan', 'badge-yellow'];
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
        $sla  = null;

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
        $today    = strtotime(date('Y-m-d'));
        $sisa     = floor(($estimasi - $today) / 86400);

        if ($sisa < 0) return '<span class="badge badge-danger">OVERDUE</span>';
        if ($sisa == 0) return '<span class="badge badge-danger">H-0</span>';
        if ($sisa == 1) return '<span class="badge badge-danger">H-1</span>';
        if ($sisa == 2 || $sisa == 3) return '<span class="badge badge-warning">H-' . $sisa . '</span>';
        if ($sisa <= 7) return '<span class="badge badge-info">H-' . $sisa . '</span>';

        return '<span class="badge badge-success">ON TRACK</span>';
    }

    private function badgeSelisihQtyPasuruan($r): string
    {
        $totalDo    = is_numeric($r->total_do_pasuruan) ? (float) $r->total_do_pasuruan : 0;
        $actualRaw  = $r->actual_delivery_quantity_pasuruan;
        $belumDiisi = ($actualRaw === null || $actualRaw === '' || (float) $actualRaw == 0);

        if ($belumDiisi) {
            return '<span class="badge badge-secondary">-</span>';
        }

        $actual  = (float) $actualRaw;
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
            $tiba    = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $today   = strtotime(date('Y-m-d'));
            $selisih = max(0, floor(($today - $tiba) / 86400));
            $class   = $selisih == 0 ? 'orange' : 'red';

            return '<span class="badge status-bongkar ' . $class . '">H+' . $selisih . '</span>';
        }

        return '<span class="badge status-bongkar gray">-</span>';
    }

    private function badgeOverstayPasuruan($r): string
    {
        if (empty($r->tanggal_tiba_pasuruan) || empty($r->tanggal_bongkar_pasuruan)) {
            return '<span class="badge gray">-</span>';
        }

        $tiba     = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
        $bongkar  = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
        $overstay = max(0, floor(($bongkar - $tiba) / 86400));
        $text     = $overstay == 0 ? '0 Hari' : 'H+' . $overstay . ' Hari';
        $class    = $overstay == 0 ? 'green' : 'red';

        return '<span class="badge ' . $class . '">' . $text . '</span>';
    }

    private function badgeSlaBongkarPasuruan($r): string
    {
        if (empty($r->tanggal_tiba_pasuruan) || empty($r->tanggal_bongkar_pasuruan)) {
            return '<span class="badge gray">-</span>';
        }

        $tiba    = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
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
        $slaTiba    = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
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
        $slaTiba    = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
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

    // =====================================================
    // INPUT CLEANING
    // =====================================================
    private function cleanDesimalPasuruan($value): ?float
    {
        if ($value === null || $value === '' || $value === '-') return null;

        $value = str_replace(['%', ' ', "\xc2\xa0"], '', (string) $value);

        // "1.234,56" (format ID) -> "1234.56"
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9.]/', '', $value);

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * Bersihkan 4 input kapasitas/muatan (kubikasi, tonase, total kubik,
     * total tonase). Hasil % & status optimal TIDAK dihitung di sini,
     * melainkan per shipment lewat recalculateHasilKubikTonase().
     */
    private function applyKubikTonasePasuruan(array &$data, $logistik = null): void
    {
        foreach (['kubikasi_pasuruan', 'tonase_pasuruan', 'total_kubik_pasuruan', 'total_tonase_pasuruan'] as $f) {
            if (array_key_exists($f, $data)) {
                $data[$f] = $this->cleanDesimalPasuruan($data[$f]);
            }
        }
    }

    private function parseRupiah($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9]/', '', (string) $value);

        return $clean === '' ? null : (float) $clean;
    }

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

    // =====================================================
    // CRUD
    // =====================================================
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

        $routeOptions     = $tarifPengiriman->pluck('route')->filter()->unique()->sort()->values();
        $mobilOptions     = $tarifPengiriman->pluck('mobil')->filter()->unique()->sort()->values();
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
            'planner_pasuruan'               => 'nullable|string|max:100',
            'no_shipment_pasuruan'           => 'nullable|string|max:50',
            'kubikasi_pasuruan'              => 'nullable|string',
            'tonase_pasuruan'                => 'nullable|string',
            'total_kubik_pasuruan'           => 'nullable|string',
            'total_tonase_pasuruan'          => 'nullable|string',
            'dist_channel_pasuruan'          => 'nullable|string|max:100',
            'transport_lead_time_pasuruan'   => 'nullable|numeric',
            'tujuan_pasuruan'                => 'nullable|string|max:150',
            'area_pasuruan'                  => 'nullable|string|max:100',
            'route_pasuruan'                 => 'nullable|string|max:150',
            'mobil_pasuruan'                 => 'nullable|string|max:100',
            'nilai_muatan_pasuruan'          => 'nullable|string',
            'biaya_kirim_pasuruan'           => 'nullable|string',
            'kategori_ekspedisi_pasuruan'    => 'nullable|string|max:100',
            'ekspedisi_pasuruan'             => 'nullable|string|max:100',
            'tanggal_terima_po_pasuruan'     => 'nullable|date',
            'rencana_kirim_pasuruan'         => 'nullable|date',
            'tanggal_dpt_unit_pasuruan'      => 'nullable|date',
            'biaya_kuli_pasuruan'            => 'nullable|string',

            'tanggal_tiba_gudang_pasuruan'   => 'nullable|date',
            'planning_loading_pasuruan'      => 'nullable|date',
            'tanggal_keluar_gudang_pasuruan' => 'nullable|date',
            'keterangan_pasuruan'            => 'nullable|string',
            'create_tgl_pasuruan'            => 'nullable|date',
        ]);

        $validated['nilai_muatan_pasuruan'] = $this->parseRupiah($validated['nilai_muatan_pasuruan'] ?? null);
        $validated['biaya_kuli_pasuruan']   = $this->parseRupiah($validated['biaya_kuli_pasuruan'] ?? null);
        $this->applyKubikTonasePasuruan($validated);

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
        unset($validated['keterangan_pasuruan']);

        $logistik = LogistikPengirimanPasuruan::create($validated);

        $this->refreshShipmentCalc($logistik->no_shipment_pasuruan);
        $this->invalidatePlannerAreaCachePasuruan();

        return redirect()
            ->route('pasuruan.admin')
            ->with('success', 'Data berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        return view('pasuruan.edit', compact('logistik'));
    }

    // =====================================================
    // UPDATE (form biasa / ajax) - anti deadlock
    // =====================================================
    public function update(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {

            [$logistik, $cr] = $this->saveRowCore($request, $id, true);

            $this->invalidatePlannerAreaCachePasuruan();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Data berhasil diupdate.',
                    'cr'      => $cr,
                ]);
            }

            return redirect()->route('pasuruan.admin')
                ->with('success', 'Data berhasil diupdate.');
        }, 5); // retry otomatis 5x kalau tetap kena deadlock
    }

    // =====================================================
    // AUTOSAVE - anti deadlock
    // =====================================================
    public function autosaveRow(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {

            [$logistik, $cr] = $this->saveRowCore($request, $id, false);

            return response()->json([
                'message' => 'Auto-save berhasil.',
                'id'      => $logistik->id,
                'cr'      => $cr,
            ]);
        }, 5);
    }

    // =====================================================
    // LOGIKA SIMPAN BERSAMA (update + autosave)
    // $withEstimasi = true  -> sebar field shipment + hitung ulang
    //                          estimasi tiba per shipment (khusus update)
    // return: [$logistik, $cr]
    // =====================================================
    private function saveRowCore(Request $request, $id, bool $withEstimasi): array
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        $oldShipmentNo = $logistik->no_shipment_pasuruan;

        // ---- kunci dulu semua baris shipment terkait ----
        $this->lockShipments([$oldShipmentNo, $request->input('no_shipment_pasuruan')]);

        // baca ulang setelah lock (data bisa berubah saat menunggu antrean)
        $logistik->refresh();

        $data = $request->only($this->fillableFields);

        // hasil jumlahan grup: tidak boleh ditulis balik dari grid
        unset(
            $data['nilai_muatan_pasuruan'],
            $data['total_kubik_pasuruan'],
            $data['total_tonase_pasuruan'],
            $data['total_do_pasuruan'],
            $data['biaya_kirim_pasuruan'],
            $data['kirim_kontribusi_pasuruan']
        );

        // ---- selisih / actual per GRUP (shipment + tujuan), bukan per DO ----
        //   actual = total semua DO dalam grup - selisih
        //   selisih & actual hanya disimpan di baris yang diedit, DO lain dikosongkan
        $groupTujuan = (string) $logistik->tujuan_pasuruan;

        $groupTotalDo = (float) $logistik->total_do_pasuruan;
        if (!empty($oldShipmentNo)) {
            $groupTotalDo = (float) LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $oldShipmentNo)
                ->whereRaw("COALESCE(tujuan_pasuruan,'') = ?", [$groupTujuan])
                ->sum(DB::raw("COALESCE(CAST(NULLIF(TRIM(total_do_pasuruan),'') AS DECIMAL(18,4)),0)"));
        }

        if (array_key_exists('selisih_quantity_pasuruan', $data)) {
            $selisihQty = $data['selisih_quantity_pasuruan'];
        } else {
            // field tidak dikirim (mis. autosave parsial): pakai total selisih grup yang sudah ada
            $selisihQty = null;
            if (!empty($oldShipmentNo)) {
                $existing = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $oldShipmentNo)
                    ->whereRaw("COALESCE(tujuan_pasuruan,'') = ?", [$groupTujuan])
                    ->whereNotNull('selisih_quantity_pasuruan')
                    ->whereRaw("TRIM(selisih_quantity_pasuruan) <> ''")
                    ->selectRaw("COUNT(*) as jml, SUM(CAST(selisih_quantity_pasuruan AS DECIMAL(18,4))) as total")
                    ->first();

                if ($existing && (int) $existing->jml > 0) {
                    $selisihQty = (float) $existing->total;
                }
            }
        }

        if ($selisihQty !== null && $selisihQty !== '') {
            $data['selisih_quantity_pasuruan']         = (float) $selisihQty;
            $data['actual_delivery_quantity_pasuruan'] = $groupTotalDo - (float) $selisihQty;
        } else {
            $data['selisih_quantity_pasuruan']         = null;
            $data['actual_delivery_quantity_pasuruan'] = null;
        }

        $actualQtyForKuli = $data['actual_delivery_quantity_pasuruan'];
        $biayaKuli        = $data['biaya_kuli_pasuruan'] ?? $logistik->biaya_kuli_pasuruan;

        $data['biaya_kuli_pasuruan']       = $this->parseRupiah($biayaKuli);
        $data['total_biaya_kuli_pasuruan'] = ((float) $actualQtyForKuli) * ((float) $data['biaya_kuli_pasuruan']);

        if (array_key_exists('nilai_muatan_pasuruan', $data)) {
            $data['nilai_muatan_pasuruan'] = $this->parseRupiah($data['nilai_muatan_pasuruan']);
        }

        $this->applyKubikTonasePasuruan($data, $logistik);

        $this->generateMonitoringPasuruan($data);

        // ---- field level-shipment: disebarkan ke semua baris dengan no_shipment yang sama (khusus update) ----
        if ($withEstimasi) {
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

            $shipmentData = array_intersect_key($data, array_flip($shipmentFields));

            if (!empty($shipmentData) && !empty($oldShipmentNo)) {
                LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $oldShipmentNo)
                    ->update($shipmentData);
            }
        }

        // ---- field level-grup (shipment + tujuan) ----
        // selisih/actual/total kuli TIDAK disebar: disimpan di 1 baris saja (lihat di bawah)
        $groupFields = [
            'tanggal_tiba_pasuruan', 'tanggal_bongkar_pasuruan', 'lama_perjalanan_pasuruan',
            'sla_tiba_pasuruan', 'overstay_days_pasuruan', 'sla_bongkar_pasuruan',
            'monitoring_alert_pasuruan', 'action_required_pasuruan',
            'reason_waktu_tiba_pasuruan', 'reason_waktu_bongkar_pasuruan', 'remarks_pasuruan',
            'act_urutan_bongkar_pasuruan',
            'reason_pengiriman_optimal_customer_pasuruan',
            'biaya_kuli_pasuruan',   // tarif kuli sama untuk semua DO dalam grup
        ];
        $groupData = array_intersect_key($data, array_flip($groupFields));

        if (!empty($groupData) && !empty($oldShipmentNo)) {
            LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $oldShipmentNo)
                ->whereRaw("COALESCE(tujuan_pasuruan,'') = ?", [$groupTujuan])
                ->update($groupData);
        }

        // reason optimal = level SHIPMENT: isi di satu baris, semua tujuan ikut
        if (array_key_exists('reason_pengiriman_optimal_pasuruan', $data) && !empty($oldShipmentNo)) {
            $baru = trim((string) $data['reason_pengiriman_optimal_pasuruan']);
            $lama = trim((string) $logistik->reason_pengiriman_optimal_pasuruan);

            // hanya sebar kalau ada perubahan nyata di baris ini
            if ($baru !== $lama) {
                LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $oldShipmentNo)
                    ->update(['reason_pengiriman_optimal_pasuruan' => $baru !== '' ? $baru : null]);
            }
        }

        $logistik->update($data);
        $logistik->refresh();

        // selisih/actual/total kuli hanya hidup di 1 baris per tujuan, DO lain dikosongkan
        // (sekaligus merapikan data lama yang selisihnya tersebar per DO)
        if (!empty($oldShipmentNo)) {
            LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $oldShipmentNo)
                ->whereRaw("COALESCE(tujuan_pasuruan,'') = ?", [$groupTujuan])
                ->where('id', '!=', $logistik->id)
                ->update([
                    'selisih_quantity_pasuruan'         => null,
                    'actual_delivery_quantity_pasuruan' => null,
                    'total_biaya_kuli_pasuruan'         => 0,
                ]);
        }

        // CR + hasil kubik/tonase/optimal per shipment
        $cr = $this->refreshShipmentCalc($logistik->no_shipment_pasuruan);

        if ($oldShipmentNo && $oldShipmentNo !== $logistik->no_shipment_pasuruan) {
            $this->refreshShipmentCalc($oldShipmentNo);
        }

        // ---- estimasi tiba per shipment (khusus update) ----
        if ($withEstimasi) {
            $shipment = LogistikPengirimanPasuruan::where(
                'no_shipment_pasuruan',
                $logistik->no_shipment_pasuruan
            )->get();

            $keluar   = optional($shipment->first())->tanggal_keluar_gudang_pasuruan;
            $leadtime = (int) optional($shipment->first())->transport_lead_time_pasuruan;

            $baseEstimasi = $keluar
                ? date('Y-m-d', strtotime($keluar . " +{$leadtime} days"))
                : null;

            $lastBongkar = $shipment
                ->whereNotNull('tanggal_bongkar_pasuruan')
                ->max('tanggal_bongkar_pasuruan');

            $nextEstimasi = $lastBongkar
                ? date('Y-m-d', strtotime($lastBongkar . ' +1 day'))
                : $baseEstimasi;

            // SATU query, bukan foreach ->save() per baris
            LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $logistik->no_shipment_pasuruan)
                ->whereNull('tanggal_bongkar_pasuruan')
                ->update(['estimasi_tiba_pasuruan' => $nextEstimasi]);
        }

        return [$logistik, $cr];
    }

    public function destroy($id)
    {
        $logistik = LogistikPengirimanPasuruan::findOrFail($id);

        $noShipment = $logistik->no_shipment_pasuruan;

        $logistik->delete();

        $this->refreshShipmentCalc($noShipment);
        $this->invalidatePlannerAreaCachePasuruan();

        return redirect()->route('pasuruan.admin')
            ->with('success', 'Data berhasil dihapus.');
    }

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

    // =====================================================
    // DATA ADMIN (EDITABLE GRID, SERVER-SIDE)
    // =====================================================
    public function dataAjaxPasuruan(Request $request)
    {
        $draw        = (int) $request->input('draw', 1);
        $start       = (int) $request->input('start', 0);
        $length      = (int) $request->input('length', 25);
        $searchValue = trim((string) $request->input('search.value', ''));

      $baseQuery = LogistikPengirimanPasuruan::query();

$totalRecords = $this->countGroupsPasuruan(LogistikPengirimanPasuruan::query());

if ($request->filled('planner_filter')) {
    $baseQuery->where('planner_pasuruan', $request->input('planner_filter'));
}
if ($request->filled('area_filter')) {
    $baseQuery->where('area_pasuruan', $request->input('area_filter'));
}
if ($request->filled('create_tgl_filter')) {
    $baseQuery->whereDate('created_at', $request->input('create_tgl_filter'));
}

if ($searchValue !== '') {
    $baseQuery->where(function ($q) use ($searchValue) {
        $cols = [
            'planner_pasuruan', 'no_shipment_pasuruan', 'tujuan_pasuruan', 'route_pasuruan',
            'pulau_pasuruan', 'area_pasuruan', 'via_kirim_pasuruan', 'dist_channel_pasuruan',
            'kategori_ekspedisi_pasuruan', 'ekspedisi_pasuruan', 'nama_driver_pasuruan',
            'reason_pengiriman_optimal_pasuruan', 'no_pol_pasuruan', 'mobil_pasuruan',
        ];
        foreach ($cols as $col) {
            $q->orWhere($col, 'like', "%{$searchValue}%");
        }
    });
}

$recordsFiltered = $this->countGroupsPasuruan($baseQuery);
$orderDir = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

$rows = LogistikPengirimanPasuruan::query()
    ->whereIn('id', $this->groupIdsQueryPasuruan($baseQuery))
    // baris tanpa no_shipment taruh paling bawah
    ->orderByRaw("(no_shipment_pasuruan IS NULL OR TRIM(no_shipment_pasuruan) = '') ASC")
    ->orderByRaw("CAST(no_shipment_pasuruan AS UNSIGNED) {$orderDir}")
    ->orderBy('no_shipment_pasuruan', $orderDir)   // tie-breaker kalau ada huruf
    ->orderBy('tujuan_pasuruan')                   // tujuan dalam 1 shipment berdekatan
    ->orderBy('id')                                // urutan stabil antar halaman
    ->skip($start)
    ->take($length)
    ->get();
$groupAgg = $this->adminGroupAggPasuruan($rows);
        $routeOptions     = DB::table('tarif_pengiriman')->whereNotNull('route')->distinct()->orderBy('route')->pluck('route');
        $mobilOptions     = DB::table('tarif_pengiriman')->whereNotNull('mobil')->distinct()->orderBy('mobil')->pluck('mobil');
        $ekspedisiOptions = DB::table('tarif_pengiriman')->whereNotNull('ekpedisi')->distinct()->orderBy('ekpedisi')->pluck('ekpedisi');

        $reasonOptimal = DB::table('akurasi3')
            ->whereNotNull('reason_optimal')
            ->where('reason_optimal', '<>', '')
            ->distinct()
            ->orderBy('reason_optimal')
            ->pluck('reason_optimal');

        $reasonTiba       = DB::table('akurasi3')->whereNotNull('akurasi_waktu_tiba')->where('akurasi_waktu_tiba', '<>', '')->distinct()->pluck('akurasi_waktu_tiba');
        $reasonBongkar    = DB::table('akurasi3')->whereNotNull('akurasi_waktu_bongkar')->where('akurasi_waktu_bongkar', '<>', '')->distinct()->pluck('akurasi_waktu_bongkar');
        $reasonSelisihQty = DB::table('akurasi3')->whereNotNull('remarks_qty')->where('remarks_qty', '<>', '')->distinct()->pluck('remarks_qty');

        $lists = compact('routeOptions', 'mobilOptions', 'ekspedisiOptions', 'reasonTiba', 'reasonBongkar', 'reasonSelisihQty', 'reasonOptimal');

     $data = [];
foreach ($rows as $r) {
    $r->jml_do = 1;
    $g = $groupAgg[$r->no_shipment_pasuruan . '|' . ($r->tujuan_pasuruan ?? '')] ?? null;

    if ($g && trim((string) $r->no_shipment_pasuruan) !== '') {
        $r->jml_do                    = (int) $g->jml_do;
        $r->nilai_muatan_pasuruan     = $g->sum_nilai;
        $r->total_kubik_pasuruan      = $g->sum_kubik;
        $r->total_tonase_pasuruan     = $g->sum_tonase;
        $r->kirim_kontribusi_pasuruan = $g->sum_kontribusi;
        $r->biaya_kirim_pasuruan      = $g->sum_biaya;

        $q = (float) $g->sum_qty;
        $r->total_do_pasuruan = floor($q) == $q ? (int) $q : $q;

        // actual / selisih / biaya kuli per grup (shipment + tujuan)
        $this->applyGroupQtyKuliPasuruan($r, $g);

        $r->cr_pasuruan = (float) $g->sum_nilai > 0
            ? ((float) $g->sum_biaya / (float) $g->sum_nilai) * 100
            : 0;
    }

    $data[] = $this->renderRowColumnsPasuruan($r, $lists);
}

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    private function renderRowColumnsPasuruan($r, array $lists)
    {
        $id       = $r->id;
        $formAttr = 'form="form-update-' . $id . '"';

        $dateInput = function ($name, $value, $readonly = false) use ($formAttr) {
            $val = $value ? date('Y-m-d', strtotime($value)) : '';
            $ro  = $readonly ? 'readonly style="background:#f1f5f9;"' : '';
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
            $html  = '<select ' . $formAttr . ' name="' . $name . '" class="' . $extraClass . '">';
            $html .= '<option value="">-- Pilih --</option>';
            foreach ($options as $opt) {
                $sel   = ((string) $selected === (string) $opt) ? 'selected' : '';
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

        $formattedDesimalPasuruan = function ($angka) {
            if ($angka === null || $angka === '') return '';
            return number_format((float) $angka, 2, ',', '.');
        };

        // hasil per shipment: baca langsung dari kolom database
        $hasilKubikRow  = $r->hasil_kubik_pasuruan  !== null ? (float) $r->hasil_kubik_pasuruan  : null;
        $hasilTonaseRow = $r->hasil_tonase_pasuruan !== null ? (float) $r->hasil_tonase_pasuruan : null;

        $statusMobilHtml = !empty($r->tanggal_dpt_unit_pasuruan)
            ? '<span class="badge-status bg-success text-white">SUDAH DAPAT</span>'
            : '<span class="badge-status bg-danger text-white">BELUM DAPAT</span>';

        $slaMobilHtml = '<span class="badge-status bg-secondary text-white">-</span>';
        if ($r->rencana_kirim_pasuruan && $r->tanggal_dpt_unit_pasuruan) {
            $area    = strtoupper(trim($r->area_pasuruan ?? ''));
            $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
            $dpt     = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
            $selisih = floor(($dpt - $rencana) / 86400);
            $batas   = ($area == 'JABODEBEK' || $area == 'JABODETABEK') ? 0 : ($area == 'JAWA_BARAT' ? 1 : 2);
            $text    = $selisih > $batas ? 'H+' . ($selisih - $batas) : 'Sesuai SLA';
            $slaMobilHtml = '<span class="badge-status ' . (str_contains($text, 'H+') ? 'bg-danger text-white' : 'bg-success text-white') . '">' . $text . '</span>';
        }

        if (!empty($r->tanggal_bongkar_pasuruan)) {
            $statusBongkarHtml = '<span class="badge green">Telah Bongkar</span>';
        } elseif (!empty($r->tanggal_tiba_pasuruan)) {
            $selisih = max(0, floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)))) / 86400));
            $cls     = $selisih == 0 ? 'orange' : 'red';
            $statusBongkarHtml = '<span class="badge ' . $cls . '">H+' . $selisih . '</span>';
        } else {
            $statusBongkarHtml = '<span class="badge gray">-</span>';
        }

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
           '<span class="fw-medium">' . e($r->no_shipment_pasuruan) . '</span>'
    . '<input type="hidden" ' . $formAttr . ' name="no_shipment_pasuruan" value="' . e($r->no_shipment_pasuruan) . '">',
         '<span class="fw-medium">' . e($r->tujuan_pasuruan)
    . (($r->jml_do ?? 1) > 1 ? ' <span class="badge gray">' . $r->jml_do . ' DO</span>' : '') . '</span>'
    . '<input type="hidden" ' . $formAttr . ' name="tujuan_pasuruan" value="' . e($r->tujuan_pasuruan) . '">',
            $dateInput('tanggal_terima_po_pasuruan', $r->tanggal_terima_po_pasuruan),
            $dateInput('rencana_kirim_pasuruan', $r->rencana_kirim_pasuruan),
            $dateInput('tanggal_dpt_unit_pasuruan', $r->tanggal_dpt_unit_pasuruan),
            $dateInput('planning_loading_pasuruan', $r->planning_loading_pasuruan),
            $dateInput('tanggal_tiba_gudang_pasuruan', $r->tanggal_tiba_gudang_pasuruan),
            $dateInput('tanggal_keluar_gudang_pasuruan', $r->tanggal_keluar_gudang_pasuruan),

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
           '<input type="number" ' . $formAttr . ' name="total_do_pasuruan" value="' . e($r->total_do_pasuruan) . '" readonly style="background:#f1f5f9;">',

$textInput('nilai_muatan_pasuruan', $formattedRupiah($r->nilai_muatan_pasuruan), 'row-nilai-muatan input-rupiah', true),
$textInput('kubikasi_pasuruan',     $formattedDesimalPasuruan($r->kubikasi_pasuruan), 'row-kubik-tonase'),
$textInput('tonase_pasuruan',       $formattedDesimalPasuruan($r->tonase_pasuruan),   'row-kubik-tonase'),
$textInput('total_kubik_pasuruan',  $formattedDesimalPasuruan($r->total_kubik_pasuruan),  'row-kubik-tonase', true),
$textInput('total_tonase_pasuruan', $formattedDesimalPasuruan($r->total_tonase_pasuruan), 'row-kubik-tonase', true),
            '<input type="text" ' . $formAttr . ' name="hasil_kubik_pasuruan" class="row-hasil-kubik-pasuruan" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;" value="' . e($this->formatHasilPersenPasuruan($hasilKubikRow)) . '">',
            '<input type="text" ' . $formAttr . ' name="hasil_tonase_pasuruan" class="row-hasil-tonase-pasuruan" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;" value="' . e($this->formatHasilPersenPasuruan($hasilTonaseRow)) . '">',
            '<span class="row-optimal-pasuruan">' . $this->badgePengirimanOptimalPasuruan($r) . '</span>',

            $buildSelect(
                'reason_pengiriman_optimal_pasuruan',
                $r->reason_pengiriman_optimal_pasuruan,
                $lists['reasonOptimal'],
                'reason-optimal-select select-search'
            ),

            // BARU: badge optimal customer (per tujuan, baca kolom DB)
$this->badgePengirimanOptimalCustomerPasuruan($r),

// BARU: reason optimal customer
$buildSelect(
    'reason_pengiriman_optimal_customer_pasuruan',
    $r->reason_pengiriman_optimal_customer_pasuruan,
    $lists['reasonOptimal'],
    'reason-optimal-customer-select select-search'
),
          $textInput('biaya_kirim_pasuruan', $formattedRupiah($r->biaya_kirim_pasuruan), 'row-biaya-kirim input-rupiah', true),
            '<input type="text" ' . $formAttr . ' name="cr_pasuruan" class="row-cr" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;" value="' . e(is_numeric($r->cr_pasuruan) ? number_format((float) $r->cr_pasuruan, 4) . '%' : $r->cr_pasuruan) . '">',
            $statusMobilHtml,
            '<span class="text-primary fw-medium">' . e($r->lama_waktu_pencarian_pasuruan) . '</span>',
            $slaMobilHtml,
            $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-',
            $textInput('pic_monitoring_pasuruan', $r->pic_monitoring_pasuruan),
            $dateInput('act_pgi_date_pasuruan', $r->act_pgi_date_pasuruan),
            '<input type="number" ' . $formAttr . ' name="act_urutan_bongkar_pasuruan" value="' . e($r->act_urutan_bongkar_pasuruan) . '">',
            '<input type="number" ' . $formAttr . ' name="selisih_quantity_pasuruan" value="' . e(($r->selisih_quantity_pasuruan === null || (float) $r->selisih_quantity_pasuruan === 0.0) ? '' : $r->selisih_quantity_pasuruan) . '">',
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