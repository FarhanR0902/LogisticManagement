<?php

namespace App\Imports;

use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Collection;

class UpdateQtyPgiImport implements ToCollection, WithHeadingRow
{
    // ===== hasil proses total_do_qty_car (kunci: no_shipment + tujuan) =====
    private int $qtyUpdated = 0;
    private array $qtyNotFound = [];
    private array $qtyAmbiguous = [];
    private int $qtySkipped = 0;

    // ===== hasil proses act_pgi_date (kunci: no_shipment) =====
    private int $pgiUpdated = 0;
    private array $pgiNotFound = [];
    private int $pgiSkipped = 0;

    // ===== hasil proses kubikasi/tonase/total_kubik/total_tonase (kunci: no_shipment) =====
    private int $kubikTonaseUpdated = 0;
    private array $kubikTonaseNotFound = [];
    private int $kubikTonaseSkipped = 0;

    // dedup supaya field level-shipment tidak diproses berkali-kali
    // per no_shipment yang muncul di banyak baris Excel
    private array $pgiProcessed = [];
    private array $kubikTonaseProcessed = [];

    // ================= GETTERS =================
    public function getQtyUpdated(): int { return $this->qtyUpdated; }
    public function getQtyNotFound(): array { return $this->qtyNotFound; }
    public function getQtyAmbiguous(): array { return $this->qtyAmbiguous; }
    public function getQtySkipped(): int { return $this->qtySkipped; }

    public function getPgiUpdated(): int { return $this->pgiUpdated; }
    public function getPgiNotFound(): array { return $this->pgiNotFound; }
    public function getPgiSkipped(): int { return $this->pgiSkipped; }

    public function getKubikTonaseUpdated(): int { return $this->kubikTonaseUpdated; }
    public function getKubikTonaseNotFound(): array { return $this->kubikTonaseNotFound; }
    public function getKubikTonaseSkipped(): int { return $this->kubikTonaseSkipped; }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            $noShipment = $this->cleanText($row['no_shipment'] ?? null);

            if (empty($noShipment)) {
                $this->qtySkipped++;
                $this->pgiSkipped++;
                $this->kubikTonaseSkipped++;
                continue;
            }

            // =====================================================
            // 1) TOTAL DO QTY CAR (kunci: no_shipment + tujuan)
            //    Independen — hanya jalan kalau kolom ini ADA & terisi.
            // =====================================================
            $tujuan = $this->cleanText($row['tujuan'] ?? null);
            $qty    = $this->cleanNumber($row['total_do_qty_car'] ?? null);

            if (!empty($tujuan) && $qty !== null) {

                $matches = LogistikPengiriman::where('no_shipment', $noShipment)
                    ->where('tujuan', $tujuan)
                    ->get();

                if ($matches->isEmpty()) {
                    $this->qtyNotFound[] = "{$noShipment} - {$tujuan}";
                } elseif ($matches->count() > 1) {
                    $this->qtyAmbiguous[] = "{$noShipment} - {$tujuan} ({$matches->count()} baris)";
                } else {
                    $matches->first()->update(['total_do_qty_car' => $qty]);
                    $this->qtyUpdated++;
                }
            } else {
                $this->qtySkipped++;
            }

            // =====================================================
            // 2) ACT PGI DATE (kunci: no_shipment saja)
            //    Independen — hanya jalan kalau kolom ini ADA & terisi.
            // =====================================================
            $pgiDate = $this->convertDate($row['act_pgi_date'] ?? null);

            if ($pgiDate !== null && !isset($this->pgiProcessed[$noShipment])) {

                $affected = LogistikPengiriman::where('no_shipment', $noShipment)
                    ->update(['act_pgi_date' => $pgiDate]);

                if ($affected === 0) {
                    $this->pgiNotFound[] = $noShipment;
                } else {
                    $this->pgiUpdated += $affected;
                }

                $this->pgiProcessed[$noShipment] = true;

            } elseif ($pgiDate === null) {
                $this->pgiSkipped++;
            }

