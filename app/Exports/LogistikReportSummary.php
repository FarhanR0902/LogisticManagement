<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;

/**
 * Sheet "Ringkasan" HIDUP: semua angka = rumus SUMIFS ke sheet Data (kolom "Rk ...").
 * Ikut berubah saat filter tabel / slicer dipakai. Daftar baris (label) tetap dari database saat export.
 */
class LogistikReportSummary implements WithTitle, WithEvents, WithCharts
{
    private const KPI_START = 5;     // header KPI di baris 4, isi mulai baris 5 (20 baris -> s/d 24)
    private const CHART_END = 67;

    /** metrik => heading kolom "Rk ..." di sheet Data */
    private const RK = [
        'total' => 'Rk Wakil Grup', 'tiba_ot' => 'Rk Tiba OT', 'tiba_dl' => 'Rk Tiba Delay',
        'bong_ot' => 'Rk Bongkar OT', 'bong_dl' => 'Rk Bongkar Delay',
        'ak_ot' => 'Rk Akhir OT', 'ak_dp' => 'Rk Akhir Delay Perjalanan',
        'ak_dpb' => 'Rk Akhir Delay Pembongkaran', 'ak_dt' => 'Rk Akhir Delay Total',
        'del_ot' => 'Rk Delivered OT', 'del_dl' => 'Rk Delivered Delay',
        'in_transit' => 'Rk In Transit', 'belum_tiba' => 'Rk Belum Tiba', 'belum_bong' => 'Rk Belum Bongkar',
        'nilai' => 'Rk Nilai Grup', 'biaya' => 'Rk Biaya Grup',
    ];

    private ?array $lay = null;
    private ?int $lastRow = null;

