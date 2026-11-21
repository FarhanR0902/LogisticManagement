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

/**
 * Sheet 1 "Dashboard": hanya grafik (+ slicer di kolom A-C). Semua data grafik dibaca dari sheet "Ringkasan".
 *
 * Susunan (tiap band = 1 baris judul + 20 baris grafik):
 *  0: SLA Tiba per Area        | SLA Tiba per Tujuan
 *  1: SLA Bongkar per Area     | SLA Bongkar per Tujuan
 *  2: CR per Area              | CR per Tujuan
 *  3: CR per Dist Channel      | SLA Tiba Dry vs Reefer
 *  4: Ekspedisi: Tiba On Time vs Delay di Gudang vs Delay di Konsumen (selebar layar)
 */
class LogistikReportDashboard implements WithTitle, WithEvents, WithCharts
{
    private const FIRST_BANNER = 4;
    private const BAND_H       = 22;
    private const SIDES        = ['L' => ['D', 'K'], 'R' => ['L', 'S'], 'F' => ['D', 'S']];

    private ?array $specs = null;

    public function __construct(private LogistikReportSummary $summary, private string $filterInfo = '')
    {
    }

    public function title(): string
    {
        return 'Dashboard';
    }

    /**
     * Slot slicer: 2 kolom x 3 baris di kolom A-C (kiri grafik), mulai baris 4.
     * Format: [kolomAwal, offsetKolomAwal, kolomAkhir, offsetKolomAkhir, barisAwal, barisAkhir] (0-based, EMU)
     * PENTING: injector slicer harus menempelkan slicer ke sheet "Dashboard" (sheet pertama).
     */
    public static function slicerSlots(): array
    {
        $cols  = [[0, 38100, 0, 2350000], [1, 38100, 2, 1050000]];
        $slots = [];
     for ($band = 0; $band < 4; $band++) {   // 4 band × 2 kolom = 8 slot, baris 4-59 (kolom A-C)
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

        // grafik dari satu blok tabel di Ringkasan
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

        // grafik dari tabel bantu "Top 10 tujuan" (kosong kalau area belum dipilih)
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

$fromBlock('area', 0, 'L', 'chTibaArea',    'SLA TIBA PER AREA',    ['C', 'D']);
$fromHelper(0, 'chTibaTujuan',    'SLA TIBA PER TUJUAN (TOP 10)',    ['U', 'V']);

$fromBlock('area', 1, 'L', 'chBongkarArea', 'SLA BONGKAR PER AREA', ['F', 'G']);
$fromHelper(1, 'chBongkarTujuan', 'SLA BONGKAR PER TUJUAN (TOP 10)', ['W', 'X']);

// BARU: tiba di gudang
$fromBlock('area', 2, 'L', 'chGudangArea',  'TIBA DI GUDANG PER AREA: ON TIME vs DELAY', ['O', 'P']);
$fromHelper(2, 'chGudangTujuan', 'TIBA DI GUDANG PER TUJUAN (TOP 10): ON TIME vs DELAY', ['Z', 'AA']);

$fromBlock('area', 3, 'L', 'chCrArea',      'CR PER AREA',          ['N']);
$fromHelper(3, 'chCrTujuan',      'CR PER TUJUAN (TOP 10)',          ['Y']);

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
            $top       = $this->bannerRow($sp['band']) + 1;

            $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sp['cat'], null, $sp['n'])];
            $tags = $values = [];
            foreach ($sp['series'] as [$nameRef, $valRef]) {
                $tags[]   = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $nameRef, null, 1);
                $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $valRef, null, $sp['n']);
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

            // judul grafik = sel banner di atasnya (bisa berubah otomatis), jadi title chart dikosongkan
            $chart = new Chart(
                $sp['name'],
                null,
                new Legend(Legend::POSITION_BOTTOM, null, false),
                new PlotArea(null, [$ds])
            );
            $chart->setTopLeftPosition("{$c1}{$top}");
            $chart->setBottomRightPosition("{$c2}" . ($top + 19));

            $charts[] = $chart;
        }

        return $charts;
    }

    // ============================================================
    // TULIS SHEET (judul, banner, lebar kolom)
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
                    [$c1, $c2] = self::SIDES[$sp['side']];
                    $row       = $this->bannerRow($sp['band']);

                    $s->mergeCells("{$c1}{$row}:{$c2}{$row}");

                    if ($sp['dynamic']) {
                        $s->setCellValueExplicit(
                            "{$c1}{$row}",
                            '=IF(' . LogistikReportSummary::FLAG_REF . '=1,"' . $sp['title']
                            . '","Pilih Area di slicer untuk menampilkan grafik per tujuan")',
                            DataType::TYPE_FORMULA
                        );
                    } else {
                        $s->setCellValue("{$c1}{$row}", $sp['title']);
                    }

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