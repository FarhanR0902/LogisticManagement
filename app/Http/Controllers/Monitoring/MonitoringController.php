<?php

namespace App\Http\Controllers\Monitoring;
use App\Exports\InTransitExport;
use App\Traits\LogsInput;
use App\Http\Controllers\Controller;
use App\Models\LogistikPengiriman;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exports\MonitoringExport;
use Maatwebsite\Excel\Facades\Excel;

class MonitoringController extends Controller
{
     use LogsInput;

    // =====================================================
    // DASHBOARD (tidak berubah — sudah pakai COUNT/aggregate,
    // bukan load semua baris jadi objek PHP)
    // =====================================================
private const GUDANG = [
    ''   => 'KACS',
    '_2' => 'SENTUL',
    '_3' => 'CCIE',
    '_4' => 'P5',
    '_5' => 'FIFATEX',
];
/** [planning, tiba, keluar] per gudang */
private function gudangCycles(): array
{
    return array_map(
        fn($s) => ["planning_loading{$s}", "tanggal_tiba_gudang{$s}", "tanggal_keluar_gudang{$s}"],
        $this->gudangSuffixes()
    );
}

/** SQL: ada siklus gudang menggantung (sudah planning/tiba, belum keluar) */
private function blockedSql(): string
{
    $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
    $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

    $parts = [];
    foreach ($this->gudangCycles() as [$p, $t, $k]) {
        $parts[] = '((' . $filled($p) . ' OR ' . $filled($t) . ') AND ' . $empty($k) . ')';
    }
    return '(' . implode(' OR ', $parts) . ')';
}
private function gudangSuffixes(): array
{
    return array_keys(self::GUDANG);
}

    public function dashboard()
{
    $total_data = $this->countGroups(LogistikPengiriman::query());

    $total_tiba_ontime = $this->countGroups(LogistikPengiriman::where('sla_tiba', 'On Time'));
    $total_tiba_delay  = $this->countGroups(LogistikPengiriman::where('sla_tiba', 'Delay'));

    $total_bongkar_ontime = $this->countGroups(LogistikPengiriman::where('sla_bongkar', 'On Time'));
    $total_bongkar_delay  = $this->countGroups(LogistikPengiriman::where('sla_bongkar', 'Delay'));

    $total_ontime_total       = $this->countGroups(LogistikPengiriman::where('status_akhir', 'On Time Total'));
    $total_delay_perjalanan   = $this->countGroups(LogistikPengiriman::where('status_akhir', 'Delay Perjalanan'));
    $total_delay_pembongkaran = $this->countGroups(LogistikPengiriman::where('status_akhir', 'Delay Pembongkaran'));
    $total_delay_total        = $this->countGroups(LogistikPengiriman::where('status_akhir', 'Delay Total'));

    $total_in_transit = $this->countGroups($this->inTransitQuery());

    $delivered_ontime = $this->countGroups(LogistikPengiriman::where('monitoring_alert', 'Delivered On Time'));
    $delivered_delay  = $this->countGroups(LogistikPengiriman::where('monitoring_alert', 'Delivered Delay'));

    $belum_tiba = $this->countGroups(LogistikPengiriman::whereNull('tanggal_tiba'));
    $belum_bongkar = $this->countGroups(
        LogistikPengiriman::whereNotNull('tanggal_tiba')->whereNull('tanggal_bongkar')
    );

    // summary area: hitung per grup (shipment + tujuan), bukan per DO
    $summary_area = DB::table('logistik_pengiriman')
        ->whereIn('id', $this->groupIdsQuery(DB::table('logistik_pengiriman')))
        ->select('area', DB::raw('COUNT(*) as total'))
        ->groupBy('area')
        ->orderByDesc('total')
        ->get();

    return view('monitoring.dashboard', compact(
        'total_data',
        'total_tiba_ontime',
        'total_tiba_delay',
        'total_in_transit',
        'total_bongkar_ontime',
        'total_bongkar_delay',
        'total_ontime_total',
        'total_delay_perjalanan',
        'total_delay_pembongkaran',
        'total_delay_total',
        'delivered_ontime',
        'delivered_delay',
        'belum_tiba',
        'belum_bongkar',
        'summary_area'
    ));
}

/** Validasi isian monitoring. Kembalikan daftar pesan (kosong = lolos). */
private function cekMonitoring(array $d, LogistikPengiriman $l): array
{
    $err = [];

    // tanggal: tahun 4 digit + tanggal nyata. Return timestamp hari, atau null.
    $tgl = function ($v, $label) use (&$err) {
        $v = is_string($v) ? trim($v) : $v;
        if ($v === null || $v === '') return null;
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            || $m[1] < 2020 || $m[1] > 2100) {
            $err[] = "{$label}: tanggal tidak valid ({$v}). Tahun harus 4 digit, contoh 2026.";
            return null;
        }
        return strtotime(substr($v, 0, 10));
    };

    $jam = function ($v, $label) use (&$err) {
        $v = trim((string) ($v ?? ''));
        if ($v === '') return null;
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v, $m)) {
            $err[] = "{$label} harus format HH:MM, contoh 08:30 (diisi: {$v}).";
            return null;
        }
        return ((int) $m[1] * 3600) + ((int) $m[2] * 60);
    };

    $tiba    = $tgl($d['tanggal_tiba']    ?? null, 'Tanggal Tiba');
    $bongkar = $tgl($d['tanggal_bongkar'] ?? null, 'Tanggal Bongkar');
    $tgl($d['act_pgi_date'] ?? null, 'Act PGI Date');
    $etd = $tgl($d['etd'] ?? null, 'ETD');
    $eta = $tgl($d['eta'] ?? null, 'ETA');
    $atd = $tgl($d['atd'] ?? null, 'ATD');
    $ata = $tgl($d['ata'] ?? null, 'ATA');

    $jTiba    = $jam($d['waktu_tiba']    ?? null, 'Jam Tiba');
    $jBongkar = $jam($d['waktu_bongkar'] ?? null, 'Jam Bongkar');

    // jam tanpa tanggal (sekarang jamnya dibuang diam-diam)
    if (empty($d['tanggal_tiba'])    && trim((string) ($d['waktu_tiba'] ?? ''))    !== '') $err[] = 'Jam Tiba diisi tapi Tanggal Tiba kosong.';
    if (empty($d['tanggal_bongkar']) && trim((string) ($d['waktu_bongkar'] ?? '')) !== '') $err[] = 'Jam Bongkar diisi tapi Tanggal Bongkar kosong.';

    // bongkar tanpa tiba
    if ($bongkar && !$tiba && empty($d['tanggal_tiba'])) {
        $err[] = 'Tanggal Bongkar diisi tapi Tanggal Tiba masih kosong.';
    }

    // bongkar lebih awal dari tiba (tanggal + jam)
    if ($tiba && $bongkar) {
        $a = $tiba + ($jTiba ?? 0);
        $b = $bongkar + ($jBongkar ?? 0);
        if ($b < $a) $err[] = 'Waktu Bongkar lebih awal dari waktu Tiba.';
    }

    // tiba lebih awal dari keluar gudang terakhir
    $keluar = $this->getKeluarGudangInfo($l)['keluar'];
    if ($tiba && $keluar && $tiba < strtotime(date('Y-m-d', $keluar))) {
        $err[] = 'Tanggal Tiba (' . date('d-m-Y', $tiba) . ') lebih awal dari tanggal keluar gudang (' . date('d-m-Y', $keluar) . ').';
    }

    // transport laut
    if ($etd && $eta && $eta < $etd) $err[] = 'ETA lebih awal dari ETD.';
    if ($atd && $ata && $ata < $atd) $err[] = 'ATA lebih awal dari ATD.';

    // angka
    foreach (['selisih_qty' => 'Selisih Qty', 'biaya_kuli' => 'Biaya Kuli', 'act_urutan_bongkar' => 'Urutan Bongkar'] as $k => $label) {
        $v = $d[$k] ?? null;
        if ($v !== null && $v !== '' && !is_numeric($v)) $err[] = "{$label} harus angka (diisi: {$v}).";
    }
    if (isset($d['biaya_kuli']) && is_numeric($d['biaya_kuli']) && $d['biaya_kuli'] < 0) {
        $err[] = 'Biaya Kuli tidak boleh negatif.';
    }
    if (isset($d['act_urutan_bongkar']) && is_numeric($d['act_urutan_bongkar']) && $d['act_urutan_bongkar'] < 0) {
        $err[] = 'Urutan Bongkar tidak boleh negatif.';
    }

    // selisih qty tidak boleh melebihi total DO satu grup
    if (isset($d['selisih_qty']) && is_numeric($d['selisih_qty']) && $d['selisih_qty'] > 0) {
        $total = (float) LogistikPengiriman::where('no_shipment', $l->no_shipment)
            ->whereRaw("COALESCE(tujuan,'') = ?", [(string) $l->tujuan])
            ->sum('total_do_qty_car');
        if ($total > 0 && $d['selisih_qty'] > $total) {
            $err[] = "Selisih Qty ({$d['selisih_qty']}) lebih besar dari Total DO Qty ({$total}).";
        }
    }

    return $err;
}

