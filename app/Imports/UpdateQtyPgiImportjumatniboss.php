<?php

namespace App\Imports;

use App\Models\LogistikPengiriman;
use App\Models\TarifPengiriman;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class UpdateQtyPgiImport implements ToCollection, WithHeadingRow, WithEvents, WithCalculatedFormulas
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

    // ===== hasil proses GENERAL (kunci: no_shipment + tujuan) =====
    private int $generalCreated = 0;
    private int $generalUpdated = 0;
    private int $generalUnchanged = 0;
    private array $generalAmbiguous = [];
    private int $generalSkipped = 0;

    // ===== hasil recalc biaya_kirim/kubikasi/tonase dari tarif_pengiriman =====
    private int $biayaKirimUpdated = 0;
    private array $tarifNotFound = [];
    private array $tarifAmbiguous = [];

    // dedup supaya field level-shipment tidak diproses berkali-kali
    private array $pgiProcessed = [];
    private array $kubikTonaseProcessed = [];

    // no_shipment yang tersentuh import ini -> dipakai buat recalc di AfterImport
    private array $touchedShipments = [];

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

    public function getGeneralCreated(): int { return $this->generalCreated; }
    public function getGeneralUpdated(): int { return $this->generalUpdated; }
    public function getGeneralUnchanged(): int { return $this->generalUnchanged; }
    public function getGeneralAmbiguous(): array { return $this->generalAmbiguous; }
    public function getGeneralSkipped(): int { return $this->generalSkipped; }

    public function getBiayaKirimUpdated(): int { return $this->biayaKirimUpdated; }
    public function getTarifNotFound(): array { return $this->tarifNotFound; }
    public function getTarifAmbiguous(): array { return $this->tarifAmbiguous; }

    // ================= EVENTS =================

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                $this->recalcShipments();
            },
        ];
    }

    // ================= MAIN =================

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            $noShipment = $this->cleanText($row['no_shipment'] ?? null);

            if (empty($noShipment)) {
                $this->qtySkipped++;
                $this->pgiSkipped++;
                $this->kubikTonaseSkipped++;
                $this->generalSkipped++;
                continue;
            }

            $this->touchedShipments[(string) $noShipment] = true;

            $tujuan = $this->cleanText($row['tujuan'] ?? null);

            // =====================================================
            // 0) GENERAL UPSERT (kunci: no_shipment + tujuan)
            //    Dijalankan paling awal supaya row baru langsung
            //    ketemu oleh blok qty/pgi/kubikasi di bawah.
            // =====================================================
            if (empty($tujuan)) {
                $this->generalSkipped++;
            } else {
                $this->processGeneral($row, $noShipment, $tujuan);
            }

            // =====================================================
            // 1) TOTAL DO QTY CAR (kunci: no_shipment + tujuan)
            // =====================================================
            $qty = $this->cleanNumber($row['total_do_qty_car'] ?? null);

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

            
// 3) TOTAL KUBIK / TOTAL TONASE (kunci: no_shipment + tujuan)
//    Beda per tujuan, jadi ditulis PER BARIS.
//    Kapasitas (kubikasi/tonase) selalu dari tarif_pengiriman,
//    tidak dibaca dari Excel.
// =====================================================
$totalKubikNew  = $this->cleanDecimal($row['total_kubik'] ?? null);
$totalTonaseNew = $this->cleanDecimal($row['total_tonase'] ?? null);

