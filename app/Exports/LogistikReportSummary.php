<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

/**
 * Sheet 2 "Ringkasan": semua angka = rumus SUMIFS ke sheet Data (kolom "Rk ..."), ikut filter/slicer.
 *
 * TAMPILAN SEPERTI PIVOT: tiap blok (Area, PIC, Dist Channel, Ekspedisi, Tujuan, Planner, Periode)
 * hanya menampilkan kategori yang lolos slicer (Total grup > 0), baris naik ke atas, dan baris TOTAL
 * menempel tepat di bawah baris terakhir. Sisa baris di bawahnya kosong.
 *   - Data lengkap semua kategori dihitung di kolom SUMBER (mulai kolom CL, disembunyikan)
 *   - Kolom A-Q (yang terlihat) hanya mengambil baris yang lolos dari kolom sumber via INDEX
 *   - Blok Tipe (Dry/Reefer + anak per area) tetap seperti biasa
 *
 * Tabel bantu untuk Dashboard:
 *  - Top 20 tujuan (kolom AC-AK, flag di T2, jumlah tampil di AM2)
 *  - Tabel "padat" per Area / Dist Channel / Ekspedisi / PIC (kolom AO dst, 10 kolom per blok)
 *  - Dry vs Reefer (S16:U18), CR total (W16:X17)
 */
class LogistikReportSummary implements WithTitle, WithEvents
{
    private const KPI_START   = 5;     // header KPI di baris 4, isi baris 5-24
    private const BLOCK_START = 27;
    public const TUJ_TOP = 20;
   private const MAX_ROWS = 10000;   // sebelumnya 10000 (kategori diurut nilai terbesar, jadi yang teratas tetap ada)

    public const HELP_HDR   = 3;
    public const HELP_FIRST = 4;
    public const FLAG_REF   = "'Ringkasan'!\$T\$2";   // 1 = slicer/filter sedang aktif

    /** blok yang punya tabel bantu "padat" untuk grafik Dashboard */
    public const CMP_KEYS = ['area', 'dist_channel', 'ekpedisi', 'pic_monitoring'];
    /** maksimal kategori per grafik */
public const CMP_N = 40;
    /** kolom blok yang disalin ke tabel padat */
    private const CMP_COLS = ['C', 'D', 'F', 'G', 'N', 'O', 'P'];
    private const CMP_START_COL = 41;   // AO
    private const CMP_STEP      = 10;

    /** kolom SUMBER (tersembunyi): CJ = kunci, CK = posisi, CL.. = salinan kolom A-Q */
    private const SRC_START = 90;

    private const RK = [
        'total' => 'Rk Wakil Grup', 'tiba_ot' => 'Rk Tiba OT', 'tiba_dl' => 'Rk Tiba Delay',
        'gd_ot' => 'Rk Gudang OT', 'gd_dl' => 'Rk Gudang Delay',
        'bong_ot' => 'Rk Bongkar OT', 'bong_dl' => 'Rk Bongkar Delay',
        'ak_ot' => 'Rk Akhir OT', 'ak_dp' => 'Rk Akhir Delay Perjalanan',
        'ak_dpb' => 'Rk Akhir Delay Pembongkaran', 'ak_dt' => 'Rk Akhir Delay Total',
        'del_ot' => 'Rk Delivered OT', 'del_dl' => 'Rk Delivered Delay',
        'in_transit' => 'Rk In Transit', 'belum_tiba' => 'Rk Belum Tiba', 'belum_bong' => 'Rk Belum Bongkar',
        'nilai' => 'Rk Nilai Grup', 'biaya' => 'Rk Biaya Grup',
        'in_gudang' => 'Rk In Gudang',
    ];

    private ?array $lay = null;
    private ?int $lastRow = null;
    private array $rngCache = [];

