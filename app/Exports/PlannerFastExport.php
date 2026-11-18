<?php

namespace App\Exports;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PlannerFastExport extends PlannerExport
{
    private int $chunk = 5000;

    public function download(string $filename)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        return response()->streamDownload(function () {
            while (ob_get_level() > 0) ob_end_clean();
            $this->write('php://output');
        }, $filename, [
            'Content-Type'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function base()
    {
        return $this->query()->toBase()->reorder();   // tanpa ORDER BY, tanpa model Eloquent
    }

    private function write(string $target): void
    {
        $heads = $this->headings();

        $options = new Options();
        $options->setColumnWidthForRange(20, 1, count($heads));

        $writer = new Writer($options);
        $writer->openToFile($target);

        $hStyle = (new Style())->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('0284C7');

        // ===== sheet 1: Ringkasan (angka dari SQL, sebelum data) =====
        $writer->getCurrentSheet()->setName('Ringkasan');
        $writer->addRow(new Row(array_map(
            fn($h) => Cell::fromValue($h, $hStyle),
            ['Planner', 'Total Shipment+Tujuan', 'Sudah Armada', 'Belum Armada', 'Gudang Selesai', 'Di Gudang']
        )));

        $sum = $this->base()->where('is_rep', 1)
            ->selectRaw("COALESCE(NULLIF(TRIM(planner),''),'(kosong)') AS p,
                COUNT(*) AS total,
                SUM(CASE WHEN NULLIF(TRIM(rencana_kirim),'') IS NOT NULL AND NULLIF(TRIM(tanggal_dpt_unit),'') IS NOT NULL THEN 1 ELSE 0 END) AS sudah,
                SUM(CASE WHEN selesai_gudang = 1 THEN 1 ELSE 0 END) AS selesai")
            ->groupBy('p')->orderByDesc('total')->get();

        foreach ($sum as $s) {
            $writer->addRow(Row::fromValues([
                $s->p, (int) $s->total, (int) $s->sudah, (int) $s->total - (int) $s->sudah,
                (int) $s->selesai, (int) $s->total - (int) $s->selesai,
            ]));
        }

        // ===== sheet 2: Data (streaming per chunk) =====
        $writer->addNewSheetAndMakeItCurrent();
        $writer->getCurrentSheet()->setName('Data');
        $writer->addRow(new Row(array_map(fn($h) => Cell::fromValue($h, $hStyle), $heads)));

        $styles = [];
        foreach ($this->columnFormats() as $letter => $fmt) {
            $styles[Coordinate::columnIndexFromString($letter) - 1] = (new Style())->withFormat($fmt);
        }

        $this->base()->chunkById($this->chunk, function ($rows) use ($writer, $styles) {
            foreach ($rows as $r) {
                $cells = [];
                foreach ($this->map($r) as $i => $v) {
                    $cells[] = Cell::fromValue($v, $styles[$i] ?? null);
                }
                $writer->addRow(new Row($cells));
            }
        }, 'id');

        $writer->close();
    }
}