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
 * Sheet 1 "Dashboard": hanya grafik (+ slicer di kolom A-C). Semua data grafik dibaca dari sheet "Ringkasan".
 *
 * Gaya (seperti dashboard monitoring): judul di dalam chart, sudut membulat, warna per series,
 * label angka berkotak warna + border (kotak & border ditulis oleh XlsxChartLabelStyler setelah file jadi).
 * Pengecualian: chart "Top 10 tujuan" tetap pakai banner di atasnya (isinya pesan dinamis).
 *
 * Susunan (tiap band = 22 baris):
 *  0: SLA Tiba per Area        | SLA Tiba per Tujuan
 *  1: SLA Bongkar per Area     | SLA Bongkar per Tujuan
 *  2: Tiba di Gudang per Area  | Tiba di Gudang per Tujuan
 *  3: CR per Area              | CR per Tujuan
 *  4: CR per Dist Channel      | SLA Tiba Dry vs Reefer
 *  5: Ekspedisi (selebar layar)
 *  6: Sudah Tiba vs Belum Tiba per PIC (selebar layar)  -> butuh kolom Q di Ringkasan
 */
class LogistikReportDashboard implements WithTitle, WithEvents, WithCharts
{
    private const FIRST_BANNER = 4;
    private const BAND_H       = 22;
    private const SIDES        = ['L' => ['D', 'K'], 'R' => ['L', 'S'], 'F' => ['D', 'S']];

    /** urutan warna series: hijau, oranye, biru, merah */
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

    /**
     * Slot slicer: 2 kolom x 4 baris di kolom A-C (kiri grafik), mulai baris 4.
     * Format: [kolomAwal, offsetKolomAwal, kolomAkhir, offsetKolomAkhir, barisAwal, barisAkhir] (0-based, EMU)
     */
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
                               int $max = 20, string $grouping = DataSeries::GROUPING_CLUSTERED) use (&$out, $b) {
            if (!isset($b[$key])) {
                return;
            }
            $blk    = $b[$key];
            $n      = min($blk['n'], $max);
            $first  = $blk['first'];
            $series = [];
            foreach ($cols as $c) {
                $series[] = [$this->ref($c, $blk['hdrRow']), $this->ref($c, $first, $first + $n - 1)];
            }
            $out[] = [
                'name' => $name, 'band' => $band, 'side' => $side, 'title' => $title, 'dynamic' => false,
                'cat' => $this->ref('A', $first, $first + $n - 1), 'n' => $n, 'series' => $series, 'grouping' => $grouping,
            ];
        };

        $fromHelper = function (int $band, string $name, string $title, array $cols) use (&$out, $b) {
            if (!isset($b['tujuan'])) {
                return;
            }
            $h1 = LogistikReportSummary::HELP_FIRST;
            $h2 = $h1 + LogistikReportSummary::HELP_N - 1;
            $series = [];
            foreach ($cols as $c) {
                $series[] = [$this->ref($c, LogistikReportSummary::HELP_HDR), $this->ref($c, $h1, $h2)];
            }
            $out[] = [
                'name' => $name, 'band' => $band, 'side' => 'R', 'title' => $title, 'dynamic' => true,
                'cat' => $this->ref('T', $h1, $h2), 'n' => LogistikReportSummary::HELP_N,
                'series' => $series, 'grouping' => DataSeries::GROUPING_CLUSTERED,
            ];
        };

        $fromBlock('area', 0, 'L', 'chTibaArea', 'SLA TIBA PER AREA', ['C', 'D']);
        $fromHelper(0, 'chTibaTujuan', 'SLA TIBA PER TUJUAN (TOP 10)', ['U', 'V']);

        $fromBlock('area', 1, 'L', 'chBongkarArea', 'SLA BONGKAR PER AREA', ['F', 'G']);
        $fromHelper(1, 'chBongkarTujuan', 'SLA BONGKAR PER TUJUAN (TOP 10)', ['W', 'X']);

        $fromBlock('area', 2, 'L', 'chGudangArea', 'TIBA DI GUDANG PER AREA: ON TIME vs DELAY', ['O', 'P']);
        $fromHelper(2, 'chGudangTujuan', 'TIBA DI GUDANG PER TUJUAN (TOP 10): ON TIME vs DELAY', ['Z', 'AA']);

        $fromBlock('area', 3, 'L', 'chCrArea', 'CR PER AREA', ['N']);
        $fromHelper(3, 'chCrTujuan', 'CR PER TUJUAN (TOP 10)', ['Y']);

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
            ];
        }

        $fromBlock('ekpedisi', 5, 'F', 'chEkspedisi', 'PER EKSPEDISI: TIBA ON TIME / DELAY & GUDANG ON TIME / DELAY',
            ['C', 'D', 'O', 'P'], 20);

        // Sudah Tiba (Q) vs Belum Tiba (I) per PIC
        $fromBlock('pic_monitoring', 6, 'F', 'chPic', 'SUDAH TIBA vs BELUM TIBA PER PIC', ['Q', 'I'], 20);

        return $this->specs = $out;
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
            $top       = $this->bannerRow($sp['band']) + ($card ? 0 : 1);
            $single    = count($sp['series']) === 1;

            $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sp['cat'], null, $sp['n'])];
            $tags = $values = [];
            foreach ($sp['series'] as $i => [$nameRef, $valRef]) {
                $tags[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $nameRef, null, 1);

                $v = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valRef, null, $sp['n']);
                $v->setFillColor($single ? self::SINGLE_COLOR : (self::PALETTE[$i] ?? '6B7280'));
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
            $chart->setBottomRightPosition("{$c2}" . ($top + 19));

            $charts[] = $chart;
        }

        return $charts;
    }

    // ============================================================
    // TULIS SHEET (judul, banner chart dinamis, lebar kolom)
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

                foreach ($this->specs() as $sp) {
                    if (!$sp['dynamic']) {
                        continue;   // judul sudah ada di dalam chart
                    }

                    [$c1, $c2] = self::SIDES[$sp['side']];
                    $row       = $this->bannerRow($sp['band']);

                    $s->mergeCells("{$c1}{$row}:{$c2}{$row}");
                    $s->setCellValueExplicit(
                        "{$c1}{$row}",
                        '=IF(' . LogistikReportSummary::FLAG_REF . '=1,"' . $sp['title']
                        . '","Pilih Area di slicer untuk menampilkan grafik per tujuan")',
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