    public function __construct(private LogistikReportData $data, private string $filterInfo = '')
    {
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    /**
     * Slot slicer (rapi): 2 kolom x 3 baris, di bawah tabel KPI, di kiri chart (kolom A-C, baris 26-67).
     * Format: [kolomAwal, offsetKolomAwal, kolomAkhir, offsetKolomAkhir, barisAwal, barisAkhir] (0-based, EMU)
     */
    public static function slicerSlots(): array
    {
        $cols  = [[0, 38100, 0, 2350000], [1, 38100, 2, 1050000]];
        $slots = [];
        for ($band = 0; $band < 3; $band++) {
            $r1 = 25 + $band * 14;
            foreach ($cols as [$c1, $o1, $c2, $o2]) {
                $slots[] = [$c1, $o1, $c2, $o2, $r1, $r1 + 13];
            }
        }
        return $slots;
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
        $l = $this->data->letter($head);
        return "Data!\${$l}\$2:\${$l}\${$this->lastRow()}";
    }

    /** SUMIFS(kolom Rk metrik, Rk Tampil, 1 [, kolom kunci, kriteria]) */
    private function sumifs(string $metric, ?array $crit = null): string
    {
        $f = 'SUMIFS(' . $this->rng(self::RK[$metric]) . ',' . $this->rng('Rk Tampil') . ',1';
        if ($crit) {
            $f .= ',' . $this->rng($crit[0]) . ',' . $crit[1];
        }
        return $f . ')';
    }

    private function formula($s, string $cell, string $f): void
    {
        $s->setCellValueExplicit($cell, '=' . ltrim($f, '='), DataType::TYPE_FORMULA);
    }

    // ============================================================
    // DAFTAR BARIS (label) DARI DATABASE
    // ============================================================
    private function layout(): array
    {
        if ($this->lay !== null) {
            return $this->lay;
        }

        $row    = self::CHART_END + 3;
        $blocks = [];

        // [judul, kunci, kolom DB, label header, heading kolom kunci di sheet Data]
        $defs = [
            ['Per Area',                  'area',           'area',           'Area',           'Rk Area'],
            ['Per PIC Monitoring',        'pic_monitoring', 'pic_monitoring', 'PIC Monitoring', 'Rk PIC Monitoring'],
            ['Per Dist Channel',          'dist_channel',   'dist_channel',   'Dist Channel',   'Rk Dist Channel'],
            ['Per Ekspedisi',             'ekpedisi',       'ekpedisi',       'Ekspedisi',      'Rk Ekspedisi'],
            ['Per Tujuan (Top 10)',       'tujuan',         'tujuan',         'Tujuan',         'Rk Tujuan'],
            ['Per Planner (Top 10)',      'planner',        'planner',        'Planner',        'Rk Planner'],
            ['Per Periode Keluar Gudang', 'periode',        null,             'Periode Keluar', 'Periode Keluar'],
        ];

        foreach ($defs as [$title, $key, $col, $label, $rk]) {
            $q = $this->data->groupRows();

            if ($col !== null) {
                if (!$this->data->hasColumn($col)) {
                    continue;
                }
                $keyExpr = "COALESCE(NULLIF(TRIM(`{$col}`),''),'(kosong)')";
            } else {
                $keyExpr = $this->data->keluarExpr();
                if ($keyExpr === null) {
                    continue;
                }
                $q->whereRaw("{$keyExpr} > '1900-01-01'");
                $keyExpr = "DATE_FORMAT({$keyExpr}, '%Y%m')";
            }

            $isTop = in_array($key, ['tujuan', 'planner'], true);

            $q->selectRaw("{$keyExpr} AS k, SUM(g.g_nilai) AS nilai")->groupBy('k');
            $isTop ? $q->orderByDesc('nilai') : $q->orderBy('k');

            $keys = $q->limit($isTop ? 10 : 300)->get()->map(fn($x) => $x->k)->all();
            if (empty($keys)) {
                continue;
            }

            $n = count($keys);
            $blocks[$key] = [
                'title'     => $title,
                'label'     => $label,
                'rk'        => $rk,
                'top'       => $isTop,
                'isPeriode' => $col === null,
                'keys'      => $keys,
                'titleRow'  => $row,
                'hdrRow'    => $row + 1,
                'first'     => $row + 2,
                'last'      => $row + 1 + $n,
                'total'     => $row + 2 + $n,
                'n'         => $n,
            ];

            $row += $n + 5;
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

                $s->setCellValue('A1', 'LAPORAN LOGISTIK');
                $s->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $s->setCellValue('A2', 'Dibuat ' . date('d-m-Y H:i') . ($this->filterInfo ? ' | ' . $this->filterInfo : ''));
                $s->setCellValue('A3', 'Angka ikut slicer (kiri bawah) & filter sheet Data. Per grup (no shipment + tujuan).');
                $s->getStyle('A2:A3')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');

                $this->writeKpi($s);

                foreach ($lay['blocks'] as $b) {
                    $this->writeBlock($s, $b);
                }

                $s->getColumnDimension('A')->setWidth(36);
                foreach (range('B', 'N') as $c) {
                    $s->getColumnDimension($c)->setWidth(16);
                }
                $s->getColumnDimension('L')->setWidth(22);
                $s->getColumnDimension('M')->setWidth(30);
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
        ];
        foreach ($heads as $col => $text) {
            $s->setCellValue("{$col}{$b['hdrRow']}", $text);
        }
        $s->getStyle("A{$b['hdrRow']}:N{$b['hdrRow']}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '111827']],
        ]);

        $metricCols = [
            'B' => 'total', 'C' => 'tiba_ot', 'D' => 'tiba_dl', 'F' => 'bong_ot', 'G' => 'bong_dl',
            'I' => 'belum_tiba', 'J' => 'belum_bong', 'K' => 'in_transit', 'L' => 'nilai', 'M' => 'biaya',
        ];

        $r = $b['first'];
        foreach ($b['keys'] as $k) {
            if ($b['isPeriode']) {
                $s->setCellValue("A{$r}", (int) $k);
                $s->getStyle("A{$r}")->getNumberFormat()->setFormatCode('0000"-"00');
                $s->getStyle("A{$r}")->getAlignment()->setHorizontal('left');
            } else {
                $s->setCellValueExplicit("A{$r}", (string) $k, DataType::TYPE_STRING);
            }

            foreach ($metricCols as $col => $metric) {
                $this->formula($s, "{$col}{$r}", $this->sumifs($metric, [$b['rk'], "\$A{$r}"]));
            }
            $this->writePctCr($s, $r);
            $r++;
        }

        // baris total = jumlah baris di atasnya
        $tr = $b['total'];
        $s->setCellValue("A{$tr}", $b['top'] ? 'TOTAL TOP 10' : 'TOTAL');
        foreach (array_keys($metricCols) as $col) {
            $this->formula($s, "{$col}{$tr}", "SUM({$col}{$b['first']}:{$col}{$b['last']})");
        }
        $this->writePctCr($s, $tr);

        $s->getStyle("A{$tr}:N{$tr}")->getFont()->setBold(true);
        $s->getStyle("A{$tr}:N{$tr}")->getBorders()->getTop()->setBorderStyle('thin');

        $s->getStyle("B{$b['first']}:K{$tr}")->getNumberFormat()->setFormatCode('#,##0');
        $s->getStyle("L{$b['first']}:M{$tr}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $s->getStyle("E{$b['first']}:E{$tr}")->getNumberFormat()->setFormatCode('0.0%');
        $s->getStyle("H{$b['first']}:H{$tr}")->getNumberFormat()->setFormatCode('0.0%');
        $s->getStyle("N{$b['first']}:N{$tr}")->getNumberFormat()->setFormatCode('0.00%');
    }

    /** kolom persen & CR dihitung dari sel di barisnya sendiri */
    private function writePctCr($s, int $r): void
    {
        $this->formula($s, "E{$r}", "IF(C{$r}+D{$r}>0,C{$r}/(C{$r}+D{$r}),0)");
        $this->formula($s, "H{$r}", "IF(F{$r}+G{$r}>0,F{$r}/(F{$r}+G{$r}),0)");
        $this->formula($s, "N{$r}", "IF(L{$r}>0,M{$r}/L{$r},0)");
    }

    // ============================================================
    // GRAFIK (membaca sel Ringkasan, jadi ikut hidup)
    // ============================================================
    public function charts()
    {
        $blocks = $this->layout()['blocks'];
        $charts = [];

        if (isset($blocks['area'])) {
            $charts[] = $this->chart($blocks['area'], 'chartArea', 'SLA Tiba per Area', 'D3', 'K23');
        }
        if (isset($blocks['periode'])) {
            // 20 periode TERAKHIR (bukan 20 tertua)
            $charts[] = $this->chart($blocks['periode'], 'chartPeriode', 'SLA Tiba per Periode Keluar', 'L3', 'S23', ['C', 'D'], 20, true);
        }
        if (isset($blocks['tujuan'])) {
            $t   = $blocks['tujuan'];
            $top = 'Top 10 Tujuan';

            $charts[] = $this->chart($t, 'chartTibaTujuan',    "SLA Tiba per Tujuan - {$top}",         'D25', 'K45', ['C', 'D'], 10);
            $charts[] = $this->chart($t, 'chartBongkarTujuan', "SLA Bongkar per Tujuan - {$top}",      'L25', 'S45', ['F', 'G'], 10);
            $charts[] = $this->chart($t, 'chartNilaiTujuan',   "Nilai Muatan vs Biaya Kirim - {$top}", 'D47', 'K67', ['L', 'M'], 10);
            $charts[] = $this->chart($t, 'chartCrTujuan',      "CR per Tujuan - {$top}",               'L47', 'S67', ['N'],      10);
        }

        return $charts;
    }

    private function chart(
        array $b, string $name, string $title, string $tl, string $br,
        array $cols = ['C', 'D'], int $max = 20, bool $tail = false
    ): Chart {
        $n     = min($b['n'], $max);
        $first = $tail ? $b['last'] - $n + 1 : $b['first'];
        $last  = $first + $n - 1;
        $hdr   = $b['hdrRow'];

        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Ringkasan'!\$A\${$first}:\$A\${$last}", null, $n)];

        $tags = $values = [];
        foreach ($cols as $c) {
            $tags[]   = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Ringkasan'!\${$c}\${$hdr}", null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Ringkasan'!\${$c}\${$first}:\${$c}\${$last}", null, $n);
        }

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($values) - 1),
            $tags,
            $labels,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $chart = new Chart(
            $name,
            new Title($title),
            new Legend(Legend::POSITION_BOTTOM, null, false),
            new PlotArea(null, [$series])
        );
        $chart->setTopLeftPosition($tl);
        $chart->setBottomRightPosition($br);

        return $chart;
    }
}