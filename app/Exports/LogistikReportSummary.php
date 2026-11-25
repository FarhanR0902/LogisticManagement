<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Sheet 2 "Ringkasan": semua angka = rumus SUMIFS ke sheet Data (kolom "Rk ..."), ikut filter/slicer.
 * Juga menyimpan tabel bantu grafik "Top 10 tujuan" untuk Dashboard (kolom R-AA).
 * Kolom Q "Sudah Tiba" (= Total - Belum Tiba) dipakai chart "Sudah Tiba vs Belum Tiba per PIC".
 */
class LogistikReportSummary implements WithTitle, WithEvents
{
    private const KPI_START   = 5;     // header KPI di baris 4, isi baris 5-24
    private const BLOCK_START = 27;
    private const MAX_ROWS    = 10000; // batas pengaman jumlah baris per tabel

    public const HELP_HDR   = 3;
    public const HELP_FIRST = 4;
    // public const HELP_N     = 10;
    public const FLAG_REF   = "'Ringkasan'!\$T\$2";   // 1 = area sedang dipilih/difilter

    private const RK = [
        'total' => 'Rk Wakil Grup', 'tiba_ot' => 'Rk Tiba OT', 'tiba_dl' => 'Rk Tiba Delay',
        'gd_ot' => 'Rk Gudang OT', 'gd_dl' => 'Rk Gudang Delay',
        'bong_ot' => 'Rk Bongkar OT', 'bong_dl' => 'Rk Bongkar Delay',
        'ak_ot' => 'Rk Akhir OT', 'ak_dp' => 'Rk Akhir Delay Perjalanan',
        'ak_dpb' => 'Rk Akhir Delay Pembongkaran', 'ak_dt' => 'Rk Akhir Delay Total',
        'del_ot' => 'Rk Delivered OT', 'del_dl' => 'Rk Delivered Delay',
        'in_transit' => 'Rk In Transit', 'belum_tiba' => 'Rk Belum Tiba', 'belum_bong' => 'Rk Belum Bongkar',
        'nilai' => 'Rk Nilai Grup', 'biaya' => 'Rk Biaya Grup',
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
                // ekspedisi, planner & tujuan urut nilai terbesar (grafik Dashboard ambil baris teratas); sisanya abjad
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
                $s->setCellValue('A3', 'Angka ikut slicer (sheet Dashboard) & filter sheet Data. Per grup (no shipment + tujuan).');
                $s->getStyle('A2:A3')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');

                $this->writeKpi($s);

                foreach ($lay['blocks'] as $b) {
                    $this->writeBlock($s, $b);
                }

                // tabel bantu grafik Top 10 tujuan (kolom R-AA) + flag area di T2
                if (isset($lay['blocks']['tujuan'])) {
                    $this->writeTujuanHelper($s, $lay['blocks']['tujuan'], $lay['blocks']['area'] ?? null);
                }

                // tabel bantu grafik Dry vs Reefer (S16:U18)
                if (isset($lay['blocks']['tipe'])) {
                    $this->writeTipeHelper($s, $lay['blocks']['tipe']);
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
                 foreach (['AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK'] as $c) {
                    $s->getColumnDimension($c)->setWidth(16);
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

    private function writeBlock($s, array $b): void
    {
        $s->setCellValue("A{$b['titleRow']}", strtoupper($b['title']));
        $s->getStyle("A{$b['titleRow']}")->getFont()->setBold(true)->setSize(12);

        $heads = [
            'A' => $b['label'], 'B' => 'Total (grup)',
            'C' => 'Tiba On Time', 'D' => 'Tiba Delay', 'E' => '% Tiba On Time',
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

        $r = $b['first'];

        if ($b['key'] === 'tipe') {
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
        } else {
            foreach ($b['keys'] as $k) {
                $this->writeKeyRow($s, $r, $k, $b['isPeriode'], $metricCols, [[$b['rk'], "\$A{$r}"]]);
                $r++;
            }

            $tr = $b['total'];
            $s->setCellValue("A{$tr}", $b['totalLabel']);
            foreach (array_keys($metricCols) as $col) {
                $this->formula($s, "{$col}{$tr}", "SUM({$col}{$b['first']}:{$col}{$b['last']})");
            }
        }

        $this->writePctCr($s, $tr);

        $s->getStyle("A{$tr}:Q{$tr}")->getFont()->setBold(true);
        $s->getStyle("A{$tr}:Q{$tr}")->getBorders()->getTop()->setBorderStyle('thin');

        $s->getStyle("B{$b['first']}:K{$tr}")->getNumberFormat()->setFormatCode('#,##0');
        $s->getStyle("O{$b['first']}:Q{$tr}")->getNumberFormat()->setFormatCode('#,##0');
        $s->getStyle("L{$b['first']}:M{$tr}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $s->getStyle("E{$b['first']}:E{$tr}")->getNumberFormat()->setFormatCode('0.0%');
        $s->getStyle("H{$b['first']}:H{$tr}")->getNumberFormat()->setFormatCode('0.0%');
        $s->getStyle("N{$b['first']}:N{$tr}")->getNumberFormat()->setFormatCode('0.00%');
    }

    /** satu baris data: label di kolom A + rumus SUMIFS tiap metrik + kolom persen/CR */
    private function writeKeyRow($s, int $r, $label, bool $isPeriode, array $metricCols, array $crits): void
    {
        if ($isPeriode) {
            $s->setCellValue("A{$r}", (int) $label);
            $s->getStyle("A{$r}")->getNumberFormat()->setFormatCode('0000"-"00');
            $s->getStyle("A{$r}")->getAlignment()->setHorizontal('left');
        } else {
            $s->setCellValueExplicit("A{$r}", (string) $label, DataType::TYPE_STRING);
        }

        foreach ($metricCols as $col => $metric) {
            $this->formula($s, "{$col}{$r}", $this->sumifs($metric, $crits));
        }
        $this->writePctCr($s, $r);
    }

    /** tabel bantu kecil untuk grafik Dry vs Reefer (baris induk tidak berurutan, jadi disalin ke sini) */
    private function writeTipeHelper($s, array $b): void
    {
        [$p1, $p2] = $b['parents'];

        $s->setCellValue('S16', 'Tipe');
        $s->setCellValue('T16', 'Tiba On Time');
        $s->setCellValue('U16', 'Tiba Delay');
        foreach ([17 => $p1, 18 => $p2] as $r => $p) {
            $this->formula($s, "S{$r}", "A{$p}");
            $this->formula($s, "T{$r}", "C{$p}");
            $this->formula($s, "U{$r}", "D{$p}");
        }
        $s->getStyle('S16:U18')->getFont()->getColor()->setRGB('9CA3AF');
    }

    private function writePctCr($s, int $r): void
    {
        $this->formula($s, "E{$r}", "IF(C{$r}+D{$r}>0,C{$r}/(C{$r}+D{$r}),0)");
        $this->formula($s, "H{$r}", "IF(F{$r}+G{$r}>0,F{$r}/(F{$r}+G{$r}),0)");
        $this->formula($s, "N{$r}", "IF(L{$r}>0,M{$r}/L{$r},0)");
        $this->formula($s, "Q{$r}", "B{$r}-I{$r}");   // Sudah Tiba = Total - Belum Tiba
    }

    // ============================================================
    // TABEL BANTU GRAFIK TUJUAN (kolom R-AA)
    //  - R (di baris blok Tujuan): ranking by Nilai Muatan, unik
    //  - T2: 1 kalau Area sedang difilter (jumlah area tampil < total area), selain itu 0
    //  - S3:AA13: Top 10 tujuan dinamis; kalau T2 = 0 semua jadi kosong / #N/A, grafik kosong
    // ============================================================
      // ============================================================
    // TABEL BANTU GRAFIK TUJUAN (kolom AC-AK) - SEMUA tujuan, urut Nilai Muatan
    //  - R (di baris blok Tujuan): ranking by Nilai Muatan, unik
    //  - T2: 1 kalau Area sedang difilter, selain itu 0
    // ============================================================
    private function writeTujuanHelper($s, array $b, ?array $area): void
    {
        $f = $b['first'];
        $l = $b['last'];
        $n = $b['n'];

        $s->setCellValue("R{$b['hdrRow']}", 'Rank (bantu)');
        for ($r = $f; $r <= $l; $r++) {
            $this->formula($s, "R{$r}", "RANK(L{$r},\$L\${$f}:\$L\${$l},0)+COUNTIF(\$L\${$f}:L{$r},L{$r})-1");
        }

        $flag = $area
            ? 'IF(COUNTIF(B' . $area['first'] . ':B' . $area['last'] . ',">0")<' . $area['n'] . ',1,0)'
            : '1';

        $s->setCellValue('S1', 'BANTU GRAFIK DASHBOARD (jangan diubah)');
        $s->setCellValue('S2', 'Area dipilih? (1=ya)');
        $this->formula($s, 'T2', $flag);

        $cols = [
            'AE' => ['Tiba On Time', 'C'], 'AF' => ['Tiba Delay', 'D'],
            'AG' => ['Bongkar On Time', 'F'], 'AH' => ['Bongkar Delay', 'G'],
            'AI' => ['CR (%)', 'N'],
            'AJ' => ['On Time di Gudang', 'O'],
            'AK' => ['Delay di Gudang', 'P'],
        ];

        $s->setCellValue('AC3', 'Pos');
        $s->setCellValue('AD3', 'Tujuan');
        foreach ($cols as $c => [$name]) {
            $this->formula($s, "{$c}3", 'IF($T$2=1,"' . $name . '","")');
        }

        for ($k = 1; $k <= $n; $k++) {
            $r = self::HELP_HDR + $k;

            $this->formula($s, "AC{$r}", "IFERROR(MATCH({$k},\$R\${$f}:\$R\${$l},0),0)");
            $this->formula(
                $s,
                "AD{$r}",
                "IF(AND(\$T\$2=1,AC{$r}>0),IF(INDEX(\$B\${$f}:\$B\${$l},AC{$r})>0,INDEX(\$A\${$f}:\$A\${$l},AC{$r}),\"\"),\"\")"
            );

            foreach ($cols as $c => [, $src]) {
                $this->formula($s, "{$c}{$r}", "IF(\$AD{$r}=\"\",NA(),INDEX(\${$src}\${$f}:\${$src}\${$l},\$AC{$r}))");
            }
        }

        $last = self::HELP_HDR + $n;
        $s->getStyle('AI' . self::HELP_FIRST . ":AI{$last}")->getNumberFormat()->setFormatCode('0.00%');
        $s->getStyle('S1:T2')->getFont()->getColor()->setRGB('9CA3AF');
        $s->getStyle("AC3:AK{$last}")->getFont()->getColor()->setRGB('9CA3AF');
        $s->getStyle("R{$f}:R{$l}")->getFont()->setBold(false)->getColor()->setRGB('9CA3AF');
    }
}