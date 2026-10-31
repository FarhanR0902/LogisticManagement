<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Membaca Excel hanya untuk dicocokkan, TIDAK menyimpan apa pun ke DB.
 * Header yang dipakai: no_shipment, tujuan, no_do (huruf kecil, spasi jadi underscore).
 */
class CocoklogiImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    /** "SHIPMENT|TUJUAN" => [shipment, tujuan] */
    public array $groups = [];

    /** "SHIPMENT|NO_DO" => [shipment, no_do] */
    public array $dos = [];

    public int $rows = 0;
    public int $skippedNoShipment = 0;

    /** Normalisasi nomor (shipment / DO): trim, buang ".0", angka float Excel jadi string utuh, uppercase */
    public static function normNo($v): string
    {
        if (is_float($v) && fmod($v, 1.0) == 0.0) {
            $v = sprintf('%.0f', $v);
        }

        $v = trim((string) $v);
        $v = preg_replace('/\.0+$/', '', $v);

        return strtoupper($v);
    }

    /** Normalisasi tujuan: trim, spasi ganda dirapikan, uppercase */
    public static function normTujuan($v): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $v)));
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $r) {
            $ship = self::normNo($r['no_shipment'] ?? '');

            if ($ship === '') {
                $this->skippedNoShipment++;
                continue;
            }

            $tujuan = self::normTujuan($r['tujuan'] ?? '');
            $this->groups[$ship . '|' . $tujuan] = [$ship, trim((string) ($r['tujuan'] ?? ''))];

            $do = self::normNo($r['no_do'] ?? '');
            if ($do !== '') {
                $this->dos[$ship . '|' . $do] = [$ship, $do];
            }

            $this->rows++;
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}