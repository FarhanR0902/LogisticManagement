<?php

namespace App\Imports;

use App\Models\Kota;
use App\Models\DbKota;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class KotaImport implements ToCollection, WithHeadingRow
{
    private $imported = 0;
    private $skipped  = 0;

    public function getImportedCount(): int { return $this->imported; }
    public function getSkippedCount(): int  { return $this->skipped; }

    // Kolom master yang otomatis di-auto-fill berdasarkan tujuan
    private $masterFields = ['pic_toko', 'area_kecil', 'area_besar', 'alamat_customer', 'customer_id'];

    public function collection(Collection $rows)
    {
        // --------------------------------------------------------------------
        // PASS 0: Ambil data master (pic_toko, area_kecil, area_besar,
        // alamat_customer, customer_id) yang SUDAH ADA di database, per tujuan
        // --------------------------------------------------------------------
        $tujuanMasterFromDb = Kota::select('tujuan', ...$this->masterFields)
            ->whereNotNull('tujuan')
            ->get()
            ->groupBy('tujuan')
            ->map(function ($items) {
                // Ambil nilai pertama yang tidak kosong untuk masing-masing kolom master
                $result = [];
                foreach ($this->masterFields as $field) {
                    $found = $items->pluck($field)->first(function ($val) {
                        return $val !== null && $val !== '';
                    });
                    $result[$field] = $found;
                }
                return $result;
            });

        // Ambil data master dari tabel referensi dbkota (sumber master utama per tujuan)
        $tujuanMasterFromDbKota = DbKota::select('tujuan', ...$this->masterFields)
            ->whereNotNull('tujuan')
            ->get()
            ->groupBy('tujuan')
            ->map(function ($items) {
                $result = [];
                foreach ($this->masterFields as $field) {
                    $found = $items->pluck($field)->first(function ($val) {
                        return $val !== null && $val !== '';
                    });
                    $result[$field] = $found;
                }
                return $result;
            });

        // --------------------------------------------------------------------
        // PASS 1: Hitung TOTAL BARIS + LOCK alasan_pending per kombinasi no_shipment + tujuan
        //         + LOCK data master (pic_toko dkk) per tujuan, dalam file yang sedang diimport
        // --------------------------------------------------------------------
        $shipmentTokoCount    = [];
        $shipmentAlasanLocked = []; // key ('no_shipment_tujuan') => alasan_pending
        $tujuanMasterLocked   = []; // key (tujuan) => [pic_toko, area_kecil, area_besar, alamat_customer, customer_id]

        foreach ($rows as $row) {
            $noDo       = $this->cleanText($row['no_do'] ?? null);
            $noShipment = $this->cleanText($row['no_shipment'] ?? null);
            $tujuan     = $this->cleanText($row['tujuan'] ?? null);
            $alasan     = $this->cleanText($row['alasan_pending'] ?? null);

            if (empty($noDo) && empty($noShipment)) {
                continue;
            }

            if ($noShipment && $tujuan) {
                $key = $noShipment . '_' . $tujuan;

                if (!isset($shipmentTokoCount[$key])) {
                    $shipmentTokoCount[$key] = 0;
                }
                $shipmentTokoCount[$key]++;

                if ($alasan && !isset($shipmentAlasanLocked[$key])) {
                    $shipmentAlasanLocked[$key] = $alasan;
                }
            }

            // Lock data master per tujuan (dalam file yang sama)
            if ($tujuan) {
                if (!isset($tujuanMasterLocked[$tujuan])) {
                    $tujuanMasterLocked[$tujuan] = [];
                }
                foreach ($this->masterFields as $field) {
                    $val = $this->cleanText($row[$field] ?? null);
                    if ($val && empty($tujuanMasterLocked[$tujuan][$field])) {
                        $tujuanMasterLocked[$tujuan][$field] = $val;
                    }
                }
            }
        }

        // --------------------------------------------------------------------
        // PASS 2: Simpan data ke Database
        // --------------------------------------------------------------------
        foreach ($rows as $row) {
            $noDo       = $this->cleanText($row['no_do'] ?? null);
            $noShipment = $this->cleanText($row['no_shipment'] ?? null);

            if (empty($noDo) && empty($noShipment)) {
                $this->skipped++;
                continue;
            }

            $tujuan    = $this->cleanText($row['tujuan'] ?? null);
            $createOn  = $this->convertDate($row['create_on'] ?? null);
            $tglDo     = $this->convertDate($row['tgl_do'] ?? null);
            $kubikasi  = $this->cleanDecimal($row['kubikasi'] ?? null);
            $tonase    = $this->cleanDecimal($row['tonase'] ?? null);

            $jumlahToko = 0;
            if ($noShipment && $tujuan) {
                $key = $noShipment . '_' . $tujuan;
                $jumlahToko = $shipmentTokoCount[$key] ?? 0;
            } else {
                $jumlahToko = $this->cleanNumber($row['jumlah_toko'] ?? null) ?? 0;
            }

            $pengirimanOptimal = $this->hitungPengirimanOptimal($kubikasi, $tonase, $row['pengiriman_optimal'] ?? null);
            $waktuPengiriman   = $this->hitungWaktuPengiriman($createOn, $tglDo, $row['waktu_pengiriman'] ?? null);

            // ALASAN PENDING (locking per no_shipment+tujuan)
            $alasanPending = null;
            if ($noShipment && $tujuan) {
                $key = $noShipment . '_' . $tujuan;
                $alasanPending = $shipmentAlasanLocked[$key] ?? $this->cleanText($row['alasan_pending'] ?? null);
            } else {
                $alasanPending = $this->cleanText($row['alasan_pending'] ?? null);
            }

            // --------------------------------------------------------------------
            // DATA MASTER (pic_toko, area_kecil, area_besar, alamat_customer, customer_id)
            // Prioritas: nilai di baris ini -> locked dalam file (tujuan sama) ->
            //            data lama di tabel kota (tujuan sama) -> tabel master dbkota (tujuan sama)
            // --------------------------------------------------------------------
            $masterData = [];
            foreach ($this->masterFields as $field) {
                $fromRow    = $this->cleanText($row[$field] ?? null);
                $fromFile   = $tujuan ? ($tujuanMasterLocked[$tujuan][$field] ?? null) : null;
                $fromDb     = $tujuan ? ($tujuanMasterFromDb[$tujuan][$field] ?? null) : null;
                $fromDbKota = $tujuan ? ($tujuanMasterFromDbKota[$tujuan][$field] ?? null) : null;

                $masterData[$field] = $fromRow ?? $fromFile ?? $fromDb ?? $fromDbKota;
            }

            Kota::create([
                'kode_gudang'        => $this->cleanText($row['kode_gudang'] ?? null),
                'no_so'              => $this->cleanText($row['no_so'] ?? null),
                'no_do'              => $noDo,
                'create_on'          => $createOn,
                'tgl_do'             => $tglDo,
                'tanggal_kirim'      => $this->convertDate($row['tanggal_kirim'] ?? null),
                'customer_id'        => $masterData['customer_id'],
                'tujuan'             => $tujuan,
                'alamat_customer'    => $masterData['alamat_customer'],
                'area_kecil'         => $masterData['area_kecil'],
                'area_besar'         => $masterData['area_besar'],
                'kode_barang'        => $this->cleanText($row['kode_barang'] ?? null),
                'nama_barang'        => $this->cleanText($row['nama_barang'] ?? null),
                'qty_prdk'           => $this->cleanNumber($row['qty_prdk'] ?? null),
                'no_pol'             => $this->cleanText($row['no_pol'] ?? null),
                'nama_driver'        => $this->cleanText($row['nama_driver'] ?? null),
                'no_shipment'        => $noShipment,
                'EKPEDISI'           => $this->cleanText($row['ekpedisi'] ?? null),
                'jenis_mobil'        => $this->cleanText($row['jenis_mobil'] ?? null),
                'jenis_tonase'       => $this->cleanText($row['jenis_tonase'] ?? null),
                'Kubikasi'           => $kubikasi,
                'tonase'             => $tonase,
                'pengiriman_optimal' => $pengirimanOptimal,
                'jumlah_toko'        => $jumlahToko,
                'pic_toko'           => $masterData['pic_toko'],
                'pic_driver'         => $this->cleanText($row['pic_driver'] ?? null),
                'waktu_pengiriman'   => $waktuPengiriman,
                'alasan_pending'     => $alasanPending,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            $this->imported++;
        }
    }

    private function hitungPengirimanOptimal($kubikasi, $tonase, $defaultVal = null)
    {
        if ($kubikasi !== null || $tonase !== null) {
            $isKubikasiOptimal = ($kubikasi >= 0.85) || ($kubikasi >= 85);
            $isTonaseOptimal   = ($tonase >= 0.85) || ($tonase >= 85);

            return ($isKubikasiOptimal || $isTonaseOptimal) ? 'OPTIMAL' : 'TIDAK OPTIMAL';
        }

        return $this->cleanText($defaultVal) ?? '-';
    }

    private function hitungWaktuPengiriman($createOn, $tglDo, $defaultVal = null)
    {
        if ($createOn && $tglDo) {
            return ($createOn !== $tglDo) ? 'PENDING' : 'TEPAT WAKTU';
        }

        return $this->cleanText($defaultVal) ?? '-';
    }

    private function cleanText($value)
    {
        if ($value === null || $value === '' || $value === '-' || $value === '#VALUE!') return null;
        if (is_array($value)) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function cleanNumber($value)
    {
        if ($value === null || $value === '' || $value === '-') return null;
        if (is_numeric($value)) return (float) $value;
        $value = str_replace(['Rp', 'rp', ' '], '', (string) $value);
        $value = str_replace(['.', ','], '', $value);
        return is_numeric($value) ? (float) $value : null;
    }

    private function cleanDecimal($value)
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
        if (!$value || $value === '-' || $value === '#VALUE!') return null;
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }
        $timestamp = strtotime(str_replace('/', '-', trim((string) $value)));
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}