            // =====================================================
            // 3) KUBIKASI / TONASE / TOTAL KUBIK / TOTAL TONASE
            //    (kunci: no_shipment saja, level-shipment)
            //    Independen — hanya jalan kalau MINIMAL SATU dari
            //    4 kolom ini ADA & terisi di baris ini.
            // =====================================================
            if (!isset($this->kubikTonaseProcessed[$noShipment])) {

                $kubikasiNew    = $this->cleanPersen($row['kubikasi'] ?? null);
                $tonaseNew      = $this->cleanPersen($row['tonase'] ?? null);
                $totalKubikNew  = $this->cleanDecimal($row['total_kubik'] ?? null);
                $totalTonaseNew = $this->cleanDecimal($row['total_tonase'] ?? null);

                $adaInputBaru = $kubikasiNew !== null || $tonaseNew !== null
                    || $totalKubikNew !== null || $totalTonaseNew !== null;

                if ($adaInputBaru) {

                    $this->kubikTonaseProcessed[$noShipment] = true;

                    // ambil 1 baris existing sebagai representasi nilai
                    // yang SEKARANG ada di DB untuk shipment ini
                    $existing = LogistikPengiriman::where('no_shipment', $noShipment)->first();

                    if (!$existing) {
                        $this->kubikTonaseNotFound[] = $noShipment;
                    } else {

                        // gabung: kolom yang TIDAK diisi di Excel kali ini
                        // -> pakai nilai lama yang sudah ada di DB
                        $kubikasiFinal    = $kubikasiNew    ?? $existing->kubikasi;
                        $tonaseFinal      = $tonaseNew      ?? $existing->tonase;
                        $totalKubikFinal  = $totalKubikNew  ?? $existing->total_kubik;
                        $totalTonaseFinal = $totalTonaseNew ?? $existing->total_tonase;

                        $hasilKubik = ($kubikasiFinal !== null && $kubikasiFinal > 0 && $totalKubikFinal !== null)
                            ? round(($totalKubikFinal / $kubikasiFinal) * 100, 2)
                            : null;

                        $hasilTonase = ($tonaseFinal !== null && $tonaseFinal > 0 && $totalTonaseFinal !== null)
                            ? round(($totalTonaseFinal / $tonaseFinal) * 100, 2)
                            : null;

                        $pengirimanOptimal = null;
                        if ($hasilKubik !== null || $hasilTonase !== null) {
                            $pengirimanOptimal = (($hasilKubik >= 85) || ($hasilTonase >= 85))
                                ? 'OPTIMAL'
                                : 'TIDAK OPTIMAL';
                        }

                        // hanya kolom yang BENAR-BENAR diisi di Excel kali ini
                        // yang ditulis ulang ke DB untuk field mentahnya;
                        // hasil_kubik/hasil_tonase/pengiriman_optimal selalu
                        // ikut ditulis ulang karena itu turunan dari nilai
                        // gabungan (lama + baru) yang paling up to date.
                        $payload = array_filter([
                            'kubikasi'     => $kubikasiNew,
                            'tonase'       => $tonaseNew,
                            'total_kubik'  => $totalKubikNew,
                            'total_tonase' => $totalTonaseNew,
                        ], fn($v) => $v !== null);

                        $payload['hasil_kubik']        = $hasilKubik;
                        $payload['hasil_tonase']       = $hasilTonase;
                        $payload['pengiriman_optimal'] = $pengirimanOptimal;

                        $affected = LogistikPengiriman::where('no_shipment', $noShipment)
                            ->update($payload);

                        $this->kubikTonaseUpdated += $affected;
                    }

                } else {
                    $this->kubikTonaseSkipped++;
                }
            }
        }
    }

    // ================= HELPERS =================

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

    private function cleanPersen($value)
    {
        if ($value === null || $value === '' || $value == '-') return null;

        $value = str_replace('%', '', (string) $value);
        $value = str_replace(',', '.', $value);
        $value = preg_replace('/[^0-9.]/', '', $value);

        if (!is_numeric($value)) return null;

        $value = (float) $value;
        if ($value < 0) $value = 0;
        if ($value > 100) $value = 100;

        return round($value, 2);
    }

    private function cleanDecimal($value): ?float
    {
        if ($value === null || $value === '' || $value === '-') return null;
        if (is_numeric($value)) return (float) $value;

        $value = trim((string) $value);

        if (preg_match('/^\d+,\d+$/', $value)) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
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