/** Terjemahkan exception jadi daftar kalimat yang bisa dibaca user */
private function pesanList(\Throwable $e): array
{
    if ($e instanceof ValidationException) {
        return $e->errors()['input'] ?? array_merge(...array_values($e->errors()));
    }
    if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
        return ['Data tidak ditemukan. Kemungkinan sudah dihapus user lain. Refresh halaman.'];
    }

    $msg = $e->getMessage();

    if (preg_match("/Incorrect (?:datetime|date|time|integer|decimal) value: '([^']*)' for column '([^']+)'/", $msg, $m)) {
        return ['Kolom ' . ucwords(str_replace('_', ' ', $m[2])) . " berisi nilai yang salah: '{$m[1]}'. Untuk tanggal, pastikan tahun 4 digit (contoh 2026)."];
    }
    if (preg_match("/Data too long for column '([^']+)'/", $msg, $m)) {
        return ['Isian ' . ucwords(str_replace('_', ' ', $m[1])) . ' terlalu panjang. Persingkat lalu simpan lagi.'];
    }
    if (preg_match("/Unknown column '([^']+)'/", $msg, $m)) {
        return ["Kolom {$m[1]} belum ada di database. Hubungi admin untuk menjalankan migrasi."];
    }
    if (preg_match("/Out of range value for column '([^']+)'/", $msg, $m)) {
        return ['Angka di kolom ' . ucwords(str_replace('_', ' ', $m[1])) . ' di luar batas.'];
    }
    if (stripos($msg, 'Deadlock') !== false || stripos($msg, 'Lock wait timeout') !== false) {
        return ['Data sedang dipakai user lain. Coba simpan lagi.'];
    }

    return ['Terjadi kesalahan di server. Hubungi admin. (' . mb_substr($msg, 0, 120) . ')'];
}
public function export(Request $request)
{
    $filters = $request->only([
        'pic_monitoring', 'dist_channel', 'area', 'bulan', 'tahun',
        'keluar_gudang_dari', 'keluar_gudang_sampai',
        'tgl_dari', 'tgl_sampai',
    ]);

    return Excel::download(
        new MonitoringExport($filters),
        'Monitoring_Logistik_' . date('Ymd_His') . '.xlsx'
    );
}

    // =====================================================
    // HALAMAN DATA MONITORING
    // FIX PERFORMA: tidak lagi query semua baris ke Blade.
    // View sekarang ambil data lewat DataTables server-side
    // (endpoint dataAjax()). Di sini hanya kirim dropdown list
    // (di-cache 1 jam karena jarang berubah) + modal shipment laut.
    // =====================================================
    public function dataLogistik(Request $request)
    {
        $areaList = Cache::remember('monitoring_area_list', 3600, function () {
            return LogistikPengiriman::whereNotNull('area')
                ->distinct()->orderBy('area')->pluck('area');
        });
        $distChannelList = Cache::remember('monitoring_dist_channel_list', 3600, function () {
    return LogistikPengiriman::whereNotNull('dist_channel')
        ->where('dist_channel', '!=', '')
        ->distinct()->orderBy('dist_channel')->pluck('dist_channel');
});

        $picList = Cache::remember('monitoring_pic_list', 3600, function () {
            return LogistikPengiriman::whereNotNull('pic_monitoring')
                ->distinct()->orderBy('pic_monitoring')->pluck('pic_monitoring');
        });
         $tujuanList = Cache::remember('monitoring_tujuan_list', 3600, function () {
        return DB::table('tujuanfillterr')
            ->whereNotNull('tujuan')->where('tujuan', '!=', '')
            ->distinct()->orderBy('tujuan')->pluck('tujuan');
    });
    $shipmentList = LogistikPengiriman::selectRaw('
        no_shipment,
        MAX(tujuan) as tujuan,
        MAX(nama_kapal) as nama_kapal,
        MAX(etd) as etd,
        MAX(eta) as eta,
        MAX(atd) as atd,
        MAX(ata) as ata
    ')
    ->whereNotNull('no_shipment')
    ->groupBy('no_shipment')
    ->orderBy('no_shipment')
    ->get();

        $akurasiTiba = Cache::remember('monitoring_akurasi_tiba', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_tiba');
        });

        $akurasiBongkar = Cache::remember('monitoring_akurasi_bongkar', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_bongkar');
        });

        $akurasiQty = Cache::remember('monitoring_akurasi_qty', 3600, function () {
            return DB::table('akurasi3')->distinct()->pluck('remarks_qty');
        });

        // untuk dropdown "No Shipment" di modal shipment laut —
        // ambil list ringan (id + no_shipment + tujuan saja), bukan full row
        // $shipmentList = LogistikPengiriman::select('no_shipment', 'tujuan')
        //     ->whereNotNull('no_shipment')
        //     ->distinct()
        //     ->orderBy('no_shipment')
        //     ->get();

        return view('monitoring.data_monitoring', compact(
            'areaList',
            'picList',
             'tujuanList',
             'distChannelList',
            'akurasiTiba',
            'akurasiBongkar',
            'akurasiQty',
            'shipmentList'
        ));
    }
        public function updateTransportLaut(Request $request)
{
    $request->validate(['no_shipment' => 'required']);

    $data = array_filter([
        'nama_kapal' => $request->nama_kapal,
        'etd' => $request->etd,
        'eta' => $request->eta,
        'atd' => $request->atd,
        'ata' => $request->ata,
    ], fn($v) => $v !== null && $v !== '');

    if (empty($data)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Tidak ada data yang diisi',
        ], 422);
    }
    $err = [];
foreach (['etd' => 'ETD', 'eta' => 'ETA', 'atd' => 'ATD', 'ata' => 'ATA'] as $k => $label) {
    $v = $request->$k;
    if ($v && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || $m[1] < 2020 || $m[1] > 2100)) {
        $err[] = "{$label}: tanggal tidak valid ({$v}). Tahun harus 4 digit.";
    }
}
if ($request->etd && $request->eta && $request->eta < $request->etd) $err[] = 'ETA lebih awal dari ETD.';
if ($request->atd && $request->ata && $request->ata < $request->atd) $err[] = 'ATA lebih awal dari ATD.';
if ($err) {
    return response()->json(['status' => 'error', 'title' => 'Data gagal disimpan', 'message' => implode("\n", $err), 'errors' => $err], 422);
}

try {
    LogistikPengiriman::where('no_shipment', $request->no_shipment)->update($data);
} catch (\Throwable $e) {
    report($e);
    $errs = $this->pesanList($e);
    return response()->json(['status' => 'error', 'title' => 'Gagal menyimpan', 'message' => implode("\n", $errs), 'errors' => $errs], 500);
}

    LogistikPengiriman::where('no_shipment', $request->no_shipment)->update($data);

    return response()->json([
        'status' => 'success',
        'message' => 'Data transport laut berhasil diupdate'
    ]);
}
/** 1 id wakil (MIN id) per shipment + tujuan dari query yang sudah terfilter */
private function groupIdsQuery($query)
{
    return (clone $query)
        ->select(DB::raw('MIN(id) as id'))
        ->groupByRaw("COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)), COALESCE(tujuan,'')");
}

private function countGroups($query): int
{
    return DB::query()->fromSub($this->groupIdsQuery($query), 'g')->count();
}

/** jumlah per shipment + tujuan untuk baris yang tampil di halaman ini */
private function groupAgg($rows): array
{
    $shipments = $rows->pluck('no_shipment')
        ->filter(fn($v) => trim((string) $v) !== '')->unique()->values();

    if ($shipments->isEmpty()) return [];

    return LogistikPengiriman::query()
        ->whereIn('no_shipment', $shipments)
        ->select(
            'no_shipment',
            DB::raw("COALESCE(tujuan,'') as tujuan_key"),
            DB::raw('COUNT(*) as jml_do'),
            DB::raw('SUM(COALESCE(total_do_qty_car,0)) as sum_qty'),
            DB::raw('SUM(COALESCE(selisih_qty,0)) as sum_selisih'),
            DB::raw('SUM(COALESCE(qty_monitoring,0)) as sum_qty_mon'),
            DB::raw('SUM(COALESCE(total_biaya_kuli,0)) as sum_kuli')
        )
        ->groupBy('no_shipment', 'tujuan_key')
        ->get()
        ->keyBy(fn($g) => $g->no_shipment . '|' . $g->tujuan_key)
        ->all();
}
private function multiInput(Request $request, string $key): array
{
    return array_values(array_filter(
        (array) $request->input($key, []),
        fn($v) => $v !== null && $v !== ''
    ));
}

private function applyCommonFilters($query, Request $request): void
{
    if ($request->filled('jenis')) {
        $query->where('transportasi', strtoupper($request->input('jenis')));
    }

    foreach (['area', 'pic_monitoring', 'dist_channel'] as $col) {
        $vals = $this->multiInput($request, $col);
        if (!empty($vals)) {
            $query->whereIn($col, $vals);
        }
    }

  $keluarTerakhir = $this->keluarTerakhirSql();

foreach (['bulan' => 'MONTH', 'tahun' => 'YEAR'] as $key => $fn) {
    $vals = array_map('intval', $this->multiInput($request, $key));
    if (!empty($vals)) {
        $ph = implode(',', array_fill(0, count($vals), '?'));
        $query->whereRaw("{$fn}({$keluarTerakhir}) IN ({$ph})", $vals);
    }
}

if ($request->filled('keluar_gudang_dari')) {
    $query->whereRaw("DATE({$keluarTerakhir}) >= ?", [$request->input('keluar_gudang_dari')]);
}
if ($request->filled('keluar_gudang_sampai')) {
    $query->whereRaw("DATE({$keluarTerakhir}) <= ?", [$request->input('keluar_gudang_sampai')]);
};

    foreach (['bulan' => 'MONTH', 'tahun' => 'YEAR'] as $key => $fn) {
        $vals = array_map('intval', $this->multiInput($request, $key));
        if (!empty($vals)) {
            $ph = implode(',', array_fill(0, count($vals), '?'));
            $query->whereRaw("{$fn}({$keluarTerakhir}) IN ({$ph})", $vals);
        }
    }

    if ($request->filled('keluar_gudang_dari')) {
        $query->whereDate('tanggal_keluar_gudang', '>=', $request->input('keluar_gudang_dari'));
    }
    if ($request->filled('keluar_gudang_sampai')) {
        $query->whereDate('tanggal_keluar_gudang', '<=', $request->input('keluar_gudang_sampai'));
    }
}
    // =====================================================
    // ENDPOINT SERVER-SIDE DATATABLES
    // Hanya ambil & hitung baris yang benar-benar tampil
    // (10-100 baris per request), bukan semua data sekaligus.
    // =====================================================
   
