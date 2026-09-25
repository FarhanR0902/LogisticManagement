<?php

namespace App\Imports;

use App\Models\Kota;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KotaAlasanPendingImport implements ToCollection, WithHeadingRow
{
    private $updatedRows  = 0; // total baris di DB yang ke-update
    private $matchedKeys  = 0; // total kombinasi no_shipment+tujuan yang ketemu
    private $notFoundKeys = 0; // kombinasi yang tidak ketemu di DB
    private $skipped      = 0;

    public function getUpdatedRowsCount(): int  { return $this->updatedRows; }
    public function getMatchedKeysCount(): int  { return $this->matchedKeys; }
    public function getNotFoundKeysCount(): int { return $this->notFoundKeys; }
    public function getSkippedCount(): int      { return $this->skipped; }

    public function collection(Collection $rows)
    {
        // --------------------------------------------------------------------
        // PASS 1: Kumpulkan alasan_pending per kombinasi no_shipment + tujuan
        // (kalau ada baris duplikat di file update ini, yang pertama non-kosong menang)
        // --------------------------------------------------------------------
        $alasanPerKey = [];

        foreach ($rows as $row) {
            $noShipment = $this->cleanText($row['no_shipment'] ?? null);
            $tujuan     = $this->cleanText($row['tujuan'] ?? null);
            $alasan     = $this->cleanText($row['alasan_pending'] ?? null);

            if (empty($noShipment) || empty($tujuan)) {
                $this->skipped++;
                continue;
            }

            if (empty($alasan)) {
                $this->skipped++;
                continue;
            }

            $key = $noShipment . '_' . $tujuan;

            if (!isset($alasanPerKey[$key])) {
                $alasanPerKey[$key] = [
                    'no_shipment' => $noShipment,
                    'tujuan'      => $tujuan,
                    'alasan'      => $alasan,
                ];
            }
        }

        // --------------------------------------------------------------------
        // PASS 2: Update semua baris di DB yang cocok, per kombinasi
        // --------------------------------------------------------------------
        foreach ($alasanPerKey as $item) {
            $this->matchedKeys++;

            $affected = Kota::where('no_shipment', $item['no_shipment'])
                ->where('tujuan', $item['tujuan'])
                ->update(['alasan_pending' => $item['alasan']]);

            if ($affected > 0) {
                $this->updatedRows += $affected;
            } else {
                $this->notFoundKeys++;
            }
        }
    }

    private function cleanText($value)
    {
        if ($value === null || $value === '' || $value === '-' || $value === '#VALUE!') return null;
        if (is_array($value)) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}