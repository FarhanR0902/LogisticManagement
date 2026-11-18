<?php

namespace App\Exports;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class LogistikStreamExport
{
    public function __construct(
        private LogistikExportFull $export,
        private int $chunk = 2000
    ) {
    }

    public function download(string $filename)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        return response()->streamDownload(function () {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            $this->write('php://output');
        }, $filename, [
            'Content-Type'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function write(string $target): void
    {
        $heads    = $this->export->headings();
        $rawCount = $this->export->rawCount();

        $options = new Options();
        $options->setColumnWidthForRange(20, 1, count($heads));

        $writer = new Writer($options);
        $writer->openToFile($target);

        // header: hijau = kolom database, biru = kolom hitungan
        $hGreen = (new Style())
            ->withFontBold(true)
            ->withFontColor(Color::WHITE)
            ->withBackgroundColor('03C03C');
        $hBlue = (new Style())
            ->withFontBold(true)
            ->withFontColor(Color::WHITE)
            ->withBackgroundColor('2563EB');

        $cells = [];
        foreach ($heads as $i => $h) {
            $cells[] = Cell::fromValue($h, $i < $rawCount ? $hGreen : $hBlue);
        }
        $writer->addRow(new Row($cells));

        // style per kolom (tanggal / rupiah / persen), dibuat sekali
        $styles = [];
        foreach ($this->export->columnFormats() as $letter => $fmt) {
            $styles[Coordinate::columnIndexFromString($letter) - 1] = (new Style())->withFormat($fmt);
        }

        $this->export->query()->chunk($this->chunk, function ($rows) use ($writer, $styles) {
            // agregat hanya untuk shipment di chunk ini
            $this->export->scopeAggregates(
                $rows->pluck('no_shipment')
                    ->map(fn($v) => trim((string) $v))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all()
            );

            foreach ($rows as $r) {
                $cells = [];
                foreach ($this->export->map($r) as $i => $v) {
                    $cells[] = Cell::fromValue($v, $styles[$i] ?? null);
                }
                $writer->addRow(new Row($cells));
            }
        });

        $writer->close();
    }
}