public function dataAjax(Request $request)
{
    $draw        = (int) $request->input('draw', 1);
    $start       = (int) $request->input('start', 0);
    $length      = (int) $request->input('length', 10);
    $searchValue = trim((string) $request->input('search.value', ''));

    $baseQuery = LogistikPengiriman::query();

    // ================= FILTER =================
    $this->applyCommonFilters($baseQuery, $request);

    $kelFilter = array_filter((array) $request->input('kelengkapan', []));
    if (!empty($kelFilter)) {
        $levelMap = [
            'lengkap'           => [1],
            'sampai_tujuan'     => [2],
            'belum_lengkap'     => [3],
            'belum_jatuh_tempo' => [0],
        ];
        $levels = [];
        foreach ($kelFilter as $f) {
            if (is_string($f) && isset($levelMap[$f])) {
                $levels = array_merge($levels, $levelMap[$f]);
            }
        }
        $levels = array_values(array_unique($levels));

        if (!empty($levels) && count($levels) < 4) {
            $baseQuery->whereRaw('(' . $this->kelengkapanSql() . ') IN (' . implode(',', $levels) . ')');
        }
    }

    // BERUBAH: hitung jumlah GRUP (shipment + tujuan), bukan jumlah baris DO
    $totalRecords = $this->countGroups($baseQuery);

    // ================= GLOBAL SEARCH =================
    if ($searchValue !== '') {
        $baseQuery->where(function ($q) use ($searchValue) {
            foreach (['no_shipment', 'tujuan', 'area', 'ekpedisi', 'dist_channel', 'pic_monitoring', 'remarks'] as $col) {
                $q->orWhere($col, 'like', "%{$searchValue}%");
            }
        });
    }

    $recordsFiltered = $this->countGroups($baseQuery);   // BERUBAH

    // BARU: 1 baris wakil per shipment + tujuan.
    // Dari sini ke bawah, semua orderBy memakai $query (bukan $baseQuery lagi).
    $query = LogistikPengiriman::query()
        ->whereIn('id', $this->groupIdsQuery($baseQuery));

    // ================= ORDERING =================
    $orderColumnMap = [
        0  => 'tanggal_keluar_gudang',
        1  => 'act_pgi_date',
        2  => 'dist_channel',
        3  => 'area',
        4  => 'no_shipment',
        5  => 'tujuan',
        6  => 'ekpedisi',
        7  => 'pic_monitoring',
        8  => 'status_kendaraan',
        10 => 'total_do_qty_car',
        11 => 'selisih_qty',
        12 => 'biaya_kuli',
        13 => 'total_biaya_kuli',
        14 => 'qty_monitoring',
        15 => 'remarks_qty',
        16 => 'act_urutan_bongkar',
        17 => 'estimasi_tiba',
        18 => 'tanggal_tiba',
        19 => 'lama_perjalanan',
        20 => 'sla_tiba',
        21 => 'tanggal_bongkar',
        23 => 'overstay_days',
        24 => 'sla_bongkar',
        25 => 'reason_tiba',
        26 => 'reason_bongkar',
        27 => 'remarks',
        28 => 'nama_kapal',
        29 => 'etd',
        30 => 'eta',
        31 => 'atd',
        32 => 'ata',
    ];

    $orderCol = $request->input('order.0.column');
    $orderDir = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

    $kelSort = strtolower((string) $request->input('kelengkapan_sort', ''));
    if (in_array($kelSort, ['asc', 'desc'], true)) {
        $query->orderByRaw('(' . $this->kelengkapanSql() . ') ' . strtoupper($kelSort));
    }

    $query->orderByRaw("CASE WHEN NULLIF(TRIM(tanggal_bongkar), '') IS NOT NULL THEN 1 ELSE 0 END ASC");
    $query->orderByRaw('(' . $this->prioritasTampilSql() . ') ASC');

  if ($orderCol !== null && (int) $orderCol === 0) {
    $query->orderByRaw($this->keluarTerakhirSql() . " {$orderDir}");
} elseif ($orderCol !== null && isset($orderColumnMap[$orderCol])) {
    $query->orderBy($orderColumnMap[$orderCol], $orderDir);
} else {
    $query->orderByRaw($this->keluarTerakhirSql() . ' ASC');
}

    // BERUBAH: tambah tujuan supaya tujuan dalam 1 shipment berdekatan
    $query->orderBy('no_shipment', 'ASC')
          ->orderBy('tujuan', 'ASC')
          ->orderBy('act_urutan_bongkar', 'ASC');

    $rows = $query->skip($start)->take($length)->get();

    // ============ BARU: jumlahkan angka per shipment + tujuan ============
    $groupAgg = $this->groupAgg($rows);

    foreach ($rows as $r) {
        $r->jml_do = 1;
        $g = $groupAgg[$r->no_shipment . '|' . ($r->tujuan ?? '')] ?? null;

        if ($g) {
            $r->jml_do           = (int) $g->jml_do;
            $r->total_do_qty_car = $g->sum_qty;
            $r->selisih_qty      = ((float) $g->sum_selisih) ?: null;
            $r->qty_monitoring   = $g->sum_qty_mon;
            $r->total_biaya_kuli = $g->sum_kuli;
        }
    }
    // =====================================================================

    // ---- (bawah ini sama persis seperti sebelumnya) ----
    $grouped = $rows->groupBy('no_shipment');
    foreach ($grouped as $shipment => $items) {
        $gudangInfo    = $this->getKeluarGudangInfo($items->first());
        $keluar        = $gudangInfo['keluar'];
        $blocked       = $gudangInfo['blocked'];
        $blockedStatus = $gudangInfo['blocked_status'];
        $leadtime      = (int) ($items->first()->transport_lead_time ?? 0);

        $estimasi = (!$blocked && $keluar) ? strtotime("+{$leadtime} days", $keluar) : null;

        foreach ($items as $r) {
            $r->_keluar           = $keluar;
            $r->_blocked          = $blocked;
            $r->_blocked_status   = $blockedStatus;
            $r->_tanggal_estimasi = $r->estimasi_tiba ? strtotime($r->estimasi_tiba) : $estimasi;
        }
    }

    $akurasiTiba    = Cache::remember('monitoring_akurasi_tiba', 3600, fn() => DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_tiba'));
    $akurasiBongkar = Cache::remember('monitoring_akurasi_bongkar', 3600, fn() => DB::table('akurasi3')->distinct()->pluck('akurasi_waktu_bongkar'));
    $akurasiQty     = Cache::remember('monitoring_akurasi_qty', 3600, fn() => DB::table('akurasi3')->distinct()->pluck('remarks_qty'));
    $tujuanList     = Cache::remember('monitoring_tujuan_list', 3600, function () {
        return DB::table('tujuanfillterr')
            ->whereNotNull('tujuan')->where('tujuan', '!=', '')
            ->distinct()->orderBy('tujuan')->pluck('tujuan');
    });

    $lists = compact('akurasiTiba', 'akurasiBongkar', 'akurasiQty', 'tujuanList');

    $data = [];
    foreach ($rows as $r) {
        $data[] = $this->renderRowColumns($r, $lists);
    }

    return response()->json([
        'draw'            => $draw,
        'recordsTotal'    => $totalRecords,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data,
    ]);
}

    /**
     * Bangun 1 baris (array kolom, index HARUS sinkron dengan
     * <thead> di data_monitoring.blade.php) — semua badge/alert/
     * kelengkapan dihitung di PHP di sini (bukan di JS lagi),
     * supaya frontend tinggal render, tidak perlu loop ribuan baris.
     */
    private function renderRowColumns($r, array $lists)
    {
        $id = $r->id;
        $rowAttr = 'data-id="' . $id . '"';

        $textInput = function ($name, $value) {
            return '<input type="text" name="' . $name . '" value="' . e($value) . '">';
        };

        $numberInput = function ($name, $value) {
            return '<input type="number" name="' . $name . '" value="' . e($value) . '">';
        };

   $selectBox = function ($name, $selected, $options, $placeholder) {
    $html = '<select name="' . $name . '" class="reason-select searchable-select" data-placeholder="' . e($placeholder) . '">';
    $html .= '<option value="">' . e($placeholder) . '</option>';
    foreach ($options as $opt) {
        $sel = ((string) $selected === (string) $opt) ? ' selected' : '';
        $html .= '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
    }
    $html .= '</select>';
    return $html;
};

        $keluar   = $r->_keluar ?? null;
        $blocked  = $r->_blocked ?? false;
        $estimasi = $r->_tanggal_estimasi ?? null;
        $blockedStatus = $r->_blocked_status ?? null;

        $tiba = $r->tanggal_tiba ? strtotime($r->tanggal_tiba) : null;
        $blockedLabel = $blockedStatus === 'sedang'
    ? 'Sedang di Gudang Berikutnya'
    : 'Menuju Gudang Berikutnya';

        $lama_perjalanan = '-';
        if ($tiba && $keluar) {
            $lama_perjalanan = floor(($tiba - $keluar) / 86400);
        }

        $alert = '-';
        $alertClass = '';
        $estimasi_show = '-';
        

$alert = '';
$alertClass = 'gray';

if ($blocked) {
    $estimasi_show = $blockedLabel;
    $alertClass = 'gray';
} else {
    $estimasi_show = $estimasi ? date('d-m-Y', $estimasi) : '-';

    // Hitung H- hanya kalau belum tiba
    if (!$r->tanggal_tiba && $estimasi) {
        $today = strtotime(date('Y-m-d'));
        $hariSisa = floor(($estimasi - $today) / 86400);

        if ($hariSisa < 0) {
            $alert = 'Pending Tiba  H+' . abs($hariSisa);
            $alertClass = 'red';
        } elseif ($hariSisa == 0) {
            $alert = 'H-0'; $alertClass = 'red';
        } elseif ($hariSisa == 1) {
            $alert = 'H-1'; $alertClass = 'red';
        } elseif ($hariSisa == 2) {
            $alert = 'H-2'; $alertClass = 'orange';
        } elseif ($hariSisa == 3) {
            $alert = 'H-3'; $alertClass = 'orange';
        } elseif ($hariSisa <= 7) {
            $alert = 'H-' . $hariSisa; $alertClass = 'blue';
        } else {
            $alert = 'ON TRACK'; $alertClass = 'green';
        }
    }
}

// Prioritas: Selesai (bongkar) > Tiba > Alert H-
if ($r->tanggal_bongkar) {
    $alertHtml = '<span class="badge green">✅ Sudah Selesai</span>';
} elseif ($r->tanggal_tiba) {
    $alertHtml = '<span class="badge blue">🚚 Tiba (Belum Bongkar)</span>';
} else {
    $alertHtml = '<span class="badge ' . $alertClass . '">' . e($alert) . '</span>';
}
        $sla_tiba = $r->sla_tiba ?? '-';
        $sla_tiba_html = $sla_tiba == '-'
            ? '-'
            : ($sla_tiba == 'On Time'
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">' . e($sla_tiba) . '</span>');

        $sla_bongkar = $r->sla_bongkar ?? '-';
        $sla_bongkar_html = $sla_bongkar == '-'
            ? '-'
            : ($sla_bongkar == 'On Time'
                ? '<span class="badge green">On Time</span>'
                : '<span class="badge red">' . e($sla_bongkar) . '</span>');

        $statusBongkar = '-';
        $statusBongkarClass = '';
        if ($r->tanggal_bongkar) {
            $statusBongkar = 'Sudah Bongkar';
            $statusBongkarClass = 'green';
        } elseif ($r->tanggal_tiba) {
            $tanggalTiba = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)));
            $today = strtotime(date('Y-m-d'));
            $hariBongkar = floor(($today - $tanggalTiba) / 86400);
            $statusBongkar = 'Pending Bongkar H+' . max(0, $hariBongkar);
            $statusBongkarClass = $hariBongkar == 0 ? 'orange' : 'red';
        }
        $statusBongkarHtml = $statusBongkar != '-'
            ? '<span class="badge ' . $statusBongkarClass . '">' . e($statusBongkar) . '</span>'
            : '-';

