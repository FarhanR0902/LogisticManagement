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

class PlannerReportSummary implements FromArray, WithTitle, WithEvents, WithCharts
{
    // judul => kolom Rk
    private const METRICS = [
        'Total Grup'         => 'Rk Wakil Grup',
        'Sudah Armada'       => 'Rk Armada Sudah',
        'Belum Armada'       => 'Rk Armada Belum',
        'Armada Delay'       => 'Rk Armada Delay',
        'Gudang Selesai'     => 'Rk Gudang Selesai',
        'Di Gudang'          => 'Rk Di Gudang',
        'Belum Input Gudang' => 'Rk Belum Input Gudang',
        'Gudang Delay'       => 'Rk Gudang Delay',
        'Optimal'            => 'Rk Optimal',
        'Tidak Optimal'      => 'Rk Tidak Optimal',
        'In Transit'         => 'Rk In Transit',
    ];

    private const DIMS = [   // judul, kolom Rk, kolom DB
        ['PER PLANNER',          'Rk Planner',      'planner'],
        ['PER AREA',             'Rk Area',         'area'],
        ['PER DIST CHANNEL',     'Rk Dist Channel', 'dist_channel'],
        ['PER PERIODE SHIPMENT', 'Rk Periode',      'periode_shipment'],
    ];

    public function __construct(private PlannerReportData $data, private string $filterInfo = '') {}

    public function title(): string { return 'Ringkasan'; }
    public function array(): array { return [[]]; }

    private function rng(string $head): string
    {
        $c = $this->data->letter($head);
        $n = $this->data->totalRows() + 1;
        return "Data!\${$c}\$2:\${$c}\${$n}";
    }

    private function f(string $flag, ?string $keyHead = null, ?string $keyCell = null): string
    {
        $s = "SUMIFS({$this->rng($flag)},{$this->rng('Rk Tampil')},1";
        if ($keyHead) $s .= ",{$this->rng($keyHead)},{$keyCell}";
        return $s . ')';
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $ws = $event->sheet->getDelegate();
            $ws->getTabColor()->setRGB('F59E0B');
            $put = fn($cell, $formula) => $ws->setCellValueExplicit($cell, '=' . $formula, DataType::TYPE_FORMULA);
            $col2L = fn(int $i) => Coordinate::stringFromColumnIndex($i);

            $ws->setCellValue('A1', 'DASHBOARD PLANNER');
            $ws->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $ws->setCellValue('A2', $this->filterInfo ?: 'Tanpa filter. Gunakan slicer di kanan untuk menyaring.');
            $ws->setCellValue('A3', 'Angka dihitung per shipment + tujuan dan ikut berubah saat slicer/filter tabel dipakai.');

            // ---------- KPI ----------
            $col = 1; $row = 5;
            foreach (self::METRICS as $label => $flag) {
                $L = $col2L($col);
                $ws->setCellValue("{$L}{$row}", $label);
                $put("{$L}" . ($row + 1), $this->f($flag));
                $col++;
            }
            $L  = $col2L($col);
            $L2 = $col2L($col + 1);
            $L3 = $col2L($col + 2);
            $ws->setCellValue("{$L}{$row}", 'Nilai Muatan');  $put("{$L}6", $this->f('Rk Nilai Grup'));
            $ws->setCellValue("{$L2}{$row}", 'Biaya Kirim');  $put("{$L2}6", $this->f('Rk Biaya Grup'));
            $ws->setCellValue("{$L3}{$row}", 'CR (%)');       $put("{$L3}6", "IF({$L}6>0,{$L2}6/{$L}6*100,0)");
            $ws->getStyle("A{$row}:{$L3}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0284C7']],
            ]);
            $ws->getStyle("A6:{$L3}6")->getFont()->setBold(true)->setSize(14);
            $ws->getStyle("{$L}6:{$L2}6")->getNumberFormat()->setFormatCode('#,##0');
            $ws->getStyle("{$L3}6")->getNumberFormat()->setFormatCode('0.0000"%"');

            // ---------- TABEL PER DIMENSI ----------
            $head = array_merge(array_keys(self::METRICS), ['Nilai Muatan', 'Biaya Kirim', 'CR (%)']);
            $r = 9;
            foreach (self::DIMS as [$judul, $rk, $dbCol]) {
                $keys = $this->data->distinctKeys($dbCol);
                if (!$keys) continue;

                $ws->setCellValue("A{$r}", $judul);
                $ws->getStyle("A{$r}")->getFont()->setBold(true)->setSize(13);
                $r++;
                $ws->fromArray(array_merge([''], $head), null, "A{$r}");
                $ws->getStyle("A{$r}:" . $col2L(count($head) + 1) . "{$r}")
                    ->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F2937']],
                    ]);
                $r++;
                $first = $r;

                foreach ($keys as $k) {
                    $ws->setCellValueExplicit("A{$r}", (string) $k, DataType::TYPE_STRING);
                    $c = 2;
                    foreach (self::METRICS as $flag) {
                        $Lc = $col2L($c++);
                        $put("{$Lc}{$r}", $this->f($flag, $rk, "\$A{$r}"));
                    }
                    $nL = $col2L($c);
                    $bL = $col2L($c + 1);
                    $cL = $col2L($c + 2);
                    $put("{$nL}{$r}", $this->f('Rk Nilai Grup', $rk, "\$A{$r}"));
                    $put("{$bL}{$r}", $this->f('Rk Biaya Grup', $rk, "\$A{$r}"));
                    $put("{$cL}{$r}", "IF({$nL}{$r}>0,{$bL}{$r}/{$nL}{$r}*100,0)");
                    $r++;
                }
                $last = $r - 1;
                $ws->getStyle("{$nL}{$first}:{$bL}{$last}")->getNumberFormat()->setFormatCode('#,##0');
                $ws->getStyle("{$cL}{$first}:{$cL}{$last}")->getNumberFormat()->setFormatCode('0.0000"%"');

                $r += 2;
            }
            $ws->getColumnDimension('A')->setWidth(34);
            foreach (range('B', 'O') as $c) $ws->getColumnDimension($c)->setWidth(15);
        }];
    }

    /** grafik: Gudang Selesai vs Di Gudang per planner (blok planner selalu yang pertama: judul baris 9, header 10, data 11+) */
    public function charts()
    {
        $n = count($this->data->distinctKeys('planner'));
        if ($n === 0) return [];

        $hdr = 10; $first = 11; $last = $first + $n - 1;
        $s = "'Ringkasan'";
        $labels = [
            new DataSeriesValues('String', "{$s}!\$F\${$hdr}", null, 1),   // Gudang Selesai
            new DataSeriesValues('String', "{$s}!\$G\${$hdr}", null, 1),   // Di Gudang
        ];
        $values = [
            new DataSeriesValues('Number', "{$s}!\$F\${$first}:\$F\${$last}", null, $n),
            new DataSeriesValues('Number', "{$s}!\$G\${$first}:\$G\${$last}", null, $n),
        ];
        $cats = [new DataSeriesValues('String', "{$s}!\$A\${$first}:\$A\${$last}", null, $n)];

        $ds = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0, 1], $labels, $cats, $values);
        $ds->setPlotDirection(DataSeries::DIRECTION_COL);

        $chart = new Chart('c_planner', new Title('Gudang Selesai vs Di Gudang per Planner'),
            new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$ds]), true);
        $chart->setTopLeftPosition('Q9');
        $chart->setBottomRightPosition('V28');

        return [$chart];
    }
}