if ($totalKubikNew === null && $totalTonaseNew === null) {
    $this->kubikTonaseSkipped++;
} elseif (empty($tujuan)) {
    $this->kubikTonaseNotFound[] = "{$noShipment} (tujuan kosong, total kubik/tonase dilewati)";
} else {
    $payload = array_filter([
        'total_kubik'  => $totalKubikNew,
        'total_tonase' => $totalTonaseNew,
    ], fn($v) => $v !== null);

    $affected = LogistikPengiriman::where('no_shipment', $noShipment)
        ->where('tujuan', $tujuan)
        ->update($payload);

    if ($affected === 0) {
        $this->kubikTonaseNotFound[] = "{$noShipment} - {$tujuan}";
    } else {
        $this->kubikTonaseUpdated += $affected;
    }
}
        }
    }

    // ================= GENERAL UPSERT =================

    private function processGeneral($row, string $noShipment, string $tujuan): void
    {
        $matches = LogistikPengiriman::where('no_shipment', $noShipment)
            ->where('tujuan', $tujuan)
            ->get();

        $ekpedisiNew          = $this->cleanText($row['ekpedisi'] ?? null);
        $routeNew             = $this->cleanText($row['route'] ?? null);
        $mobilNew             = $this->cleanText($row['mobil'] ?? null);
        $pulauNew             = $this->cleanText($row['pulau'] ?? null);
        $areaNew              = $this->cleanText($row['area'] ?? null);
        $viaKirimNew          = $this->cleanText($row['via_kirim'] ?? null);
        $kategoriEkspedisiNew = $this->cleanText($row['kategori_ekspedisi'] ?? null);
        $nilaiMuatanNew       = $this->cleanDecimal(
            $row['nilai_muatan_rp'] ?? $row['nilai_muatan'] ?? null
        );

        if ($matches->count() > 1) {
            $this->generalAmbiguous[] = "{$noShipment} - {$tujuan} ({$matches->count()} baris)";
            return;
        }

        if ($matches->isEmpty()) {
            // ===== INSERT BARU =====
            $tarifRow = $this->findTarifRow($ekpedisiNew, $routeNew, $mobilNew);

            LogistikPengiriman::create(array_filter([
                'no_shipment'        => $noShipment,
                'tujuan'             => $tujuan,
                'ekpedisi'           => $tarifRow->ekpedisi ?? $ekpedisiNew,
                'route'              => $routeNew,
                'mobil'              => $tarifRow->mobil ?? $mobilNew,
                'pulau'              => $pulauNew,
                'area'               => $areaNew,
                'via_kirim'          => $viaKirimNew,
                'kategori_ekspedisi' => $kategoriEkspedisiNew,
                'nilai_muatan'       => $nilaiMuatanNew,
                'biaya_kirim'        => $tarifRow ? $this->cleanRupiah($tarifRow->biaya_kirim) : null,
                'kubikasi'           => $tarifRow ? $this->cleanDecimal($tarifRow->kubikasi) : null,
                'tonase'             => $tarifRow ? $this->cleanDecimal($tarifRow->tonase) : null,
                'create_tgl'         => Carbon::now(),
            ], fn($v) => $v !== null));

            $this->generalCreated++;

            // baris baru di shipment yang sudah punya baris lain ->
            // samakan kapasitas dengan baris lama supaya konsisten
            if ($tarifRow) {
                $this->syncKapasitasShipment($noShipment, $tarifRow);
            }
            return;
        }

        // ===== SUDAH ADA -> UPDATE HANYA YANG BEDA =====
        $existing = $matches->first();

        $candidates = [
            'ekpedisi'           => $ekpedisiNew,
            'route'              => $routeNew,
            'mobil'              => $mobilNew,
            'pulau'              => $pulauNew,
            'area'               => $areaNew,
            'via_kirim'          => $viaKirimNew,
            'kategori_ekspedisi' => $kategoriEkspedisiNew,
            'nilai_muatan'       => $nilaiMuatanNew,
        ];

        $payload = [];
        foreach ($candidates as $column => $newValue) {
            if ($newValue === null) {
                continue;
            }
            if (!$this->valuesEqual($existing->{$column}, $newValue)) {
                $payload[$column] = $newValue;
            }
        }

        // =====================================================
        // RECALC biaya_kirim + kubikasi + tonase (+ betulin
        // ekpedisi/mobil yang kepotong) HANYA kalau ekpedisi
        // ATAU mobil beda dari yang ada di DB sekarang.
        // =====================================================
        $ekpedisiBerubah = $ekpedisiNew !== null && !$this->valuesEqual($existing->ekpedisi, $ekpedisiNew);
        $mobilBerubah    = $mobilNew    !== null && !$this->valuesEqual($existing->mobil, $mobilNew);

        $tarifUntukSync = null;

        if ($ekpedisiBerubah || $mobilBerubah) {

            $ekpedisiFinal = $ekpedisiNew ?? $existing->ekpedisi;
            $routeFinal    = $routeNew    ?? $existing->route;
            $mobilFinal    = $mobilNew    ?? $existing->mobil;

            $tarifRow = $this->findTarifRow($ekpedisiFinal, $routeFinal, $mobilFinal);

            if ($tarifRow) {
                $tarifUntukSync = $tarifRow;

                $biayaKirimBaru = $this->cleanRupiah($tarifRow->biaya_kirim);
                if (!$this->valuesEqual($existing->biaya_kirim, $biayaKirimBaru)) {
                    $payload['biaya_kirim'] = $biayaKirimBaru;
                    $this->biayaKirimUpdated++;
                }
                if (!$this->valuesEqual($existing->ekpedisi, $tarifRow->ekpedisi)) {
                    $payload['ekpedisi'] = $tarifRow->ekpedisi;
                }
                if (!$this->valuesEqual($existing->mobil, $tarifRow->mobil)) {
                    $payload['mobil'] = $tarifRow->mobil;
                }

                $kubikasiTarif = $this->cleanDecimal($tarifRow->kubikasi);
                $tonaseTarif   = $this->cleanDecimal($tarifRow->tonase);

                if ($kubikasiTarif !== null && !$this->valuesEqual($existing->kubikasi, $kubikasiTarif)) {
                    $payload['kubikasi'] = $kubikasiTarif;
                }
                if ($tonaseTarif !== null && !$this->valuesEqual($existing->tonase, $tonaseTarif)) {
                    $payload['tonase'] = $tonaseTarif;
                }
            }
            // kalau $tarifRow null (not found / ambiguous): ekpedisi/mobil
            // tetap versi mentah Excel, biaya_kirim/kubikasi/tonase tidak disentuh.
        }

        if (!empty($payload)) {
            $existing->update($payload);
            $this->generalUpdated++;
        } else {
            $this->generalUnchanged++;
        }

        // samakan kapasitas ke semua baris di shipment yang sama
        // (recalc pakai MAX(kubikasi)/MAX(tonase) per shipment)
        if ($tarifUntukSync) {
            $this->syncKapasitasShipment($noShipment, $tarifUntukSync);
        }
    }

    /**
     * Sebarkan kubikasi & tonase dari tarif ke semua baris dalam shipment.
     */
   private function syncKapasitasShipment(string $noShipment, object $tarifRow): void
{
    $data = array_filter([
        'ekpedisi'    => $tarifRow->ekpedisi,
        'mobil'       => $tarifRow->mobil,
        'biaya_kirim' => $this->cleanRupiah($tarifRow->biaya_kirim),
        'kubikasi'    => $this->cleanDecimal($tarifRow->kubikasi),
        'tonase'      => $this->cleanDecimal($tarifRow->tonase),
    ], fn($v) => $v !== null);

    if (!empty($data)) {
        LogistikPengiriman::where('no_shipment', $noShipment)->update($data);
    }
}

    // ================= RECALC SETELAH IMPORT =================

    /**
     * Hitung ulang cr, hasil_kubik, hasil_tonase, pengiriman_optimal
     * untuk shipment yang ada di file Excel ini (bukan seluruh tabel).
     * Dihitung dari SUM seluruh baris dalam satu no_shipment.
     */
    private function recalcShipments(): void
    {
        $shipments = array_map('strval', array_keys($this->touchedShipments));

        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $bindings = array_merge($chunk, $chunk);

            // ---- CR ----
            DB::update("
                UPDATE logistik_pengiriman lp
                JOIN (
                    SELECT no_shipment,
                           MAX(biaya_kirim) AS biaya,
                           SUM(COALESCE(nilai_muatan, 0)) AS muatan
                    FROM logistik_pengiriman
                    WHERE no_shipment IN ($in)
                    GROUP BY no_shipment
                ) x ON lp.no_shipment = x.no_shipment
                SET lp.cr = IF(
                    x.muatan = 0 OR COALESCE(lp.nilai_muatan, 0) <= 0,
                    0,
                    ROUND((lp.nilai_muatan * COALESCE(x.biaya, 0)) / (x.muatan * x.muatan) * 100, 4)
                )
                WHERE lp.no_shipment IN ($in)
            ", $bindings);

            // ---- HASIL KUBIK / HASIL TONASE / PENGIRIMAN OPTIMAL ----
            DB::update("
                UPDATE logistik_pengiriman lp
                JOIN (
                    SELECT no_shipment,
                           SUM(COALESCE(total_kubik, 0))  AS sum_kubik,
                           SUM(COALESCE(total_tonase, 0)) AS sum_tonase,
                           MAX(kubikasi) AS kubikasi,
                           MAX(tonase)   AS tonase
                    FROM logistik_pengiriman
                    WHERE no_shipment IN ($in)
                    GROUP BY no_shipment
                ) x ON lp.no_shipment = x.no_shipment
                SET
                    lp.hasil_kubik = CASE
                        WHEN x.kubikasi > 0 THEN ROUND(x.sum_kubik / x.kubikasi * 100, 2)
                        ELSE NULL
                    END,
                    lp.hasil_tonase = CASE
                        WHEN x.tonase > 0 THEN ROUND(x.sum_tonase / x.tonase * 100, 2)
                        ELSE NULL
                    END,
                    lp.pengiriman_optimal = CASE
                        WHEN (x.kubikasi > 0 AND (x.sum_kubik  / x.kubikasi * 100) >= 85)
                          OR (x.tonase   > 0 AND (x.sum_tonase / x.tonase   * 100) >= 85)
                            THEN 'OPTIMAL'
                        WHEN x.kubikasi > 0 OR x.tonase > 0
                            THEN 'TIDAK OPTIMAL'
                        ELSE NULL
                    END
                WHERE lp.no_shipment IN ($in)
            ", $bindings);
        }
    }

    // ================= TARIF LOOKUP =================

    /**
     * Cache seluruh isi tarif_pengiriman (sekali per proses import),
     * supaya matching bisa dilakukan di PHP dengan teks yang sudah dinormalisasi.
     */
    private static ?Collection $tarifCache = null;

    private function loadTarifCache(): Collection
    {
        if (self::$tarifCache === null) {
            self::$tarifCache = TarifPengiriman::select(
                'ekpedisi', 'route', 'mobil', 'biaya_kirim', 'kubikasi', 'tonase'
            )->get();
        }
        return self::$tarifCache;
    }

    /**
     * Normalisasi teks untuk KEPERLUAN MATCHING SAJA (bukan buat disimpan).
     */
    private function normalizeKey(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));
        $value = preg_replace('/\s*-\s*/', '-', $value);
        return strtolower($value);
    }

    /**
     * Cari 1 baris tarif_pengiriman berdasarkan ekpedisi + route + mobil.
     * Exact match dulu, lalu prefix match untuk mobil yang kepotong.
     * - Tidak ketemu    -> null (dicatat not found)
     * - Ketemu > 1      -> null (dicatat ambiguous)
     * - Ketemu persis 1 -> objek tarif
     */
    private function findTarifRow(?string $ekpedisi, ?string $route, ?string $mobil): ?object
    {
        if (empty($ekpedisi) || empty($route) || empty($mobil)) {
            return null;
        }

        $ekpedisiKey = $this->normalizeKey($ekpedisi);
        $routeKey    = $this->normalizeKey($route);
        $mobilKey    = $this->normalizeKey($mobil);
        $key = "{$ekpedisi} | {$route} | {$mobil}";

        $tarif = $this->loadTarifCache()->filter(function ($t) use ($ekpedisiKey, $routeKey, $mobilKey) {
            return $this->normalizeKey($t->ekpedisi) === $ekpedisiKey
                && $this->normalizeKey($t->route) === $routeKey
                && $this->normalizeKey($t->mobil) === $mobilKey;
        });

        if ($tarif->isEmpty()) {
            $tarif = $this->loadTarifCache()->filter(function ($t) use ($ekpedisiKey, $routeKey, $mobilKey) {
                $tarifMobilKey = $this->normalizeKey($t->mobil);
                return $this->normalizeKey($t->ekpedisi) === $ekpedisiKey
                    && $this->normalizeKey($t->route) === $routeKey
                    && (str_starts_with($tarifMobilKey, $mobilKey) || str_starts_with($mobilKey, $tarifMobilKey));
            });
        }

        if ($tarif->isEmpty()) {
            if (!in_array($key, $this->tarifNotFound, true)) {
                $this->tarifNotFound[] = $key;
            }
            return null;
        }

        if ($tarif->count() > 1) {
            if (!in_array($key, $this->tarifAmbiguous, true)) {
                $this->tarifAmbiguous[] = "{$key} ({$tarif->count()} baris)";
            }
            return null;
        }

        return $tarif->first();
    }

    /**
     * Bandingkan nilai lama vs baru, toleran terhadap tipe data.
     */
    private function valuesEqual($old, $new): bool
    {
        if ($old === null && $new === null) {
            return true;
        }
        if ($old === null || $new === null) {
            return false;
        }
        if (is_numeric($old) && is_numeric($new)) {
            return abs((float) $old - (float) $new) < 0.0001;
        }
        return trim((string) $old) === trim((string) $new);
    }

    // ================= HELPERS =================

    /**
     * Bersihkan nilai biaya_kirim format "Rp 33.000.000" / "33,000,000"
     * jadi angka murni.
     */
    private function cleanRupiah($value): ?float
    {
        if ($value === null || $value === '' || $value == '-') return null;
        if (is_int($value) || is_float($value)) return (float) $value;

        $value = str_replace(['Rp', 'rp', ' '], '', (string) $value);
        $value = str_replace(['.', ','], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function cleanText($value)
    {
        if ($value === null || $value === '' || $value == '-') return null;
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        return preg_replace('/\s+/', ' ', trim($value));
    }

    private function cleanNumber($value)
    {
        if ($value === null || $value === '' || $value == '-') return null;
        $value = preg_replace('/[^0-9]/', '', (string) $value);
        return $value === '' ? null : (int) $value;
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