    public function __construct(private LogistikReportData $data, private string $filterInfo = '')
    {
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    /** dipertahankan supaya injector slicer lama tidak putus; slot sekarang ada di Dashboard */
    public static function slicerSlots(): array
    {
        return LogistikReportDashboard::slicerSlots();
    }

    // ============================================================
    // RUMUS
    // ============================================================
    private function lastRow(): int
    {
        return $this->lastRow ??= max(2, $this->data->totalRows() + 1);
    }

    private function rng(string $head): string
    {
        if (!isset($this->rngCache[$head])) {
            $l = $this->data->letter($head);
            $this->rngCache[$head] = "Data!\${$l}\$2:\${$l}\${$this->lastRow()}";
        }
        return $this->rngCache[$head];
    }

    /** SUMIFS(kolom Rk metrik, Rk Tampil, 1 [, kolom kunci, kriteria]...) ; $crits = [[heading, kriteria], ...] */
    private function sumifs(string $metric, array $crits = []): string
    {
        $f = 'SUMIFS(' . $this->rng(self::RK[$metric]) . ',' . $this->rng('Rk Tampil') . ',1';
        foreach ($crits as [$head, $crit]) {
            $f .= ',' . $this->rng($head) . ',' . $crit;
        }
        return $f . ')';
    }

    private function formula($s, string $cell, string $f): void
    {
        $s->setCellValueExplicit($cell, '=' . ltrim($f, '='), DataType::TYPE_FORMULA);
    }

    /** huruf kolom SUMBER untuk kolom tampil $c (A -> CL, B -> CM, ...) */
    private function srcCol(string $c): string
    {
        return Coordinate::stringFromColumnIndex(self::SRC_START + Coordinate::columnIndexFromString($c) - 1);
    }

    // ============================================================
    // LAYOUT BLOK (label baris diambil dari 1 query grup di LogistikReportData)
    // ============================================================
    public function layout(): array
    {
        if ($this->lay !== null) {
            return $this->lay;
        }

        $row    = self::BLOCK_START;
        $blocks = [];

        // [judul, kunci, mode, kolom DB, label header, heading kolom kunci di Data]
        $defs = [
            ['Per Area',                      'area',           'col',     'area',           'Area',           'Rk Area'],
            ['Per Tipe Muatan (Dry/Reefer)',  'tipe',           'static',  null,             'Tipe',           'Rk Tipe Mobil'],
            ['Per PIC Monitoring',            'pic_monitoring', 'col',     'pic_monitoring', 'PIC Monitoring', 'Rk PIC Monitoring'],
            ['Per Dist Channel',              'dist_channel',   'col',     'dist_channel',   'Dist Channel',   'Rk Dist Channel'],
            ['Per Ekspedisi',                 'ekpedisi',       'col',     'ekpedisi',       'Ekspedisi',      'Rk Ekspedisi'],
            ['Per Tujuan',                    'tujuan',         'col',     'tujuan',         'Tujuan',         'Rk Tujuan'],
            ['Per Planner',                   'planner',        'col',     'planner',        'Planner',        'Rk Planner'],
            ['Per Periode Keluar Gudang',     'periode',        'periode', null,             'Periode Keluar', 'Periode Keluar'],
        ];

        foreach ($defs as [$title, $key, $mode, $col, $label, $rk]) {
            if ($mode === 'static') {
                $keys = ['Dry', 'Reefer'];
            } elseif ($mode === 'periode') {
                $keys = $this->data->periodeKeys();
            } else {
                if (!$this->data->hasColumn($col)) {
                    continue;
                }
                // ekspedisi, planner & tujuan urut nilai terbesar; sisanya abjad
                $keys = $this->data->keyList(
                    $col,
                    in_array($key, ['ekpedisi', 'planner', 'tujuan'], true) ? 'nilai' : 'abjad',
                    self::MAX_ROWS
                );
            }

            if (empty($keys)) {
                continue;
            }

            $m       = 0;
            $parents = [];
            if ($key === 'tipe') {
                // tiap tipe = 1 baris induk + 1 baris per area (anak, bisa di-expand)
                $m       = isset($blocks['area']) ? count($blocks['area']['keys']) : 0;
                $rows    = count($keys) * (1 + $m);
                $parents = [$row + 2, $row + 2 + (1 + $m)];   // baris Dry & Reefer
            } else {
                $rows = count($keys);
            }

            $blocks[$key] = [
                'key'        => $key,
                'title'      => $title,
                'label'      => $label,
                'rk'         => $rk,
                'totalLabel' => 'TOTAL',
                'isPeriode'  => $mode === 'periode',
                'keys'       => $keys,
                'childKeys'  => $key === 'tipe' ? ($blocks['area']['keys'] ?? []) : [],
                'm'          => $m,
                'parents'    => $parents,
                'titleRow'   => $row,
                'hdrRow'     => $row + 1,
                'first'      => $row + 2,
                'last'       => $row + 1 + $rows,
                'total'      => $row + 2 + $rows,
                'n'          => count($keys),
            ];

            $row += $rows + 5;
        }

        return $this->lay = ['blocks' => $blocks];
    }

    /**
     * Peta kolom tabel bantu "padat" untuk 1 blok (dipakai Dashboard untuk referensi grafik).
     * null kalau blok itu tidak punya tabel padat / tidak ada datanya.
     */
    public function compactMap(string $key): ?array
    {
        $i = array_search($key, self::CMP_KEYS, true);
        $b = $this->layout()['blocks'][$key] ?? null;
        if ($i === false || !$b) {
            return null;
        }

        $c0   = self::CMP_START_COL + $i * self::CMP_STEP;
        $cols = [];
        foreach (self::CMP_COLS as $j => $src) {
            $cols[$src] = Coordinate::stringFromColumnIndex($c0 + 2 + $j);
        }

        return [
            'pos'   => Coordinate::stringFromColumnIndex($c0),
            'label' => Coordinate::stringFromColumnIndex($c0 + 1),
            'cols'  => $cols,
            'last'  => Coordinate::stringFromColumnIndex($c0 + 1 + count(self::CMP_COLS)),
            'hdr'   => self::HELP_HDR,
            'first' => self::HELP_FIRST,
            'n'     => min(self::CMP_N, $b['n']),
            'blk'   => $b,
        ];
    }

    // ============================================================
    // TULIS SHEET
    // ============================================================
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s   = $event->sheet->getDelegate();
                $lay = $this->layout();

                $s->getTabColor()->setRGB('2563EB');

                $s->setCellValue('A1', 'RINGKASAN LOGISTIK');
                $s->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $s->setCellValue('A2', 'Dibuat ' . date('d-m-Y H:i') . ($this->filterInfo ? ' | ' . $this->filterInfo : ''));
                $s->setCellValue('A3', 'Angka ikut slicer (sheet Dashboard) & filter sheet Data. Per grup (no shipment + tujuan). Hanya data yang lolos slicer yang tampil.');
                $s->getStyle('A2:A3')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');

                $this->writeKpi($s);

                foreach ($lay['blocks'] as $b) {
                    $this->writeBlock($s, $b);
                }

                // tabel bantu grafik Top 20 tujuan (kolom AC-AM) + flag slicer di T2
                if (isset($lay['blocks']['tujuan'])) {
                    $this->writeTujuanHelper($s, $lay['blocks']['tujuan']);
                }

                // tabel bantu grafik Dry vs Reefer (S16:U18)
                if (isset($lay['blocks']['tipe'])) {
                    $this->writeTipeHelper($s, $lay['blocks']['tipe']);
                }

                // tabel bantu grafik CR total data yang dipilih di slicer (W16:X17)
                $this->writeCrTotalHelper($s);
                $this->writeStatusPosisiHelper($s);

                // tabel padat per Area / Channel / Ekspedisi / PIC (kolom AO dst)
                foreach (self::CMP_KEYS as $k) {
                    $this->writeCompact($s, $k);
                }

                $s->getColumnDimension('A')->setWidth(36);
                foreach (range('B', 'Q') as $c) {
                    $s->getColumnDimension($c)->setWidth(16);
                }
                $s->getColumnDimension('L')->setWidth(22);
                $s->getColumnDimension('M')->setWidth(30);
                $s->getColumnDimension('R')->setWidth(12);
                foreach (array_merge(range('S', 'Z'), ['AA']) as $c) {
                    $s->getColumnDimension($c)->setWidth(16);
                }
                foreach (['AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AM'] as $c) {
                    $s->getColumnDimension($c)->setWidth(16);
                }
                foreach (self::CMP_KEYS as $k) {
                    $m = $this->compactMap($k);
                    if ($m) {
                        $s->getColumnDimension($m['label'])->setWidth(22);
                    }
                }

                // kolom SUMBER (kunci, posisi, salinan A-Q) disembunyikan
                for ($i = self::SRC_START - 2; $i <= self::SRC_START + 16; $i++) {
                    $s->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setVisible(false);
                }
            },
        ];
    }

    private function writeKpi($s): void
    {
        $r = self::KPI_START - 1;

        $s->setCellValue("A{$r}", 'RINGKASAN (DASHBOARD)');
        $s->setCellValue("B{$r}", 'Nilai');
        $s->getStyle("A{$r}:B{$r}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '111827']],
        ]);
        $r++;

        $ref = fn(int $i) => 'B' . (self::KPI_START + $i);
        $S   = fn(string $m) => $this->sumifs($m);

        $items = [
            /* 0  */ ['Jumlah DO (baris tampil)',               'SUM(' . $this->rng('Rk Tampil') . ')', null],
            /* 1  */ ['Total Shipment-Tujuan (grup)',           $S('total'), null],
            /* 2  */ ['Total Nilai Muatan',                     $S('nilai'), '"Rp" #,##0'],
            /* 3  */ ['Total Biaya Kirim (Kirim Kontribusi)',   $S('biaya'), '"Rp" #,##0'],
            /* 4  */ ['CR (Biaya Kirim / Nilai Muatan)',        "IF({$ref(2)}>0,{$ref(3)}/{$ref(2)},0)", '0.00%'],
            /* 5  */ ['SLA Tiba - On Time',                     $S('tiba_ot'), null],
            /* 6  */ ['SLA Tiba - Delay',                       $S('tiba_dl'), null],
            /* 7  */ ['% On Time Tiba',                         "IF({$ref(5)}+{$ref(6)}>0,{$ref(5)}/({$ref(5)}+{$ref(6)}),0)", '0.0%'],
            /* 8  */ ['SLA Bongkar - On Time',                  $S('bong_ot'), null],
            /* 9  */ ['SLA Bongkar - Delay',                    $S('bong_dl'), null],
            /* 10 */ ['% On Time Bongkar',                      "IF({$ref(8)}+{$ref(9)}>0,{$ref(8)}/({$ref(8)}+{$ref(9)}),0)", '0.0%'],
            /* 11 */ ['Status Akhir: On Time Total',            $S('ak_ot'), null],
            /* 12 */ ['Status Akhir: Delay Perjalanan',         $S('ak_dp'), null],
            /* 13 */ ['Status Akhir: Delay Pembongkaran',       $S('ak_dpb'), null],
            /* 14 */ ['Status Akhir: Delay Total',              $S('ak_dt'), null],
            /* 15 */ ['Delivered On Time',                      $S('del_ot'), null],
            /* 16 */ ['Delivered Delay',                        $S('del_dl'), null],
            /* 17 */ ['In Transit',                             $S('in_transit'), null],
            /* 18 */ ['Belum Tiba',                             $S('belum_tiba'), null],
            /* 19 */ ['Belum Bongkar (sudah tiba)',             $S('belum_bong'), null],
            /* 20 */ ['In Gudang',                                $S('in_gudang'), null],
        ];

        foreach ($items as [$label, $f, $fmt]) {
            $s->setCellValue("A{$r}", $label);
            $this->formula($s, "B{$r}", $f);
            $s->getStyle("B{$r}")->getNumberFormat()->setFormatCode($fmt ?: '#,##0');
            $r++;
        }

        $s->getStyle('A' . self::KPI_START . ':A' . ($r - 1))->getFont()->setBold(true);
        $s->getStyle('A' . self::KPI_START . ':B' . ($r - 1))->getBorders()->getAllBorders()
            ->setBorderStyle('thin')->getColor()->setRGB('E5E7EB');
    }

    /** tabel bantu grafik posisi: In Gudang vs In Transit (ikut slicer lewat KPI) */
