<?php

namespace App\Imports;

use App\Models\LogistikPengiriman;
use App\Models\TarifPengiriman;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Collection;
use Carbon\Carbon;

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
    // TIDAK DIUBAH — tetap ambil dari kolom kubikasi/tonase di Excel.
    private int $kubikTonaseUpdated = 0;
    private array $kubikTonaseNotFound = [];
    private int $kubikTonaseSkipped = 0;

    // ===== hasil proses GENERAL (ekpedisi/route/mobil/pulau/area/via_kirim/
    //       nilai_muatan/kategori_ekspedisi) — kunci: no_shipment + tujuan =====
    private int $generalCreated = 0;      // row baru dibuat (no_shipment+tujuan belum ada)
    private int $generalUpdated = 0;      // row lama, ada field yang beda -> diupdate
    private int $generalUnchanged = 0;    // row lama, tidak ada field yang beda
    private array $generalAmbiguous = []; // no_shipment+tujuan ketemu >1 baris, di-skip
    private int $generalSkipped = 0;      // tujuan kosong, jadi gak bisa dipakai sbg key

    // ===== hasil recalc biaya_kirim dari tarif_pengiriman
    //       (kunci: ekpedisi + route + mobil, HANYA kalau ekpedisi/mobil berubah) =====
    private int $biayaKirimUpdated = 0;
    private array $tarifNotFound = [];    // kombinasi ekpedisi+route+mobil tidak ada di tarif_pengiriman
    private array $tarifAmbiguous = [];   // kombinasi ketemu >1 baris tarif, di-skip

    // dedup supaya field level-shipment tidak diproses berkali-kali
    // per no_shipment yang muncul di banyak baris Excel
    private array $pgiProcessed = [];
    private array $kubikTonaseProcessed = [];

    // ================= GETTERS (lama) =================
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

    // ================= GETTERS (baru) =================
    public function getGeneralCreated(): int { return $this->generalCreated; }
    public function getGeneralUpdated(): int { return $this->generalUpdated; }
    public function getGeneralUnchanged(): int { return $this->generalUnchanged; }
    public function getGeneralAmbiguous(): array { return $this->generalAmbiguous; }
    public function getGeneralSkipped(): int { return $this->generalSkipped; }

    public function getBiayaKirimUpdated(): int { return $this->biayaKirimUpdated; }
    public function getTarifNotFound(): array { return $this->tarifNotFound; }
    public function getTarifAmbiguous(): array { return $this->tarifAmbiguous; }

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

            $tujuan = $this->cleanText($row['tujuan'] ?? null);

            // =====================================================
            // 0) GENERAL UPSERT (kunci: no_shipment + tujuan)
            //    - Kombinasi belum ada -> INSERT baru (create_tgl = sekarang).
            //    - Sudah ada -> update HANYA field yang beda dari Excel.
            //      Field yang tidak diisi di Excel kali ini TIDAK disentuh
            //      (data manual di web aman).
            //    - Ketemu >1 baris (ambigu) -> skip, dicatat.
            //    biaya_kirim HANYA dihitung ulang (lookup ke tarif_pengiriman)
            //    kalau ekpedisi ATAU mobil di baris ini beda dari yang ada
            //    di DB sekarang. Kalau kombinasi ekpedisi/mobil gak berubah,
            //    biaya_kirim TIDAK disentuh sama sekali.
            //    Kolom tanggal (rencana_kirim, tanggal_dpt_unit, dll)
            //    SENGAJA TIDAK diproses di sini karena diisi manual di web.
            //    Dijalankan PALING AWAL supaya kalau row baru dibuat di sini,
            //    blok qty/pgi/kubikasi di bawah langsung menemukan row itu.
            // =====================================================
            if (empty($tujuan)) {
                $this->generalSkipped++;
            } else {
                $this->processGeneral($row, $noShipment, $tujuan);
            }

            // =====================================================
            // 1) TOTAL DO QTY CAR (kunci: no_shipment + tujuan)
            //    Independen — hanya jalan kalau kolom ini ADA & terisi.
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
            //    TIDAK DIUBAH — tetap logic lama, ambil dari Excel.
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

                    $existing = LogistikPengiriman::where('no_shipment', $noShipment)->first();

                    if (!$existing) {
                        $this->kubikTonaseNotFound[] = $noShipment;
                    } else {

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

    // ================= LOGIC BARU: GENERAL UPSERT =================

    private function processGeneral($row, string $noShipment, string $tujuan): void
    {
        $matches = LogistikPengiriman::where('no_shipment', $noShipment)
            ->where('tujuan', $tujuan)
            ->get();

        // nilai baru dari Excel (null kalau kolom kosong / gak ada)
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
            // no_shipment + tujuan belum pernah ada -> tambah data baru.
            // Row baru -> selalu lookup tarif dari kombinasi yang diisi di Excel.
            // Kalau ketemu (exact atau lewat prefix-match karena kepotong),
            // ekpedisi/mobil yang DISIMPAN dibetulin jadi versi lengkap dari
            // tarif_pengiriman, bukan versi kepotong dari Excel.
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
                'create_tgl'         => Carbon::now(),
            ], fn($v) => $v !== null));

            $this->generalCreated++;
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
            // kolom yang tidak diisi di Excel kali ini -> jangan disentuh
            if ($newValue === null) {
                continue;
            }
            // hanya ditulis kalau nilainya benar-benar beda dari yang di DB
            if (!$this->valuesEqual($existing->{$column}, $newValue)) {
                $payload[$column] = $newValue;
            }
        }

        // =====================================================
        // RECALC biaya_kirim (+ betulin ekpedisi/mobil yang kepotong):
        // HANYA kalau ekpedisi ATAU mobil di baris ini beda dari yang ada
        // di DB sekarang. Kalau kombinasi gak berubah (Excel gak isi
        // kolomnya, atau isinya sama persis), biaya_kirim/ekpedisi/mobil
        // TIDAK disentuh sama sekali.
        // =====================================================
        $ekpedisiBerubah = $ekpedisiNew !== null && !$this->valuesEqual($existing->ekpedisi, $ekpedisiNew);
        $mobilBerubah    = $mobilNew    !== null && !$this->valuesEqual($existing->mobil, $mobilNew);

        if ($ekpedisiBerubah || $mobilBerubah) {

            $ekpedisiFinal = $ekpedisiNew ?? $existing->ekpedisi;
            $routeFinal    = $routeNew    ?? $existing->route;
            $mobilFinal    = $mobilNew    ?? $existing->mobil;

            $tarifRow = $this->findTarifRow($ekpedisiFinal, $routeFinal, $mobilFinal);

            if ($tarifRow) {
                // ketemu (exact atau prefix-match) -> pakai versi LENGKAP
                // dari tarif_pengiriman, gantikan versi kepotong dari Excel
                // yang tadi sudah dimasukkan ke $payload (kalau ada).
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
            }
            // kalau $tarifRow null (not found / ambiguous), biarkan payload
            // apa adanya: ekpedisi/mobil tetap kesimpan versi mentah dari
            // Excel (dari blok candidates di atas), biaya_kirim tidak disentuh.
        }

        if (!empty($payload)) {
            $existing->update($payload);
            $this->generalUpdated++;
        } else {
            $this->generalUnchanged++;
        }
    }

    /**
     * Cache seluruh isi tarif_pengiriman (sekali per proses import),
     * supaya matching bisa dilakukan di PHP dengan teks yang sudah
     * dinormalisasi (bukan exact-match SQL yang gampang gagal gara-gara
     * whitespace/non-breaking-space tersembunyi dari copy-paste Excel).
     */
    private static ?Collection $tarifCache = null;

    private function loadTarifCache(): Collection
    {
        if (self::$tarifCache === null) {
            self::$tarifCache = TarifPengiriman::select('ekpedisi', 'route', 'mobil', 'biaya_kirim')->get();
        }
        return self::$tarifCache;
    }

    /**
     * Normalisasi teks untuk KEPERLUAN MATCHING SAJA (bukan buat disimpan):
     * - non-breaking space (\xC2\xA0, sering nempel dari copy-paste Excel/PDF) -> spasi biasa
     * - rapikan spasi ganda & spasi di ujung
     * - lowercase, supaya "RUKMA PADAYA TRANS" == "Rukma Padaya Trans"
     */
    private function normalizeKey(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));
        return strtolower($value);
    }

    /**
     * Cari 1 baris tarif_pengiriman berdasarkan kombinasi
     * ekpedisi + route + mobil (tanpa cek valid_from/valid_to), dicocokkan
     * dengan teks yang sudah dinormalisasi supaya tahan terhadap perbedaan
     * spasi/kapitalisasi/non-breaking-space, DAN toleran terhadap nilai
     * mobil yang kepotong (mis. "Contnr 20 Ft Re" -> dicocokkan sebagai
     * awalan dari "Contnr 20 Ft Reefer" di tarif_pengiriman).
     * Mengembalikan objek tarif LENGKAP (bukan cuma biaya_kirim) supaya
     * ekpedisi/mobil yang kepotong bisa dibetulin jadi versi master.
     * - Tidak ketemu    -> null
     * - Ketemu > 1      -> null + dicatat sebagai ambiguous
     * - Ketemu persis 1 -> objek tarif tsb
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

        // 1) coba exact match dulu (ekpedisi + route + mobil persis sama)
        $tarif = $this->loadTarifCache()->filter(function ($t) use ($ekpedisiKey, $routeKey, $mobilKey) {
            return $this->normalizeKey($t->ekpedisi) === $ekpedisiKey
                && $this->normalizeKey($t->route) === $routeKey
                && $this->normalizeKey($t->mobil) === $mobilKey;
        });

        // 2) kalau gak ketemu, coba prefix match buat mobil (menangani nilai
        //    mobil yang kepotong di data lama, mis. "Contnr 20 Ft Re" vs
        //    "Contnr 20 Ft Reefer" di tarif_pengiriman)
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
     * Bandingkan nilai lama vs baru dengan toleran terhadap tipe data
     * (string vs numeric vs null) supaya tidak dianggap "beda" gara-gara
     * "1094388" vs 1094388.00 misalnya.
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

    // ================= HELPERS (lama) =================

    /**
     * Bersihkan nilai biaya_kirim dari tarif_pengiriman yang formatnya
     * "Rp 33.000.000" / "33,000,000" (ada "Rp", spasi, titik/koma ribuan)
     * jadi angka murni. (float) langsung akan berhenti di karakter non-angka
     * pertama (mis. "33,000,000" -> 33 doang), makanya perlu dibersihin dulu.
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
        // non-breaking space (sering nempel dari copy-paste Excel/PDF) -> spasi biasa
        $value = str_replace("\xC2\xA0", ' ', $value);
        // rapikan spasi ganda & spasi di ujung
        return preg_replace('/\s+/', ' ', trim($value));
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