<?php

namespace App\Http\Controllers\Planner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PlannerExport;
use App\Exports\{PlannerReportExport, XlsxSlicerInjector};
use Illuminate\Support\Facades\Storage;


class PlannerController extends Controller
{
    private const PULAU_MAP = [
        'JAWA'       => ['JABODEBEK', 'BANTEN', 'JAWA_BARAT', 'JAWA_TENGAH', 'JAWA_TIMUR', 'YOGYAKARTA'],
        'SUMATERA'   => ['ACEH', 'SUMATERA_UTARA', 'SUMATERA_BARAT', 'RIAU', 'KEP._RIAU', 'JAMBI', 'SUMATERA_SELATAN', 'BENGKULU', 'LAMPUNG', 'KEP._BANGKA_BELITUNG'],
        'KALIMANTAN' => ['KALIMANTAN_BARAT', 'KALIMANTAN_TENGAH', 'KALIMANTAN_SELATAN', 'KALIMANTAN_TIMUR', 'KALIMANTAN_UTARA'],
        'SULAWESI'   => ['SULAWESI_UTARA', 'SULAWESI_TENGAH', 'SULAWESI_SELATAN', 'SULAWESI_TENGGARA', 'SULAWESI_BARAT', 'GORONTALO'],
        'BALI_NUSRA' => ['PROV._BALI', 'NUSA_TENGGARA_BARAT', 'NUSA_TENGGARA_TIMUR'],
        'MALUKU'     => ['PROV._MALUKU', 'PROV._MALUKU_UTARA'],
        'PAPUA'      => ['PROV._PAPUA', 'PAPUA_BARAT', 'PAPUA_BARAT_DAYA', 'PAPUA_SELATAN', 'PAPUA_TENGAH'],
    ];

    // kunci per shipment saja (tanpa tujuan). Baris tanpa no_shipment dianggap berdiri sendiri.
    private const SHIPMENT_KEY = "COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id))";
    // kunci grup = no_shipment + tujuan (baris tanpa no_shipment dianggap grup sendiri)
    private const GROUP_KEY = "COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)), COALESCE(tujuan,'')";

    // suffix kolom => nama gudang. Tambah gudang baru cukup di sini.
    private const GUDANG = [
        ''   => 'KACS',
        '_2' => 'SENTUL',
        '_3' => 'CCIE',
        '_4' => 'P5',
        '_5' => 'FIFATEX',
    ];

    /** Aturan urutan hanya berlaku untuk tanggal_shipment >= ini (null = semua data). */
    private const URUTAN_MULAI = null;   // contoh: '2026-11-01'

    /** true = blocker harus periode_shipment yang sama. false = semua tanggal sebelumnya ikut mengunci. */
    private const URUTAN_PAKAI_PERIODE = false;

    /** lama cache (detik) */
    private const CACHE_DASHBOARD = 120;
    private const CACHE_TOTAL     = 60;
    private const CACHE_BLOKER    = 60;

    private function gudangSuffixes(): array
    {
        return array_keys(self::GUDANG);
    }

    /** [planning, tiba, keluar] per gudang */
    private function gudangCycles(): array
    {
        return array_map(
            fn($s) => ["planning_loading{$s}", "tanggal_tiba_gudang{$s}", "tanggal_keluar_gudang{$s}"],
            $this->gudangSuffixes()
        );
    }

    /** Semua field gudang untuk update/autosave */
    private function gudangPayload(Request $request, array $rumus): array
    {
        $out = [];
        foreach ($this->gudangSuffixes() as $s) {
            $out["planning_loading{$s}"]      = $request->{"planning_loading{$s}"};
            $out["tanggal_tiba_gudang{$s}"]   = $request->{"tanggal_tiba_gudang{$s}"};
            $out["tanggal_keluar_gudang{$s}"] = $request->{"tanggal_keluar_gudang{$s}"};
            $out["waktu_tiba_gudang{$s}"]     = $this->cleanTime($request->{"waktu_tiba_gudang{$s}"});
            $out["waktu_keluar_gudang{$s}"]   = $this->cleanTime($request->{"waktu_keluar_gudang{$s}"});
            $out["reason_gudang{$s}"]         = ($rumus["status_gudang{$s}"] ?? null) === 'Delay'
                ? ($request->{"reason_gudang{$s}"} ?: null) : null;
            $out["lama_digudang{$s}"] = $rumus["lama_digudang{$s}"] ?? null;
            $out["status_gudang{$s}"] = $rumus["status_gudang{$s}"] ?? null;
            $out["sla_loading{$s}"]   = $rumus["sla_loading{$s}"] ?? null;
        }
        return $out;
    }

    /* =====================================================================
     |  CACHE: hapus saat data berubah
     * ===================================================================== */
    private function forgetCaches(?string $periode = null): void
    {
        Cache::forget('planner.bloker');
        Cache::forget($this->totalKey($periode));
    }

    private function totalKey(?string $periode): string
    {
        return 'planner.total.' . md5((string) $periode);
    }

    public function exportPlannerReport(Request $request)
    {
        $filters = array_filter([
            'planner'          => $this->multiInput($request, 'planner_filter', 'planner'),
            'area'             => $this->multiInput($request, 'area_filter', 'area'),
            'dist_channel'     => $this->multiInput($request, 'dist_channel_filter', 'dist_channel'),
            'tanggal_shipment' => $request->input('tanggal_shipment_filter'),
        ]);

        $name = 'Laporan_Planner_' . date('Ymd_His') . '.xlsx';
        $rel  = 'exports/' . $name;

        Excel::store(new PlannerReportExport($filters, 'Filter aktif: ' . json_encode($filters)), $rel, 'local');
        $path = Storage::disk('local')->path($rel);

        // 7 slicer: kolom yang ada di sheet Data
        $wanted = ['Nama Planner', 'Area', 'Dist Channel', 'Periode Shipment', 'Pulau', 'Ekspedisi', 'Mobil'];
        $slots = [];
        foreach ($wanted as $i => $_) {
            $slots[] = [23, 0, 26, 0, $i * 13, $i * 13 + 12];   // [kolom awal, off, kolom akhir, off, baris awal, baris akhir]
        }

        XlsxSlicerInjector::inject($path, 'Ringkasan', $wanted, $slots);   // kalau gagal, file tetap valid tanpa slicer

        return response()->download($path, $name)->deleteFileAfterSend(true);
    }

    public function store(Request $request)
    {
        $rumus = $this->hitungSla($request);

        $data = $request->only([
            'create_tgl',
            'no_shipment',
            'kubikasi',
            'planner',
            'waktu_tiba_gudang', 'waktu_keluar_gudang',
            'waktu_tiba_gudang_2', 'waktu_keluar_gudang_2',
            'waktu_tiba_gudang_3', 'waktu_keluar_gudang_3',
            'planning_loading_4', 'tanggal_tiba_gudang_4', 'tanggal_keluar_gudang_4', 'reason_gudang_4',
            'planning_loading_5', 'tanggal_tiba_gudang_5', 'tanggal_keluar_gudang_5', 'reason_gudang_5',
            'dist_channel',
            'transport_lead_time',
            'tujuan',
            'reason_optimal',
            'area',
            'ketersediaan_unit',
            'mobil',
            'perubahan_mobil',
            'nilai_muatan',
            'biaya_kirim',
            'cr',
            'kategori_ekspedisi',
            'ekpedisi',

            'tanggal_naik_logistik',
            'rencana_kirim',
            'tanggal_dpt_unit',
            'planning_loading',
            'tanggal_tiba_gudang',
            'tanggal_keluar_gudang',
            'reason_gudang',
            'reason_gudang_2',
            'reason_gudang_3',
            'planning_loading_2',
            'tanggal_tiba_gudang_2',
            'tanggal_keluar_gudang_2',
            'tanggal_tiba_gudang_3',
            'planning_loading_3',
            'tanggal_keluar_gudang_3',
            'keterangan',
            'route',
            'pulau',
            'via_kirim'
        ]);

        // jam gudang 1..5 dibersihkan sekali lewat daftar gudang
        foreach ($this->gudangSuffixes() as $s) {
            foreach (['waktu_tiba_gudang', 'waktu_keluar_gudang'] as $f) {
                $data[$f . $s] = $this->cleanTime($request->{$f . $s});
            }
        }

        $data['total_kubik']  = $this->cleanDecimalPlanner($request->total_kubik);
        $data['total_tonase'] = $this->cleanDecimalPlanner($request->total_tonase);

        $p = $request->input('periode_shipment');
        if ($p && $p !== '__KOSONG__') {
            $data['periode_shipment'] = $p;
        }

        $payload = array_merge($data, $rumus);

        if ($err = array_merge($this->cekInput($request, true), $this->cekKolom(array_keys($payload), true))) {
            return $this->gagal($request, $err);
        }

        try {
            LogistikPengiriman::create($payload);
        } catch (\Throwable $e) {
            report($e);
            return $this->gagal($request, [$this->pesanDb($e)], 500, 'Gagal menyimpan ke database');
        }

        $this->forgetCaches($data['periode_shipment'] ?? null);

        return back()->with('success', 'Data berhasil disimpan');
    }

    /* =====================================================================
     |  DASHBOARD
     |  Semua hitungan dilakukan di MySQL (bukan menarik seluruh tabel ke PHP)
     |  dan hasilnya di-cache 2 menit per kombinasi filter.
     * ===================================================================== */
    public function dashboard(Request $request)
    {
        $key  = 'planner.dashboard.' . md5(json_encode($request->query()));
        $data = Cache::remember($key, self::CACHE_DASHBOARD, fn() => $this->dashboardData($request));

        return view('planner.dashboard', $data);
    }

    /** 1 baris wakil (MIN id) per no_shipment, dihitung di SQL */
    private function repShipment()
    {
        return DB::table('logistik_pengiriman as l')->joinSub(
            DB::table('logistik_pengiriman')
                ->selectRaw('MIN(id) AS id')
                ->groupByRaw("COALESCE(no_shipment,'')"),
            'r', 'r.id', '=', 'l.id'
        );
    }

