<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet "Data" versi Maatwebsite/PhpSpreadsheet untuk Monitoring.
 * Kolom, filter, dan logika wakil grup diambil dari MonitoringSlicerExport (satu sumber).
 */
class MonitoringReportData implements
    FromQuery, WithHeadings, WithMapping, WithColumnFormatting, WithStyles, WithCustomChunkSize, WithEvents, WithTitle
{
    private MonitoringSlicerExport $x;

    public function __construct(array $filters = [])
    {
        $this->x = new MonitoringSlicerExport($filters);
    }

    public function x(): MonitoringSlicerExport
    {
        return $this->x;
    }

    public function title(): string
    {
        return 'Data';
    }

    public function chunkSize(): int
    {
        return 5000;
    }

    public function query()
    {
        return $this->x->query()->orderBy('id');   // urut id: wakil grup = id terkecil
    }

    public function headings(): array
    {
        return $this->x->headings();
    }

    public function map($r): array
    {
        return $this->x->map($r);
    }

    public function columnFormats(): array
    {
        return $this->x->columnFormats();
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');
        return [1 => ['font' => ['bold' => true]]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet   = $event->sheet->getDelegate();
            $lastRow = $sheet->getHighestRow();
            $lastCol = $sheet->getHighestColumn();
            $sheet->getTabColor()->setRGB('0284C7');

            // header kolom bantu (Rk ...) diberi warna abu-abu
            $first = $this->x->letter('Rk Tampil');
            $sheet->getStyle("{$first}1:{$lastCol}1")->applyFromArray([
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '6B7280']],
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            ]);

            if ($lastRow >= 2) {
                $tc = $this->x->letter('Rk Tampil');
                $gr = $this->x->letter('Rk Grup');
                for ($r = 2; $r <= $lastRow; $r++) {
                    $sheet->setCellValueExplicit("{$tc}{$r}", "=SUBTOTAL(103,\${$gr}{$r})", DataType::TYPE_FORMULA);
                }
                $table = new Table("A1:{$lastCol}{$lastRow}", 'tblLogistik'); // nama dipakai XlsxSlicerInjector
                $table->setShowHeaderRow(true);
                $table->setAllowFilter(true);
                $table->setStyle((new TableStyle(TableStyle::TABLE_STYLE_LIGHT1))->setShowRowStripes(true));
                $sheet->addTable($table);
            }
        }];
    }
}