// ===== Kelengkapan Data =====
$today     = strtotime(date('Y-m-d'));
$isOverdue = ($estimasi && !$blocked) ? ($estimasi < $today) : false;

$tibaEmpty  = trim((string) $r->tanggal_tiba) === '';
$wTibaEmpty = trim((string) $r->waktu_tiba) === '';

$tibaEmpty     = trim((string) $r->tanggal_tiba) === '';
$wTibaEmpty    = trim((string) $r->waktu_tiba) === '';
$bongkarEmpty  = trim((string) $r->tanggal_bongkar) === '';
$wBongkarEmpty = trim((string) $r->waktu_bongkar) === '';

if (!$tibaEmpty && !$wTibaEmpty && !$bongkarEmpty && !$wBongkarEmpty) {
    $kelengkapanHtml = '<span class="badge completeness-badge green" title="Data tiba & bongkar lengkap">✅ Lengkap</span>';
} elseif (!$tibaEmpty) {
    $kelengkapanHtml = '<span class="badge completeness-badge blue" title="Sudah sampai tujuan, data bongkar belum lengkap">🚚 Sampai Tujuan</span>';
} elseif ($isOverdue) {
    $kelengkapanHtml = '<span class="badge completeness-badge red" title="Sudah lewat estimasi tiba, Tanggal Tiba belum diinput">❌ Belum Lengkap</span>';
} else {
    $kelengkapanHtml = '<span class="badge completeness-badge gray" title="Belum jatuh tempo estimasi tiba">-</span>';
}

  


       return [
   // 0 Tanggal Keluar Gudang
$blocked
    ? '<span class="badge red">' . e($blockedLabel) . '</span>'
    : ($keluar ? '<span class="badge green">' . date('d-m-Y', $keluar) . '</span>' : '-'),
         // 1 Act PGI Date (editable) — pakai kolom act_pgi_date, bukan create_tgl
'<input type="date" name="act_pgi_date" value="' . ($r->act_pgi_date ? date('Y-m-d', strtotime($r->act_pgi_date)) : '') . '">',
            // 2 Dist Channel
            e($r->dist_channel),
            // 3 Area
            e($r->area),
            // 4 No Shipment
            e($r->no_shipment),
// 5 Tujuan
'<span class="fw-medium">' . e($r->tujuan)
    . (($r->jml_do ?? 1) > 1 ? ' <span class="badge gray">' . $r->jml_do . ' DO</span>' : '')
    . '</span>',
            // 6 Ekspedisi
            e($r->ekpedisi),
            // 7 PIC (editable)
            $textInput('pic_monitoring', $r->pic_monitoring),
            // 8 Status (editable select)
            '<select name="status_kendaraan" class="form-select status-select">'
                . '<option value="On Track"' . ($r->status_kendaraan == 'On Track' ? ' selected' : '') . '>🟢 On Track</option>'
                . '<option value="Potential Delay"' . ($r->status_kendaraan == 'Potential Delay' ? ' selected' : '') . '>🔴 Potential Delay</option>'
                . '</select>',
            // 9 Alert
            $alertHtml,
            // 10 Total DO Qty
            e($r->total_do_qty_car),
            // 11 Selisih Qty Do (editable)
            '<input type="number" name="selisih_qty" class="row-selisih-qty" data-total-do="' . e($r->total_do_qty_car) . '" value="' . e($r->selisih_qty) . '">',
            // 12 Biaya Kuli (editable)
            '<input type="number" name="biaya_kuli" class="row-biaya-kuli" value="' . e($r->biaya_kuli) . '">',
            // 13 Total Biaya Kuli
            '<input type="text" name="total_biaya_kuli" class="row-total-biaya-kuli" value="Rp ' . number_format($r->total_biaya_kuli ?? 0, 0, ',', '.') . '" readonly>',
            // 14 Qty Actual Do
            '<input type="number" name="qty_monitoring" class="row-qty-monitoring" value="' . e($r->qty_monitoring) . '" readonly style="background:#f1f5f9;color:#0284c7;font-weight:600;">',
            // 15 Reason Qty (editable select)
            $selectBox('remarks_qty', $r->remarks_qty, $lists['akurasiQty'], 'Pilih Reason Qty'),
            // 16 Urutan Bongkar (editable)
            $numberInput('act_urutan_bongkar', $r->act_urutan_bongkar),
            // 17 Estimasi Tiba
            e($estimasi_show),
         // 18 Tanggal Tiba (tanggal + jam terpisah)
'<div class="dt-pair">'
    . '<input type="text" class="flatpickr-date" name="tanggal_tiba" data-required="true" data-label="Tgl Tiba" placeholder="ddmmyyyy" value="' . ($r->tanggal_tiba ? date('Y-m-d', strtotime($r->tanggal_tiba)) : '') . '">'
    . '<input type="text" class="input-jam" name="waktu_tiba" placeholder="HH:MM" maxlength="5" inputmode="numeric" autocomplete="off" value="' . ($r->waktu_tiba ? substr($r->waktu_tiba, 0, 5) : '') . '">'
    . '</div>',
            // 19 Lama Perjalanan
            e($lama_perjalanan),
            // 20 SLA Tiba
            $sla_tiba_html,
           // 21 Tanggal Bongkar (tanggal + jam terpisah)
'<div class="dt-pair">'
    . '<input type="text" class="flatpickr-date" name="tanggal_bongkar" data-required="true" data-label="Tgl Bongkar" placeholder="ddmmyyyy" value="' . ($r->tanggal_bongkar ? date('Y-m-d', strtotime($r->tanggal_bongkar)) : '') . '">'
    . '<input type="text" class="input-jam" name="waktu_bongkar" placeholder="HH:MM" maxlength="5" inputmode="numeric" autocomplete="off" value="' . ($r->waktu_bongkar ? substr($r->waktu_bongkar, 0, 5) : '') . '">'
    . '</div>',
            // 22 Status Bongkar
            $statusBongkarHtml,
            // 23 Overstay
            e($r->overstay_days ?? '-'),
            // 24 SLA Bongkar
            $sla_bongkar_html,
            // 25 Reason Tiba (editable select)
            $selectBox('reason_tiba', $r->reason_tiba, $lists['akurasiTiba'], 'Pilih Reason Tiba'),
            // 26 Reason Bongkar (editable select)
            $selectBox('reason_bongkar', $r->reason_bongkar, $lists['akurasiBongkar'], 'Pilih Reason Bongkar'),
            // 27 Remarks (editable)
            $textInput('remarks', $r->remarks),
            // 28 Nama Kapal (editable)
            $textInput('nama_kapal', $r->nama_kapal),
           // 29 ETD
'<input type="date" name="etd" value="' . ($r->etd ? date('Y-m-d', strtotime($r->etd)) : '') . '">',
// 30 ETA
'<input type="date" name="eta" value="' . ($r->eta ? date('Y-m-d', strtotime($r->eta)) : '') . '">',
// 31 ATD
'<input type="date" name="atd" value="' . ($r->atd ? date('Y-m-d', strtotime($r->atd)) : '') . '">',
// 32 ATA
'<input type="date" name="ata" value="' . ($r->ata ? date('Y-m-d', strtotime($r->ata)) : '') . '">',
            // 33 Kelengkapan Data
            $kelengkapanHtml,
            // 34 Action
            '<span class="save-status"></span><button type="button" class="save-btn" data-id="' . $id . '" onclick="saveRow(this)">SAVE</button>',
        ];
    }
public function updateMonitoring(Request $request, $id)
{
    if (!LogistikPengiriman::whereKey($id)->exists()) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Data tidak ditemukan (mungkin sudah dihapus)',
        ], 404);
    }
 
    try {
        $this->loggedSave('monitoring', 'single', $id, $request->all(), function () use ($id, $request) {
            DB::transaction(function () use ($id, $request) {
                $logistik = LogistikPengiriman::findOrFail($id);
                $this->applyMonitoringUpdate($logistik, $request->all());
            });
        });
   } catch (\Throwable $e) {
    report($e);
    $errs = $this->pesanList($e);
    return response()->json([
        'status'  => 'error',
        'title'   => 'Data gagal disimpan',
        'message' => implode(' ', $errs),
        'errors'  => $errs,
    ], 422);
}
 
    return response()->json([
        'status'  => 'success',
        'message' => 'Data transport laut berhasil diupdate',
    ]);
}
//     public function updateMonitoring(Request $request, $id)
// {
//     $logistik = LogistikPengiriman::findOrFail($id);
 
