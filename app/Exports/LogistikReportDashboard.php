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
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

/**
 * Sheet 1 "Dashboard": grafik (+ slicer di kolom A-C). Data grafik dibaca dari sheet "Ringkasan".
 *
  * Band 0-6 (tiap band 22 baris): grafik per Area / Dist Channel / Dry-Reefer / Ekspedisi (Tiba, lalu Gudang, full width).
 * Grafik Area, Dist Channel, dan Ekspedisi membaca tabel "padat" di Ringkasan (LogistikReportSummary::compactMap),
 * jadi hanya kategori yang lolos slicer yang tampil (urut Nilai Muatan terbesar, maks CMP_N).
 * Grafik Dry vs Reefer membaca S16:U18 (cuma 2 kategori).
 * Grafik TOP 20 TUJUAN (1 grafik per metrik, di kanan grafik Area) baru terisi kalau slicer/filter aktif
 * (flag Ringkasan!T2 = 1), kalau tidak kosong.
 */
class LogistikReportDashboard implements WithTitle, WithEvents, WithCharts
{
    private const FIRST_BANNER   = 4;
    private const BAND_H         = 22;
    private const SIDES          = ['L' => ['D', 'K'], 'R' => ['L', 'S'], 'F' => ['D', 'S']];

    private const PALETTE      = ['16A34A', 'F59E0B', '2563EB', 'DC2626'];
    private const SINGLE_COLOR = '2563EB';

    private ?array $specs = null;

    public function __construct(private LogistikReportSummary $summary, private string $filterInfo = '')
    {
    }

    public function title(): string
    {
        return 'Dashboard';
    }

    public static function slicerSlots(): array
    {
        $cols  = [[0, 38100, 0, 2350000], [1, 38100, 2, 1050000]];
        $slots = [];
        for ($band = 0; $band < 4; $band++) {
            $r1 = 3 + $band * 14;
            foreach ($cols as [$c1, $o1, $c2, $o2]) {
                $slots[] = [$c1, $o1, $c2, $o2, $r1, $r1 + 13];
            }
        }
        return $slots;
    }

    private function bannerRow(int $band): int
    {
        return self::FIRST_BANNER + $band * self::BAND_H;
    }

    private function ref(string $col, int $r1, ?int $r2 = null): string
    {
        return "'Ringkasan'!\${$col}\${$r1}" . ($r2 !== null ? ":\${$col}\${$r2}" : '');
    }

