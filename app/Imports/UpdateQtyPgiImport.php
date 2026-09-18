<?php

namespace App\Imports;

use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Collection;

class UpdateQtyPgiImport implements ToCollection, WithHeadingRow
{
    // ===== hasil proses total_do_qty_car =====
    private int $qtyUpdated = 0;
    private array $qtyNotFound = [];
    private array $qtyAmbiguous = [];
    private int $qtySkipped = 0;

    // ===== hasil proses act_pgi_date =====
    private int $pgiUpdated = 0;
    private array $pgiNotFound = [];
    private int $pgiSkipped = 0;

    public function getQtyUpdated(): int { return $this->qtyUpdated; }
    public function getQtyNotFound(): array { return $this->qtyNotFound; }
    public function getQtyAmbiguous(): array { return $this->qtyAmbiguous; }
    public function getQtySkipped(): int { return $this->qtySkipped; }

    public function getPgiUpdated(): int { return $this->pgiUpdated; }
    public function getPgiNotFound(): array { return $this->pgiNotFound; }
    public function getPgiSkipped(): int { return $this->pgiSkipped; }

    public function collection(Collection $rows)
    {
        // supaya act_pgi_date tidak di-update berkali-kali kalau
        // no_shipment yang sama muncul di beberapa baris Excel
        $pgiProcessed = [];

        foreach ($rows as $row) {

            $noShipment = $this->cleanText($row['no_shipment'] ?? null);

            if (empty($noShipment)) {
                $this->qtySkipped++;
                $this->pgiSkipped++;
                continue;
            }

            // ================= TOTAL DO QTY CAR (kunci: no_shipment + tujuan) =================
            // Dikunci ganda karena satu no_shipment bisa punya beberapa
            // tujuan berbeda dengan qty pengiriman yang beda-beda pula
            // (mis. no_shipment sama tapi tujuan "Bandung 1" dan "Bandung 2"
            // dengan qty masing-masing berbeda).
            $tujuan = $this->cleanText($row['tujuan'] ?? null);
            $qty    = $this->cleanNumber($row['total_do_qty_car'] ?? null);

            if (!empty($tujuan) && $qty !== null) {

                $matches = LogistikPengiriman::where('no_shipment', $noShipment)
                    ->where('tujuan', $tujuan)
                    ->get();

                if ($matches->isEmpty()) {
                    $this->qtyNotFound[] = "{$noShipment} - {$tujuan}";
                } elseif ($matches->count() > 1) {
                    // Lebih dari 1 baris cocok persis -> tidak bisa ditentukan
                    // otomatis mana yang dimaksud, dilewati dan dilaporkan
                    // supaya dicek manual.
                    $this->qtyAmbiguous[] = "{$noShipment} - {$tujuan} ({$matches->count()} baris)";
                } else {
                    $matches->first()->update(['total_do_qty_car' => $qty]);
                    $this->qtyUpdated++;
                }

            } else {
                $this->qtySkipped++;
            }

            // ================= ACT PGI DATE (kunci: no_shipment saja) =================
            // Field level-shipment, jadi update SEMUA baris dengan no_shipment
            // yang sama sekaligus (konsisten dengan sifat data act_pgi_date
            // yang merepresentasikan tanggal shipment keluar dari sistem).
            $pgiDateRaw = $row['act_pgi_date'] ?? ($row['act_pgi_date'] ?? null);
            $pgiDate    = $this->convertDate($pgiDateRaw);

            if ($pgiDate !== null && !isset($pgiProcessed[$noShipment])) {

                $affected = LogistikPengiriman::where('no_shipment', $noShipment)
                    ->update(['act_pgi_date' => $pgiDate]);

                if ($affected === 0) {
                    $this->pgiNotFound[] = $noShipment;
                } else {
                    $this->pgiUpdated += $affected;
                }

                $pgiProcessed[$noShipment] = true;

            } elseif ($pgiDate === null) {
                $this->pgiSkipped++;
            }
        }
    }

    private function cleanText($value)
    {
        if ($value === null || $value === '' || $value == '-') return null;
        return trim((string) $value);
    }

    private function cleanNumber($value)
    {
        if ($value === null || $value === '' || $value == '-') return null;

        $value = preg_replace('/[^0-9]/', '', (string) $value);

        return $value === '' ? null : (int) $value;
    }

    private function convertDate($value)
    {
        if (!$value || $value == '-') return null;

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $timestamp = strtotime(str_replace('/', '-', trim($value)));

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}