//     try {
//         $this->applyMonitoringUpdate($logistik, $request->all());
//     } catch (\Throwable $e) {
//         return response()->json([
//             'status'  => 'error',
//             'message' => $e->getMessage(),
//         ], 422);
//     }
 
//     return response()->json([
//         'status'  => 'success',
//         'message' => 'Data transport laut berhasil diupdate',
//     ]);
// }
 
// ================================================================
// BARU: batch update — terima array baris sekaligus, semua dalam
// 1 DB transaction. Kalau ada 1 baris gagal, baris lain tetap lanjut
// (supaya user tidak kehilangan semua progress gara-gara 1 error),
// tapi hasilnya dilaporkan per baris supaya user tahu mana yang gagal.
// ================================================================
public function updateMonitoringBatch(Request $request)
{
    $rows = $request->input('rows', []);

    if (!is_array($rows) || empty($rows)) {
        return response()->json([
            'status'  => 'error',
            'title'   => 'Tidak ada data',
            'message' => 'Tidak ada data yang dikirim untuk disimpan',
            'errors'  => ['Tidak ada data yang dikirim untuk disimpan'],
        ], 422);
    }

    $results      = [];
    $failedCount  = 0;
    $successCount = 0;

    foreach ($rows as $rowData) {
        $id = $rowData['id'] ?? null;

        if (empty($id)) {
            $failedCount++;
            $this->writeInputLog('monitoring', 'batch', null, null, 'failed', 'ID baris kosong', $rowData);
            $results[] = [
                'id'          => null,
                'no_shipment' => null,
                'status'      => 'error',
                'message'     => 'ID baris kosong',
                'errors'      => ['ID baris kosong'],
            ];
            continue;
        }

        $noShipment = LogistikPengiriman::where('id', $id)->value('no_shipment');

        try {
            $this->loggedSave('monitoring', 'batch', $id, $rowData, function () use ($id, $rowData) {
                DB::transaction(function () use ($id, $rowData) {
                    $logistik = LogistikPengiriman::findOrFail($id);
                    $this->applyMonitoringUpdate($logistik, $rowData);
                }, 3);
            });

            $successCount++;
            $results[] = [
                'id'          => $id,
                'no_shipment' => $noShipment,
                'status'      => 'success',
            ];
        } catch (\Throwable $e) {
            report($e);
            $failedCount++;
            $errs = $this->pesanList($e);
            $results[] = [
                'id'          => $id,
                'no_shipment' => $noShipment,
                'status'      => 'error',
                'message'     => implode(' ', $errs),
                'errors'      => $errs,
            ];
        }
    }

    return response()->json([
        'status'        => $failedCount === 0 ? 'success' : ($successCount === 0 ? 'error' : 'partial'),
        'message'       => "{$successCount} baris tersimpan" . ($failedCount > 0 ? ", {$failedCount} gagal" : ''),
        'success_count' => $successCount,
        'failed_count'  => $failedCount,
        'results'       => $results,
    ]);
}
private function friendlyError(\Throwable $e): string
{
    return implode(' ', $this->pesanList($e));
}
// ================================================================
// Isi logic yang SAMA PERSIS seperti updateMonitoring() versi lama,
// cuma sumber datanya sekarang array $data (bisa dari $request->all()
// untuk single update, atau dari 1 elemen array 'rows' untuk batch),
// bukan langsung objek Request.
// ================================================================
private function applyMonitoringUpdate(LogistikPengiriman $logistik, array $data): void
{
    if ($err = $this->cekMonitoring($data, $logistik)) {
        throw ValidationException::withMessages(['input' => $err]);
    }

    $gudangInfo = $this->getKeluarGudangInfo($logistik);
    $keluar  = $gudangInfo['keluar'];
    $blocked = $gudangInfo['blocked'];
 
 $tanggalTibaInput = !empty($data['tanggal_tiba']) ? $data['tanggal_tiba'] : null;
    $tiba = $tanggalTibaInput
        ? strtotime(date('Y-m-d', strtotime($tanggalTibaInput)))
        : null;

    $tanggalBongkarInput = !empty($data['tanggal_bongkar']) ? $data['tanggal_bongkar'] : null;
    $bongkar = $tanggalBongkarInput
        ? strtotime(date('Y-m-d', strtotime($tanggalBongkarInput)))
        : null;
 
    $leadtime = (int) ($logistik->transport_lead_time ?? 0);
 
    $estimasi = $logistik->estimasi_tiba
        ? strtotime($logistik->estimasi_tiba)
        : (
            (!$blocked && $keluar)
            ? strtotime("+{$leadtime} days", $keluar)
            : null
        );
 
    $lama_perjalanan = ($keluar && $tiba)
        ? max(0, floor(($tiba - $keluar) / 86400))
        : null;
 
    $sla_tiba = ($tiba && $estimasi)
        ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
        : '-';
 
   // ---- Overstay & SLA Bongkar berdasarkan tanggal + jam ----
$tibaDT = ($tanggalTibaInput && !empty($data['waktu_tiba']))
    ? strtotime(date('Y-m-d', strtotime($tanggalTibaInput)) . ' ' . $data['waktu_tiba'])
    : null;

$bongkarDT = ($tanggalBongkarInput && !empty($data['waktu_bongkar']))
    ? strtotime(date('Y-m-d', strtotime($tanggalBongkarInput)) . ' ' . $data['waktu_bongkar'])
    : null;

$overstay = null;

if ($tiba && $bongkar) {
    if ($tibaDT && $bongkarDT) {
        // jam lengkap: pakai selisih jam sebenarnya
        $selisihJam = ($bongkarDT - $tibaDT) / 3600;
        $overstay   = $selisihJam <= 24 ? 0 : (int) floor($selisihJam / 24);
    } else {
        // jam belum diisi: fallback ke aturan lama (per tanggal)
        $overstay = max(0, floor(($bongkar - $tiba) / 86400));
    }
}

$sla_bongkar = ($tiba && $bongkar)
    ? (($overstay <= 0) ? 'On Time' : 'Delay')
    : '-';
 
    $logic = $this->generateStatusAlert($sla_tiba, $sla_bongkar);
 
    $logistik->status_akhir = $logic['status_akhir'];
    $logistik->monitoring_alert = $logic['alert'];
 
    $logistik->sla_tiba = $sla_tiba;
    $logistik->sla_bongkar = $sla_bongkar;
    $logistik->overstay_days   = $overstay;          // <-- TAMBAH
$logistik->lama_perjalanan = $lama_perjalanan;   // <-- TAMBAH
 
    if (empty($logistik->estimasi_tiba)) {
        if (!$logistik->tanggal_bongkar && empty($logistik->estimasi_tiba)) {
            $logistik->estimasi_tiba = (!$blocked && $estimasi)
                ? date('Y-m-d', $estimasi)
                : null;
        }
    }
 
    $logistik->reason_tiba    = $data['reason_tiba'] ?? null;
    $logistik->tujuan         = $data['tujuan'] ?? $logistik->tujuan;
    $logistik->reason_bongkar = $data['reason_bongkar'] ?? null;
 
    $logistik->pic_monitoring   = $data['pic_monitoring'] ?? null;
    $logistik->status_kendaraan = $data['status_kendaraan'] ?? null;
    $logistik->remarks_qty      = $data['remarks_qty'] ?? null;
    $logistik->action_required  = $data['action_required'] ?? null;
 
    $logistik->act_urutan_bongkar = $data['act_urutan_bongkar'] ?? null;
 
    // $logistik->total_do_qty_car = $data['total_do_qty_car'] ?? $logistik->total_do_qty_car;
    // total_do_qty_car per DO tidak diubah dari grid
    $logistik->selisih_qty      = $data['selisih_qty'] ?? null;
    $logistik->biaya_kuli       = $data['biaya_kuli'] ?? null;
 
    $logistik->qty_monitoring = ($logistik->total_do_qty_car ?? 0) - ($logistik->selisih_qty ?? 0);
    $logistik->total_biaya_kuli = ($logistik->qty_monitoring ?? 0) * ($logistik->biaya_kuli ?? 0);
 
  $logistik->tanggal_tiba    = $tanggalTibaInput;
$logistik->waktu_tiba      = ($tanggalTibaInput && !empty($data['waktu_tiba'])) ? $data['waktu_tiba'] : null;

$logistik->tanggal_bongkar = $tanggalBongkarInput;
$logistik->waktu_bongkar   = ($tanggalBongkarInput && !empty($data['waktu_bongkar'])) ? $data['waktu_bongkar'] : null;
 
    $logistik->remarks      = $data['remarks'] ?? null;
    $logistik->act_pgi_date = $data['act_pgi_date'] ?? null;
    $logistik->created_by   = $data['created_by'] ?? null;
 
//   if (!empty($data['nama_kapal'])) {
//     $logistik->nama_kapal = $data['nama_kapal'];
//     $logistik->etd = $data['etd'] ?? null;
//     $logistik->eta = $data['eta'] ?? null;
//     $logistik->atd = $data['atd'] ?? null;
//     $logistik->ata = $data['ata'] ?? null;
// }
if (!empty($data['nama_kapal'])) {
    $logistik->nama_kapal = $data['nama_kapal'];
}
if (!empty($data['etd'])) {
    $logistik->etd = $data['etd'];
}
if (!empty($data['eta'])) {
    $logistik->eta = $data['eta'];
}
if (!empty($data['atd'])) {
    $logistik->atd = $data['atd'];
}
if (!empty($data['ata'])) {
    $logistik->ata = $data['ata'];
}
 
    $logistik->save();

    $this->propagateToGroup($logistik, $data);
 
    // propagasi estimasi ke baris lain dalam shipment yang sama (belum tiba)
    $shipment = LogistikPengiriman::where('no_shipment', $logistik->no_shipment)->get();
 
    $baseEstimasi = (!$blocked && $keluar)
        ? strtotime("+{$leadtime} days", $keluar)
        : null;
 
    $lastBongkar = $shipment->whereNotNull('tanggal_bongkar')->max('tanggal_bongkar');
 
    $nextEstimasi = $lastBongkar
        ? date('Y-m-d', strtotime($lastBongkar . ' +1 day'))
        : ($baseEstimasi ? date('Y-m-d', $baseEstimasi) : null);
 
    foreach ($shipment as $item) {
        if (!empty($item->tanggal_tiba)) {
            continue;
        }
        $item->estimasi_tiba = $nextEstimasi;
        $item->save();
    }
}
 private function propagateToGroup(LogistikPengiriman $logistik, array $data): void
{
    $rows = LogistikPengiriman::where('no_shipment', $logistik->no_shipment)
        ->whereRaw("COALESCE(tujuan,'') = ?", [(string) $logistik->tujuan])
        ->orderBy('id')
        ->get();

    if ($rows->isEmpty()) return;

    $repId = $rows->first()->id;   // selisih qty ditaruh di 1 baris saja

    $shared = $logistik->only([
        'tanggal_tiba', 'waktu_tiba', 'tanggal_bongkar', 'waktu_bongkar',
        'sla_tiba', 'sla_bongkar', 'overstay_days', 'lama_perjalanan',
        'status_akhir', 'monitoring_alert',
        'reason_tiba', 'reason_bongkar', 'remarks', 'remarks_qty',
        'pic_monitoring', 'status_kendaraan', 'act_urutan_bongkar',
        'act_pgi_date', 'biaya_kuli', 'action_required',
    ]);

    foreach ($rows as $row) {
        $row->fill($shared);
        $row->selisih_qty      = $row->id === $repId ? ($data['selisih_qty'] ?? null) : null;
        $row->qty_monitoring   = ($row->total_do_qty_car ?? 0) - ($row->selisih_qty ?? 0);
        $row->total_biaya_kuli = $row->qty_monitoring * ($row->biaya_kuli ?? 0);
        $row->save();
    }
}
/**
 * ===========================================================================
 * TAMBAHKAN DI routes/web.php (dekat route monitoring.update yang lama):
 *
 * Route::post('/monitoring/update-batch', [MonitoringController::class, 'updateMonitoringBatch'])
 *     ->name('monitoring.update.batch');
 * ===========================================================================
 */
 

    // =====================================================
    // ALERT CONTROL — query ringan, HANYA ambil shipment yang
    // sudah lewat estimasi_tiba dan Tgl Tiba/Tgl Bongkar belum
    // diisi. Tidak perlu load semua ribuan baris ke PHP/JS.
    // =====================================================
  public function alerts(Request $request)
{
    $today = date('Y-m-d');
   $filter = DB::table('logistik_pengiriman')
        ->whereNotNull('estimasi_tiba')
        ->whereDate('estimasi_tiba', '<=', $today)   // BERUBAH: H-0 ikut + yang sudah lewat
        ->where(function ($q) {
            $q->whereNull('tanggal_tiba')->orWhereNull('tanggal_bongkar');
        });
    $this->applyCommonFilters($filter, $request);

    $groupIds = $this->groupIdsQuery($filter);

    $rows = DB::table('logistik_pengiriman')
        ->select('id', 'no_shipment', 'pic_monitoring', 'area', 'tujuan', 'estimasi_tiba', 'tanggal_tiba', 'tanggal_bongkar')
        ->whereIn('id', $groupIds)
        ->orderBy('estimasi_tiba')
        ->limit(200)
        ->get();

    $alertList = [];
    $missingSummary = [];

    foreach ($rows as $r) {
        $missing = [];
        if (empty($r->tanggal_tiba))    { $missing[] = 'Tgl Tiba';    $missingSummary['Tgl Tiba']    = ($missingSummary['Tgl Tiba'] ?? 0) + 1; }
        if (empty($r->tanggal_bongkar)) { $missing[] = 'Tgl Bongkar'; $missingSummary['Tgl Bongkar'] = ($missingSummary['Tgl Bongkar'] ?? 0) + 1; }

        $alertList[] = [
            'id'             => $r->id,
            'shipment'       => $r->no_shipment,
            'tujuan'         => $r->tujuan,
            'area'           => $r->area,
            'pic_monitoring' => $r->pic_monitoring,
            'missing'        => $missing,
            'emptyCount'     => count($missing),
            'estimasi'       => $r->estimasi_tiba,
        ];
    }

    return response()->json([
        'alerts'         => $alertList,
        'missingSummary' => $missingSummary,
        'totalAlert'     => $this->countGroups($filter),
    ]);
}
    // =====================================================
