<?php

namespace App\Imports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Events\AfterImport;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

/**
 * Baca xlsx secara streaming (OpenSpout), lalu suapkan ke LogistikImport::collection()
 * per chunk. Hasilnya sama dengan Excel::import, tapi jauh lebih ringan dan cepat.
 */
class LogistikFastImport
{
    public static function run(string $path, LogistikImport $import, int $chunk = 2000): void
    {
        $reader = new Reader(new Options());
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                $buf     = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $vals = array_map([self::class, 'cell'], $row->toArray());

                    // baris pertama = heading (slug seperti WithHeadingRow)
                    if ($headers === null) {
                        $headers = array_map(
                            fn($h) => is_string($h) && trim($h) !== '' ? Str::slug(trim($h), '_') : '',
                            $vals
                        );
                        continue;
                    }

                    $assoc = [];
                    foreach ($headers as $i => $h) {
                        if ($h === '') continue;
                        $assoc[$h] = $vals[$i] ?? null;
                    }

                    $buf[] = collect($assoc);

                    if (count($buf) >= $chunk) {
                        $import->collection(collect($buf));
                        $buf = [];
                    }
                }

                if (!empty($buf)) {
                    $import->collection(collect($buf));
                }

                break; // hanya sheet pertama, sama seperti import lama
            }
        } finally {
            $reader->close();
        }

        // jalankan hitung ulang (setara event AfterImport milik Maatwebsite)
        ($import->registerEvents()[AfterImport::class])();
    }

    /** samakan nilai dengan yang diberikan Maatwebsite: tanggal -> serial Excel (angka) */
    private static function cell($v)
    {
        if ($v instanceof \DateTimeInterface) {
            return XlDate::PHPToExcel($v);
        }
        if ($v instanceof \DateInterval) {
            return (($v->d * 86400) + ($v->h * 3600) + ($v->i * 60) + $v->s) / 86400;
        }
        return $v;
    }
}