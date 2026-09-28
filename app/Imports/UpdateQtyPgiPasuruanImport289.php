<?php

namespace App\Imports;

use App\Models\LogistikPengirimanPasuruan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Update parsial data Pasuruan dari Excel:
 *   1) Total DO Qty            (kunci: no_shipment + tujuan)
 *   2) Act PGI Date            (kunci: no_shipment)
 *   3) Kubikasi / Tonase / Total Kubik / Total Tonase (kunci: no_shipment)
 *
 * Header Excel dibaca fleksibel: dengan atau tanpa suffix "_pasuruan".
 * Kolom yang kosong di Excel TIDAK menimpa data lama.
 */
class UpdateQtyPgiPasuruanImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    private const TABLE = 'logistik_pengiriman_pasuruan';

    // ===== hasil proses total_do (kunci: no_shipment + tujuan) =====
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

    // daftar kolom yang benar-benar ada di tabel (supaya update tidak
    // error "Unknown column" kalau ada kolom hasil_* yang belum dibuat)
    private array $existingColumns = [];

    public function __construct()
    {
        $this->existingColumns = array_flip(Schema::getColumnListing(self::TABLE));
    }

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
            $row = $row->toArray();

            $noShipment = $this->cleanText($this->pick($row, [
                'no_shipment_pasuruan', 'no_shipment',
            ]));

            if (empty($noShipment)) {
                $this->qtySkipped++;
                $this->pgiSkipped++;
                $this->kubikTonaseSkipped++;
                continue;
            }

            // =====================================================
            // 1) TOTAL DO QTY (kunci: no_shipment + tujuan)
            //    Independen: hanya jalan kalau kolom ini ADA & terisi.
            // =====================================================
            $tujuan = $this->cleanText($this->pick($row, [
                'tujuan_pasuruan', 'tujuan',
            ]));

            $qty = $this->cleanNumber($this->pick($row, [
                'total_do_qty_car_pasuruan',
                'total_do_qty_car',
                'total_do_pasuruan',
                'total_do',
            ]));

            if (!empty($tujuan) && $qty !== null) {

                $matches = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)
                    ->where('tujuan_pasuruan', $tujuan)
                    ->get();

                if ($matches->isEmpty()) {
                    $this->qtyNotFound[] = "{$noShipment} - {$tujuan}";
                } elseif ($matches->count() > 1) {
                    $this->qtyAmbiguous[] = "{$noShipment} - {$tujuan} ({$matches->count()} baris)";
                } else {
                    $matches->first()->update(['total_do_pasuruan' => $qty]);
                    $this->qtyUpdated++;
                }
            } else {
                $this->qtySkipped++;
            }

            // =====================================================
            // 2) ACT PGI DATE (kunci: no_shipment saja)
            //    Independen: hanya jalan kalau kolom ini ADA & terisi.
            // =====================================================
            $pgiDate = $this->convertDate($this->pick($row, [
                'act_pgi_date_pasuruan', 'act_pgi_date',
            ]));

            if ($pgiDate !== null && !isset($this->pgiProcessed[$noShipment])) {

                $affected = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)
                    ->update(['act_pgi_date_pasuruan' => $pgiDate]);

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
            //    Independen: hanya jalan kalau MINIMAL SATU dari
            //    4 kolom ini ADA & terisi di baris ini.
            // =====================================================
            if (!isset($this->kubikTonaseProcessed[$noShipment])) {

                $kubikasiNew = $this->cleanPersen($this->pick($row, [
                    'kubikasi_pasuruan', 'kubikasi',
                ]));
                $tonaseNew = $this->cleanPersen($this->pick($row, [
                    'tonase_pasuruan', 'tonase',
                ]));
                $totalKubikNew = $this->cleanDecimal($this->pick($row, [
                    'total_kubik_pasuruan', 'total_kubikasi_pasuruan',
                    'total_kubik', 'total_kubikasi',
                ]));
                $totalTonaseNew = $this->cleanDecimal($this->pick($row, [
                    'total_tonase_pasuruan', 'total_tonase',
                ]));

                $adaInputBaru = $kubikasiNew !== null || $tonaseNew !== null
                    || $totalKubikNew !== null || $totalTonaseNew !== null;

                if ($adaInputBaru) {

                    $this->kubikTonaseProcessed[$noShipment] = true;

                    // ambil 1 baris existing sebagai representasi nilai
                    // yang SEKARANG ada di DB untuk shipment ini
                    $existing = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)->first();

                    if (!$existing) {
                        $this->kubikTonaseNotFound[] = $noShipment;
                    } else {

                        // gabung: kolom yang TIDAK diisi di Excel kali ini
                        // -> pakai nilai lama yang sudah ada di DB
                        $kubikasiFinal    = $kubikasiNew    ?? $existing->kubikasi_pasuruan;
                        $tonaseFinal      = $tonaseNew      ?? $existing->tonase_pasuruan;
                        $totalKubikFinal  = $totalKubikNew  ?? $existing->total_kubik_pasuruan;
                        $totalTonaseFinal = $totalTonaseNew ?? $existing->total_tonase_pasuruan;

                        $hasilKubik = ($kubikasiFinal !== null && $kubikasiFinal > 0 && $totalKubikFinal !== null)
                            ? round(($totalKubikFinal / $kubikasiFinal) * 100, 2)
                            : null;

                        $hasilTonase = ($tonaseFinal !== null && $tonaseFinal > 0 && $totalTonaseFinal !== null)
                            ? round(($totalTonaseFinal / $tonaseFinal) * 100, 2)
                            : null;

                        $pengirimanOptimal = null;
                        if ($hasilKubik !== null || $hasilTonase !== null) {
                            $pengirimanOptimal = (($hasilKubik !== null && $hasilKubik >= 85)
                                || ($hasilTonase !== null && $hasilTonase >= 85))
                                ? 'OPTIMAL'
                                : 'TIDAK OPTIMAL';
                        }

                        // hanya kolom yang BENAR-BENAR diisi di Excel kali ini
                        // yang ditulis ulang untuk field mentahnya;
                        // hasil_kubik/hasil_tonase/pengiriman_optimal selalu
                        // ikut ditulis ulang karena turunan dari nilai
                        // gabungan (lama + baru) yang paling up to date.
                        $payload = array_filter([
                            'kubikasi_pasuruan'     => $kubikasiNew,
                            'tonase_pasuruan'       => $tonaseNew,
                            'total_kubik_pasuruan'  => $totalKubikNew,
                            'total_tonase_pasuruan' => $totalTonaseNew,
                        ], fn($v) => $v !== null);

                        $payload['hasil_kubik_pasuruan']        = $hasilKubik;
                        $payload['hasil_tonase_pasuruan']       = $hasilTonase;
                        $payload['pengiriman_optimal_pasuruan'] = $pengirimanOptimal;

                        // buang kolom yang tidak ada di tabel (anti "Unknown column")
                        $payload = array_intersect_key($payload, $this->existingColumns);

                        if (!empty($payload)) {
                            $affected = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment)
                                ->update($payload);

                            $this->kubikTonaseUpdated += $affected;
                        }
                    }

                } else {
                    $this->kubikTonaseSkipped++;
                }
            }
        }
    }

    // ================= HELPERS =================

    /**
     * Ambil nilai pertama yang terisi dari beberapa kemungkinan nama header.
     */
    private function pick(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== '-') {
                return $row[$k];
            }
        }
        return null;
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