    private function dashboardData(Request $request): array
    {
        // ===== kartu angka: 1 query =====
        $filled = fn($c) => "NULLIF(TRIM(l.{$c}), '') IS NOT NULL";

        $adaTiba = '(' . implode(' OR ', array_map(
            fn($s) => $filled("tanggal_tiba_gudang{$s}"), $this->gudangSuffixes()
        )) . ')';

        $agg = $this->repShipment()->selectRaw("
            COUNT(*) AS total_data,
            SUM(CASE WHEN {$filled('rencana_kirim')} AND {$filled('tanggal_dpt_unit')} THEN 1 ELSE 0 END) AS armada,
            SUM(CASE WHEN {$adaTiba} THEN 1 ELSE 0 END) AS ontime,
            SUM(CASE WHEN {$filled('tanggal_dpt_unit')} AND NOT {$adaTiba} THEN 1 ELSE 0 END) AS delay
        ")->first();

        $total_data   = (int) $agg->total_data;
        $armada       = (int) $agg->armada;
        $belum_armada = $total_data - $armada;
        $ontime       = (int) $agg->ontime;
        $delay        = (int) $agg->delay;

        $summary_area = $this->repShipment()
            ->selectRaw("COALESCE(l.area,'') AS area, COUNT(*) AS total")
            ->groupByRaw("COALESCE(l.area,'')")
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'area');

        // per no_shipment (duplikat tetap dihitung 1)
        $total_in_transit = $this->applyFilter($this->inTransitQuery(), $request)->distinct()->count('no_shipment');
        $total_in_gudang  = $this->applyFilter($this->inGudangQuery(), $request)->distinct()->count('no_shipment');

        $lk = $this->lastKeluarKosongSql();

        // ringkasan per planner (per no_shipment + tujuan, sama dengan halaman alert)
        [$lastTiba, $lastPlanning] = $this->lastTibaPlanningSql();

        $summary_planner = $this->repQuery()
            ->selectRaw("planner,
                COUNT(*) AS total,
                SUM(CASE WHEN NULLIF(TRIM(rencana_kirim),'') IS NULL
                           OR NULLIF(TRIM(tanggal_dpt_unit),'') IS NULL THEN 1 ELSE 0 END) AS total_belum_armada,
                SUM(CASE WHEN ({$lastPlanning}) IS NOT NULL AND ({$lastTiba}) IS NOT NULL
                           AND DATE({$lastTiba}) <= DATE({$lastPlanning}) THEN 1 ELSE 0 END) AS total_ontime,
                SUM(CASE WHEN ({$lastPlanning}) IS NOT NULL AND ({$lastTiba}) IS NOT NULL
                           AND DATE({$lastTiba}) > DATE({$lastPlanning}) THEN 1 ELSE 0 END) AS total_delay")
            ->whereNotNull('planner')->where('planner', '!=', '')
            ->groupBy('planner')
            ->orderByDesc('total')
            ->get();

        // alert per planner per periode: keluar gudang terakhir belum diinput
        $alert_planner = $this->repQuery()
            ->selectRaw('planner AS nama, periode_shipment, COUNT(*) AS total')
            ->whereNotNull('planner')->where('planner', '!=', '')
            ->whereRaw("({$lk}) = 1")
            ->groupBy('planner', 'periode_shipment')
            ->orderByRaw('RIGHT(periode_shipment, 4), LEFT(periode_shipment, 2)')
            ->get()
            ->groupBy('nama');

        return compact(
            'total_data',
            'ontime',
            'total_in_transit',
            'total_in_gudang',
            'delay',
            'armada',
            'belum_armada',
            'summary_area',
            'summary_planner',
            'alert_planner'
        );
    }

    private function inTransitQuery()
    {
        $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $q = $this->repQuery()->whereRaw($empty('tanggal_tiba'));

        $q->whereRaw('(' . implode(' OR ', array_map(
            fn($s) => $filled("tanggal_keluar_gudang{$s}"), $this->gudangSuffixes()
        )) . ')');

        foreach ($this->gudangCycles() as [$planning, $tiba, $keluar]) {
            $q->whereRaw('NOT ((' . $filled($planning) . ' OR ' . $filled($tiba) . ') AND ' . $empty($keluar) . ')');
        }

        return $q;
    }

    private function inTransitEstimasiSql(): string
    {
        $keluar = implode(",\n", array_map(
            fn($s) => "COALESCE(tanggal_keluar_gudang{$s},'1900-01-01')", $this->gudangSuffixes()
        ));

        return "COALESCE(estimasi_tiba, DATE_ADD(
            GREATEST({$keluar}),
            INTERVAL CAST(COALESCE(NULLIF(TRIM(transport_lead_time),''),0) AS UNSIGNED) DAY
        ))";
    }

    public function inTransit(Request $request)
    {
        $today   = date('Y-m-d');
        $todayTs = strtotime($today);
        $soon    = date('Y-m-d', strtotime('+3 days'));
        $est     = $this->inTransitEstimasiSql();

        $base = $this->inTransitQuery();

        // filter dari dashboard (tanggal/bulan/tahun/area/channel/pulau) ikut terbawa
        $this->applyFilter($base, $request);

        if ($request->filled('pic_monitoring')) {
            $base->where('pic_monitoring', $request->input('pic_monitoring'));
        }
        if ($request->filled('q')) {
            $s = trim($request->input('q'));
            $base->where(function ($q) use ($s) {
                foreach (['no_shipment', 'tujuan', 'ekpedisi', 'nama_driver', 'no_pol', 'mobil'] as $col) {
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
            ->orderBy('no_shipment')
            ->paginate(50)
            ->withQueryString();

        $list->getCollection()->transform(function ($r) use ($todayTs) {
            $keluar = null;
            $asal   = '-';

            // gudang keluar terbaru (seri -> gudang yang lebih akhir)
            foreach (self::GUDANG as $s => $nama) {
                $tgl = $r->{"tanggal_keluar_gudang{$s}"};
                if (!empty($tgl)) {
                    $ts = strtotime($tgl);
                    if ($keluar === null || $ts >= $keluar) { $keluar = $ts; $asal = $nama; }
                }
            }

            $lead     = (int) ($r->transport_lead_time ?? 0);
            $keluarD  = $keluar ? strtotime(date('Y-m-d', $keluar)) : null;
            $estimasi = !empty($r->estimasi_tiba)
                ? strtotime($r->estimasi_tiba)
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

            $r->gudang_asal    = $asal;
            $r->keluar_label   = $keluar ? date('d-m-Y', $keluar) : '-';
            $r->hari_transit   = $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null;
            $r->estimasi_label = $estimasi ? date('d-m-Y', $estimasi) : '-';
            $r->alert_label    = $alert;
            $r->alert_class    = $cls;

            return $r;
        });

        // angka (qty, nilai muatan, kubik, tonase, CR, hasil) = totalan per no_shipment + tujuan
        $this->withGroupTotals($list->getCollection());

        $areaList = Cache::remember('planner.area_list', 600, fn() =>
            DB::table('logistik_pengiriman')
                ->whereNotNull('area')->distinct()->orderBy('area')->pluck('area'));

        $picList = Cache::remember('planner.pic_list', 600, fn() =>
            DB::table('logistik_pengiriman')
                ->whereNotNull('pic_monitoring')->distinct()->orderBy('pic_monitoring')->pluck('pic_monitoring'));

        $formRoute = route('planner.intransit');

        return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList', 'formRoute'));
    }


    private function applyFilter($query, $request)
    {
        if ($request->area) {
            $query->where('area', $request->area);
        }

        if ($request->dist_channel) {
            $query->where('dist_channel', $request->dist_channel);
        }

        if ($request->filled('pulau') && isset(self::PULAU_MAP[$request->pulau])) {
            $query->whereIn('area', self::PULAU_MAP[$request->pulau]);
        }

        if ($request->date) {
            $query->whereDate('tanggal_naik_logistik', $request->date);
        }

        if ($request->month) {
            $query->whereMonth('tanggal_naik_logistik', substr($request->month, 5, 2));
            $query->whereYear('tanggal_naik_logistik', substr($request->month, 0, 4));
        }

        if ($request->year) {
            $query->whereYear('tanggal_naik_logistik', $request->year);
        }

        return $query;
    }


    /**
     * =====================================================
     * UPDATE (form penuh dari halaman planner)
     * =====================================================
     */
    public function update(Request $request, $id)
    {
        $old = DB::table('logistik_pengiriman')->where('id', $id)->first();
        if ($msg = $this->pesanUrutan($old)) {
            return $this->gagal($request, [$msg], 422, 'Urutan shipment');
        }
        if ($err = $this->cekInput($request, true, $old)) {
            return $this->gagal($request, $err);
        }

        $gudangInfo = $this->getKeluarGudangInfoRequest($request);
        $keluar  = $gudangInfo['keluar'];
        $blocked = $gudangInfo['blocked'];
        $leadTime = $request->transport_lead_time;

        if (!$blocked && $keluar && $leadTime !== null && $leadTime !== '') {
            $request->merge(['estimasi_tiba' => date('Y-m-d', strtotime('+' . (int) $leadTime . ' days', $keluar))]);
        } elseif ($blocked) {
            $request->merge(['estimasi_tiba' => null]);
        } else {
            $request->merge(['estimasi_tiba' => $old->estimasi_tiba]);
        }

        $rumus = $this->hitungSla($request);

        $newNoShipment = $request->no_shipment;
        $shipment      = $newNoShipment ?: $old->no_shipment;

        $updateShipment = array_merge([
            'estimasi_tiba'         => $request->estimasi_tiba,
            'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
            'rencana_kirim'         => $request->rencana_kirim,
            'transport_lead_time'   => $request->transport_lead_time,
            'planner'               => $request->planner,
            'no_shipment'           => $newNoShipment,
            'perubahan_mobil'       => $request->perubahan_mobil,
            'kategori_ekspedisi'    => $request->kategori_ekspedisi,
            'keterangan'            => $request->keterangan,
            'ekpedisi'              => $request->ekpedisi,
            'mobil'                 => $request->mobil,
            'route'                 => $request->route,
            'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,
            'area'                  => $request->area,
            'via_kirim'             => $request->via_kirim,
            'nama_driver'           => $request->nama_driver,
            'no_pol'                => $request->no_pol,
            'dist_channel'          => $request->dist_channel,
            'lama_waktu_pencarian'  => $rumus['lama_waktu_pencarian'] ?? null,
            'sla_dapat_mobil'       => $rumus['sla_dapat_mobil'] ?? null,
            'status_pengiriman'     => $rumus['status_pengiriman'] ?? null,
            'updated_at'            => now(),
        ], $this->gudangPayload($request, $rumus));

        if ($err = $this->cekKolom(array_keys($updateShipment))) {
            return $this->gagal($request, $err, 500, 'Struktur database belum siap');
        }

        try {
            DB::transaction(function () use ($updateShipment, $old, $newNoShipment, $shipment, $request) {
                $this->shipmentScope(DB::table('logistik_pengiriman'), $old, $newNoShipment)
                     ->update($updateShipment);

                $this->groupScope($old, $shipment)->update([
                    'pulau'                   => $request->pulau,
                    'reason_optimal'          => $request->reason_optimal ?: null,
                    'reason_optimal_customer' => $request->reason_optimal_customer ?: null,
                    'updated_at'              => now(),
                ]);
            }, 3);   // otomatis coba ulang 3x kalau deadlock
        } catch (\Throwable $e) {
            report($e);
            return $this->gagal($request, [$this->pesanDb($e)], 500, 'Gagal menyimpan ke database');
        }

        $this->forgetCaches($old->periode_shipment ?? null);

        return back()->with('success', 'Data berhasil diperbarui');
    }

    /** Subquery: 1 id wakil (MIN id) per shipment+tujuan dari query yang sudah terfilter */
    private function groupIdsQuery($query)
    {
        return (clone $query)
            ->select(DB::raw('MIN(id) as id'))
            ->groupByRaw(self::GROUP_KEY);
    }

    private function countGroups($query): int
    {
        return DB::query()->fromSub($this->groupIdsQuery($query), 'g')->count();
    }

    /**
     * Query dasar 1 baris wakil (MIN id) per no_shipment + tujuan.
     * Dipakai semua halaman supaya hitungannya per grup, bukan per rincian DO.
     */
    private function repQuery()
    {
        return DB::table('logistik_pengiriman')->whereIn('id', function ($sub) {
            $sub->from('logistik_pengiriman')
                ->selectRaw('MIN(id)')
                ->groupByRaw(self::GROUP_KEY);
        });
    }

    /** isi totalan grup (no_shipment + tujuan) ke 1 baris wakil */
    private function applyGroupTotals($r, $g, array $shipOpt = []): void
    {
        $r->jml_do       = (int) $g->jml_do;
        $r->nilai_muatan = $g->sum_nilai;
        $r->biaya_kirim  = $g->sum_biaya;
        $r->total_kubik  = $g->sum_kubik;
        $r->total_tonase = $g->sum_tonase;

        // kapasitas ikut grup (bukan baris wakil)
        $r->kubikasi = $g->kap_kubik;
        $r->tonase   = $g->kap_tonase;

        $q = (float) $g->sum_qty;
        $r->total_do_qty_car = floor($q) == $q ? (int) $q : $q;

        $r->cr = (float) $g->sum_nilai > 0
            ? ((float) $g->sum_biaya / (float) $g->sum_nilai) * 100
            : 0;

        $h = $this->hitungHasilOptimal($g->sum_kubik, $g->kap_kubik, $g->sum_tonase, $g->kap_tonase);
        $r->hasil_kubik  = $h['hasil_kubik'];
        $r->hasil_tonase = $h['hasil_tonase'];

        // customer: satu per satu (hasil grup ini saja)
        $r->pengiriman_optimal_customer = $h['pengiriman_optimal'];

        // internal: gabungan seluruh shipment
        $r->pengiriman_optimal = $shipOpt[$r->no_shipment] ?? $h['pengiriman_optimal'];
    }

    /** terapkan totalan grup ke sekumpulan baris (collection) */
    private function withGroupTotals($rows)
    {
        $agg = $this->plannerGroupAgg($rows);
        $opt = $this->optimalPerShipment($agg);

        foreach ($rows as $r) {
            $g = $agg[$r->no_shipment . '|' . ($r->tujuan ?? '')] ?? null;

            if ($g && trim((string) $r->no_shipment) !== '') {
                $this->applyGroupTotals($r, $g, $opt);
            }
        }

        return $rows;
    }

    /** Total per shipment+tujuan untuk baris yang tampil di halaman ini */
    private function plannerGroupAgg($rows): array
    {
        $shipments = $rows->pluck('no_shipment')
            ->filter(fn($v) => trim((string) $v) !== '')
            ->unique()->values();

        if ($shipments->isEmpty()) return [];

        return LogistikPengiriman::query()
            ->whereIn('no_shipment', $shipments)
            ->selectRaw("no_shipment,
                COALESCE(tujuan,'') AS tujuan_key,
                COUNT(*) AS jml_do,
                SUM(COALESCE(nilai_muatan,0))     AS sum_nilai,
                SUM(COALESCE(total_kubik,0))      AS sum_kubik,
                MAX(COALESCE(biaya_kirim,0))      AS sum_biaya,
                SUM(COALESCE(total_tonase,0))     AS sum_tonase,
                SUM(COALESCE(total_do_qty_car,0)) AS sum_qty,
                MAX(kubikasi)                     AS kap_kubik,
                MAX(tonase)                       AS kap_tonase")
            ->groupBy('no_shipment', 'tujuan_key')
            ->get()
            ->keyBy(fn($g) => $g->no_shipment . '|' . $g->tujuan_key)
            ->all();
    }

    /** Sama dengan selesaiGudangSql(), tapi dicek di PHP untuk 1 baris */
    private function gudangSelesai($row): bool
    {
        $ada = false;

        foreach ($this->gudangCycles() as [$plan, $tiba, $keluar]) {
            $started = trim((string) ($row->$plan ?? '')) !== '' || trim((string) ($row->$tiba ?? '')) !== '';
            $done    = trim((string) ($row->$keluar ?? '')) !== '';

            if ($done) {
                $ada = true;
            }
            if ($started && !$done) {
                return false;   // ada gudang yang menggantung
            }
        }

        return $ada;
    }

    /* =====================================================================
     |  KUNCI URUTAN SHIPMENT
     |  - Simpan (update / autosave): cek live per 1 baris (pemblokUrutan).
     |  - Tampilan tabel: 1 query untuk SEMUA planner (blokerMap), di-cache 60 dtk
     |    dan dihapus tiap ada simpan, bukan 1 query berat per baris.
     * ===================================================================== */

    /** Shipment paling awal milik planner yang sama yang belum selesai. Null = tidak terkunci. */
    private function pemblokUrutan($row)
    {
        if (trim((string) $row->planner) === '' || empty($row->tanggal_shipment)) {
            return null;
        }
        if ($this->gudangSelesai($row)) {
            return null;   // baris ini sudah selesai, boleh dikoreksi
        }

        $tgl = date('Y-m-d', strtotime($row->tanggal_shipment));

        $q = $this->repQuery()
            ->whereRaw('UPPER(TRIM(planner)) = ?', [strtoupper(trim($row->planner))])
            ->whereDate('tanggal_shipment', '<', $tgl)
            ->whereRaw($this->selesaiGudangSql() . ' = 0');

        if (self::URUTAN_PAKAI_PERIODE) {
            $q->whereRaw("COALESCE(periode_shipment,'') = ?", [(string) $row->periode_shipment]);
        }

        if (self::URUTAN_MULAI) {
            $q->whereDate('tanggal_shipment', '>=', self::URUTAN_MULAI);
        }

        return $q->orderBy('tanggal_shipment')
            ->orderByRaw('CAST(no_shipment AS UNSIGNED)')
            ->first(['id', 'no_shipment', 'tujuan', 'tanggal_shipment']);
    }

    private function pesanUrutan($row): ?string
    {
        $b = $this->pemblokUrutan($row);
        if (!$b) return null;

        return 'Selesaikan dulu shipment ' . $b->no_shipment . ' (' . $b->tujuan . ') tanggal '
            . date('d-m-Y', strtotime($b->tanggal_shipment))
            . ' sampai tanggal keluar gudang terakhir, sebelum mengisi shipment tanggal '
            . date('d-m-Y', strtotime($row->tanggal_shipment)) . '.';
    }

    /** [PLANNER_UPPER => [periode => tanggal_shipment paling awal yang belum selesai]] */
    private function blokerMap(): array
    {
        return Cache::remember('planner.bloker', self::CACHE_BLOKER, function () {
            $q = $this->repQuery()
                ->whereNotNull('tanggal_shipment')
                ->whereRaw("TRIM(COALESCE(planner,'')) <> ''")
                ->whereRaw($this->selesaiGudangSql() . ' = 0');

            if (self::URUTAN_MULAI) {
                $q->whereDate('tanggal_shipment', '>=', self::URUTAN_MULAI);
            }

            $rows = $q->selectRaw("UPPER(TRIM(planner)) AS p,
                                   COALESCE(periode_shipment,'') AS per,
                                   MIN(DATE(tanggal_shipment)) AS tgl")
                ->groupByRaw("UPPER(TRIM(planner)), COALESCE(periode_shipment,'')")
                ->get();

            $map = [];
            foreach ($rows as $r) {
                $map[$r->p][$r->per] = $r->tgl;
            }
            return $map;
        });
    }

    /** Pesan kunci untuk 1 baris tabel, dihitung dari blokerMap (tanpa query). */
    private function pesanBlokirBaris($r, array $map): ?string
    {
        $p = strtoupper(trim((string) $r->planner));
        if ($p === '' || empty($r->tanggal_shipment) || !isset($map[$p])) return null;
        if ($this->gudangSelesai($r)) return null;

        $tglR = date('Y-m-d', strtotime($r->tanggal_shipment));

        $vals = self::URUTAN_PAKAI_PERIODE
            ? array_filter([$map[$p][(string) $r->periode_shipment] ?? null])
            : array_values($map[$p]);

        $min = $vals ? min($vals) : null;
        if (!$min || $min >= $tglR) return null;

        return 'Selesaikan dulu shipment tanggal ' . date('d-m-Y', strtotime($min))
            . ' sampai tanggal keluar gudang terakhir, sebelum mengisi shipment tanggal '
            . date('d-m-Y', strtotime($tglR)) . '.';
    }

    /** Scope semua DO dalam 1 grup (no_shipment + tujuan) */
    private function groupScope($row, ?string $shipment = null)
    {
        $shipment = $shipment ?: $row->no_shipment;
        $q = DB::table('logistik_pengiriman');

        if (trim((string) $shipment) === '') {
            return $q->where('id', $row->id);
        }
        return $q->where('no_shipment', $shipment)
                 ->whereRaw("COALESCE(tujuan,'') = ?", [(string) $row->tujuan]);
    }

    /**
     * =====================================================
     * AUTOSAVE ROW (inline edit per-cell)
     * =====================================================
     */
    public function autosaveRow(Request $request, $id)
    {
        $old = DB::table('logistik_pengiriman')->where('id', $id)->first();
        if ($msg = $this->pesanUrutan($old)) {
            return $this->gagal($request, [$msg], 422, 'Urutan shipment');
        }
        if ($err = $this->cekInput($request, false, $old)) {
            return $this->gagal($request, $err);
        }

        $newNoShipment = $request->no_shipment;
        $shipment      = $newNoShipment ?: $old->no_shipment;

        $gudangInfo = $this->getKeluarGudangInfoRequest($request);
        $keluar  = $gudangInfo['keluar'];
        $blocked = $gudangInfo['blocked'];
        $leadTime = $request->transport_lead_time;

        if (!$blocked && $keluar && $leadTime !== null && $leadTime !== '') {
            $request->merge(['estimasi_tiba' => date('Y-m-d', strtotime('+' . (int) $leadTime . ' days', $keluar))]);
        } elseif ($blocked) {
            $request->merge(['estimasi_tiba' => null]);
        } else {
            $request->merge(['estimasi_tiba' => $old->estimasi_tiba]);
        }

        $rumus = $this->hitungSla($request);

        $updateShipment = array_merge([
            'planner'               => $request->planner,
            'no_shipment'           => $newNoShipment,
            'estimasi_tiba'         => $request->estimasi_tiba,
            'tanggal_naik_logistik' => $request->tanggal_naik_logistik,
            'rencana_kirim'         => $request->rencana_kirim,
            'tanggal_dpt_unit'      => $request->tanggal_dpt_unit,
            'route'                 => $request->route,
            'pulau'                 => $request->pulau,
            'area'                  => $request->area,
            'via_kirim'             => $request->via_kirim,
            'dist_channel'          => $request->dist_channel,
            'kategori_ekspedisi'    => $request->kategori_ekspedisi,
            'ekpedisi'              => $request->ekpedisi,
            'transport_lead_time'   => $request->transport_lead_time,
            'nama_driver'           => $request->nama_driver,
            'no_pol'                => $request->no_pol,
            'mobil'                 => $request->mobil,
            'lama_waktu_pencarian'  => $rumus['lama_waktu_pencarian'] ?? null,
            'sla_dapat_mobil'       => $rumus['sla_dapat_mobil'] ?? null,
            'status_pengiriman'     => $rumus['status_pengiriman'] ?? null,
            'updated_at'            => now(),
        ], $this->gudangPayload($request, $rumus));

        if ($err = $this->cekKolom(array_keys($updateShipment))) {
            return $this->gagal($request, $err, 500, 'Struktur database belum siap');
        }

        try {
            DB::transaction(function () use ($updateShipment, $old, $newNoShipment, $shipment, $request) {
                $this->shipmentScope(DB::table('logistik_pengiriman'), $old, $newNoShipment)
                     ->update($updateShipment);

                $this->groupScope($old, $shipment)->update([
                    'pulau'                   => $request->pulau,
                    'reason_optimal'          => $request->reason_optimal ?: null,
                    'reason_optimal_customer' => $request->reason_optimal_customer ?: null,
                    'updated_at'              => now(),
                ]);
            }, 3);
        } catch (\Throwable $e) {
            report($e);
            return $this->gagal($request, [$this->pesanDb($e)], 500, 'Gagal menyimpan ke database');
        }

        $this->forgetCaches($old->periode_shipment ?? null);

        $row = DB::table('logistik_pengiriman')->where('id', $id)->first(['biaya_kirim', 'cr']);

        return response()->json([
            'success'       => true,
            'biaya_kirim'   => $row->biaya_kirim ?? null,
            'cr'            => $row->cr ?? null,
            'estimasi_tiba' => $request->estimasi_tiba,
            'sla'           => $rumus,
        ]);
    }

    public function searchReasonOptimal(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $items = DB::table('akurasi3')
            ->whereNotNull('reason_optimal')
            ->where('reason_optimal', '!=', '')
            ->when($q !== '', fn($query) => $query->where('reason_optimal', 'like', "%{$q}%"))
            ->distinct()
            ->orderBy('reason_optimal')
            ->limit(50)
            ->pluck('reason_optimal');

        return response()->json(
            $items->map(fn($v) => ['id' => $v, 'text' => $v])->values()
        );
    }

    private function cleanCr($value)
    {
        if (!$value) return null;
        $value = preg_replace('/[^0-9.]/', '', $value);
        return is_numeric($value) ? (float) $value : null;
    }

    private function cleanPersen($value)
    {
        if ($value === null || $value === '') return null;

        // buang simbol % dan karakter selain angka & titik
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.]/', '', $value);

        if (!is_numeric($value)) return null;

        $value = (float) $value;

        // clamp 0 - 100
        if ($value < 0) $value = 0;
        if ($value > 100) $value = 100;

        return round($value, 2);
    }

    private function cleanMoney($value)
    {
        if (!$value) return null;
        return (int) preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * =====================================================
     * HALAMAN DATA PLANNER
     * View mengambil data lewat DataTables server-side (dataAjax()).
     * Di sini hanya kirim list dropdown untuk filter & modal "Add New Shipment".
     * =====================================================
     */
    public function dataLogistik(Request $request)
    {
        // belum pilih periode -> balik ke halaman pilih periode
        if (!$request->filled('periode')) {
            return redirect()->route('planner.periode');
        }
        $periode = $request->input('periode');

        // list planner cuma dari periode yang dipilih
        $planners = $this->filterPeriode(LogistikPengiriman::query(), $request)
            ->whereNotNull('planner')
            ->where('planner', '!=', '')
            ->distinct()
            ->orderBy('planner')
            ->pluck('planner');

        // tarif dipakai JS untuk auto biaya kirim (di-cache, jarang berubah)
        $tarifPengiriman = Cache::remember('planner.tarif', 600, fn() =>
            DB::table('tarif_pengiriman')
                ->select('route', 'mobil', 'ekpedisi', 'biaya_kirim')
                ->whereNotNull('route')
                ->whereNotNull('mobil')
                ->get());

        // 9 list dropdown master diambil dari cache
        return view(
            'planner.data_planner',
            array_merge(
                $this->masterLists(),
                compact('planners', 'tarifPengiriman', 'periode')
            )
        );
    }

    private function multiInput(Request $request, string ...$keys): array
    {
        foreach ($keys as $key) {
            $vals = array_values(array_filter(
                (array) $request->input($key, []),
                fn($v) => $v !== null && $v !== ''
            ));
            if (!empty($vals)) return $vals;
        }
        return [];
    }

    private function cleanTime($value): ?string
    {
        $value = trim((string) $value);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value) ? $value : null;
    }

    /** Validasi isian. $old = data lama saat update (null = data baru). $ketat=false untuk autosave. */
    private function cekInput(Request $request, bool $ketat = true, $old = null): array
    {
        $err = [];

        // #5 No Shipment kosong
        if (trim((string) $request->no_shipment) === '') {
            if ($old === null) {
                $err[] = 'No Shipment wajib diisi untuk data baru.';
            } elseif (trim((string) $old->no_shipment) !== '') {
                $err[] = 'No Shipment tidak boleh dikosongkan, karena akan melepas semua tujuan dari shipment ini.';
            }
        }

        // #6 Lead Time
        $lt = $request->input('transport_lead_time');
        if ($lt !== null && $lt !== '' && (!is_numeric($lt) || $lt < 0)) {
            $err[] = "Lead Time harus angka 0 atau lebih (diisi: {$lt}).";
        }

        // #1 tanggal: tahun 4 digit dan tanggal nyata
        $cekTgl = function ($v, $label) use (&$err) {
            if ($v === null || $v === '') return;
            $ok = preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)
                && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                && $m[1] >= 2020 && $m[1] <= 2100;
            if (!$ok) $err[] = "{$label}: tanggal tidak valid ({$v}). Tahun harus 4 digit, contoh 2026.";
        };

        $cekTgl($request->input('tanggal_naik_logistik'), 'Tanggal Naik');
        $cekTgl($request->input('rencana_kirim'), 'Rencana Kirim');
        $cekTgl($request->input('tanggal_dpt_unit'), 'Tanggal Dapat Unit');

        foreach (self::GUDANG as $s => $nama) {
            $plan    = $request->input("planning_loading{$s}");
            $tiba    = $request->input("tanggal_tiba_gudang{$s}");
            $keluar  = $request->input("tanggal_keluar_gudang{$s}");
            $wTiba   = $request->input("waktu_tiba_gudang{$s}");
            $wKeluar = $request->input("waktu_keluar_gudang{$s}");

            $cekTgl($plan,   "Planning Loading {$nama}");
            $cekTgl($tiba,   "Tanggal Tiba {$nama}");
            $cekTgl($keluar, "Tanggal Keluar {$nama}");

            // #1 jam
            foreach ([[$wTiba, 'Jam Tiba'], [$wKeluar, 'Jam Keluar']] as [$w, $label]) {
                if (trim((string) $w) !== '' && $this->cleanTime($w) === null) {
                    $err[] = "{$label} {$nama} harus format HH:MM, contoh 08:30 (diisi: {$w}).";
                }
            }

            if ($tiba && $keluar) {
                $a = strtotime(date('Y-m-d', strtotime($tiba))   . ' ' . ($this->cleanTime($wTiba)   ?: '00:00:00'));
                $b = strtotime(date('Y-m-d', strtotime($keluar)) . ' ' . ($this->cleanTime($wKeluar) ?: '00:00:00'));

                if ($b < $a) {                                                          // #2
                    $err[] = "Gudang {$nama}: waktu keluar lebih awal dari waktu tiba.";
                } elseif ($ketat && ($b - $a) > 86400
                    && trim((string) $request->input("reason_gudang{$s}")) === '') {    // #4
                    $err[] = "Gudang {$nama}: Delay (lebih dari 24 jam), Reason Gudang wajib diisi.";
                }
            }

            if ($ketat && $keluar && !$tiba) {                                          // #3
                $err[] = "Gudang {$nama}: Tanggal Keluar diisi tapi Tanggal Tiba masih kosong.";
            }
        }

        return $err;
    }

    /** #8: kolom harus ada di DB (dan di $fillable kalau lewat create()) */
    private function cekKolom(array $keys, bool $cekFillable = false): array
    {
        $err    = [];
        // daftar kolom di-cache: Schema::getColumnListing() itu query ke information_schema
        $kolom  = Cache::remember('planner.columns', 3600, fn() => Schema::getColumnListing('logistik_pengiriman'));
        $hilang = array_diff($keys, $kolom);

        if ($hilang) {
            // mungkin baru migrasi: cek ulang sekali tanpa cache sebelum menolak
            $kolom  = Schema::getColumnListing('logistik_pengiriman');
            Cache::put('planner.columns', $kolom, 3600);
            $hilang = array_diff($keys, $kolom);
        }

        if ($hilang) {
            $err[] = 'Kolom belum ada di database: ' . implode(', ', $hilang) . '. Hubungi admin untuk menjalankan migrasi.';
        }

        if ($cekFillable) {
            $fill  = (new LogistikPengiriman)->getFillable();
            $tolak = $fill ? array_diff($keys, $fill) : [];
            if ($tolak) {
                $err[] = 'Kolom belum didaftarkan di $fillable model: ' . implode(', ', $tolak) . '. Hubungi admin.';
            }
        }
        return $err;
    }

    /** #5: scope update aman. Shipment kosong -> hanya baris itu sendiri (by id). */
    private function shipmentScope($q, $old, $newNoShipment)
    {
        $ships = array_values(array_unique(array_filter(
            [trim((string) $old->no_shipment), trim((string) $newNoShipment)], 'strlen'
        )));

        return $ships ? $q->whereIn('no_shipment', $ships) : $q->where('id', $old->id);
    }

    /** JSON untuk autosave, flash + redirect untuk form biasa */
    private function gagal(Request $request, array $errors, int $status = 422, string $title = 'Data gagal disimpan')
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'title' => $title, 'errors' => $errors], $status);
        }
        return back()->withInput()->with('save_error', ['title' => $title, 'errors' => $errors]);
    }

    /** #9: terjemahkan error database jadi kalimat yang bisa dibaca */
    private function pesanDb(\Throwable $e): string
    {
        $kode = $e instanceof \Illuminate\Database\QueryException ? ($e->errorInfo[0] ?? '') : '';
        $msg  = $e->getMessage();

        if (preg_match("/Incorrect (?:datetime|date|integer|decimal) value: '([^']*)' for column '([^']+)'/", $msg, $m)) {
            return 'Kolom ' . ucwords(str_replace('_', ' ', $m[2])) . " berisi nilai yang salah: '{$m[1]}'. Untuk tanggal, pastikan tahun 4 digit (contoh 2026).";
        }
        if (preg_match("/Data too long for column '([^']+)'/", $msg, $m)) {
            return 'Isian ' . ucwords(str_replace('_', ' ', $m[1])) . ' terlalu panjang. Persingkat lalu simpan lagi.';
        }
        if (preg_match("/Unknown column '([^']+)'/", $msg, $m)) {
            return "Kolom {$m[1]} belum ada di database. Hubungi admin untuk menjalankan migrasi.";
        }

        return match (true) {
            $kode === '40001' => 'Data sedang dipakai user lain. Coba simpan lagi.',
            default           => 'Terjadi kesalahan di server. Hubungi admin. (kode: ' . ($kode ?: $e->getCode()) . ')',
        };
    }

    /**
     * =====================================================
     * ENDPOINT SERVER-SIDE UNTUK DATATABLES
     * Hanya ambil & render baris yang benar-benar tampil.
     * =====================================================
     */
    public function dataAjax(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $length = $length > 0 ? min($length, 200) : 25;
        $searchValue = trim((string) $request->input('search.value', ''));
        $periode = (string) $request->input('periode', '');

        $baseQuery = LogistikPengiriman::query();
        $this->filterPeriode($baseQuery, $request);

        // total (tanpa filter lain) di-cache sebentar; dihapus tiap ada simpan
        $totalRecords = Cache::remember($this->totalKey($periode), self::CACHE_TOTAL, fn() =>
            $this->countGroups($this->filterPeriode(LogistikPengiriman::query(), $request))
        );

        $adaFilter = $searchValue !== '';

        if ($vals = $this->multiInput($request, 'planner_filter')) {
            $baseQuery->whereIn('planner', $vals);
            $adaFilter = true;
        }
        if ($vals = $this->multiInput($request, 'area_filter')) {
            $baseQuery->whereIn('area', $vals);
            $adaFilter = true;
        }
        if ($vals = $this->multiInput($request, 'dist_channel_filter')) {
            $baseQuery->whereIn('dist_channel', $vals);
            $adaFilter = true;
        }
        if ($request->filled('tanggal_shipment_filter')) {
            $baseQuery->whereDate('tanggal_shipment', $request->input('tanggal_shipment_filter'));
            $adaFilter = true;
        }

        if ($searchValue !== '') {
            $baseQuery->where(function ($q) use ($searchValue) {
                $cols = [
                    'planner', 'no_shipment', 'tujuan', 'route', 'pulau', 'area',
                    'via_kirim', 'dist_channel', 'kategori_ekspedisi', 'ekpedisi',
                    'nama_driver', 'no_pol', 'mobil', 'transport_lead_time',
                ];
                foreach ($cols as $col) {
                    $q->orWhere($col, 'like', "%{$searchValue}%");
                }
            });
        }

        // tanpa filter/pencarian, hasilnya sama dengan total -> hemat 1 query berat
        $recordsFiltered = $adaFilter ? $this->countGroups($baseQuery) : $totalRecords;

        $rows = LogistikPengiriman::query()
            ->whereIn('id', $this->groupIdsQuery($baseQuery))
            ->orderByRaw($this->selesaiGudangSql() . ' ASC')
            ->orderBy('tanggal_shipment', 'ASC')
            ->orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
            ->orderBy('id', 'ASC')
            ->skip($start)
            ->take($length)
            ->get();

        $groupAgg = $this->plannerGroupAgg($rows);
        $shipOpt  = $this->optimalPerShipment($groupAgg);

        $lists  = $this->masterLists();
        $bloker = $this->blokerMap();   // 1 query (cache) untuk semua baris

        // ===== bangun baris: totalan per shipment + tujuan =====
        $data = [];
        foreach ($rows as $r) {
            $r->jml_do = 1;
            $g = $groupAgg[$r->no_shipment . '|' . ($r->tujuan ?? '')] ?? null;

            if ($g && trim((string) $r->no_shipment) !== '') {
                $this->applyGroupTotals($r, $g, $shipOpt);
            }

            $pesanKunci = $this->pesanBlokirBaris($r, $bloker);

            $cols = $this->renderRowColumns($r, $lists);

            if ($pesanKunci) {
                $cols = array_map(fn($c) => is_string($c)
                    ? str_replace(['<input ', '<select '], ['<input disabled ', '<select disabled '], $c)
                    : $c, $cols);
                $cols[0] .= '<div class="badge red" title="' . e($pesanKunci) . '">🔒 Isi tanggal Shipment Sebelumnya</div>';
            }

            $data[] = $cols;
        }

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /** 1 = keluar gudang di gudang terakhir yang terisi masih kosong */
    private function lastKeluarKosongSql(): string
    {
        $f = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $e = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $sql = 'CASE ';
        foreach (array_reverse($this->gudangSuffixes()) as $s) {
            $sql .= 'WHEN (' . $f("planning_loading{$s}")
                . ' OR ' . $f("tanggal_tiba_gudang{$s}")
                . ' OR ' . $f("tanggal_keluar_gudang{$s}")
                . ') THEN (' . $e("tanggal_keluar_gudang{$s}") . ') ';
        }
        return $sql . 'ELSE 1 END';
    }

    /** [SQL tiba gudang terakhir, SQL planning gudang yang sama] */
    private function lastTibaPlanningSql(): array
    {
        $suffix = $this->gudangSuffixes();
        $tiba   = array_map(fn($s) => "tanggal_tiba_gudang{$s}", $suffix);
        $plan   = array_map(fn($s) => "planning_loading{$s}", $suffix);

        $greatest = 'GREATEST(' . implode(',', array_map(
            fn($c) => "COALESCE({$c},'1900-01-01')", $tiba)) . ')';

        $whenT = $whenP = '';
        foreach (array_reverse(array_keys($tiba)) as $i) {
            $cond = $i === 0
                ? "{$tiba[0]} IS NOT NULL"
                : "{$tiba[$i]} IS NOT NULL AND {$tiba[$i]} = {$greatest}";
            $whenT .= "WHEN {$cond} THEN {$tiba[$i]} ";
            $whenP .= "WHEN {$cond} THEN {$plan[$i]} ";
        }

        return ["CASE {$whenT} ELSE NULL END", "CASE {$whenP} ELSE NULL END"];
    }

    private function selesaiGudangSql(): string
    {
        $f = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $e = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $menggantung = [];
        foreach ($this->gudangCycles() as [$p, $t, $k]) {
            $menggantung[] = '((' . $f($p) . ' OR ' . $f($t) . ') AND ' . $e($k) . ')';
        }

        $adaKeluar = '(' . implode(' OR ', array_map(
            fn($s) => $f("tanggal_keluar_gudang{$s}"), $this->gudangSuffixes()
        )) . ')';

        return "CASE WHEN {$adaKeluar} AND NOT (" . implode(' OR ', $menggantung) . ") THEN 1 ELSE 0 END";
    }

    /**
     * Bangun 1 baris (array kolom, index harus sinkron dengan
     * thead di data_planner.blade.php) dalam bentuk HTML string.
     *
     * PERFORMA: <select> hanya memuat option yang sedang terpilih.
     * Daftar option lengkap diisi di browser saat select diklik (lihat JS di Blade),
     * jadi HTML 25 baris x 9 select jauh lebih kecil.
     */
    private function renderRowColumns($r, array $lists)
    {
        $id = $r->id;
        $formAttr = 'form="form-update-' . $id . '"';

        $dateInput = function ($name, $value) use ($formAttr) {
            $val = $value ? date('Y-m-d', strtotime($value)) : '';
            return '<input type="date" ' . $formAttr . ' name="' . $name . '" value="' . e($val) . '">';
        };

        $timeInput = function ($name, $value) use ($formAttr) {
            $val = $value ? substr($value, 0, 5) : '';   // 08:30:00 -> 08:30
            return '<input type="text" ' . $formAttr . ' name="' . $name . '" class="input-jam" '
                 . 'placeholder="HH:MM" maxlength="5" inputmode="numeric" autocomplete="off" value="' . e($val) . '">';
        };

        $dateTimeInput = function ($dateName, $dateVal, $timeName, $timeVal) use ($dateInput, $timeInput) {
            return $dateInput($dateName, $dateVal) . '<br>' . $timeInput($timeName, $timeVal);
        };

        $formattedPersen = function ($angka) {
            if ($angka === null || $angka === '') return '';
            return number_format((float) $angka, 2, ',', '.') . '%';
        };

        $textInput = function ($name, $value, $extraClass = '') use ($formAttr) {
            return '<input type="text" ' . $formAttr . ' name="' . $name . '" class="' . $extraClass . '" value="' . e($value) . '">';
        };

        $fmtDec = fn($v) => ($v === null || $v === '')
            ? '' : rtrim(rtrim(number_format((float) $v, 4, ',', ''), '0'), ',');

        $roInput = fn($name, $value, $cls = '') =>
            '<input type="text" ' . $formAttr . ' name="' . $name . '" class="' . $cls . '" readonly '
            . 'style="background:#f1f5f9;" value="' . e($value) . '">';

        // select ringan: hanya option terpilih. $options sengaja tidak dipakai (diisi lazy di browser).
        $buildSelect = function ($name, $selected, $options, $extraClass = '', $required = false) use ($formAttr) {
            $sel  = (string) $selected;
            $html = '<select ' . $formAttr . ' name="' . $name . '" class="' . $extraClass . ' select2-row">';
            $html .= '<option value="">-- Pilih --</option>';
            if ($sel !== '') {
                $html .= '<option value="' . e($sel) . '" selected>' . e($sel) . '</option>';
            }
            return $html . '</select>';
        };

        // ===== Status Mobil =====
        if (!empty($r->tanggal_dpt_unit)) {
            $statusMobilHtml = '<span class="badge-status bg-success text-white">SUDAH DAPAT</span>';
        } else {
            $statusMobilHtml = '<span class="badge-status bg-danger text-white">BELUM DAPAT</span>';
        }

        // ===== SLA Dapat Mobil =====
        if ($r->rencana_kirim && $r->tanggal_dpt_unit) {
            $area = strtoupper(trim($r->area ?? ''));
            $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim)));
            $dptUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit)));
            $selisihHari = floor(($dptUnit - $rencana) / 86400);

            if (in_array($area, ['JABODEBEK', 'JABODETABEK', 'BANTEN'])) {
                $batasHari = 0;
            } elseif (in_array($area, ['JAWA_BARAT', 'JAWA BARAT'])) {
                $batasHari = 1;
            } else {
                $batasHari = 2;
            }

            $text = $selisihHari > $batasHari ? 'H+' . ($selisihHari - $batasHari) : 'Sesuai SLA';
            $slaMobilHtml = '<span class="badge-status ' . (str_contains($text, 'H+') ? 'bg-danger text-white' : 'bg-success text-white') . '">' . $text . '</span>';
        } else {
            $slaMobilHtml = '<span class="badge-status bg-secondary text-white">-</span>';
        }

        // ===== Kelengkapan Data (5 field wajib) =====
        $requiredFields = [
            'mobil'       => 'Mobil',
            'ekpedisi'    => 'Ekspedisi',
            'route'       => 'Route',
            'nama_driver' => 'Nama Driver',
            'no_pol'      => 'No Pol',
        ];
        $missing = [];
        foreach ($requiredFields as $col => $label) {
            if (trim((string) ($r->$col ?? '')) === '') {
                $missing[] = $label;
            }
        }
        if (count($missing) === 0) {
            $kelengkapanHtml = '<span class="badge completeness-badge green" title="Data lengkap">✅ Lengkap</span>';
        } else {
            $emptyCount = count($missing);
            $cls = $emptyCount === 1 ? 'yellow' : ($emptyCount <= 3 ? 'orange' : 'red');
            $text = '❌ ' . implode(', ', $missing);
            $kelengkapanHtml = '<span class="badge completeness-badge ' . $cls . '" title="' . e($text) . '">' . e($text) . '</span>';
        }

        $formattedRupiah = function ($angka) {
            if (!$angka) return '';
            $stringMurni = explode('.', (string) $angka)[0];
            $angkaMurni = preg_replace('/[^0-9]/', '', $stringMurni);
            return $angkaMurni ? 'Rp ' . number_format((float) $angkaMurni, 0, ',', '.') : '';
        };

        $tanggalImportText = $r->create_tgl ? \Carbon\Carbon::parse($r->create_tgl)->format('d/m/Y H:i') : '-';

        $hiddenForm = '<form class="d-none" id="form-update-' . $id . '" action="' . route('planner.update', $id) . '" method="POST">'
            . csrf_field() . method_field('PUT') . '</form>';

        return [
            // 0
            $hiddenForm . $tanggalImportText,
            // 1
            $textInput('planner', $r->planner),
            // 2 no shipment
            '<span class="fw-medium">' . e($r->no_shipment) . '</span>'
            . '<input type="hidden" ' . $formAttr . ' name="no_shipment" value="' . e($r->no_shipment) . '">',
            // 3 tanggal shipment (hanya tampil, tidak bisa diedit)
            '<span class="fw-medium">' . ($r->tanggal_shipment ? date('d-m-Y', strtotime($r->tanggal_shipment)) : '-') . '</span>',

            // tanggal umum
            $dateInput('tanggal_naik_logistik', $r->tanggal_naik_logistik),
            $dateInput('rencana_kirim', $r->rencana_kirim),
            $dateInput('tanggal_dpt_unit', $r->tanggal_dpt_unit),

            // KACS
            $dateInput('planning_loading', $r->planning_loading),
            $dateTimeInput('tanggal_tiba_gudang',   $r->tanggal_tiba_gudang,   'waktu_tiba_gudang',   $r->waktu_tiba_gudang),
            $dateTimeInput('tanggal_keluar_gudang', $r->tanggal_keluar_gudang, 'waktu_keluar_gudang', $r->waktu_keluar_gudang),
            $buildSelect('reason_gudang', $r->reason_gudang, $lists['reasonGudangList'], 'row-reason-gudang'),

            // SENTUL
            $dateInput('planning_loading_2', $r->planning_loading_2),
            $dateTimeInput('tanggal_tiba_gudang_2',   $r->tanggal_tiba_gudang_2,   'waktu_tiba_gudang_2',   $r->waktu_tiba_gudang_2),
            $dateTimeInput('tanggal_keluar_gudang_2', $r->tanggal_keluar_gudang_2, 'waktu_keluar_gudang_2', $r->waktu_keluar_gudang_2),
            $buildSelect('reason_gudang_2', $r->reason_gudang_2, $lists['reasonGudangList'], 'row-reason-gudang'),

            // CCIE
            $dateInput('planning_loading_3', $r->planning_loading_3),
            $dateTimeInput('tanggal_tiba_gudang_3',   $r->tanggal_tiba_gudang_3,   'waktu_tiba_gudang_3',   $r->waktu_tiba_gudang_3),
            $dateTimeInput('tanggal_keluar_gudang_3', $r->tanggal_keluar_gudang_3, 'waktu_keluar_gudang_3', $r->waktu_keluar_gudang_3),
            $buildSelect('reason_gudang_3', $r->reason_gudang_3, $lists['reasonGudangList'], 'row-reason-gudang'),

            // P5
            $dateInput('planning_loading_4', $r->planning_loading_4),
            $dateTimeInput('tanggal_tiba_gudang_4',   $r->tanggal_tiba_gudang_4,   'waktu_tiba_gudang_4',   $r->waktu_tiba_gudang_4),
            $dateTimeInput('tanggal_keluar_gudang_4', $r->tanggal_keluar_gudang_4, 'waktu_keluar_gudang_4', $r->waktu_keluar_gudang_4),
            $buildSelect('reason_gudang_4', $r->reason_gudang_4, $lists['reasonGudangList'], 'row-reason-gudang'),

            // FIFATEX
            $dateInput('planning_loading_5', $r->planning_loading_5),
            $dateTimeInput('tanggal_tiba_gudang_5',   $r->tanggal_tiba_gudang_5,   'waktu_tiba_gudang_5',   $r->waktu_tiba_gudang_5),
            $dateTimeInput('tanggal_keluar_gudang_5', $r->tanggal_keluar_gudang_5, 'waktu_keluar_gudang_5', $r->waktu_keluar_gudang_5),
            $buildSelect('reason_gudang_5', $r->reason_gudang_5, $lists['reasonGudangList'], 'row-reason-gudang'),

            // tujuan
            '<span class="fw-medium">' . e($r->tujuan) . '</span>'
            . '<input type="hidden" ' . $formAttr . ' name="tujuan" value="' . e($r->tujuan) . '">',
            // route (required)
            $buildSelect('route', $r->route, $lists['routeList'], 'row-route'),
            // pulau
            $buildSelect('pulau', $r->pulau, $lists['pulauList'], 'row-pulau'),
            // area
            $buildSelect('area', $r->area, $lists['areas'], 'row-area'),
            // via kirim
            $textInput('via_kirim', $r->via_kirim),
            // dist channel
            $buildSelect('dist_channel', $r->dist_channel, $lists['distChannelList'], 'row-dist-channel'),
            // kategori ekspedisi
            $textInput('kategori_ekspedisi', $r->kategori_ekspedisi),
            // ekpedisi (required)
            $buildSelect('ekpedisi', $r->ekpedisi, $lists['ekpedisiList'], 'row-ekpedisi'),
            // lead time
            $textInput('transport_lead_time', $r->transport_lead_time),
            // nama driver (required)
            $textInput('nama_driver', $r->nama_driver),
            // no pol (required)
            $textInput('no_pol', $r->no_pol),
            // mobil (required)
            $buildSelect('mobil', $r->mobil, $lists['mobilList'], 'row-mobil'),
            // total qty
            $roInput('total_do_qty_car', $r->total_do_qty_car),
            $roInput('nilai_muatan', $formattedRupiah($r->nilai_muatan), 'row-nilai-muatan input-rupiah'),
            $roInput('biaya_kirim',  $formattedRupiah($r->biaya_kirim),  'row-biaya-kirim input-rupiah'),

            // cr
            '<input type="text" ' . $formAttr . ' name="cr" class="row-cr" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;" value="' . e(is_numeric($r->cr) ? number_format((float) $r->cr, 4) : $r->cr) . '">',
            // kubikasi: text input, diformat pakai helper
            $textInput('kubikasi', $formattedPersen($r->kubikasi), 'row-kubikasi'),
            // tonase (readonly, hasil lookup tarif)
            '<input type="text" readonly style="background:#f1f5f9;" value="' . e($r->tonase !== null ? number_format((float)$r->tonase, 2, ',', '.') . '%' : '-') . '">',

            // total kubik / tonase (manual)
            $roInput('total_kubik',  $fmtDec($r->total_kubik),  'row-total-kubikasi'),
            $roInput('total_tonase', $fmtDec($r->total_tonase), 'row-total-tonase'),

            // hasil kubik
            '<span>' . e($r->hasil_kubik !== null ? number_format((float)$r->hasil_kubik, 2, ',', '.') . '%' : '-') . '</span>',

            // hasil tonase
            '<span>' . e($r->hasil_tonase !== null ? number_format((float)$r->hasil_tonase, 2, ',', '.') . '%' : '-') . '</span>',

            // pengiriman optimal badge
            $r->pengiriman_optimal === 'OPTIMAL'
                ? '<span class="badge green">✅ Optimal</span>'
                : ($r->pengiriman_optimal ? '<span class="badge orange">⚠️ Tidak Optimal</span>' : '<span class="badge gray">-</span>'),
            $buildSelect('reason_optimal', $r->reason_optimal, $lists['reasonOptimalList'], 'row-reason-optimal'),

            // pengiriman optimal customer
            strtoupper(trim((string) $r->pengiriman_optimal_customer)) === 'OPTIMAL'
                ? '<span class="badge green">✅ Optimal</span>'
                : (trim((string) $r->pengiriman_optimal_customer) !== ''
                    ? '<span class="badge orange">⚠️ Tidak Optimal</span>'
                    : '<span class="badge gray">-</span>'),

            // reason optimal customer (sumber sama: akurasi3.reason_optimal)
            $buildSelect('reason_optimal_customer', $r->reason_optimal_customer, $lists['reasonOptimalList'], 'row-reason-optimal-customer'),

            // status mobil
            $statusMobilHtml,
            // lama waktu pencarian
            '<span class="text-primary fw-medium">' . e($r->lama_waktu_pencarian) . '</span>',
            // sla dapat mobil
            $slaMobilHtml,
            // lama / status / SLA per gudang
            ...$this->gudangCells($r, ''),
            ...$this->gudangCells($r, '_2'),
            ...$this->gudangCells($r, '_3'),
            ...$this->gudangCells($r, '_4'),
            ...$this->gudangCells($r, '_5'),
            // shipping point
            $r->route ? explode('-', trim($r->route))[0] : '-',
            // kelengkapan data
            $kelengkapanHtml,
            // hapus
            '<div class="btn-action"><a href="' . route('planner.delete', $id) . '" class="btn btn-danger btn-sm px-2 d-flex align-items-center gap-1" onclick="return confirm(\'Hapus data ini?\')"><i class="fa-solid fa-trash"></i> Del</a></div>',
        ];
    }

    /** Filter periode_shipment. '__KOSONG__' = data yang periodenya belum diisi */
    private function filterPeriode($q, Request $request)
    {
        $p = $request->input('periode');
        if ($p === null || $p === '') return $q;

        if ($p === '__KOSONG__') {
            return $q->whereRaw("COALESCE(NULLIF(TRIM(periode_shipment),''),'') = ''");
        }
        return $q->where('periode_shipment', $p);
    }

    /** Halaman pilih periode: ringkasan per periode */
    public function pilihPeriode()
    {
        $sel = $this->selesaiGudangSql();
        $kunci = "COALESCE(NULLIF(TRIM(periode_shipment),''),'__KOSONG__')";

        $periodes = $this->repQuery()
            ->selectRaw("{$kunci} AS periode,
                         COUNT(*) AS total,
                         SUM(CASE WHEN {$sel} = 0 THEN 1 ELSE 0 END) AS belum,
                         MAX(tanggal_shipment) AS terakhir")
            ->groupByRaw($kunci)
            ->orderByDesc('terakhir')
            ->get();

        return view('planner.pilih_periode', compact('periodes'));
    }

    /** Dropdown master: jarang berubah, cache 10 menit */
    private function masterLists(): array
    {
        return Cache::remember('planner.master_lists', 600, function () {
            $d = fn($t, $c) => DB::table($t)->whereNotNull($c)->where($c, '!=', '')
                                 ->distinct()->orderBy($c)->pluck($c);
            return [
                'tujuanList'        => $d('tujuanfillterr', 'tujuan'),
                'pulauList'         => $d('tujuanfillterr', 'pulau'),
                'areas'             => $d('tujuanfillterr', 'area'),
                'distChannelList'   => $d('tujuanfillterr', 'dist_channel'),
                'ekpedisiList'      => $d('tarif_pengiriman', 'ekpedisi'),
                'mobilList'         => $d('tarif_pengiriman', 'mobil'),
                'routeList'         => $d('tarif_pengiriman', 'route'),
                'reasonOptimalList' => $d('akurasi3', 'reason_optimal'),
                'reasonGudangList'  => $d('akurasi3', 'reason_gudang'),
            ];
        });
    }

    private function gudangCells($r, string $s): array
    {
        $n    = $s === '' ? 1 : (int) ltrim($s, '_');
        $info = $this->hitungGudang(
            $r->{'tanggal_tiba_gudang' . $s},   $r->{'waktu_tiba_gudang' . $s},
            $r->{'tanggal_keluar_gudang' . $s}, $r->{'waktu_keluar_gudang' . $s}
        );

        if (!$info) {
            $durasi = '-';
            $status = '<span class="badge gray">-</span>';
            $sla    = '<span class="badge bg-secondary">-</span>';
        } else {
            $durasi = e($info['text']);
            $status = $info['delay']
                ? '<span class="badge red">Delay</span>'
                : '<span class="badge green">On Time</span>';
            $sla = $info['delay']
                ? '<span class="badge red">H+' . $info['hari'] . '</span>'
                : '<span class="badge bg-success">Sesuai SLA</span>';
        }

        return [
            '<span class="g' . $n . '-durasi">' . $durasi . '</span>',
            '<span class="g' . $n . '-status">' . $status . '</span>',
            '<span class="g' . $n . '-sla">'    . $sla    . '</span>',
        ];
    }

    private function cariKapasitasTarif($route, $mobil, $ekpedisi = null)
    {
        if (!$route || !$mobil) {
            return ['kubikasi' => null, 'tonase' => null];
        }

        $normalize = function ($v) {
            if (!$v) return '';
            $v = str_replace("\xc2\xa0", ' ', $v);
            $v = preg_replace('/\s*-\s*/', '-', $v);
            $v = preg_replace('/\s+/', ' ', trim($v));
            return mb_strtolower($v);
        };

        $routeKey    = $normalize($route);
        $mobilKey    = $normalize($mobil);
        $ekpedisiKey = $ekpedisi ? $normalize($ekpedisi) : '';

        $candidates = DB::table('tarif_pengiriman')
            ->whereNotNull('route')
            ->whereNotNull('mobil')
            ->get()
            ->filter(fn($t) => $normalize($t->route) === $routeKey);

        if ($candidates->isEmpty()) {
            return ['kubikasi' => null, 'tonase' => null];
        }

        $match = null;

        if ($ekpedisiKey !== '') {
            $match = $candidates->first(function ($t) use ($normalize, $ekpedisiKey, $mobilKey) {
                return $normalize($t->ekpedisi) === $ekpedisiKey
                    && str_starts_with($normalize($t->mobil), $mobilKey);
            });
        }

        if (!$match) {
            $match = $candidates->first(fn($t) => str_starts_with($normalize($t->mobil), $mobilKey));
        }

        return [
            'kubikasi' => $match->kubikasi ?? null,
            'tonase'   => $match->tonase ?? null,
        ];
    }

    private function statusOptimal($hasilKubik, $hasilTonase): ?string
    {
        if ($hasilKubik === null && $hasilTonase === null) return null;

        return (($hasilKubik ?? 0) >= 85 || ($hasilTonase ?? 0) >= 85)
            ? 'OPTIMAL'
            : 'TIDAK OPTIMAL';
    }

    /** Status optimal per no_shipment: hasil_kubik & hasil_tonase semua grup (tujuan) dijumlah dulu */
    private function optimalPerShipment(array $groupAgg): array
    {
        $k = [];
        $t = [];

        foreach ($groupAgg as $g) {
            $h  = $this->hitungHasilOptimal($g->sum_kubik, $g->kap_kubik, $g->sum_tonase, $g->kap_tonase);
            $no = $g->no_shipment;

            if ($h['hasil_kubik']  !== null) $k[$no] = ($k[$no] ?? 0) + $h['hasil_kubik'];
            if ($h['hasil_tonase'] !== null) $t[$no] = ($t[$no] ?? 0) + $h['hasil_tonase'];
        }

        $out = [];
        foreach (array_unique(array_merge(array_keys($k), array_keys($t))) as $no) {
            $out[$no] = $this->statusOptimal($k[$no] ?? null, $t[$no] ?? null);
        }
        return $out;
    }

    private function hitungHasilOptimal($totalKubik, $kubikasi, $totalTonase, $tonase)
    {
        $hasilKubik  = null;
        $hasilTonase = null;

        if ($kubikasi !== null && (float) $kubikasi > 0 && $totalKubik !== null) {
            $hasilKubik = round(((float) $totalKubik / (float) $kubikasi) * 100, 2);
        }

        if ($tonase !== null && (float) $tonase > 0 && $totalTonase !== null) {
            $hasilTonase = round(((float) $totalTonase / (float) $tonase) * 100, 2);
        }

        $pengirimanOptimal = $this->statusOptimal($hasilKubik, $hasilTonase);

        return [
            'hasil_kubik'         => $hasilKubik,
            'hasil_tonase'        => $hasilTonase,
            'pengiriman_optimal'  => $pengirimanOptimal,
        ];
    }

    private function cleanDecimalPlanner($value): ?float
    {
        if ($value === null || $value === '' || $value === '-') return null;

        if (is_numeric($value)) return (float) $value;

        $value = trim((string) $value);

        if (preg_match('/^\d+,\d+$/', $value)) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * =====================================================
     * ALERT CONTROL (ringkasan field kosong)
     * =====================================================
     */
    public function alerts(Request $request)
    {
        $fieldsMap = [
            'mobil'       => 'Mobil',
            'ekpedisi'    => 'Ekspedisi',
            'route'       => 'Route',
            'kubikasi'    => 'Kubikasi',
            'nama_driver' => 'Nama Driver',
            'no_pol'      => 'No Pol',
        ];

        // 1 alert per no_shipment + tujuan (bukan per rincian DO)
        $query = $this->repQuery()
            ->select('id', 'no_shipment', 'mobil', 'ekpedisi', 'route', 'kubikasi', 'nama_driver', 'no_pol');

        // filter ikut sama seperti dataAjax()
        if ($vals = $this->multiInput($request, 'planner_filter')) {
            $query->whereIn('planner', $vals);
        }
        if ($vals = $this->multiInput($request, 'area_filter')) {
            $query->whereIn('area', $vals);
        }
        if ($vals = $this->multiInput($request, 'dist_channel_filter')) {
            $query->whereIn('dist_channel', $vals);
        }

        $this->filterPeriode($query, $request);

        $rows = $query->where(function ($q) {
                $q->whereNull('mobil')->orWhere('mobil', '')
                  ->orWhereNull('ekpedisi')->orWhere('ekpedisi', '')
                  ->orWhereNull('route')->orWhere('route', '')
                  ->orWhereNull('kubikasi')->orWhere('kubikasi', '')
                  ->orWhereNull('nama_driver')->orWhere('nama_driver', '')
                  ->orWhereNull('no_pol')->orWhere('no_pol', '');
            })
            ->get();

        $alertList = [];
        $missingSummary = [];

        foreach ($rows as $r) {
            $missing = [];
            foreach ($fieldsMap as $col => $label) {
                if (trim((string) $r->$col) === '') {
                    $missing[] = $label;
                    $missingSummary[$label] = ($missingSummary[$label] ?? 0) + 1;
                }
            }
            $alertList[] = [
                'id'         => $r->id,
                'shipment'   => $r->no_shipment ?: '(tanpa no shipment)',
                'missing'    => $missing,
                'emptyCount' => count($missing),
            ];
        }

        usort($alertList, fn ($a, $b) => $b['emptyCount'] <=> $a['emptyCount']);

        return response()->json([
            'alerts'         => $alertList,
            'missingSummary' => $missingSummary,
        ]);
    }

    private function hitungBiayaKirimRow($totalKubik, $kubikasi, $biayaTarif): float
    {
        $biayaTarif = (float) $biayaTarif;

        if ((float) $kubikasi > 0 && (float) $totalKubik > 0) {
            return round(((float) $totalKubik / (float) $kubikasi) * $biayaTarif, 2);
        }
        return $biayaTarif;
    }

    private function cariBiayaKirimOtomatis($route, $mobil, $ekpedisi = null)
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

        $routeKey    = $normalize($route);
        $mobilKey    = $normalize($mobil);
        $ekpedisiKey = $ekpedisi ? $normalize($ekpedisi) : '';

        $candidates = DB::table('tarif_pengiriman')
            ->whereNotNull('route')
            ->whereNotNull('mobil')
            ->get()
            ->filter(fn ($t) => $normalize($t->route) === $routeKey);

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($ekpedisiKey !== '') {
            $strict = $candidates->first(function ($t) use ($normalize, $ekpedisiKey, $mobilKey) {
                return $normalize($t->ekpedisi) === $ekpedisiKey
                    && str_starts_with($normalize($t->mobil), $mobilKey);
            });

            if ($strict) {
                return $strict->biaya_kirim;
            }
        }

        $fallback = $candidates->first(fn ($t) => str_starts_with($normalize($t->mobil), $mobilKey));

        return $fallback->biaya_kirim ?? null;
    }

    private function getTibaGudangTerdekatRequest($request)
    {
        return collect(array_map(
            fn($s) => $request->{"tanggal_tiba_gudang{$s}"}, $this->gudangSuffixes()
        ))->filter()->sort()->first();
    }

    private function getKeluarGudangInfoRequest($request)
    {
        $cycles = [];
        foreach ($this->gudangSuffixes() as $s) {
            $cycles[] = [
                'planning' => $request->{"planning_loading{$s}"},
                'tiba'     => $request->{"tanggal_tiba_gudang{$s}"},
                'keluar'   => $request->{"tanggal_keluar_gudang{$s}"},
            ];
        }

        $blocked = false;
        $keluarTimestamps = [];

        foreach ($cycles as $c) {
            $started = !empty($c['planning']) || !empty($c['tiba']);
            $selesai = !empty($c['keluar']);

            if ($started && !$selesai) {
                $blocked = true;
            }

            if ($selesai) {
                $keluarTimestamps[] = strtotime($c['keluar']);
            }
        }

        return [
            'blocked' => $blocked,
            'keluar'  => !empty($keluarTimestamps) ? max($keluarTimestamps) : null,
        ];
    }

    private function hitungSla($request)
    {
        $data = [
            'lama_waktu_pencarian' => null,
            'sla_dapat_mobil'      => null,
            'status_pengiriman'    => null,

            'lama_digudang'        => null,
            'status_gudang'        => null,
            'sla_loading'          => null,
            'lama_digudang_4' => null, 'status_gudang_4' => null, 'sla_loading_4' => null,
            'lama_digudang_5' => null, 'status_gudang_5' => null, 'sla_loading_5' => null,

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

        $start = $request->rencana_kirim
            ? date('Y-m-d H:i:s', strtotime($request->rencana_kirim))
            : null;

        $end = $request->tanggal_dpt_unit
            ? date('Y-m-d H:i:s', strtotime($request->tanggal_dpt_unit))
            : null;

        $diff = $hitungSelisih($start, $end);

        $data['lama_waktu_pencarian'] = $diff['text'] ?? null;

        if ($start && $end) {

            $area = strtoupper(trim($request->area ?? ''));

            $tanggalRencana = strtotime(date('Y-m-d', strtotime($start)));
            $tanggalDptUnit = strtotime(date('Y-m-d', strtotime($end)));

            $selisihHari = floor(
                ($tanggalDptUnit - $tanggalRencana) / 86400
            );

            if (
                $area == 'JABODETABEK' ||
                $area == 'JABODEBEK' ||
                $area == 'BANTEN'
            ) {
                $batasHari = 0;
            } elseif ($area == 'JAWA_BARAT' || $area == 'JAWA BARAT') {
                $batasHari = 1;
            } else {
                $batasHari = 2;
            }

            if ($selisihHari > $batasHari) {
                $data['sla_dapat_mobil']   = 'Delay';
                $data['status_pengiriman'] = 'Terlambat';
            } else {
                $data['sla_dapat_mobil']   = 'On Time';
                $data['status_pengiriman'] = 'Sudah Dapat';
            }

        } else {
            $data['sla_dapat_mobil']   = null;
            $data['status_pengiriman'] = null;
        }

        foreach ($this->gudangSuffixes() as $s) {
            $info = $this->hitungGudang(
                $request->{'tanggal_tiba_gudang' . $s},
                $request->{'waktu_tiba_gudang' . $s},
                $request->{'tanggal_keluar_gudang' . $s},
                $request->{'waktu_keluar_gudang' . $s}
            );

            if ($info) {
                $data['lama_digudang' . $s] = $info['text'];
                $data['status_gudang' . $s] = $info['delay'] ? 'Delay' : 'On Time';
                $data['sla_loading' . $s]   = $info['delay'] ? 'H+' . $info['hari'] : 'Sesuai SLA';
            }
        }

        return $data;
    }

    public function slaOntime(Request $request)
    {
        // 1 baris per no_shipment + tujuan
        $tibaCols = array_map(fn($s) => "tanggal_tiba_gudang{$s}", $this->gudangSuffixes());

        $query = $this->repQuery()->where(function ($q) use ($tibaCols) {
            foreach ($tibaCols as $c) $q->orWhereNotNull($c);
        });
        if ($request->filled('bulan')) {
            $query->where(function ($q) use ($request, $tibaCols) {
                foreach ($tibaCols as $c) $q->orWhereMonth($c, $request->bulan);
            });
        }
        if ($request->filled('tahun')) {
            $query->where(function ($q) use ($request, $tibaCols) {
                foreach ($tibaCols as $c) $q->orWhereYear($c, $request->tahun);
            });
        }

        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        $list = $query->get()
            ->map(function ($row) {

                $sla = $this->hitungSla($row);
                $row->lama_waktu_pencarian = $sla['lama_waktu_pencarian'];
                $row->sla_dapat_mobil      = $sla['sla_dapat_mobil'];
                $row->status_pengiriman    = $sla['status_pengiriman'];

                $gudang = []; $keluar = [];
                foreach ($this->gudangSuffixes() as $s) {
                    $n = $s === '' ? 1 : (int) ltrim($s, '_');
                    $row->{"lama_digudang{$s}"} = $sla["lama_digudang{$s}"];
                    $row->{"status_gudang{$s}"} = $sla["status_gudang{$s}"];
                    $row->{"sla_loading{$s}"}   = $sla["sla_loading{$s}"];
                    if (!empty($row->{"tanggal_tiba_gudang{$s}"}))   $gudang[$n] = strtotime($row->{"tanggal_tiba_gudang{$s}"});
                    if (!empty($row->{"tanggal_keluar_gudang{$s}"})) $keluar[$n] = strtotime($row->{"tanggal_keluar_gudang{$s}"});
                }
                $row->gudang_sla             = count($gudang) ? array_search(min($gudang), $gudang) : null;
                $row->gudang_keluar_terakhir = count($keluar) ? array_search(max($keluar), $keluar) : null;
                return $row;
            })
            ->values();

        $list_area = Cache::remember('planner.area_group', 600, fn() =>
            DB::table('logistik_pengiriman')
                ->select('area')
                ->whereNotNull('area')
                ->groupBy('area')
                ->orderBy('area')
                ->get());

        return view('planner.sla_ontime', compact('list', 'list_area'));
    }

    public function slaDelay(Request $request)
    {
        // 1 baris per no_shipment + tujuan
        $query = $this->repQuery()
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

        if ($request->filled('area')) {
            $query->where('area', $request->area);
        }

        $list = $query
            ->orderBy('tanggal_naik_logistik', 'DESC')
            ->paginate(10)
            ->withQueryString();

        $list_area = Cache::remember('planner.area_group', 600, fn() =>
            DB::table('logistik_pengiriman')
                ->select('area')
                ->whereNotNull('area')
                ->groupBy('area')
                ->orderBy('area')
                ->get());

        return view('planner.sla_delay', [
            'title' => 'SLA DELAY',
            'list' => $list,
            'list_area' => $list_area
        ]);
    }

    public function updateGudang23(Request $request)
    {
        $request->validate([
            'no_shipment' => 'required'
        ]);

        $sla = $this->hitungSla($request);

        $data = [

            'tanggal_tiba_gudang_2'   => $request->tanggal_tiba_gudang_2,
            'tanggal_keluar_gudang_2' => $request->tanggal_keluar_gudang_2,

            'tanggal_tiba_gudang_3'   => $request->tanggal_tiba_gudang_3,
            'tanggal_keluar_gudang_3' => $request->tanggal_keluar_gudang_3,

            'lama_digudang_2' => $sla['lama_digudang_2'],
            'status_gudang_2' => $sla['status_gudang_2'],
            'sla_loading_2'   => $sla['sla_loading_2'],

            'lama_digudang_3' => $sla['lama_digudang_3'],
            'status_gudang_3' => $sla['status_gudang_3'],
            'sla_loading_3'   => $sla['sla_loading_3'],

            'updated_at' => now()
        ];

        LogistikPengiriman::where(
            'no_shipment',
            $request->no_shipment
        )->update($data);

        $this->forgetCaches();

        return response()->json([
            'status' => 'success',
            'message' => 'Data Gudang 2 & Gudang 3 berhasil diupdate'
        ]);
    }

    public function summaryArea()
    {
        // 1 baris per no_shipment + tujuan, dihitung di SQL
        $summary_area = $this->repQuery()
            ->selectRaw("COALESCE(area,'') AS area, COUNT(*) AS total")
            ->groupByRaw("COALESCE(area,'')")
            ->orderByDesc('total')
            ->get();

        return view('planner.summary_area', compact('summary_area'));
    }

    private function getTibaGudangTerdekat($row)
    {
        return collect(array_map(
            fn($s) => $row->{"tanggal_tiba_gudang{$s}"}, $this->gudangSuffixes()
        ))->filter()->sort()->values()->first();
    }

    public function armada(Request $request)
    {
        // 1 baris per no_shipment + tujuan
        $query = $this->repQuery()
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

                if ($row->rencana_kirim && $row->tanggal_dpt_unit) {

                    $awal = new \DateTime(
                        date('Y-m-d H:i:s', strtotime($row->rencana_kirim))
                    );

                    $akhir = new \DateTime(
                        date('Y-m-d H:i:s', strtotime($row->tanggal_dpt_unit))
                    );

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

    private function inGudangQuery()
    {
        // per no_shipment + tujuan
        return $this->repQuery()
            ->whereRaw($this->selesaiGudangSql() . ' = 0');
    }

    public function inGudang(Request $request)
    {
        $base = $this->inGudangQuery();
        $this->applyFilter($base, $request);

        if ($request->filled('q')) {
            $s = trim($request->input('q'));
            $base->where(function ($q) use ($s) {
                foreach (['no_shipment', 'tujuan', 'ekpedisi', 'nama_driver', 'no_pol', 'mobil', 'planner'] as $col) {
                    $q->orWhere($col, 'like', "%{$s}%");
                }
            });
        }
        if ($request->filled('planner')) {
            $base->where('planner', $request->input('planner'));
        }

        // ===== ringkasan =====
        $kolomGudang = array_merge(...$this->gudangCycles());

        $total   = (clone $base)->count();
        $qBelum  = clone $base;
        foreach ($kolomGudang as $c) {
            $qBelum->whereRaw("NULLIF(TRIM({$c}), '') IS NULL");
        }
        $belumInput = $qBelum->count();

        $summary = [
            'total'       => $total,
            'belum_input' => $belumInput,
            'sedang'      => $total - $belumInput,
        ];

        $belumSql   = implode(' AND ', array_map(fn($c) => "NULLIF(TRIM({$c}), '') IS NULL", $kolomGudang));
        $selesaiSql = implode(' + ', array_map(
            fn($s) => "(NULLIF(TRIM(tanggal_keluar_gudang{$s}), '') IS NOT NULL)",
            $this->gudangSuffixes()
        ));
        $sortMap = [
            'no_shipment'    => 'CAST(no_shipment AS UNSIGNED)',
            'planner'        => 'planner',
            'tujuan'         => 'tujuan',
            'area'           => 'area',
            'dist_channel'   => 'dist_channel',
            'rencana_kirim'  => 'rencana_kirim',
            'ekpedisi'       => 'ekpedisi',
            'driver'         => 'nama_driver',
            'gudang_selesai' => "({$selesaiSql})",
            'posisi'         => "(CASE WHEN {$belumSql} THEN 1 ELSE 0 END)",
        ];

        $sort = $request->input('sort');
        $dir  = strtolower($request->input('dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $q = clone $base;

        if ($sort && isset($sortMap[$sort])) {
            $q->orderByRaw("{$sortMap[$sort]} {$dir}");
        }

        // fallback / tie-breaker supaya urutan antar halaman stabil
        $list = $q->orderBy('tanggal_naik_logistik', 'ASC')
            ->orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
            ->orderBy('id', 'ASC')
            ->paginate(50)
            ->withQueryString();

        $cycles = [];
        foreach (self::GUDANG as $s => $nama) {
            $cycles[] = [$nama, "planning_loading{$s}", "tanggal_tiba_gudang{$s}", "tanggal_keluar_gudang{$s}"];
        }

        $list->getCollection()->transform(function ($r) use ($cycles) {
            $selesai     = [];
            $menggantung = [];

            foreach ($cycles as [$nama, $plan, $tiba, $keluar]) {
                $started = !empty($r->$plan) || !empty($r->$tiba);
                $done    = !empty($r->$keluar);

                if ($done) {
                    $selesai[] = $nama;
                }

                if ($started && !$done) {
                    $menggantung[] = empty($r->$tiba)
                        ? "Planning loading {$nama}, belum tiba"
                        : "Di gudang {$nama}, belum keluar";
                }
            }

            if ($menggantung) {
                $r->posisi_label = implode(' | ', $menggantung);
                $r->posisi_class = 'orange';
            } else {
                $r->posisi_label = 'Belum diinput';
                $r->posisi_class = 'red';
            }

            $r->gudang_selesai = $selesai ? implode(', ', $selesai) : '-';
            return $r;
        });

        // angka (qty, nilai muatan, kubik, tonase, CR, hasil) = totalan per no_shipment + tujuan
        $this->withGroupTotals($list->getCollection());

        $areaList = Cache::remember('planner.area_list', 600, fn() =>
            DB::table('logistik_pengiriman')->whereNotNull('area')->distinct()->orderBy('area')->pluck('area'));

        $plannerList = Cache::remember('planner.planner_list', 600, fn() =>
            DB::table('logistik_pengiriman')->whereNotNull('planner')->where('planner', '!=', '')
                ->distinct()->orderBy('planner')->pluck('planner'));

        $channelList = Cache::remember('planner.channel_list', 600, fn() =>
            DB::table('logistik_pengiriman')
                ->whereNotNull('dist_channel')->where('dist_channel', '!=', '')
                ->distinct()->orderBy('dist_channel')->pluck('dist_channel'));

        return view('planner.in_gudang', compact('list', 'summary', 'areaList', 'plannerList', 'channelList'));
    }

    public function importDates(Request $request)
    {
        // 1 baris wakil (MIN id) per no_shipment, tujuan tidak ikut dihitung
        $q = DB::table('logistik_pengiriman')
            ->whereIn('id', function ($sub) {
                $sub->from('logistik_pengiriman')
                    ->selectRaw('MIN(id)')
                    ->groupByRaw(self::SHIPMENT_KEY);
            })
            ->whereNotNull('tanggal_shipment');

        if ($vals = $this->multiInput($request, 'planner_filter')) {
            $q->whereIn('planner', $vals);
        }
        if ($vals = $this->multiInput($request, 'area_filter')) {
            $q->whereIn('area', $vals);
        }
        if ($vals = $this->multiInput($request, 'dist_channel_filter')) {
            $q->whereIn('dist_channel', $vals);
        }

        $this->filterPeriode($q, $request);

        $sel = $this->selesaiGudangSql();

        $rows = $q->selectRaw(
                "DATE(tanggal_shipment) AS tgl, COUNT(*) AS total, "
                . "SUM(CASE WHEN {$sel} = 0 THEN 1 ELSE 0 END) AS belum"
            )
            ->groupByRaw('DATE(tanggal_shipment)')
            ->orderByRaw('DATE(tanggal_shipment) DESC')
            ->get();

        return response()->json(
            $rows->mapWithKeys(fn($r) => [
                $r->tgl => ['total' => (int) $r->total, 'belum' => (int) $r->belum],
            ])
        );
    }

    public function armadaDelay(Request $request)
    {
        // 1 baris per no_shipment + tujuan
        $query = $this->repQuery()
            ->whereNotNull('tanggal_dpt_unit')
            ->where(function ($q) {
                foreach ($this->gudangSuffixes() as $s) {
                    $q->orWhereNotNull("tanggal_tiba_gudang{$s}");
                }
            });

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

                    $awal = new \DateTime(
                        date('Y-m-d H:i:s', strtotime($row->tanggal_dpt_unit))
                    );

                    $akhir = new \DateTime(
                        date('Y-m-d H:i:s', strtotime($tibaGudang))
                    );

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
            })
            ->filter(fn($row) => $row->sla_dapat_mobil === 'Delay');

        return view('planner.armada_delay', compact('logistik'));
    }

    public function belumArmada(Request $request)
    {
        // 1 baris per no_shipment + tujuan
        $query = $this->repQuery()
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

        $logistik = $query
            ->orderBy('tanggal_naik_logistik', 'DESC')
            ->get();

        return view('planner.belum_armada', compact('logistik'));
    }

    public function baelumArmada(Request $request)
    {
        $query = DB::table('logistik_pengiriman')
            ->where(function ($q) {
                $q->whereIn('status_pengiriman', ['Belum Dapat', 'Pending', 'PENDING'])
                  ->orWhereIn('status_kendaraan', ['Belum Dapat', 'Pending', 'PENDING']);
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

    public function delete($id)
    {
        $row = LogistikPengiriman::findOrFail($id);
        $periode = $row->periode_shipment ?? null;
        $row->delete();

        $this->forgetCaches($periode);

        return redirect()
            ->back()
            ->with('success', 'Data berhasil dihapus');
    }

    /**
     * Hitung durasi di gudang (tanggal + jam).
     * Delay jika > 24 jam (1440 menit). Jam kosong dianggap 00:00.
     */
    private function hitungGudang($tglTiba, $wTiba, $tglKeluar, $wKeluar): ?array
    {
        if (empty($tglTiba) || empty($tglKeluar)) return null;

        $awal  = strtotime(date('Y-m-d', strtotime($tglTiba))   . ' ' . ($this->cleanTime($wTiba)   ?: '00:00:00'));
        $akhir = strtotime(date('Y-m-d', strtotime($tglKeluar)) . ' ' . ($this->cleanTime($wKeluar) ?: '00:00:00'));

        $menit = max(0, intdiv($akhir - $awal, 60));
        $hari  = intdiv($menit, 1440);
        $jam   = intdiv($menit % 1440, 60);
        $mnt   = $menit % 60;

        $text = trim(($hari ? "{$hari} Hari " : '') . ($jam ? "{$jam} Jam " : '') . "{$mnt} Menit");

        return [
            'text'  => $text,
            'delay' => $menit > 1440,   // lebih dari 24 jam
            'hari'  => $hari,           // dipakai untuk label H+
        ];
    }
}