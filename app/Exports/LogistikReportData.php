<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet "Data" = isi LogistikExportFull (kolom terpilih + kolom [Hitung])
 * + kolom bantu slicer (Periode Keluar, Tanggal Keluar Terakhir, Tahun/Bulan Naik Logistik)
 * + kolom bantu "Rk ..." untuk sheet Ringkasan yang HIDUP (rumus SUMIFS yang ikut filter/slicer),
 * dijadikan Excel Table "tblLogistik".
 *
 * Kolom "Rk ..." (dihitung di PHP, 1 = dihitung, 0 = tidak):
 *   Rk Tampil       -> rumus =SUBTOTAL(103, Rk Wakil Grup): 1 kalau baris sedang terlihat (tidak terfilter)
 *   Rk Wakil Grup   -> 1 hanya pada baris wakil (MIN id) tiap grup no_shipment + tujuan
 *   Rk Nilai Grup / Rk Biaya Grup -> total grup, hanya di baris wakil
 *   Rk Tiba OT ... Rk Gudang Delay -> flag metrik, hanya di baris wakil
 *   Rk Area, Rk PIC Monitoring, Rk Dist Channel, Rk Ekspedisi, Rk Tujuan, Rk Planner
 *                   -> kunci pengelompokan (kosong = "(kosong)") yang dibaca SUMIFS di Ringkasan
 */
class LogistikReportData extends LogistikExportFull implements WithEvents, WithTitle
{
    public const SUFFIXES = ['', '_2', '_3', '_4', '_5'];

    /** kolom yang tampil di sheet Data laporan (sisanya ada di Export Data cepat) */
    private const KEEP = [
        'no_shipment', 'no_do', 'tanggal_naik_logistik', 'rencana_kirim',
        'planner', 'tujuan', 'area', 'dist_channel', 'ekpedisi', 'mobil',
        'nilai_muatan', 'biaya_kirim', 'kirim_kontribusi', 'pic_monitoring',
        'tanggal_keluar_gudang', 'tanggal_tiba', 'tanggal_bongkar',
        'sla_tiba', 'sla_bongkar', 'status_akhir', 'monitoring_alert',
        '[Hitung] Update Posisi Mobil', '[Hitung] CR (%)', '[Hitung] Status Akhir',
    ];

    /** pola di kolom `mobil` yang dianggap Reefer; selain itu = Dry */
    private const REEFER_REGEX = '/reefer|reffer|refer|chiller|frozen|freezer|cold/i';

    private ?array $extraCols = null;
    private ?array $tblCols = null;
    private ?array $grp = null;
    private ?array $groupList = null;

    private ?object $factsFor = null;
    private array $facts = [];
    private ?array $gdgCols = null;

    private ?array $letters = null;
    private int $rowNo = 1;              // baris 1 = heading
    private ?int $tampilIdx = null;
    private ?string $wkLetter = null;

    public function title(): string
    {
        return 'Data';
    }

