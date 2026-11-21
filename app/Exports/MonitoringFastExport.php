<?php

namespace App\Exports;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Export monitoring paling ringan: stream langsung ke browser, tanpa rumus / tabel / chart / slicer.
 * Sheet Ringkasan dihitung di SQL (per PIC), sheet Data = semua baris, tanpa kolom bantu (Rk ...).
 */
class MonitoringFastExport
{
    private int $chunk = 5000;
    private MonitoringSlicerExport $x;

    public function __construct(array $filters = [])
    {
        $this->x = new MonitoringSlicerExport($filters);
    }

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

    private function write(string $target): void
    {
        // kolom yang ditampilkan = semua kecuali kolom bantu "Rk ..."
        $all   = $this->x->headings();
        $keep  = array_keys(array_filter($all, fn($h) => !str_starts_with($h, 'Rk ')));
        $heads = array_values(array_intersect_key($all, array_flip($keep)));

        $options = new Options();
        $options->setColumnWidthForRange(20, 1, count($heads));

        $writer = new Writer($options);
        $writer->openToFile($target);

        $hStyle = (new Style())->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('0284C7');

        // ===== sheet 1: Ringkasan per PIC (angka dari SQL, per shipment + tujuan) =====
        $writer->getCurrentSheet()->setName('Ringkasan');
        $writer->addRow(new Row(array_map(
            fn($h) => Cell::fromValue($h, $hStyle),
            ['PIC', 'Total Shipment+Tujuan', 'Sudah Tiba', 'Belum Tiba', 'Sudah Bongkar', 'Pending Bongkar']
        )));

        $g = "CONCAT(COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id',id)), '|', COALESCE(tujuan,''))";
        $tibaAda    = "NULLIF(TRIM(tanggal_tiba),'') IS NOT NULL";
        $bongkarAda = "NULLIF(TRIM(tanggal_bongkar),'') IS NOT NULL";

        $sum = $this->x->base()
            ->selectRaw("COALESCE(NULLIF(TRIM(pic_monitoring),''),'(kosong)') AS p,
                COUNT(DISTINCT {$g}) AS total,
                COUNT(DISTINCT CASE WHEN {$tibaAda} THEN {$g} END) AS tiba,
                COUNT(DISTINCT CASE WHEN {$bongkarAda} THEN {$g} END) AS bongkar,
                COUNT(DISTINCT CASE WHEN {$tibaAda} AND NOT ({$bongkarAda}) THEN {$g} END) AS pending")
            ->groupBy('p')->orderByDesc('total')->get();

        foreach ($sum as $s) {
            $writer->addRow(Row::fromValues([
                $s->p,
                (int) $s->total,
                (int) $s->tiba,
                (int) $s->total - (int) $s->tiba,
                (int) $s->bongkar,
                (int) $s->pending,
            ]));
        }

        // ===== sheet 2: Data (streaming per chunk) =====
        $writer->addNewSheetAndMakeItCurrent();
        $writer->getCurrentSheet()->setName('Data');
        $writer->addRow(new Row(array_map(fn($h) => Cell::fromValue($h, $hStyle), $heads)));

        // kolom tampilan ada di depan, jadi indeks kolom Excel = indeks array
        $styles = [];
        foreach ($this->x->columnFormats() as $letter => $fmt) {
            $i = Coordinate::columnIndexFromString($letter) - 1;
            if (in_array($i, $keep, true)) $styles[$i] = (new Style())->withFormat($fmt);
        }

        $this->x->base()->chunkById($this->chunk, function ($rows) use ($writer, $styles, $keep) {
            foreach ($rows as $r) {
                $vals  = $this->x->map($r);
                $cells = [];
                foreach ($keep as $i) {
                    $cells[] = Cell::fromValue($vals[$i], $styles[$i] ?? null);
                }
                $writer->addRow(new Row($cells));
            }
        }, 'id');

        $writer->close();
    }
}