// IN TRANSIT: sudah keluar gudang (max), belum ada tanggal_tiba
// =====================================================
private function inTransitQuery()
{
    $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
    $empty  = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

    $q = DB::table('logistik_pengiriman')->whereRaw($empty('tanggal_tiba'));

    // minimal 1 gudang sudah keluar
    $q->whereRaw('(' . implode(' OR ', array_map(
        fn($s) => $filled("tanggal_keluar_gudang{$s}"), $this->gudangSuffixes()
    )) . ')');

    // tidak boleh ada siklus yang menggantung
    foreach ($this->gudangCycles() as [$planning, $tiba, $keluar]) {
        $q->whereRaw('NOT ((' . $filled($planning) . ' OR ' . $filled($tiba) . ') AND ' . $empty($keluar) . ')');
    }

    return $q;
}
private function inTransitFiltered(Request $request)
{
    $base = $this->inTransitQuery();

    $areas = array_values(array_filter((array) $request->input('area', []), 'strlen'));
    if (!empty($areas)) {
        $base->whereIn('area', $areas);
    }

    $pics = array_values(array_filter((array) $request->input('pic_monitoring', []), 'strlen'));
    if (!empty($pics)) {
        $base->whereIn('pic_monitoring', $pics);
    }

    $asal = array_values(array_filter((array) $request->input('gudang_asal', []), 'strlen'));
    if (!empty($asal)) {
        $placeholders = implode(',', array_fill(0, count($asal), '?'));
        $base->whereRaw('(' . $this->inTransitAsalSql() . ") IN ({$placeholders})", $asal);
    }

    if ($request->filled('q')) {
        $this->applyInTransitSearch($base, $request->input('q'));
    }

    return $base;
}
private function inTransitAsalSql(): string
{
    $k = [];
    foreach (self::GUDANG as $s => $nama) {
        $k[$s] = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang{$s}),''),'1900-01-01')";
    }

    // tanggal terbaru menang; kalau sama, gudang yang belakang menang
    $whens = [];
    foreach (array_reverse(array_keys(self::GUDANG)) as $s) {
        if ($s === '') continue; // KACS = ELSE
        $cmp = [];
        foreach ($k as $s2 => $expr) {
            if ($s2 !== $s) $cmp[] = "{$k[$s]} >= {$expr}";
        }
        $whens[] = "WHEN {$k[$s]} > '1900-01-01' AND " . implode(' AND ', $cmp)
                 . " THEN '" . self::GUDANG[$s] . "'";
    }

    return 'CASE ' . implode(' ', $whens) . " ELSE '" . self::GUDANG[''] . "' END";
}
private function applyInTransitSearch($base, string $s)
{
    $s = trim($s);
    if ($s === '') {
        return $base;
    }
    $like = "%{$s}%";

    $est = $this->inTransitEstimasiSql();
  $est    = $this->inTransitEstimasiSql();
$keluar = $this->keluarTerakhirSql();
$asal   = $this->inTransitAsalSql();

 

    $sisa = "DATEDIFF(DATE({$est}), CURDATE())";

    $alert = "CASE
        WHEN {$sisa} < 0 THEN CONCAT('Pending Tiba H+', ABS({$sisa}))
        WHEN {$sisa} <= 7 THEN CONCAT('H-', {$sisa})
        ELSE 'ON TRACK' END";

    $exprs = [
        $asal,                                            // Keluar Dari
        "DATE_FORMAT({$keluar}, '%d-%m-%Y')",             // Tanggal Keluar
        "CONCAT(DATEDIFF(CURDATE(), DATE({$keluar})), ' Hari')", // Lama Di Jalan
        "DATE_FORMAT({$est}, '%d-%m-%Y')",                // Estimasi Tiba
        $alert,                                           // Alert
    ];

    $cols = [
        'no_shipment', 'tujuan', 'area', 'dist_channel', 'ekpedisi',
        'mobil', 'nama_driver', 'no_pol', 'pic_monitoring', 'remarks',
    ];

    return $base->where(function ($q) use ($cols, $exprs, $like) {
        foreach ($cols as $col) {
            $q->orWhere($col, 'like', $like);
        }
        foreach ($exprs as $expr) {
            $q->orWhereRaw("({$expr}) LIKE ?", [$like]);
        }
    });
}
private function decorateInTransitRow($r, $todayTs)
{
   $keluar = null;
$asal   = '-';
foreach (self::GUDANG as $s => $nama) {
    $tgl = $r->{"tanggal_keluar_gudang{$s}"} ?? null;
    if (!empty($tgl)) {
        $ts = strtotime($tgl);
        if ($keluar === null || $ts >= $keluar) {
            $keluar = $ts;
            $asal   = $nama;
        }
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

    $r->gudang_asal     = $asal;
    $r->keluar_label    = $keluar ? date('d-m-Y', $keluar) : '-';
    $r->hari_transit    = $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null;
    $r->estimasi_label  = $estimasi ? date('d-m-Y', $estimasi) : '-';
    $r->alert_label     = $alert;
    $r->alert_class     = $cls;

    return $r;
}

public function exportInTransit(Request $request)
{
    $todayTs = strtotime(date('Y-m-d'));
    $base    = $this->inTransitFiltered($request);

    $rows = DB::table('logistik_pengiriman')
        ->whereIn('id', $this->groupIdsQuery($base))
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->get()
        ->map(fn($r) => $this->decorateInTransitRow($r, $todayTs));

    return Excel::download(new InTransitExport($rows), 'In_Transit_' . date('Ymd_His') . '.xlsx');
}

private function prioritasTampilSql(): string
{
    $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";

    return "CASE
        WHEN {$filled('tanggal_bongkar')} THEN 3
        WHEN " . $this->blockedSql() . " THEN 1
        WHEN " . $this->keluarTerakhirSql() . " > '1900-01-01' THEN 0
        ELSE 2
    END";
}
private function keluarTerakhirSql(): string
{
    $cols = array_map(
        fn($s) => "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang{$s}),''),'1900-01-01')",
        $this->gudangSuffixes()
    );
    return 'GREATEST(' . implode(",\n", $cols) . ')';
}
// 0 = belum jatuh tempo, 1 = lengkap, 2 = 1 field kosong, 3 = 2 field kosong
private function kelengkapanSql(): string
{
    $filled = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";

    $keluar = $this->keluarTerakhirSql();
    $lead   = "CAST(COALESCE(NULLIF(TRIM(transport_lead_time),''),0) AS UNSIGNED)";

    $estimasi = "COALESCE(NULLIF(TRIM(estimasi_tiba),''),
        CASE WHEN {$keluar} > '1900-01-01' THEN DATE_ADD({$keluar}, INTERVAL {$lead} DAY) END)";

    $overdue = "(NOT " . $this->blockedSql() . " AND DATE({$estimasi}) < CURDATE())";

    return "CASE
        WHEN {$filled('tanggal_tiba')} AND {$filled('waktu_tiba')} AND {$filled('tanggal_bongkar')} AND {$filled('waktu_bongkar')} THEN 1
        WHEN {$filled('tanggal_tiba')} THEN 2
        WHEN COALESCE({$overdue}, 0) = 0 THEN 0
        ELSE 3
    END";
}
// estimasi tiba: pakai yang tersimpan, kalau kosong hitung keluar terakhir + lead time
private function inTransitEstimasiSql(): string
{
    return "COALESCE(estimasi_tiba, DATE_ADD(
        " . $this->keluarTerakhirSql() . ",
        INTERVAL CAST(COALESCE(NULLIF(TRIM(transport_lead_time),''),0) AS UNSIGNED) DAY
    ))";
}

