<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\{Chart, DataSeries, DataSeriesValues, Legend, PlotArea, Title};

class MonitoringReportSummary implements FromArray, WithTitle, WithEvents, WithCharts
{
    private ?array $lay = null;

    public function __construct(private MonitoringReportData $data, private string $filterInfo = '') {}

    public function title(): string { return 'Ringkasan'; }
    public function array(): array { return [[]]; }

    private function x(): MonitoringSlicerExport
    {
        return $this->data->x();
    }

    private function rng(string $head): string
    {
        $c = $this->x()->letter($head);
        $n = $this->x()->totalRows() + 1;
        return "Data!\${$c}\$2:\${$c}\${$n}";
    }

    private function f(string $flag, ?string $keyHead = null, ?string $keyCell = null): string
    {
        $s = "SUMIFS({$this->rng($flag)},{$this->rng('Rk Tampil')},1";
        if ($keyHead) $s .= ",{$this->rng($keyHead)},{$keyCell}";
        return $s . ')';
    }

    /** huruf kolom metrik di tabel dimensi (A = kunci, B.. = metrik sesuai urutan METRICS) */
    private function col(string $label): string
    {
        return Coordinate::stringFromColumnIndex(array_search($label, array_keys(MonitoringSlicerExport::METRICS), true) + 2);
    }