    // ============================================================
    // KOLOM = kolom LogistikExportFull + kolom bantu
    // ============================================================
    protected function columns(): array
    {
        if ($this->extraCols !== null) {
            return $this->extraCols;
        }

        $base = array_values(array_filter(
            parent::columns(),
            fn($c) => in_array($c[0], self::KEEP, true)
        ));

        $bln = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $extra = [
            ['Periode Keluar', 'raw', fn($r) => ($k = $this->keluarTs($r)) ? (int) date('Ym', $k) : ''],
            ['Tanggal Keluar Terakhir', 'date', fn($r) => ($k = $this->keluarTs($r)) ? $this->xl($k) : ''],
            ['Tahun Naik Logistik', 'raw', fn($r) => ($t = strtotime((string) $r->tanggal_naik_logistik)) ? (int) date('Y', $t) : ''],
            ['Bulan Naik Logistik', 'raw', fn($r) => ($t = strtotime((string) $r->tanggal_naik_logistik))
                ? date('m', $t) . ' ' . $bln[(int) date('n', $t)] : ''],
        ];

        return $this->extraCols = array_merge($base, $extra, $this->rankColumns());
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');
        $sheet->getDefaultColumnDimension()->setWidth(18);
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '03C03C']],
        ]);
        return [];
    }

    /** map() = nilai kolom biasa + rumus SUBTOTAL di Rk Tampil (ditulis langsung, tanpa loop kedua) */
    public function map($r): array
    {
        $row = parent::map($r);

        $this->tampilIdx ??= array_search('Rk Tampil', array_column($this->columns(), 0), true);
        $this->wkLetter  ??= $this->letter('Rk Wakil Grup');

        $row[$this->tampilIdx] = '=SUBTOTAL(103,$' . $this->wkLetter . (++$this->rowNo) . ')';

        return $row;
    }

    // ============================================================
    // DATA GRUP (SATU query)
    // ============================================================

    /** SATU query: baris wakil tiap grup + kolom kunci + kolom keluar gudang */
    private function groupList(): array
    {
        if ($this->groupList !== null) {
            return $this->groupList;
        }

        $cols = ['lp.id', 'g.g_nilai', 'g.g_biaya'];
        foreach (['area', 'pic_monitoring', 'dist_channel', 'ekpedisi', 'tujuan', 'planner'] as $c) {
            if ($this->hasColumn($c)) {
                $cols[] = "lp.{$c}";
            }
        }
        foreach (self::SUFFIXES as $s) {
            if ($this->hasColumn("tanggal_keluar_gudang{$s}")) {
                $cols[] = "lp.tanggal_keluar_gudang{$s}";
            }
        }

        return $this->groupList = $this->groupRows()->select($cols)->get()->all();
    }

    /** id baris wakil => [total nilai grup, total biaya grup] */
    private function groupInfo(): array
    {
        if ($this->grp !== null) {
            return $this->grp;
        }

        $out = [];
        foreach ($this->groupList() as $x) {
            $out[(int) $x->id] = [(float) $x->g_nilai, (float) $x->g_biaya];
        }

        return $this->grp = $out;
    }

    /** daftar label unik utk 1 kolom (case-insensitive, sama seperti SUMIFS), urut 'nilai' (desc) atau 'abjad' */
    public function keyList(string $col, string $order = 'abjad', int $limit = 10000): array
    {
        $tot = [];
        $lbl = [];
        foreach ($this->groupList() as $g) {
            $k = trim((string) ($g->{$col} ?? ''));
            if ($k === '') {
                $k = '(kosong)';
            }
            $lk = mb_strtolower($k);
            $lbl[$lk] ??= $k;
            $tot[$lk] = ($tot[$lk] ?? 0) + (float) $g->g_nilai;
        }

        $order === 'nilai' ? arsort($tot) : ksort($tot, SORT_STRING);

        $keys = [];
        foreach (array_keys($tot) as $lk) {
            $keys[] = $lbl[$lk];
        }

        return array_slice($keys, 0, $limit);
    }

    /** daftar periode keluar gudang (yyyymm) dari baris wakil */
    public function periodeKeys(): array
    {
        $set = [];
        foreach ($this->groupList() as $g) {
            $max = null;
            foreach (self::SUFFIXES as $s) {
                $v = $g->{"tanggal_keluar_gudang{$s}"} ?? null;
                if (trim((string) $v) !== '' && ($t = strtotime((string) $v))) {
                    $max = max($max ?? $t, $t);
                }
            }
            if ($max) {
                $set[date('Ym', $max)] = true;
            }
        }

        $keys = array_keys($set);
        sort($keys);

        return array_map('strval', $keys);
    }

    // ============================================================
    // FLAG TURUNAN PER BARIS (dihitung sekali, dipakai ulang tiap kolom Rk)
    // ============================================================

    /** [suffix => kolom 'mulai' yg ada di tabel] untuk gudang yang punya kolom keluar */
    private function gdgCols(): array
    {
        if ($this->gdgCols !== null) {
            return $this->gdgCols;
        }

        $out = [];
        foreach (self::SUFFIXES as $s) {
            if (!$this->hasColumn("tanggal_keluar_gudang{$s}")) {
                continue;
            }
            $out[$s] = array_values(array_filter(
                ["planning_loading{$s}", "tanggal_tiba_gudang{$s}"],
                fn($c) => $this->hasColumn($c)
            ));
        }

        return $this->gdgCols = $out;
    }

    private function facts($r): array
    {
        if ($this->factsFor === $r) {
            return $this->facts;
        }

        $has = fn(string $c) => trim((string) ($r->{$c} ?? '')) !== '';
        $lc  = fn(string $c) => strtolower(trim((string) ($r->{$c} ?? '')));

        $info = $this->groupInfo()[(int) $r->id] ?? null;
        $wk   = $info !== null ? 1 : 0;
        $f    = fn(bool $c) => ($wk && $c) ? 1 : 0;

        $tiba    = $has('tanggal_tiba');
        $bongkar = $has('tanggal_bongkar');

        // in transit: belum tiba, min. 1 gudang sudah keluar, tidak ada siklus gudang menggantung
        $inTransit = false;
        if (!$tiba && $this->gdgCols()) {
            $any      = false;
            $dangling = false;
            foreach ($this->gdgCols() as $s => $started) {
                if ($has("tanggal_keluar_gudang{$s}")) {
                    $any = true;
                    continue;
                }
                foreach ($started as $c) {
                    if ($has($c)) {
                        $dangling = true;
                        break;
                    }
                }
            }
            $inTransit = $any && !$dangling;
        }

        // status gudang: delay kalau ada gudang tiba lebih lambat dari planning, ot kalau ada data & tak ada yg telat
        $gd = null;
        foreach (self::SUFFIXES as $s) {
            $p = strtotime((string) ($r->{"planning_loading{$s}"} ?? ''));
            $t = strtotime((string) ($r->{"tanggal_tiba_gudang{$s}"} ?? ''));
            if (!$p || !$t) {
                continue;
            }
            $gd = 'ot';
            if (date('Y-m-d', $t) > date('Y-m-d', $p)) {
                $gd = 'delay';
                break;
            }
        }

        // tanggal keluar gudang terakhir
        $keluar = null;
        foreach (self::SUFFIXES as $s) {
            $v = $r->{"tanggal_keluar_gudang{$s}"} ?? null;
            if (trim((string) $v) !== '' && ($t = strtotime((string) $v))) {
                $keluar = max($keluar ?? $t, $t);
            }
        }

        $sT = $lc('sla_tiba');
        $sB = $lc('sla_bongkar');
        $sA = $lc('status_akhir');
        $mA = $lc('monitoring_alert');

        $this->factsFor = $r;

        return $this->facts = [
            'wk'         => $wk,
            'nilai'      => $info[0] ?? 0,
            'biaya'      => $info[1] ?? 0,
            'tiba_ot'    => $f($sT === 'on time'),
            'tiba_dl'    => $f($sT === 'delay'),
            'bong_ot'    => $f($sB === 'on time'),
            'bong_dl'    => $f($sB === 'delay'),
            'ak_ot'      => $f($sA === 'on time total'),
            'ak_dp'      => $f($sA === 'delay perjalanan'),
            'ak_dpb'     => $f($sA === 'delay pembongkaran'),
            'ak_dt'      => $f($sA === 'delay total'),
            'del_ot'     => $f($mA === 'delivered on time'),
            'del_dl'     => $f($mA === 'delivered delay'),
            'in_transit' => $f($inTransit),
            'belum_tiba' => $f(!$tiba),
            'belum_bong' => $f($tiba && !$bongkar),
            'gd_ot'      => $f($gd === 'ot'),
            'gd_dl'      => $f($gd === 'delay'),
            'tipe'       => preg_match(self::REEFER_REGEX, (string) ($r->mobil ?? '')) ? 'Reefer' : 'Dry',
            'keluar'     => $keluar,
        ];
    }

    private function keluarTs($r): ?int
    {
        return $this->facts($r)['keluar'];
    }

    private function rankColumns(): array
    {
        $k = fn(string $key) => fn($r) => $this->facts($r)[$key];

        $cols = [
            ['Rk Tampil',                   'raw', fn($r) => ''],   // diisi rumus di map()
            ['Rk Wakil Grup',               'raw', $k('wk')],
            ['Rk Nilai Grup',               'raw', $k('nilai')],
            ['Rk Biaya Grup',               'raw', $k('biaya')],
            ['Rk Tiba OT',                  'raw', $k('tiba_ot')],
            ['Rk Tiba Delay',               'raw', $k('tiba_dl')],
            ['Rk Bongkar OT',               'raw', $k('bong_ot')],
            ['Rk Bongkar Delay',            'raw', $k('bong_dl')],
            ['Rk Akhir OT',                 'raw', $k('ak_ot')],
            ['Rk Akhir Delay Perjalanan',   'raw', $k('ak_dp')],
            ['Rk Akhir Delay Pembongkaran', 'raw', $k('ak_dpb')],
            ['Rk Akhir Delay Total',        'raw', $k('ak_dt')],
            ['Rk Delivered OT',             'raw', $k('del_ot')],
            ['Rk Delivered Delay',          'raw', $k('del_dl')],
            ['Rk In Transit',               'raw', $k('in_transit')],
            ['Rk Belum Tiba',               'raw', $k('belum_tiba')],
            ['Rk Belum Bongkar',            'raw', $k('belum_bong')],
            ['Rk Gudang OT',                'raw', $k('gd_ot')],
            ['Rk Gudang Delay',             'raw', $k('gd_dl')],
            ['Rk Tipe Mobil',               'raw', $k('tipe')],
        ];

        // kolom kunci pengelompokan untuk SUMIFS di sheet Ringkasan (kosong => "(kosong)")
        $keyCol = fn(string $c) => fn($r) => ($v = trim((string) ($r->{$c} ?? ''))) !== '' ? $v : '(kosong)';

        foreach ([
            ['Rk Area',           'area'],
            ['Rk PIC Monitoring', 'pic_monitoring'],
            ['Rk Dist Channel',   'dist_channel'],
            ['Rk Ekspedisi',      'ekpedisi'],
            ['Rk Tujuan',         'tujuan'],
            ['Rk Planner',        'planner'],
        ] as [$h, $c]) {
            if ($this->hasColumn($c)) {
                $cols[] = [$h, 'raw', $keyCol($c)];
            }
        }

        return $cols;
    }

    /** heading harus unik (Excel Table tidak boleh ada header kembar, tidak peduli huruf besar/kecil) */
    public function headings(): array
    {
        $seen = [];
        $out  = [];

        foreach ($this->columns() as $col) {
            $h = (string) $col[0];
            $k = mb_strtolower($h);

            if (isset($seen[$k])) {
                $seen[$k]++;
                $h .= ' (' . $seen[$k] . ')';
            } else {
                $seen[$k] = 1;
            }
            $out[] = $h;
        }

        return $out;
    }

    public function columnFormats(): array
    {
        $f = parent::columnFormats();
        $f[$this->letter('Periode Keluar')] = '0000"-"00';   // 202611 tampil 2026-11
        $f[$this->letter('Rk Nilai Grup')]  = '#,##0';
        $f[$this->letter('Rk Biaya Grup')]  = '#,##0';
        return $f;
    }

    /** huruf kolom (A, B, ...) berdasarkan nama heading (di-cache) */
    public function letter(string $head): string
    {
        if ($this->letters === null) {
            $this->letters = [];
            foreach ($this->columns() as $i => $col) {
                $this->letters[$col[0]] ??= Coordinate::stringFromColumnIndex($i + 1);
            }
        }

        if (!isset($this->letters[$head])) {
            throw new \RuntimeException("Kolom '{$head}' tidak ada di sheet Data.");
        }

        return $this->letters[$head];
    }

    // ============================================================
    // QUERY AGREGAT (ikut filter yang sama dengan export)
    // ============================================================
    public function hasColumn(string $col): bool
    {
        $this->tblCols ??= array_flip(Schema::getColumnListing($this->tabel ?: 'logistik_pengiriman'));
        return isset($this->tblCols[$col]);
    }

    private function baseQuery()
    {
        $q = clone $this->builder;
        return $q instanceof EloquentBuilder ? $q->toBase() : $q;
    }

    /** jumlah baris DO (belum digabung per grup) */
    public function totalRows(): int
    {
        return (int) $this->baseQuery()->reorder()->count();
    }

    /**
     * 1 baris wakil per grup (no_shipment + tujuan), sama dengan groupIdsQuery() di controller,
     * ditambah total grup dari SEMUA DO di grup itu:
     *   g.g_nilai = SUM(nilai_muatan)
     *   g.g_biaya = SUM(kirim_kontribusi), kalau kosong semua pakai MAX(biaya_kirim)
     */
    public function groupRows()
    {
        $agg = $this->baseQuery()
            ->reorder()
            ->select(DB::raw(
                'MIN(id) AS id, '
                . 'SUM(COALESCE(nilai_muatan,0)) AS g_nilai, '
                . 'COALESCE(SUM(kirim_kontribusi), MAX(COALESCE(biaya_kirim,0)), 0) AS g_biaya'
            ))
            ->groupByRaw("COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)), COALESCE(tujuan,'')");

        return $this->source('lp')->joinSub($agg, 'g', 'g.id', '=', 'lp.id');
    }

    /** ekspresi SQL: tanggal keluar gudang terakhir dari semua gudang */
    public function keluarExpr(): ?string
    {
        $cols = [];
        foreach (self::SUFFIXES as $s) {
            if ($this->hasColumn("tanggal_keluar_gudang{$s}")) {
                $cols[] = "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang{$s}),''),'1900-01-01')";
            }
        }
        return empty($cols) ? null : 'GREATEST(' . implode(',', $cols) . ')';
    }

    /** daftar kolom hitungan metrik: alias => ekspresi SQL (dijalankan pada baris wakil per grup) */
    public function metrics(): array
    {
        $sum    = fn(string $cond) => "SUM(CASE WHEN {$cond} THEN 1 ELSE 0 END)";
        $lc     = fn(string $c) => "LOWER(TRIM({$c}))";
        $filled = fn(string $c) => "NULLIF(TRIM({$c}),'') IS NOT NULL";
        $empty  = fn(string $c) => "NULLIF(TRIM({$c}),'') IS NULL";

        $anyKeluar = [];
        $dangling  = [];
        foreach (self::SUFFIXES as $s) {
            $k = "tanggal_keluar_gudang{$s}";
            if (!$this->hasColumn($k)) {
                continue;
            }
            $anyKeluar[] = $filled($k);

            $started = [];
            foreach (["planning_loading{$s}", "tanggal_tiba_gudang{$s}"] as $c) {
                if ($this->hasColumn($c)) {
                    $started[] = $filled($c);
                }
            }
            if (!empty($started)) {
                $dangling[] = '((' . implode(' OR ', $started) . ') AND ' . $empty($k) . ')';
            }
        }

        $inTransit = empty($anyKeluar)
            ? '0=1'
            : '(' . $empty('tanggal_tiba')
              . ' AND (' . implode(' OR ', $anyKeluar) . ')'
              . (empty($dangling) ? '' : ' AND NOT (' . implode(' OR ', $dangling) . ')')
              . ')';

        return [
            'total'      => 'COUNT(*)',
            'tiba_ot'    => $sum($lc('sla_tiba') . "='on time'"),
            'tiba_dl'    => $sum($lc('sla_tiba') . "='delay'"),
            'bong_ot'    => $sum($lc('sla_bongkar') . "='on time'"),
            'bong_dl'    => $sum($lc('sla_bongkar') . "='delay'"),
            'ak_ot'      => $sum($lc('status_akhir') . "='on time total'"),
            'ak_dp'      => $sum($lc('status_akhir') . "='delay perjalanan'"),
            'ak_dpb'     => $sum($lc('status_akhir') . "='delay pembongkaran'"),
            'ak_dt'      => $sum($lc('status_akhir') . "='delay total'"),
            'del_ot'     => $sum($lc('monitoring_alert') . "='delivered on time'"),
            'del_dl'     => $sum($lc('monitoring_alert') . "='delivered delay'"),
            'in_transit' => $sum($inTransit),
            'belum_tiba' => $sum($empty('tanggal_tiba')),
            'belum_bong' => $sum($filled('tanggal_tiba') . ' AND ' . $empty('tanggal_bongkar')),
            'nilai'      => 'SUM(g.g_nilai)',
            'biaya'      => 'SUM(g.g_biaya)',
        ];
    }

    /**
     * Ringkasan per kolom ($col = 'tujuan' / 'planner') dari baris wakil per grup.
     */
    public function summaryBy(string $col, string $fallback): array
    {
        if (!$this->hasColumn($col)) {
            return [];
        }

        $m   = $this->metrics();
        $sel = [];
        foreach (['total', 'tiba_ot', 'tiba_dl', 'bong_ot', 'bong_dl', 'nilai', 'biaya'] as $k) {
            $sel[] = "{$m[$k]} AS {$k}";
        }

        $lbl = "COALESCE(NULLIF(TRIM(lp.{$col}),''), '{$fallback}')";

        return $this->groupRows()
            ->selectRaw("{$lbl} AS grp_label, " . implode(', ', $sel))
            ->groupByRaw($lbl)
            ->orderByDesc('nilai')
            ->get()
            ->map(function ($r) {
                $nilai = (float) $r->nilai;
                $biaya = (float) $r->biaya;

                return [
                    'label'   => $r->grp_label,
                    'total'   => (int) $r->total,
                    'tiba_ot' => (int) $r->tiba_ot,
                    'tiba_dl' => (int) $r->tiba_dl,
                    'bong_ot' => (int) $r->bong_ot,
                    'bong_dl' => (int) $r->bong_dl,
                    'nilai'   => $nilai,
                    'biaya'   => $biaya,
                    'cr'      => $nilai > 0 ? round($biaya / $nilai * 100, 4) : 0,
                ];
            })->all();
    }

    public function perTujuan(): array
    {
        return $this->summaryBy('tujuan', 'TANPA TUJUAN');
    }

    public function perPlanner(): array
    {
        return $this->summaryBy('planner', 'TANPA PLANNER');
    }

    /**
     * (Tidak dipakai Ringkasan hidup; dipertahankan supaya pemanggil lain tidak putus.)
     * Tulis blok Per Tujuan + Per Planner + 4 chart ke sheet mana pun.
     */
    public function tulisPerTujuanPlanner(Worksheet $sheet, int $startRow = 1, int $topN = 15): int
    {
        $head = ['Total DO', 'Tiba On Time', 'Tiba Delay', 'Bongkar On Time',
                 'Bongkar Delay', 'Nilai Muatan', 'Biaya Kirim', 'CR (%)'];

        $blocks = [
            ['RINGKASAN PER TUJUAN',  'Tujuan',  $this->perTujuan()],
            ['RINGKASAN PER PLANNER', 'Planner', $this->perPlanner()],
        ];

        $r  = $startRow;
        $tj = null;

        foreach ($blocks as $i => [$judul, $label, $rows]) {
            $sheet->setCellValue("A{$r}", $judul);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(13);
            $r++;

            $sheet->fromArray(array_merge([$label], $head), null, "A{$r}");
            $sheet->getStyle("A{$r}:I{$r}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F2937']],
            ]);
            $hdr = $r;
            $r++;

            $first = $r;
            $data  = array_map(fn($x) => [
                $x['label'], $x['total'], $x['tiba_ot'], $x['tiba_dl'],
                $x['bong_ot'], $x['bong_dl'], $x['nilai'], $x['biaya'], $x['cr'],
            ], $rows);

            if (!empty($data)) {
                $sheet->fromArray($data, null, "A{$r}");
                $r += count($data);
            }
            $last = $r - 1;

            if ($last >= $first) {
                $sheet->getStyle("G{$first}:H{$last}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("I{$first}:I{$last}")->getNumberFormat()->setFormatCode('0.0000"%"');
            }

            if ($i === 0) {
                $tj = ['hdr' => $hdr, 'first' => $first, 'last' => $last];
            }
            $r += 2;
        }

        if ($tj && $tj['last'] >= $tj['first']) {
            $s    = "'" . str_replace("'", "''", $sheet->getTitle()) . "'";
            $cEnd = min($tj['last'], $tj['first'] + $topN - 1);
            $n    = $cEnd - $tj['first'] + 1;
            $top  = "Top {$n} Tujuan";

            $mk = function (string $name, string $title, array $cols, int $row) use ($sheet, $s, $tj, $cEnd, $n) {
                $labels = $values = [];
                foreach ($cols as $c) {
                    $labels[] = new DataSeriesValues('String', "{$s}!\${$c}\${$tj['hdr']}", null, 1);
                    $values[] = new DataSeriesValues('Number', "{$s}!\${$c}\${$tj['first']}:\${$c}\${$cEnd}", null, $n);
                }
                $cats = [new DataSeriesValues('String', "{$s}!\$A\${$tj['first']}:\$A\${$cEnd}", null, $n)];

                $ds = new DataSeries(
                    DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED,
                    range(0, count($cols) - 1), $labels, $cats, $values
                );
                $ds->setPlotDirection(DataSeries::DIRECTION_COL);

                $ch = new Chart($name, new Title($title), new Legend(Legend::POSITION_BOTTOM, null, false),
                    new PlotArea(null, [$ds]), true);
                $ch->setTopLeftPosition("K{$row}");
                $ch->setBottomRightPosition('S' . ($row + 19));
                $sheet->addChart($ch);
            };

            $row = $startRow;
            $mk("c_tiba_{$startRow}",    "SLA Tiba per Tujuan - {$top}",         ['C', 'D'], $row);
            $mk("c_bongkar_{$startRow}", "SLA Bongkar per Tujuan - {$top}",      ['E', 'F'], $row + 21);
            $mk("c_nilai_{$startRow}",   "Nilai Muatan vs Biaya Kirim - {$top}", ['G', 'H'], $row + 42);
            $mk("c_cr_{$startRow}",      "CR (%) per Tujuan - {$top}",           ['I'],      $row + 63);
        }

        return $r;
    }

    // ============================================================
    // EVENT: jadikan Excel Table, warnai kolom bantu
    // (rumus Rk Tampil sudah ditulis langsung di map())
    // ============================================================
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $lastCol = $sheet->getHighestColumn();

                $sheet->getTabColor()->setRGB('03C03C');

                // kolom bantu (Periode Keluar ... Rk Planner): header abu-abu
                $first = $this->letter('Periode Keluar');
                $sheet->getStyle("{$first}1:{$lastCol}1")->applyFromArray([
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '6B7280']],
                ]);

                // Table hanya valid kalau ada minimal 1 baris data
                if ($lastRow >= 2) {
                    $table = new Table("A1:{$lastCol}{$lastRow}", 'tblLogistik');
                    $table->setShowHeaderRow(true);
                    $table->setAllowFilter(true);
                    $table->setStyle((new TableStyle(TableStyle::TABLE_STYLE_LIGHT1))->setShowRowStripes(true));
                    $sheet->addTable($table);
                }
            },
        ];
    }
}