private function writeStatusPosisiHelper($s): void
{
    $s->setCellValue('Z16', 'Posisi');
    $s->setCellValue('AA16', 'Jumlah (grup)');
    $s->setCellValue('Z17', 'In Transit');
    $s->setCellValue('Z18', 'In Gudang');
    $s->setCellValue('Z19', 'Sudah Tiba');
    $this->formula($s, 'AA17', 'B' . (self::KPI_START + 17));   // In Transit
    $this->formula($s, 'AA18', 'B' . (self::KPI_START + 20));   // In Gudang
    // Sudah Tiba = Total grup - Belum Tiba (sama dengan kolom Q di blok)
    $this->formula($s, 'AA19', 'B' . (self::KPI_START + 1) . '-B' . (self::KPI_START + 18));
    $s->getStyle('Z16:AA19')->getFont()->getColor()->setRGB('9CA3AF');
}

    private function writeBlock($s, array $b): void
    {
        $s->setCellValue("A{$b['titleRow']}", strtoupper($b['title']));
        $s->getStyle("A{$b['titleRow']}")->getFont()->setBold(true)->setSize(12);

        $heads = [
            'A' => $b['label'], 'B' => 'Total (grup)',
            'C' => 'Tiba On Time Customer', 'D' => 'Tiba Delay Customer', 'E' => '% Tiba On Time Customer',
            'F' => 'Bongkar On Time', 'G' => 'Bongkar Delay', 'H' => '% Bongkar On Time',
            'I' => 'Belum Tiba', 'J' => 'Belum Bongkar', 'K' => 'In Transit',
            'L' => 'Nilai Muatan', 'M' => 'Biaya Kirim (Kirim Kontribusi)', 'N' => 'CR (%)',
            'O' => 'On Time di Gudang', 'P' => 'Delay di Gudang',
            'Q' => 'Sudah Tiba',
        ];
        foreach ($heads as $col => $text) {
            $s->setCellValue("{$col}{$b['hdrRow']}", $text);
        }
        $s->getStyle("A{$b['hdrRow']}:Q{$b['hdrRow']}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '111827']],
        ]);

        $metricCols = [
            'B' => 'total', 'C' => 'tiba_ot', 'D' => 'tiba_dl', 'F' => 'bong_ot', 'G' => 'bong_dl',
            'I' => 'belum_tiba', 'J' => 'belum_bong', 'K' => 'in_transit', 'L' => 'nilai', 'M' => 'biaya',
            'O' => 'gd_ot', 'P' => 'gd_dl',
        ];

        if ($b['key'] === 'tipe') {
            $r = $b['first'];
            $s->setShowSummaryBelow(false);   // tombol [+]/[-] muncul di baris induk (Dry / Reefer)

            foreach ($b['keys'] as $tipe) {
                $this->writeKeyRow($s, $r, $tipe, false, $metricCols, [[$b['rk'], "\$A{$r}"]]);
                $s->getStyle("A{$r}:Q{$r}")->getFont()->setBold(true);
                $s->getStyle("A{$r}:Q{$r}")->getFill()->setFillType('solid')->getStartColor()->setRGB('E5E7EB');
                if ($b['m'] > 0) {
                    $s->getRowDimension($r)->setCollapsed(true);
                }
                $r++;

                foreach ($b['childKeys'] as $area) {
                    $this->writeKeyRow($s, $r, $area, false, $metricCols, [
                        [$b['rk'], '"' . $tipe . '"'],
                        ['Rk Area', "\$A{$r}"],
                    ]);
                    $s->getStyle("A{$r}")->getAlignment()->setIndent(2);
                    $s->getRowDimension($r)->setOutlineLevel(1)->setVisible(false);   // tertutup dulu
                    $r++;
                }
            }

            // total = jumlah baris induk saja (anak tidak ikut, supaya tidak dobel)
            $tr = $b['total'];
            [$p1, $p2] = $b['parents'];
            $s->setCellValue("A{$tr}", $b['totalLabel']);
            foreach (array_keys($metricCols) as $col) {
                $this->formula($s, "{$col}{$tr}", "{$col}{$p1}+{$col}{$p2}");
            }
            $this->writePctCr($s, $tr);

            $s->getStyle("A{$tr}:Q{$tr}")->getFont()->setBold(true);
            $s->getStyle("A{$tr}:Q{$tr}")->getBorders()->getTop()->setBorderStyle('thin');
        } else {
            $tr = $this->writeCompacted($s, $b, $metricCols);
        }

        $s->getStyle("B{$b['first']}:K{$tr}")->getNumberFormat()->setFormatCode('#,##0');
        $s->getStyle("O{$b['first']}:Q{$tr}")->getNumberFormat()->setFormatCode('#,##0');
        $s->getStyle("L{$b['first']}:M{$tr}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $s->getStyle("E{$b['first']}:E{$tr}")->getNumberFormat()->setFormatCode('0.0%');
        $s->getStyle("H{$b['first']}:H{$tr}")->getNumberFormat()->setFormatCode('0.0%');
        $s->getStyle("N{$b['first']}:N{$tr}")->getNumberFormat()->setFormatCode('0.00%');

        if ($b['isPeriode']) {
            $s->getStyle("A{$b['first']}:A{$tr}")->getNumberFormat()->setFormatCode('0000"-"00');
            $s->getStyle("A{$b['first']}:A{$tr}")->getAlignment()->setHorizontal('left');
        }
    }

    /**
     * Blok gaya pivot: SUMBER (semua kategori, kolom CL.., tersembunyi) + tampilan A-Q yang hanya
     * berisi kategori lolos slicer, naik ke atas, baris TOTAL tepat di bawahnya.
     * Mengembalikan nomor baris terakhir area tampilan (= baris TOTAL maksimal).
     */
    private function writeCompacted($s, array $b, array $metricCols): int
    {
        $f  = $b['first'];
        $l  = $b['last'];
        $tr = $b['total'];

        $m    = fn(string $c) => $this->srcCol($c);
        $keyC = Coordinate::stringFromColumnIndex(self::SRC_START - 2);   // CJ
        $posC = Coordinate::stringFromColumnIndex(self::SRC_START - 1);   // CK

        // ---- SUMBER: semua kategori + total, rumus SUMIFS di kolom CL..
        $r = $f;
        foreach ($b['keys'] as $k) {
            $this->writeKeyRow($s, $r, $k, $b['isPeriode'], $metricCols, [[$b['rk'], '$' . $m('A') . $r]], $m);
            $this->formula($s, "{$keyC}{$r}", 'IF(' . $m('B') . "{$r}>0,{$r},\"\")");   // kunci: nomor baris kalau lolos
            $r++;
        }

        $s->setCellValue($m('A') . $tr, $b['totalLabel']);
        foreach (array_keys($metricCols) as $col) {
            $this->formula($s, $m($col) . $tr, 'SUM(' . $m($col) . "{$f}:" . $m($col) . "{$l})");
        }
        $this->writePctCr($s, $tr, $m);

        // ---- TAMPILAN: baris ke-k = kategori lolos ke-k; baris ke-(jumlah+1) = TOTAL; sisanya kosong
        $keys = "\${$keyC}\${$f}:\${$keyC}\${$l}";
        $cnt  = "COUNT({$keys})";

        for ($r = $f; $r <= $tr; $r++) {
            $k = $r - $f + 1;
            $this->formula($s, "{$posC}{$r}", "IF({$cnt}>={$k},SMALL({$keys},{$k})-{$f}+1,IF({$cnt}={$k}-1,-1,0))");

            $p = "\${$posC}{$r}";
            foreach (range('A', 'Q') as $col) {
                $sc = $m($col);
                $this->formula(
                    $s,
                    "{$col}{$r}",
                    "IF({$p}>0,INDEX(\${$sc}\${$f}:\${$sc}\${$l},{$p}),IF({$p}<0,\${$sc}\${$tr},\"\"))"
                );
            }
        }

        // baris TOTAL (dinamis) ditebalkan + garis atas
        $cf = new Conditional();
        $cf->setConditionType(Conditional::CONDITION_EXPRESSION)
            ->addCondition("\$A{$f}=\"{$b['totalLabel']}\"");
        $cf->getStyle()->getFont()->setBold(true);
        $cf->getStyle()->getBorders()->getTop()->setBorderStyle('thin');
        $s->getStyle("A{$f}:Q{$tr}")->setConditionalStyles([$cf]);

        return $tr;
    }

    /**
     * satu baris data: label di kolom A + rumus SUMIFS tiap metrik + kolom persen/CR.
     * $m = pemetaan kolom (null = kolom tampil A-Q; srcCol = kolom SUMBER)
     */
    private function writeKeyRow($s, int $r, $label, bool $isPeriode, array $metricCols, array $crits, ?callable $m = null): void
    {
        $m ??= fn(string $c) => $c;
        $a  = $m('A') . $r;

        if ($isPeriode) {
            $s->setCellValue($a, (int) $label);
            $s->getStyle($a)->getNumberFormat()->setFormatCode('0000"-"00');
            $s->getStyle($a)->getAlignment()->setHorizontal('left');
        } else {
            $s->setCellValueExplicit($a, (string) $label, DataType::TYPE_STRING);
        }

        foreach ($metricCols as $col => $metric) {
            $this->formula($s, $m($col) . $r, $this->sumifs($metric, $crits));
        }
        $this->writePctCr($s, $r, $m);
    }

    /** tabel bantu kecil untuk grafik Dry vs Reefer (baris induk tidak berurutan, jadi disalin ke sini) */
    private function writeTipeHelper($s, array $b): void
    {
        [$p1, $p2] = $b['parents'];

        $s->setCellValue('S16', 'Tipe');
        $s->setCellValue('T16', 'Tiba On Time Customer');
        $s->setCellValue('U16', 'Tiba Delay Customer');
        foreach ([17 => $p1, 18 => $p2] as $r => $p) {
            $this->formula($s, "S{$r}", "A{$p}");
            $this->formula($s, "T{$r}", "C{$p}");
            $this->formula($s, "U{$r}", "D{$p}");
        }
        $s->getStyle('S16:U18')->getFont()->getColor()->setRGB('9CA3AF');
    }

    /** tabel bantu grafik "CR Total": 1 angka = CR dari KPI (Biaya Kirim / Nilai Muatan semua data yang lolos slicer) */
    private function writeCrTotalHelper($s): void
    {
        $crCell = 'B' . (self::KPI_START + 4);   // KPI "CR (Biaya Kirim / Nilai Muatan)"

        $s->setCellValue('W16', 'Cakupan');
        $s->setCellValue('X16', 'CR Total (%)');
        $s->setCellValue('W17', 'Total data terpilih');
        $this->formula($s, 'X17', $crCell);
        $s->getStyle('X17')->getNumberFormat()->setFormatCode('0.00%');
        $s->getStyle('W16:X17')->getFont()->getColor()->setRGB('9CA3AF');
    }

    private function writePctCr($s, int $r, ?callable $m = null): void
    {
        $m ??= fn(string $c) => $c;
        $c  = fn(string $x) => $m($x) . $r;

        $this->formula($s, $c('E'), "IF({$c('C')}+{$c('D')}>0,{$c('C')}/({$c('C')}+{$c('D')}),0)");
        $this->formula($s, $c('H'), "IF({$c('F')}+{$c('G')}>0,{$c('F')}/({$c('F')}+{$c('G')}),0)");
        $this->formula($s, $c('N'), "IF({$c('L')}>0,{$c('M')}/{$c('L')},0)");
        $this->formula($s, $c('Q'), "{$c('B')}-{$c('I')}");   // Sudah Tiba = Total - Belum Tiba
    }

    // ============================================================
    // TABEL PADAT (Area / Dist Channel / Ekspedisi / PIC) UNTUK GRAFIK
    //  - R/S (baris blok): ranking kategori yang lolos slicer (Total > 0), urut Nilai Muatan
    //  - kolom AO dst: baris 4.. berisi kategori ke-1, ke-2, ... (kosong kalau tidak ada)
    //  Dibaca dari kolom SUMBER (bukan dari tampilan A-Q yang sudah dipadatkan)
    // ============================================================
    private function writeCompact($s, string $key): void
    {
        $cm = $this->compactMap($key);
        if (!$cm) {
            return;
        }

        $b = $cm['blk'];
        $f = $b['first'];
        $l = $b['last'];
        $n = $cm['n'];

        $sA = $this->srcCol('A');
        $sB = $this->srcCol('B');
        $sL = $this->srcCol('L');

        $s->setCellValue("R{$b['hdrRow']}", 'Rank (bantu)');
        $s->setCellValue("S{$b['hdrRow']}", 'Kunci urut (bantu)');
        for ($r = $f; $r <= $l; $r++) {
            $this->formula($s, "S{$r}", "IF({$sB}{$r}>0,{$sL}{$r}+{$sB}{$r}/1000,-1)");
            $this->formula($s, "R{$r}", "RANK(S{$r},\$S\${$f}:\$S\${$l},0)+COUNTIF(\$S\${$f}:S{$r},S{$r})-1");
        }
        $s->getStyle("R{$f}:S{$l}")->getFont()->setBold(false)->getColor()->setRGB('9CA3AF');

        $cnt = "COUNTIF(\${$sB}\${$f}:\${$sB}\${$l},\">0\")";
        // jumlah kategori yang tampil (maks n), disimpan di baris 2 kolom 'pos'
$this->formula($s, "{$cm['pos']}2", "MIN({$n},{$cnt})");
$s->getStyle("{$cm['pos']}2")->getFont()->getColor()->setRGB('9CA3AF');

        $s->setCellValue("{$cm['pos']}3", 'Pos');
        $s->setCellValue("{$cm['label']}3", $b['label']);
        foreach ($cm['cols'] as $src => $c) {
            $this->formula($s, "{$c}3", "{$src}{$b['hdrRow']}");   // salin header kolom blok
        }

        for ($k = 1; $k <= $n; $k++) {
            $r = self::HELP_HDR + $k;
            $p = $cm['pos'] . $r;
            $a = $cm['label'] . $r;

            $this->formula($s, $p, "IFERROR(MATCH({$k},\$R\${$f}:\$R\${$l},0),0)");
            $this->formula($s, $a, "IF(AND({$k}<={$cnt},{$p}>0),INDEX(\${$sA}\${$f}:\${$sA}\${$l},{$p}),\"\")");

            foreach ($cm['cols'] as $src => $c) {
                $sc = $this->srcCol($src);
                $this->formula($s, "{$c}{$r}", "IF({$a}=\"\",0,INDEX(\${$sc}\${$f}:\${$sc}\${$l},{$p}))");
            }
        }

        $last = self::HELP_HDR + $n;
        if (isset($cm['cols']['N'])) {
            $s->getStyle("{$cm['cols']['N']}" . self::HELP_FIRST . ":{$cm['cols']['N']}{$last}")
                ->getNumberFormat()->setFormatCode('0.00%');
        }
        $s->getStyle("{$cm['pos']}3:{$cm['last']}{$last}")->getFont()->getColor()->setRGB('9CA3AF');
    }

    // ============================================================
    // TABEL BANTU GRAFIK TUJUAN (kolom AC-AK) - Top 20 tujuan urut Nilai Muatan
    //  - R/S (baris blok Tujuan): ranking unik by Nilai Muatan (yang tersaring slicer = 0, jatuh ke bawah)
    //  - T2: 1 kalau slicer/filter aktif (baris tampil < jumlah baris data)
    //  - AM2: jumlah tujuan yang tampil (Total grup > 0), maksimal TUJ_TOP; 0 kalau T2 = 0
    //  Dibaca dari kolom SUMBER.
    // ============================================================
    private function writeTujuanHelper($s, array $b): void
    {
        $f = $b['first'];
        $l = $b['last'];
        $n = min(self::TUJ_TOP, $b['n']);

        $sA = $this->srcCol('A');
        $sB = $this->srcCol('B');
        $sL = $this->srcCol('L');

        // FLAG: 1 kalau slicer/filter aktif (baris tampil < jumlah baris data yang terisi)
        $tampil = $this->rng('Rk Tampil');
        $wk     = $this->rng('Rk Wakil Grup');
        $s->setCellValue('S2', 'Flag slicer aktif');
        $this->formula($s, 'T2', "IF(SUM({$tampil})<COUNT({$wk}),1,0)");

        $s->setCellValue("R{$b['hdrRow']}", 'Rank (bantu)');
        $s->setCellValue("S{$b['hdrRow']}", 'Kunci urut (bantu)');
        for ($r = $f; $r <= $l; $r++) {
            $this->formula($s, "S{$r}", "{$sL}{$r}+{$sB}{$r}/1000");
            $this->formula($s, "R{$r}", "RANK(S{$r},\$S\${$f}:\$S\${$l},0)+COUNTIF(\$S\${$f}:S{$r},S{$r})-1");
        }

        // jumlah tujuan yang ditampilkan: 0 kalau slicer belum aktif, selain itu maksimal TUJ_TOP
        $s->setCellValue('AM1', 'Jumlah tujuan tampil (maks ' . $n . ')');
        $this->formula($s, 'AM2', "IF(\$T\$2=1,MIN({$n},COUNTIF(\${$sB}\${$f}:\${$sB}\${$l},\">0\")),0)");

        $cols = [
            'AE' => ['Tiba On Time Customer', 'C'], 'AF' => ['Tiba Delay Customer', 'D'],
            'AG' => ['Bongkar On Time', 'F'], 'AH' => ['Bongkar Delay', 'G'],
            'AI' => ['CR (%)', 'N'],
            'AJ' => ['On Time di Gudang', 'O'],
            'AK' => ['Delay di Gudang', 'P'],
        ];

        $s->setCellValue('AC3', 'Pos');
        $s->setCellValue('AD3', 'Tujuan');
        foreach ($cols as $c => [$name]) {
            $s->setCellValue("{$c}3", $name);
        }

        for ($k = 1; $k <= $n; $k++) {
            $r = self::HELP_HDR + $k;

            $this->formula($s, "AC{$r}", "IF(\$T\$2=1,IFERROR(MATCH({$k},\$R\${$f}:\$R\${$l},0),0),0)");
            $this->formula($s, "AD{$r}", "IF(AND(\$T\$2=1,{$k}<=\$AM\$2,AC{$r}>0),INDEX(\${$sA}\${$f}:\${$sA}\${$l},AC{$r}),\"\")");

            foreach ($cols as $c => [, $src]) {
                $sc = $this->srcCol($src);
                $this->formula($s, "{$c}{$r}", "IF(\$AD{$r}=\"\",0,INDEX(\${$sc}\${$f}:\${$sc}\${$l},\$AC{$r}))");
            }
        }

        $last = self::HELP_HDR + $n;
        $s->getStyle('AI' . self::HELP_FIRST . ":AI{$last}")->getNumberFormat()->setFormatCode('0.00%');
        $s->getStyle("AC1:AM{$last}")->getFont()->getColor()->setRGB('9CA3AF');
        $s->getStyle('S2:T2')->getFont()->getColor()->setRGB('9CA3AF');
        $s->getStyle("R{$f}:S{$l}")->getFont()->setBold(false)->getColor()->setRGB('9CA3AF');
    }
}