// public function inTransit(Request $request)
// {
//     $today    = date('Y-m-d');
//     $todayTs  = strtotime($today);
//     $soon     = date('Y-m-d', strtotime('+3 days'));
//     $est      = $this->inTransitEstimasiSql();

//     $base = $this->inTransitQuery();

//     if ($request->filled('area')) {
//         $base->where('area', $request->input('area'));
//     }
//     if ($request->filled('pic_monitoring')) {
//         $base->where('pic_monitoring', $request->input('pic_monitoring'));
//     }
//     if ($request->filled('q')) {
//         $s = trim($request->input('q'));
//         $base->where(function ($q) use ($s) {
//             foreach (['no_shipment', 'tujuan', 'ekpedisi', 'nama_driver', 'no_pol', 'mobil'] as $col) {
//                 $q->orWhere($col, 'like', "%{$s}%");
//             }
//         });
//     }

//     // ===== ringkasan (1 query) =====
//     $sum = (clone $base)->selectRaw("
//         COUNT(*) AS total,
//         SUM(CASE WHEN DATE({$est}) < ? THEN 1 ELSE 0 END) AS overdue,
//         SUM(CASE WHEN DATE({$est}) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS soon,
//         SUM(CASE WHEN DATE({$est}) > ? THEN 1 ELSE 0 END) AS ontrack
//     ", [$today, $today, $soon, $soon])->first();

//     $summary = [
//         'total'   => (int) ($sum->total ?? 0),
//         'overdue' => (int) ($sum->overdue ?? 0),
//         'soon'    => (int) ($sum->soon ?? 0),
//         'ontrack' => (int) ($sum->ontrack ?? 0),
//     ];

//     // ===== data tabel =====
//    // SESUDAH
// $list = (clone $base)
//     ->orderBy('no_shipment', 'ASC')
//     ->orderBy('act_urutan_bongkar', 'ASC')   // tie-breaker: baris dalam 1 shipment urut sesuai urutan bongkar
//     ->paginate(50)
//     ->withQueryString();

//     $list->getCollection()->transform(function ($r) use ($todayTs) {
//         // gudang terakhir yang dikeluarkan
//         $keluar = null;
//         $asal   = '-';
//         foreach ([
//             ['KACS',   $r->tanggal_keluar_gudang],
//             ['SENTUL', $r->tanggal_keluar_gudang_2],
//             ['CCIE',   $r->tanggal_keluar_gudang_3],
//         ] as [$nama, $tgl]) {
//             if (!empty($tgl)) {
//                 $ts = strtotime($tgl);
//                 if ($keluar === null || $ts >= $keluar) {
//                     $keluar = $ts;
//                     $asal   = $nama;
//                 }
//             }
//         }

//         $lead     = (int) ($r->transport_lead_time ?? 0);
//         $keluarD  = $keluar ? strtotime(date('Y-m-d', $keluar)) : null;
//         $estimasi = !empty($r->estimasi_tiba)
//             ? strtotime($r->estimasi_tiba)
//             : ($keluarD ? strtotime("+{$lead} days", $keluarD) : null);

//         $alert = '-';
//         $cls   = 'gray';
//         if ($estimasi) {
//             $sisa = floor(($estimasi - $todayTs) / 86400);
//             if     ($sisa < 0)  { $alert = 'Pending Tiba H+' . abs($sisa); $cls = 'red'; }
//             elseif ($sisa <= 1) { $alert = 'H-' . $sisa;                    $cls = 'red'; }
//             elseif ($sisa <= 3) { $alert = 'H-' . $sisa;                    $cls = 'orange'; }
//             elseif ($sisa <= 7) { $alert = 'H-' . $sisa;                    $cls = 'blue'; }
//             else                { $alert = 'ON TRACK';                      $cls = 'green'; }
//         }

//         $r->gudang_asal     = $asal;
//         $r->keluar_label    = $keluar ? date('d-m-Y', $keluar) : '-';
//         $r->hari_transit    = $keluarD ? max(0, floor(($todayTs - $keluarD) / 86400)) : null;
//         $r->estimasi_label  = $estimasi ? date('d-m-Y', $estimasi) : '-';
//         $r->alert_label     = $alert;
//         $r->alert_class     = $cls;

//         return $r;
//     });

//     $areaList = Cache::remember('monitoring_area_list', 3600, function () {
//         return LogistikPengiriman::whereNotNull('area')
//             ->distinct()->orderBy('area')->pluck('area');
//     });

//     $picList = Cache::remember('monitoring_pic_list', 3600, function () {
//         return LogistikPengiriman::whereNotNull('pic_monitoring')
//             ->distinct()->orderBy('pic_monitoring')->pluck('pic_monitoring');
//     });

//     return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList'));
// }