    // ============================================================
    // DEFINISI GRAFIK
    // ============================================================
      private function specs(): array
    {
        if ($this->specs !== null) {
            return $this->specs;
        }

        $b   = $this->summary->layout()['blocks'];
        $out = [];

        $fromBlock = function (string $key, int $band, string $side, string $name, string $title, array $cols,
                               ?int $max = null, string $grouping = DataSeries::GROUPING_CLUSTERED) use (&$out, $b) {
            if (!isset($b[$key])) {
                return;
            }
            $blk = $b[$key];
            $cmp = $this->summary->compactMap($key);
            $dyn = [];

            if ($cmp) {
                // tabel padat: hanya kategori yang lolos slicer
                $n      = $cmp['n'];
                $f      = $cmp['first'];
                $cat    = $this->ref($cmp['label'], $f, $f + $n - 1);
                $series = [];

                $dyn[$cat] = "'Ringkasan'!dyn_{$key}_label";
                foreach ($cols as $c) {
                    $valRef       = $this->ref($cmp['cols'][$c], $f, $f + $n - 1);
                    $series[]     = [$this->ref($cmp['cols'][$c], $cmp['hdr']), $valRef];
                    $dyn[$valRef] = "'Ringkasan'!dyn_{$key}_{$c}";
                }
            } else {
                $n = $max === null ? $blk['n'] : min($blk['n'], $max);
                if ($n < 1) {
                    return;
                }
                $first  = $blk['first'];
                $cat    = $this->ref('A', $first, $first + $n - 1);
                $series = [];
                foreach ($cols as $c) {
                    $series[] = [$this->ref($c, $blk['hdrRow']), $this->ref($c, $first, $first + $n - 1)];
                }
            }

            $out[] = [
                'name' => $name, 'band' => $band, 'side' => $side, 'title' => $title, 'dynamic' => false,
                'cat' => $cat, 'n' => $n, 'series' => $series, 'grouping' => $grouping,
                'row' => $this->bannerRow($band), 'rows' => 19,
                'dyn' => $dyn,
            ];
        };

        $fromBlock('area', 0, 'L', 'chTibaArea', 'SLA TIBA PER AREA', ['C', 'D']);
        $fromBlock('area', 1, 'L', 'chBongkarArea', 'SLA BONGKAR PER AREA', ['F', 'G']);
        $fromBlock('area', 2, 'L', 'chGudangArea', 'TIBA DI GUDANG PER AREA: ON TIME vs DELAY', ['O', 'P']);
        $fromBlock('area', 3, 'L', 'chCrArea', 'CR PER AREA', ['N']);
        $fromBlock('dist_channel', 4, 'L', 'chCrChannel', 'CR PER DIST CHANNEL', ['N']);

        if (isset($b['tipe'])) {
            $out[] = [
                'name' => 'chTibaTipe', 'band' => 4, 'side' => 'R', 'title' => 'SLA TIBA: DRY vs REEFER', 'dynamic' => false,
                'cat' => $this->ref('S', 17, 18), 'n' => 2,
                'series' => [
                    [$this->ref('T', 16), $this->ref('T', 17, 18)],
                    [$this->ref('U', 16), $this->ref('U', 17, 18)],
                ],
                'grouping' => DataSeries::GROUPING_CLUSTERED,
                'row' => $this->bannerRow(4), 'rows' => 19,
            ];
        }

        // ekspedisi dipisah jadi 2 grafik full width (banyak ekspedisi, jadi label perlu lebar)
        $fromBlock('ekpedisi', 5, 'F', 'chEkspedisiTiba', 'PER EKSPEDISI: TIBA ON TIME vs DELAY (CUSTOMER)',
            ['C', 'D']);
        $fromBlock('ekpedisi', 6, 'F', 'chEkspedisiGudang', 'PER EKSPEDISI: TIBA DI GUDANG ON TIME vs DELAY',
            ['O', 'P']);

        // ---- CR TOTAL: 1 batang = CR seluruh data yang lolos slicer
        $out[] = [
            'name' => 'chCrTotal', 'band' => 7, 'side' => 'L', 'title' => 'CR TOTAL (SESUAI SLICER)', 'dynamic' => false,
            'cat' => $this->ref('W', 17), 'n' => 1,
            'series' => [[$this->ref('X', 16), $this->ref('X', 17)]],
            'grouping' => DataSeries::GROUPING_CLUSTERED,
            'row' => $this->bannerRow(7), 'rows' => 19,
        ];

        // ---- IN TRANSIT vs IN GUDANG vs SUDAH TIBA (jumlah grup, ikut slicer)
       

        // ---- grafik tujuan: TOP 20, 1 grafik per metrik, di kanan grafik Area yang sepasang
        //      kosong kalau slicer belum aktif (flag Ringkasan!T2 = 0)
        if (isset($b['tujuan'])) {
            $n   = min(LogistikReportSummary::TUJ_TOP, $b['tujuan']['n']);
            $h1  = LogistikReportSummary::HELP_FIRST;
            $h2  = $h1 + $n - 1;
            $top = LogistikReportSummary::TUJ_TOP;

            // [band, nama, judul, kolom bantu]  (band sama dengan chart Area di sebelah kirinya)
            $list = [
                [0, 'chTibaTujuan',    "SLA TIBA PER TUJUAN (TOP {$top})",                         ['AE', 'AF']],
                [1, 'chBongkarTujuan', "SLA BONGKAR PER TUJUAN (TOP {$top})",                      ['AG', 'AH']],
                [2, 'chGudangTujuan',  "TIBA DI GUDANG PER TUJUAN: ON TIME vs DELAY (TOP {$top})", ['AJ', 'AK']],
                [3, 'chCrTujuan',      "CR PER TUJUAN (TOP {$top})",                               ['AI']],
            ];

            foreach ($list as [$band, $name, $title, $cols]) {
                $series = [];
                foreach ($cols as $c) {
                    $series[] = [$this->ref($c, LogistikReportSummary::HELP_HDR), $this->ref($c, $h1, $h2)];
                }

                $out[] = [
                    'name' => $name, 'band' => $band, 'side' => 'R', 'dynamic' => true,
                    'title' => $title,
                    'cat' => $this->ref('AD', $h1, $h2), 'n' => $n,
                    'series' => $series, 'grouping' => DataSeries::GROUPING_CLUSTERED,
                    'row' => $this->bannerRow($band), 'rows' => 19,
                ];
            }
        }

        return $this->specs = $out;
    }
/** peta [referensi statis => named range dinamis] untuk dipasang di XML chart */
public function dynRefs(): array
{
    $m = [];
    foreach ($this->specs() as $sp) {
        $m += $sp['dyn'] ?? [];
    }
    return $m;
}
    // ============================================================
    // GRAFIK
    // ============================================================
    public function charts()
    {
        $charts = [];

        foreach ($this->specs() as $sp) {
            [$c1, $c2] = self::SIDES[$sp['side']];
            $card      = !$sp['dynamic'];                           // judul di dalam chart
            $top       = $sp['row'] + ($card ? 0 : 1);
            $single    = count($sp['series']) === 1;

            $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sp['cat'], null, $sp['n'])];
            $tags = $values = [];
                     foreach ($sp['series'] as $i => [$nameRef, $valRef]) {
                $tags[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $nameRef, null, 1);

                $v = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valRef, null, $sp['n']);
                $v->setFillColor($sp['colors'] ?? ($single ? self::SINGLE_COLOR : (self::PALETTE[$i] ?? '6B7280')));
                $values[] = $v;
            }

            $ds = new DataSeries(
                DataSeries::TYPE_BARCHART,
                $sp['grouping'],
                range(0, count($values) - 1),
                $tags,
                $labels,
                $values
            );
            $ds->setPlotDirection(DataSeries::DIRECTION_COL);

            // label angka ditulis oleh XlsxChartLabelStyler (per series), jadi PlotArea tanpa Layout

            $chart = new Chart(
                $sp['name'],
                $card ? new Title($sp['title']) : null,
                new Legend(Legend::POSITION_BOTTOM, null, false),
                new PlotArea(null, [$ds])
            );
            if (method_exists($chart, 'setRoundedCorners')) {
                $chart->setRoundedCorners(true);
            }
            $chart->setTopLeftPosition("{$c1}{$top}");
            $chart->setBottomRightPosition("{$c2}" . ($top + $sp['rows']));

            $charts[] = $chart;
        }

        return $charts;
    }

    // ============================================================
    // TULIS SHEET (judul, banner chart tujuan, lebar kolom)
    // ============================================================
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();

                $s->getTabColor()->setRGB('111827');
                $s->setShowGridlines(false);
                $s->getSheetView()->setZoomScale(85);

                $s->setCellValue('A1', 'DASHBOARD LOGISTIK');
                $s->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $s->setCellValue('A2', 'Dibuat ' . date('d-m-Y H:i') . ($this->filterInfo ? ' | ' . $this->filterInfo : ''));
                $s->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');

                $s->getColumnDimension('A')->setWidth(36);
                foreach (range('B', 'S') as $c) {
                    $s->getColumnDimension($c)->setWidth(16);
                }

                $flag = LogistikReportSummary::FLAG_REF;   // 'Ringkasan'!$T$2 (1 = slicer/filter aktif)

                foreach ($this->specs() as $sp) {
                    if (!$sp['dynamic']) {
                        continue;   // judul sudah ada di dalam chart
                    }

                    [$c1, $c2] = self::SIDES[$sp['side']];
                    $row       = $sp['row'];

                    $s->mergeCells("{$c1}{$row}:{$c2}{$row}");
                    $s->setCellValueExplicit(
                        "{$c1}{$row}",
                        '=IF(' . $flag . '=1,'
                        . '"' . $sp['title'] . ' (sesuai slicer)",'
                        . '"' . $sp['title'] . ' - pilih slicer (mis. Area) untuk menampilkan data")',
                        DataType::TYPE_FORMULA
                    );

                    $s->getStyle("{$c1}{$row}:{$c2}{$row}")->applyFromArray([
                        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F2937']],
                        'alignment' => ['vertical' => 'center', 'indent' => 1],
                    ]);
                    $s->getRowDimension($row)->setRowHeight(20);
                }
            },
        ];
    }
}