    /** posisi baris tiap blok (dihitung deterministik, dipakai registerEvents dan charts) */
    private function layout(): array
    {
        if ($this->lay !== null) return $this->lay;

        $r = 9;
        $blocks = [];
        foreach ($this->x()->dimensions() as $head => $judul) {
            $keys = $this->x()->distinctKeys($head);
            if (!$keys) continue;
            $blocks[$head] = ['title' => $r, 'hdr' => $r + 1, 'first' => $r + 2, 'keys' => $keys, 'judul' => $judul];
            $r += 2 + count($keys) + 2;
        }

        return $this->lay = ['blocks' => $blocks, 'slaTitle' => $r, 'slaHdr' => $r + 1, 'slaFirst' => $r + 2];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $ws = $event->sheet->getDelegate();
            $ws->getTabColor()->setRGB('F59E0B');
            $put   = fn($cell, $formula) => $ws->setCellValueExplicit($cell, '=' . $formula, DataType::TYPE_FORMULA);
            $col2L = fn(int $i) => Coordinate::stringFromColumnIndex($i);
            $lay   = $this->layout();

            $ws->setCellValue('A1', 'DASHBOARD MONITORING');
            $ws->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $ws->setCellValue('A2', $this->filterInfo ?: 'Tanpa filter. Gunakan slicer di kanan untuk menyaring.');
            $ws->setCellValue('A3', 'Angka dihitung per shipment + tujuan dan ikut berubah saat slicer/filter tabel dipakai.');

            // ---------- KPI ----------
            $col = 1;
            foreach (MonitoringSlicerExport::METRICS as $label => $flag) {
                $L = $col2L($col);
                $ws->setCellValue("{$L}5", $label);
                $put("{$L}6", $this->f($flag));
                $col++;
            }
            $lastL = $col2L($col - 1);
            $ws->getStyle("A5:{$lastL}5")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0284C7']],
            ]);
            $ws->getStyle("A6:{$lastL}6")->getFont()->setBold(true)->setSize(14);

            // ---------- TABEL PER DIMENSI ----------
            $head = array_keys(MonitoringSlicerExport::METRICS);
            foreach ($lay['blocks'] as $dimHead => $b) {
                $r = $b['title'];
                $ws->setCellValue("A{$r}", $b['judul']);
                $ws->getStyle("A{$r}")->getFont()->setBold(true)->setSize(13);
                $r++;
                $ws->fromArray(array_merge([''], $head), null, "A{$r}");
                $ws->getStyle("A{$r}:" . $col2L(count($head) + 1) . "{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F2937']],
                ]);
                $r++;

                foreach ($b['keys'] as $k) {
                    $ws->setCellValueExplicit("A{$r}", (string) $k, DataType::TYPE_STRING);
                    $c = 2;
                    foreach (MonitoringSlicerExport::METRICS as $flag) {
                        $put($col2L($c++) . $r, $this->f($flag, $dimHead, "\$A{$r}"));
                    }
                    $r++;
                }
            }

            // ---------- LEAD TIME & SLA PER PIC ----------
            $pic = $this->x()->distinctKeys('PIC');
            if ($pic) {
                $r = $lay['slaTitle'];
                $ws->setCellValue("A{$r}", 'LEAD TIME & SLA PER PIC');
                $ws->getStyle("A{$r}")->getFont()->setBold(true)->setSize(13);
                $r++;
                $ws->fromArray([
                    'PIC', 'Lama Perjalanan Tercatat', 'Rata² Lama Perjalanan (hari)',
                    'SLA Tiba Tercatat', 'SLA Tiba Delay', '% SLA Tiba OK',
                    'SLA Bongkar Tercatat', 'SLA Bongkar Delay', '% SLA Bongkar OK',
                ], null, "A{$r}");
                $ws->getStyle("A{$r}:I{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '7C3AED']],
                ]);
                $ws->getStyle("A{$r}:I{$r}")->getAlignment()->setWrapText(true);
                $r++;
                $first = $r;

                foreach (array_merge($pic, [null]) as $k) {
                    $sum = fn(string $flag) => $k === null ? $this->f($flag) : $this->f($flag, 'PIC', "\$A{$r}");

                    if ($k === null) {
                        $ws->setCellValue("A{$r}", 'TOTAL');
                        $ws->getStyle("A{$r}:I{$r}")->getFont()->setBold(true);
                    } else {
                        $ws->setCellValueExplicit("A{$r}", (string) $k, DataType::TYPE_STRING);
                    }

                    $put("B{$r}", $sum('Rk Lama Ada'));
                    $put("C{$r}", "IF(B{$r}>0," . $sum('Rk Lama Hari') . "/B{$r},0)");
                    $put("D{$r}", $sum('Rk SlaTiba Ada'));
                    $put("E{$r}", $sum('Rk SlaTiba Delay'));
                    $put("F{$r}", "IF(D{$r}>0,(D{$r}-E{$r})/D{$r}*100,0)");
                    $put("G{$r}", $sum('Rk SlaBongkar Ada'));
                    $put("H{$r}", $sum('Rk SlaBongkar Delay'));
                    $put("I{$r}", "IF(G{$r}>0,(G{$r}-H{$r})/G{$r}*100,0)");
                    $r++;
                }
                $last = $r - 1;
                $ws->getStyle("C{$first}:C{$last}")->getNumberFormat()->setFormatCode('0.0');
                $ws->getStyle("F{$first}:F{$last}")->getNumberFormat()->setFormatCode('0.00"%"');
                $ws->getStyle("I{$first}:I{$last}")->getNumberFormat()->setFormatCode('0.00"%"');
            }

            $ws->getColumnDimension('A')->setWidth(34);
            foreach (range('B', 'K') as $c) $ws->getColumnDimension($c)->setWidth(15);
        }];
    }

    private function bar(string $id, string $title, string $tl, string $br, string $cat, int $n, array $series): Chart
    {
        $labels = [];
        $values = [];
        foreach ($series as [$lbl, $val]) {
            $labels[] = new DataSeriesValues('String', "'Ringkasan'!{$lbl}", null, 1);
            $values[] = new DataSeriesValues('Number', "'Ringkasan'!{$val}", null, $n);
        }
        $cats = [new DataSeriesValues('String', "'Ringkasan'!{$cat}", null, $n)];

        $ds = new DataSeries(
            DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED,
            range(0, count($series) - 1), $labels, $cats, $values
        );
        $ds->setPlotDirection(DataSeries::DIRECTION_COL);

        $chart = new Chart($id, new Title($title), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$ds]), true);
        $chart->setTopLeftPosition($tl);
        $chart->setBottomRightPosition($br);
        return $chart;
    }

    public function charts()
    {
        $lay = $this->layout();
        $b   = $lay['blocks']['PIC'] ?? null;
        if (!$b) return [];

        $n     = count($b['keys']);
        $hdr   = $b['hdr'];
        $first = $b['first'];
        $last  = $first + $n - 1;
        $cat   = "\$A\${$first}:\$A\${$last}";
        $rngOf = fn(string $label) => ($c = $this->col($label)) ? "\${$c}\${$first}:\${$c}\${$last}" : '';
        $hdOf  = fn(string $label) => '$' . $this->col($label) . '$' . $hdr;

        $sf = $lay['slaFirst'];
        $sl = $sf + $n - 1;
        $sh = $lay['slaHdr'];

        return [
            $this->bar('c_tiba', 'Sudah Tiba vs Belum Tiba per PIC', 'Q9', 'U28', $cat, $n, [
                [$hdOf('Sudah Tiba'), $rngOf('Sudah Tiba')],
                [$hdOf('Belum Tiba'), $rngOf('Belum Tiba')],
            ]),
            $this->bar('c_bongkar', 'Sudah Bongkar vs Pending Bongkar per PIC', 'Q30', 'U49', $cat, $n, [
                [$hdOf('Sudah Bongkar'), $rngOf('Sudah Bongkar')],
                [$hdOf('Pending Bongkar'), $rngOf('Pending Bongkar')],
            ]),
            $this->bar('c_sla', 'SLA Tiba: Tercatat vs Delay per PIC', 'Q51', 'U70', "\$A\${$sf}:\$A\${$sl}", $n, [
                ["\$D\${$sh}", "\$D\${$sf}:\$D\${$sl}"],
                ["\$E\${$sh}", "\$E\${$sf}:\$E\${$sl}"],
            ]),
        ];
    }
}