public function inTransit(Request $request)
{
    $today   = date('Y-m-d');
    $todayTs = strtotime($today);
    $soon    = date('Y-m-d', strtotime('+3 days'));
    $est     = $this->inTransitEstimasiSql();

    $base    = $this->inTransitFiltered($request);
    $grouped = DB::table('logistik_pengiriman')->whereIn('id', $this->groupIdsQuery($base));

    $sum = (clone $grouped)->selectRaw("
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

    $list = (clone $grouped)
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->paginate(50)
        ->withQueryString();

    $list->getCollection()->transform(fn($r) => $this->decorateInTransitRow($r, $todayTs));

    $areaList = Cache::remember('monitoring_area_list', 3600, function () {
        return LogistikPengiriman::whereNotNull('area')
            ->distinct()->orderBy('area')->pluck('area');
    });

    $picList = Cache::remember('monitoring_pic_list', 3600, function () {
        return LogistikPengiriman::whereNotNull('pic_monitoring')
            ->distinct()->orderBy('pic_monitoring')->pluck('pic_monitoring');
    });

    return view('monitoring.in_transit', compact('list', 'summary', 'areaList', 'picList'));
}

    // public function updateMonitoring(Request $request, $id)
    // {
    //     $logistik = LogistikPengiriman::findOrFail($id);
    //     $oldTanggalTiba = $logistik->tanggal_tiba;

    //     $gudangInfo = $this->getKeluarGudangInfo($logistik);
    //     $keluar  = $gudangInfo['keluar'];
    //     $blocked = $gudangInfo['blocked'];

    //     $tiba = $request->tanggal_tiba
    //         ? strtotime(date('Y-m-d', strtotime($request->tanggal_tiba)))
    //         : null;

    //     $bongkar = $request->tanggal_bongkar
    //         ? strtotime(date('Y-m-d', strtotime($request->tanggal_bongkar)))
    //         : null;

    //     $leadtime = (int)($logistik->transport_lead_time ?? 0);

    //     $estimasi = $logistik->estimasi_tiba
    //         ? strtotime($logistik->estimasi_tiba)
    //         : (
    //             (!$blocked && $keluar)
    //             ? strtotime("+{$leadtime} days", $keluar)
    //             : null
    //         );

    //     $lama_perjalanan = ($keluar && $tiba)
    //         ? max(0, floor(($tiba - $keluar) / 86400))
    //         : null;

    //     $sla_tiba = ($tiba && $estimasi)
    //         ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
    //         : '-';

    //     $overstay = ($tiba && $bongkar)
    //         ? max(0, floor(($bongkar - $tiba) / 86400))
    //         : null;

    //     $sla_bongkar = ($tiba && $bongkar)
    //         ? (($overstay <= 0) ? 'On Time' : 'Delay')
    //         : '-';

    //     $logic = $this->generateStatusAlert($sla_tiba, $sla_bongkar);

    //     $logistik->status_akhir = $logic['status_akhir'];
    //     $logistik->monitoring_alert = $logic['alert'];

    //     $logistik->sla_tiba = $sla_tiba;
    //     $logistik->sla_bongkar = $sla_bongkar;

    //     if (empty($logistik->estimasi_tiba)) {
    //         if (!$logistik->tanggal_bongkar && empty($logistik->estimasi_tiba)) {
    //             $logistik->estimasi_tiba = (!$blocked && $estimasi)
    //                 ? date('Y-m-d', $estimasi)
    //                 : null;
    //         }
    //     }

    //     $logistik->reason_tiba    = $request->reason_tiba;
    //     $logistik->tujuan         = $request->input('tujuan', $logistik->tujuan);
    //     $logistik->reason_bongkar = $request->reason_bongkar;

    //     $logistik->pic_monitoring   = $request->pic_monitoring;
    //     $logistik->status_kendaraan = $request->status_kendaraan;
    //     $logistik->remarks_qty     = $request->remarks_qty;
    //     $logistik->action_required  = $request->action_required;

    //     $logistik->act_urutan_bongkar = $request->act_urutan_bongkar;

    //     $logistik->total_do_qty_car = $request->total_do_qty_car ?? $logistik->total_do_qty_car;
    //     $logistik->selisih_qty      = $request->selisih_qty;
    //     $logistik->biaya_kuli       = $request->biaya_kuli;

    //     $logistik->qty_monitoring = ($logistik->total_do_qty_car ?? 0) - ($logistik->selisih_qty ?? 0);
    //     $logistik->total_biaya_kuli = ($logistik->qty_monitoring ?? 0) * ($logistik->biaya_kuli ?? 0);

    //     $logistik->remarks_qty = $request->remarks_qty;

    //     $logistik->tanggal_tiba    = $request->tanggal_tiba;
    //     $logistik->tanggal_bongkar = $request->tanggal_bongkar;

    //     $logistik->overstay_days   = $overstay;
    //     $logistik->lama_perjalanan = $lama_perjalanan;

    //     $logistik->reason_tiba    = $request->reason_tiba;
    //     $logistik->reason_bongkar = $request->reason_bongkar;

    //     $logistik->remarks        = $request->remarks;
    //     $logistik->act_pgi_date      = $request->input('act_pgi_date');
    //     $logistik->created_by        = $request->input('created_by');

    //     if ($request->filled('nama_kapal')) {
    //         $logistik->nama_kapal = $request->nama_kapal;
    //         $logistik->etd = $request->etd;
    //         $logistik->eta = $request->eta;
    //         $logistik->atd = $request->atd;
    //         $logistik->ata = $request->ata;
    //     }
    //     $logistik->save();

    //     $shipment = LogistikPengiriman::where('no_shipment', $logistik->no_shipment)->get();

    //     $baseEstimasi = (!$blocked && $keluar)
    //         ? strtotime("+{$leadtime} days", $keluar)
    //         : null;

    //     $lastBongkar = $shipment->whereNotNull('tanggal_bongkar')->max('tanggal_bongkar');

    //     $nextEstimasi = $lastBongkar
    //         ? date('Y-m-d', strtotime($lastBongkar . ' +1 day'))
    //         : ($baseEstimasi ? date('Y-m-d', $baseEstimasi) : null);

    //     foreach ($shipment as $item) {
    //         if (!empty($item->tanggal_tiba)) {
    //             continue;
    //         }
    //         $item->estimasi_tiba = $nextEstimasi;
    //         $item->save();
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Data transport laut berhasil diupdate'
    //     ]);
    // }

    // public function updateTransportLaut(Request $request)
    // {
    //     $request->validate(['no_shipment' => 'required']);

    //     $data = [
    //         'nama_kapal' => $request->nama_kapal,
    //         'etd' => $request->etd,
    //         'eta' => $request->eta,
    //         'atd' => $request->atd,
    //         'ata' => $request->ata,
    //     ];

    //     LogistikPengiriman::where('no_shipment', $request->no_shipment)->update($data);

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Data transport laut berhasil diupdate'
    //     ]);
    // }

 private function getKeluarGudangInfo($r)
{
    $blocked = false;
    $blockedStatus = null; // 'menuju' | 'sedang'
    $keluarTimestamps = [];

    foreach ($this->gudangSuffixes() as $s) {
        $planning = $r->{"planning_loading{$s}"} ?? null;
        $tiba     = $r->{"tanggal_tiba_gudang{$s}"} ?? null;
        $keluar   = $r->{"tanggal_keluar_gudang{$s}"} ?? null;

        $started = !empty($planning) || !empty($tiba);
        $selesai = !empty($keluar);

        if ($started && !$selesai) {
            $blocked = true;
            $blockedStatus = !empty($tiba) ? 'sedang' : 'menuju';
        }
        if ($selesai) {
            $keluarTimestamps[] = strtotime($keluar);
        }
    }

    return [
        'blocked'        => $blocked,
        'blocked_status' => $blockedStatus,
        'keluar'         => !empty($keluarTimestamps) ? max($keluarTimestamps) : null,
    ];
}

    private function generateStatusAlert($sla_tiba, $sla_bongkar)
    {
        $sla_tiba = strtolower(trim($sla_tiba ?? '-'));
        $sla_bongkar = strtolower(trim($sla_bongkar ?? '-'));

        if ($sla_tiba == '-' || $sla_bongkar == '-') {
            return ['status_akhir' => '-', 'alert' => '-'];
        }

        if ($sla_tiba == 'on time' && $sla_bongkar == 'on time') {
            return ['status_akhir' => 'On Time Total', 'alert' => 'Delivered On Time'];
        }

        if ($sla_tiba == 'delay' && $sla_bongkar == 'on time') {
            return ['status_akhir' => 'Delay Perjalanan', 'alert' => 'Delay Perjalanan'];
        }

        if ($sla_tiba == 'on time' && $sla_bongkar == 'delay') {
            return ['status_akhir' => 'Delay Pembongkaran', 'alert' => 'Delay Pembongkaran'];
        }

        return ['status_akhir' => 'Delay Total', 'alert' => 'Delivered Delay'];
    }

 public function bongkarDelay(Request $request)
{
    $filter = DB::table('logistik_pengiriman')
        ->where(function ($q) {
            $q->whereIn('sla_bongkar', ['Delay', 'Critical Delay'])
              ->orWhere('overstay_days', '>', 0);
        })
        ->whereNotNull('tanggal_bongkar')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00');

    if ($request->filled('tanggal_bongkar')) $filter->whereDate('tanggal_bongkar', $request->tanggal_bongkar);
    if ($request->filled('area'))            $filter->where('area', $request->area);

    $list = DB::table('logistik_pengiriman')
        ->whereIn('id', $this->groupIdsQuery($filter))
        ->orderByDesc('tanggal_bongkar')
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->paginate(50)
        ->withQueryString();

    return view('monitoring.bongkar_delay', compact('list'));
}

   public function bongkarOntime(Request $request)
{
    $filter = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_bongkar')
        ->whereNotNull('tanggal_tiba')
        ->where('tanggal_bongkar', '!=', '1899-12-31 00:00:00')
        ->whereRaw("DATEDIFF(DATE(tanggal_bongkar), DATE(tanggal_tiba)) <= 0");

    if ($request->filled('tanggal_bongkar')) $filter->whereDate('tanggal_bongkar', $request->tanggal_bongkar);
    if ($request->filled('area'))            $filter->where('area', $request->area);

    $list = DB::table('logistik_pengiriman')
        ->selectRaw("logistik_pengiriman.*, 'On Time' AS sla_bongkar")
        ->whereIn('id', $this->groupIdsQuery($filter))
        ->orderByDesc('tanggal_bongkar')
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->paginate(50)
        ->withQueryString();

    return view('monitoring.bongkar_ontime', compact('list'));
}
  public function slaOntime(Request $request)
{
    // 1) kondisi filter (tanpa select)
    $filter = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('estimasi_tiba')
        ->whereRaw("DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0");

    if ($request->filled('bulan')) {
        $filter->whereMonth('tanggal_tiba', $request->bulan);
    }
    if ($request->filled('tahun')) {
        $filter->whereYear('tanggal_tiba', $request->tahun);
    }

    // 2) tampilkan hanya 1 baris wakil per shipment + tujuan
    $logistik = DB::table('logistik_pengiriman')
        ->selectRaw("
            logistik_pengiriman.*,
            estimasi_tiba AS tanggal_estimasi,
            CASE
                WHEN DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) <= 0
                THEN 'On Time' ELSE 'Delay'
            END AS sla_tiba
        ")
        ->whereIn('id', $this->groupIdsQuery($filter))
        ->orderByDesc('tanggal_tiba')
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->paginate(50)
        ->withQueryString();

    return view('monitoring.sla_ontime', compact('logistik'));
}
 public function slaDelay(Request $request)
{
    $filter = DB::table('logistik_pengiriman')
        ->whereNotNull('tanggal_tiba')
        ->whereNotNull('estimasi_tiba')
        ->whereRaw("DATEDIFF(DATE(tanggal_tiba), DATE(estimasi_tiba)) > 0");

    if ($request->filled('bulan')) $filter->whereMonth('tanggal_tiba', $request->bulan);
    if ($request->filled('tahun')) $filter->whereYear('tanggal_tiba', $request->tahun);

    $logistik = DB::table('logistik_pengiriman')
        ->selectRaw("
            logistik_pengiriman.*,
            estimasi_tiba AS tanggal_estimasi,
            'Delay' AS sla_tiba
        ")
        ->whereIn('id', $this->groupIdsQuery($filter))
        ->orderByDesc('tanggal_tiba')
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->paginate(50)
        ->withQueryString();

    return view('monitoring.sla_delay', compact('logistik'));
}
  public function summaryArea()
{
    $summary_area = DB::table('logistik_pengiriman')
        ->whereIn('id', $this->groupIdsQuery(DB::table('logistik_pengiriman')))
        ->select('area', DB::raw('COUNT(*) as total'))
        ->groupBy('area')
        ->orderByDesc('total')
        ->get();

    return view('monitoring.summary_area', compact('summary_area'));
}

public function summaryAreaDetail(Request $request)
{
    $area   = $request->area;
    $filter = DB::table('logistik_pengiriman')->where('area', $area);

    $logistik = DB::table('logistik_pengiriman')
        ->whereIn('id', $this->groupIdsQuery($filter))
        ->orderBy('no_shipment')
        ->orderBy('tujuan')
        ->paginate(50)
        ->withQueryString();

    return view('monitoring.summary_area_detail', compact('logistik', 'area'));
}
}