<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import data shipment (utamanya shipment BARU).
 *
 * Aturan:
 *  - 1 baris Excel = 1 DO. Kunci baris: no_do (kalau kosong: no_shipment + tujuan).
 *  - MODE DEFAULT (skipExisting = true):
 *      * no_do yang SUDAH ada di DB dilewati total (tidak diproses, tidak diupdate, tidak dobel).
 *      * no_do yang BELUM ada di DB di-insert (batch). Cocok untuk upload berulang tgl 1, 1-2, 1-2-3.
 *      * Baris lama yang no_do-nya masih kosong (data lama) dicocokkan lewat no_shipment + tujuan,
 *        lalu diisi lewat jalur update lama (supaya tidak jadi duplikat).
 *  - MODE UPDATE (skipExisting = false): perilaku lama.
 *      * Baris yang SUDAH ada: kolom input user TIDAK ditimpa (hanya diisi kalau kosong),
 *        kecuali kolom di ALWAYS_OVERWRITE (data per DO + angka dari Excel).
 *  - Nilai kosong di Excel tidak pernah menimpa data lama.
 *  - kirim_kontribusi di Excel = biaya 1 mobil per no_shipment (diulang di banyak baris tidak masalah,
 *    dihitung 1x). Dibagi ke tiap DO berdasarkan total_kubik.
 *  - biaya_kirim per tujuan = SUM kirim_kontribusi DO di tujuan itu.
 *  - cr, hasil_kubik, hasil_tonase, pengiriman_optimal dihitung ulang setelah import,
 *    HANYA untuk shipment yang kena baris baru / diubah.
 *
 * PERFORMA (target 10.000 - 15.000 baris):
 *  - File dibaca per 1000 baris (WithChunkReading), tidak dimuat sekaligus.
 *  - no_do yang sudah ada dimuat 1x ke memori; baris duplikat dilewati sebelum diproses.
 *  - Insert per batch (300 baris), bukan satu-satu.
 *  - Hasil lookup tarif (nama mobil lengkap + kubikasi + tonase) di-cache per route|ekspedisi|mobil.
 *  - Hitung ulang di AfterImport hanya untuk shipment yang tersentuh.
 *  - Wajib ada index: logistik_pengiriman(no_shipment), (no_do), (periode_shipment).
 */
class LogistikImport implements ToCollection, WithHeadingRow, WithEvents, WithCalculatedFormulas, WithChunkReading
{
    private const TABLE       = 'logistik_pengiriman';
    private const TARIF_TABLE = 'tarif_pengiriman';

    // jumlah baris per batch insert
    private const INSERT_BATCH = 300;

    // kolom yang tidak pernah diisi dari Excel
    private const SKIP_COLUMNS = ['id', 'created_at', 'updated_at'];

    // pada baris yang SUDAH ada (mode update), hanya kolom ini yang boleh menimpa nilai lama
    private const ALWAYS_OVERWRITE = [
        'no_shipment',
        'tujuan',
        'no_do',
        'sales_document',
        'customer_reference',
        'contract_end_date',
        'nilai_muatan',
        'total_do_qty_car',
        'total_kubik',
        'total_tonase',
        'tanggal_shipment',
        'periode_shipment',
    ];

    // kolom hasil hitung ulang, tidak boleh diisi mentah dari Excel
    private const RECALC_COLUMNS = [
        'kirim_kontribusi',
        'biaya_kirim',
        'cr',
        'hasil_kubik',
        'hasil_tonase',
        'pengiriman_optimal',
        'pengiriman_optimal_customer',
        'reason_optimal_customer',
    ];

    // area => PIC. Hanya area yang PIC-nya satu; dipakai kalau PIC masih kosong.
    private const AREA_PIC = [
        'ACEH'             => 'Bernard',
        'BANTEN'           => 'Dani',
        'BENGKULU'         => 'Rafly',
        'JABODEBEK'        => 'Dani',
        'JAWA_BARAT'       => 'Dani',
        'JAWA_TENGAH'      => 'Emyr',
        'JAWA_TIMUR'       => 'Emyr',
        'KALIMANTAN_BARAT' => 'Emyr',
        'KALIMANTAN_TIMUR' => 'Aldi',
        'KEP._RIAU'        => 'Emyr',
        'LAMPUNG'          => 'Rafly',
        'SULAWESI_SELATAN' => 'Emyr',
        'SULAWESI_UTARA'   => 'Emyr',
        'SUMATERA_BARAT'   => 'Bernard',
        'SUMATERA_SELATAN' => 'Rafly',
        'YOGYAKARTA'       => 'Emyr',
    ];

    // nama PIC lama (huruf kecil) => nama baru
    private const PIC_RENAME = [
        'asti' => 'Emyr',
    ];
    private const ROUTE_ALIASES = [
        'jabodetabek' => 'Sentul-Jabodetabek',
    ];
    private const EKSPEDISI_ALIASES = [
        // 'namadiexcel' => 'PT Nama Di Master',
    ];

    // kolom hasil lookup master tujuan yang boleh "ikut" baris lain di shipment yang sama
    private const SIBLING_FILL_COLUMNS = [
        'area',
        'pulau',
        'transport_lead_time',
        'planner',
        'pic_monitoring',
        'tanggal_shipment',
        'periode_shipment',
    ];

    // header Excel (sudah di-slug) -> kolom DB, hanya untuk yang namanya BEDA
    private const HEADER_ALIASES = [
        'tgl_terima_dari_admin'  => 'tanggal_naik_logistik',
        'tgl_rencana_kirim'      => 'rencana_kirim',
        'tanggal_tiba_di_gudang' => 'tanggal_tiba_gudang',
        'rute'  => 'route',
        'rute_name' => 'route',
        'ac_turutan_bongkar'     => 'act_urutan_bongkar',
        'reason_waktu_tiba'      => 'reason_tiba',
        'reason_waktu_bongkar'   => 'reason_bongkar',
        'nilai_muatan_rp'        => 'nilai_muatan',
        'biaya_kirim_rp'         => 'biaya_kirim',
        'nopol'                  => 'no_pol',
        'via'                    => 'via_kirim',
        'kode_planner'           => 'code_planner',

        // typo header yang sering muncul
        'no_shipmetn'            => 'no_shipment',
        'no_shipmnet'            => 'no_shipment',
        'sales_doucmet'          => 'sales_document',
        'sales_documet'          => 'sales_document',

        // ===== header file pivot DO =====
        'nomer_shipment'     => 'no_shipment',
        'sold_to_party_name' => 'tujuan',
        'vendor'             => 'ekpedisi',
        'route_name'         => 'route',
        'sum_of_nilai_do'    => 'nilai_muatan',
        'sum_of_qty_do'      => 'total_do_qty_car',
        'sum_of_kubikasi2'   => 'total_kubik',
        'sum_of_tonase3'     => 'total_tonase',
    ];

    private static $areaMap        = null;   // area => ['planner' => ..., 'pic' => ...]
    private static $customerMap    = null;   // tujuan => row         (cadangan)
    private static $customerByCode = null;   // "code|tujuan" => row  (prioritas)
    private static $tarifByRoute   = null;   // route => rows tarif
    private static $mobilMaster    = null;   // semua nama mobil lengkap di master tarif

    private array $allColumns = [];
    private array $dbColumns  = [];        // [kolom => tipe], tanpa SKIP_COLUMNS
    private ?array $map = null;            // [header => [kolom, kind]]
    private bool $hasCreatedAt = false;
    private bool $hasUpdatedAt = false;

    // forward-fill (sel merge di Excel)
    private $lastNoShipmentRaw = null;
    private $lastNoShipment    = null;
    private $lastRoute         = null;
    private $lastMobil         = null;
    private $lastEkpedisi      = null;
    private array $ekspedisiResolved = [];

    // cache baris DB per no_shipment (dipakai jalur update lama)
    private array $shipCache = [];

    // kombinasi route|ekspedisi|mobil yang tarifnya tidak ketemu (supaya log cuma 1x)
    private array $tarifWarned = [];

    // cache hasil tarif: "route|ekspedisi|mobil" => [mobil lengkap, kubikasi, tonase]
    private array $tarifMemo = [];

    // ===== mode skip data yang sudah ada =====
    private bool $skipExisting;
    private array $doSet     = [];   // no_do (lowercase) yang sudah ada di DB / sudah muncul di file
    private array $legacySet = [];   // "no_shipment|tujuan" baris lama yang no_do-nya KOSONG
    private array $insertBuf = [];   // signature kolom => baris yang antre insert
    private array $touched   = [];   // no_shipment yang ditambah / diubah (untuk hitung ulang)
    private int $skippedExisting = 0;
private array $shipSet      = [];   // no_shipment yang sudah ada di DB sebelum import (mode skip)
private array $shipHadRows  = [];   // mode update: shipment yang sudah punya baris sebelum import
private array $changeLog    = [];   // "shipment|tujuan" => detail perubahan
private int $unchanged      = 0;
private array $byDate = [];   // 'Y-m-d' => [baru, diperbarui, tidak_berubah, sudah_ada]
    // no_shipment => [no_shipment, biaya 1 mobil]  (diambil dari baris PERTAMA yang terisi)
    private array $groupBiaya    = [];
    // no_shipment => true kalau angka biaya di Excel beda-beda dalam 1 shipment
    private array $biayaConflict = [];

    // ===== hasil proses =====
    private $inserted = 0;
    private $updated  = 0;
    private $skipped  = 0;
    private $failed   = 0;
    private array $failedList = [];
    private array $allNoShipmentInFile = [];
    private array $ignoredHeaders = [];
    private array $missingColumns = [];
    private array $unknownPlannerCodes = [];

    public function getInsertedCount(): int
    {
        return $this->inserted;
    }
    public function getUpdatedCount(): int
    {
        return $this->updated;
    }
    public function getImportedCount(): int
    {
        return $this->inserted + $this->updated;
    }
    public function getSkippedCount(): int
    {
        return $this->skipped;
    }
    /** jumlah baris yang dilewati karena no_do-nya sudah ada di DB */
    public function getSkippedExistingCount(): int
    {
        return $this->skippedExisting;
    }
    public function getFailedCount(): int
    {
        return $this->failed;
    }
    public function getFailedList(): array
    {
        return $this->failedList;
    }
    public function getAllNoShipmentInFile(): array
    {
        return $this->allNoShipmentInFile;
    }
    public function getIgnoredHeaders(): array
    {
        return array_values(array_unique($this->ignoredHeaders));
    }
    public function getMissingColumns(): array
    {
        return array_keys($this->missingColumns);
    }
    public function getUnknownPlannerCodes(): array
    {
        return array_values(array_unique($this->unknownPlannerCodes));
    }
    public function getBiayaConflict(): array
    {
        return array_map('strval', array_keys($this->biayaConflict));
    }

    // ===== TAMBAHAN BARU: mulai =====
 public function getChangeLog(): array
{
    return array_values(array_map(function ($c) {
        $alasan = [];

        if (!empty($c['do_baru'])) {
            $alasan[] = 'DO baru ditambah: ' . implode(', ', $c['do_baru']);
        }

        foreach ($c['kolom'] as $col => $pair) {
            [$old, $new] = $pair;
            $o = ($old === null || trim((string) $old) === '') ? 'kosong' : (string) $old;
            $alasan[] = $col . ' (' . $o . ' → ' . (string) $new . ')';
        }

        $c['alasan'] = $alasan;
        return $c;
    }, $this->changeLog));
}

    public function getUnchangedCount(): int
    {
        return $this->unchanged;
    }
    public function getByDate(): array
{
    ksort($this->byDate);

    $out = [];
    foreach ($this->byDate as $tgl => $c) {
        $out[] = ['tanggal' => $tgl] + $c;
    }
    return $out;
}

private function countDate(string $type, ?string $tgl): void
{
    $tgl = $tgl ?: '(tanpa tanggal)';

    $this->byDate[$tgl] ??= ['baru' => 0, 'diperbarui' => 0, 'tidak_berubah' => 0, 'sudah_ada' => 0];
    $this->byDate[$tgl][$type]++;
}
    // ===== TAMBAHAN BARU: selesai =====

    /** baca file per 1000 baris supaya memori aman untuk 10.000+ baris */
    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * @param bool $skipExisting true  = lewati no_do yang sudah ada di DB (default)
     *                           false = mode update lama (isi kolom kosong + ALWAYS_OVERWRITE)
     */
    public function __construct(bool $skipExisting = true)
    {
        $this->skipExisting = $skipExisting;

        foreach (DB::select('SHOW COLUMNS FROM `' . self::TABLE . '`') as $c) {
            $this->allColumns[] = $c->Field;

            if (!in_array($c->Field, self::SKIP_COLUMNS, true)) {
                $this->dbColumns[$c->Field] = strtolower($c->Type);
            }
        }

        $this->hasCreatedAt = in_array('created_at', $this->allColumns, true);
        $this->hasUpdatedAt = in_array('updated_at', $this->allColumns, true);

        if ($this->skipExisting) {
            $this->loadExisting();
        }

        if (self::$customerMap === null) {
            $rows = DB::table('tujuanfillterr')
                ->select(
                    'tujuan',
                    'code_planner',
                    'Planner',
                    'pulau',
                    'area',
                    'biaya_kuli',
                    'transport_lead_time',
                    'Monitoring'
                )
                ->where('Div', 'HO Meruya')
                ->get();

            self::$customerMap    = [];
            self::$customerByCode = [];

            foreach ($rows as $r) {
                $t = $this->normTujuan($r->tujuan);
                if ($t === '') continue;

                self::$customerMap[$t] ??= $r;

                $code = strtolower(trim((string) $r->code_planner));
                if ($code !== '') {
                    self::$customerByCode[$code . '|' . $t] ??= $r;
                }
            }

            // peta area -> planner & PIC (paling sering muncul di master)
            $tally = [];
            foreach ($rows as $r) {
                $a = trim((string) $r->area);
                if ($a === '') continue;

                $p = trim((string) $r->Planner);
                $m = trim((string) $r->Monitoring);

                if ($p !== '') $tally[$a]['p'][$p] = ($tally[$a]['p'][$p] ?? 0) + 1;
                if ($m !== '') $tally[$a]['m'][$m] = ($tally[$a]['m'][$m] ?? 0) + 1;
            }

            self::$areaMap = [];
            foreach ($tally as $a => $t) {
                self::$areaMap[(string) $a] = [
                    'planner' => isset($t['p']) ? (string) array_search(max($t['p']), $t['p']) : null,
                    'pic'     => isset($t['m']) ? (string) array_search(max($t['m']), $t['m']) : null,
                ];
            }
            foreach (self::$areaMap as $a => $info) {
                $pic = self::AREA_PIC[strtoupper($a)] ?? $info['pic'];
                self::$areaMap[$a]['pic'] = $pic !== null ? (self::PIC_RENAME[strtolower($pic)] ?? $pic) : null;
            }
            foreach (self::AREA_PIC as $a => $pic) {
                self::$areaMap[$a] ??= ['planner' => null, 'pic' => $pic];
            }
        }

        if (self::$mobilMaster === null) {
            self::$mobilMaster = DB::table(self::TARIF_TABLE)
                ->whereNotNull('mobil')->where('mobil', '!=', '')
                ->distinct()->pluck('mobil')->all();
        }

        if (self::$tarifByRoute === null) {
            self::$tarifByRoute = DB::table(self::TARIF_TABLE)
                ->select('ekpedisi', 'route', 'mobil', 'biaya_kirim', 'kubikasi', 'tonase')
                ->get()
                ->groupBy(fn($row) => $this->normalize($row->route));
        }
    }

    // =========================================================
    // DETEKSI DATA YANG SUDAH ADA
    // =========================================================

    /** muat semua no_do yang sudah ada di DB (sekali, per 5000 baris) */
    private function loadExisting(): void
    {
        DB::table(self::TABLE)
            ->select('id', 'no_do', 'no_shipment', 'tujuan')
            ->chunkById(5000, function ($rows) {
                foreach ($rows as $r) {
                    $do = mb_strtolower(trim((string) $r->no_do));
                    $this->shipSet[mb_strtolower(trim((string) $r->no_shipment))] = true;

                    if ($do !== '') {
                        $this->doSet[$do] = true;
                    } else {
                        $this->legacySet[$this->legacyKey($r->no_shipment, $r->tujuan)] = true;
                    }
                }
            }, 'id');
    }

    private function legacyKey($shipment, $tujuan): string
    {
        return mb_strtolower(trim((string) $shipment)) . '|' . mb_strtolower(trim((string) $tujuan));
    }

    /** baris yang dilewati tetap harus mewariskan route/mobil/ekspedisi (sel merge di Excel) */
    private function rememberForwardFill(array $row, string $noShipment): void
    {
        if ($noShipment !== $this->lastNoShipment) {
            $this->lastRoute = $this->lastMobil = $this->lastEkpedisi = null;
            $this->lastNoShipment = $noShipment;
        }

        if ($v = $this->cleanText($row['route'] ?? null))    $this->lastRoute    = $this->applyRouteAlias($v);
        if ($v = $this->cleanText($row['mobil'] ?? null))    $this->lastMobil    = $v;
        if ($v = $this->cleanText($row['ekpedisi'] ?? null)) $this->lastEkpedisi = $v;
    }

    private function normTujuan(?string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $value);
        return preg_replace('/\s+/', ' ', strtolower(trim($value)));
    }

    /** Lengkapi nama mobil terpotong. Cari berurutan: ekspedisi+route -> route -> semua master. */
    private function completeMobilName(?string $mobil, ?string $route = null, ?string $ekpedisi = null): ?string
    {
        if ($mobil === null || $mobil === '') return $mobil;

        $key = $this->normalizeMobil($mobil);
        if (strlen($key) < 8) return $mobil;   // terlalu pendek, jangan ditebak

        $rows = self::$tarifByRoute[$this->normalize($route)] ?? null;
        $eKey = $this->ekspedisiKey($ekpedisi);

        $pools = [];
        if ($rows) {
            if ($eKey !== '') {
                $pools[] = $rows->filter(fn($r) => $this->ekspedisiKey($r->ekpedisi) === $eKey)->pluck('mobil');
            }
            $pools[] = $rows->pluck('mobil');
        }
        $pools[] = collect(self::$mobilMaster);

        foreach ($pools as $pool) {
            $hits = [];
            foreach ($pool->filter()->unique() as $full) {
                $fk = $this->normalizeMobil($full);
                if ($fk === $key) return $full;                 // sudah lengkap
                if (str_starts_with($fk, $key)) $hits[$full] = true;
            }
            if (count($hits) === 1) return array_key_first($hits);
            if (count($hits) > 1) return $mobil;                // ambigu, jangan ditebak
        }

        return $mobil;
    }

    // =========================================================
    // IMPORT PER BARIS
    // =========================================================
    public function collection(Collection $rows)
    {
        foreach ($rows as $rowCollection) {
            $row = $rowCollection->toArray();

            // samakan nama header Excel dengan nama kolom DB
            foreach (self::HEADER_ALIASES as $from => $to) {
                if (array_key_exists($from, $row) && !array_key_exists($to, $row)) {
                    $row[$to] = $row[$from];
                }
            }

            if ($this->map === null) {
                $this->buildMap(array_keys($row));
            }

            // baris kosong total -> tidak masuk
            if ($this->isEmptyRow($row)) {
                $this->skipped++;
                continue;
            }

            $noShipment = $this->cleanText($row['no_shipment'] ?? null);
            $tujuan     = $this->cleanText($row['tujuan'] ?? null);

            // sel merge: no_shipment kosong tapi tujuan ada -> ikut baris di atas
            if ($noShipment === null && $tujuan !== null) {
                $noShipment = $this->lastNoShipmentRaw;
            }
            if ($noShipment !== null) {
                $this->lastNoShipmentRaw = $noShipment;
            }

            // no_shipment atau tujuan kosong -> tidak masuk
            if ($noShipment === null || $tujuan === null) {
                $this->skipped++;
                continue;
            }

            $noDo  = $this->cleanText($row['no_do'] ?? null);
            $doKey = $noDo !== null ? mb_strtolower(trim($noDo)) : null;

            // SUDAH ADA di DB (atau sudah muncul di file ini) -> lewati total
// SUDAH ADA di DB (atau sudah muncul di file ini) -> lewati total
if ($this->skipExisting && $doKey !== null && isset($this->doSet[$doKey])) {
    $this->skippedExisting++;
    $this->countDate('sudah_ada', $this->convertDateUs($row['tanggal_shipment'] ?? null));
    $this->rememberForwardFill($row, $noShipment);
    continue;
}

            $this->allNoShipmentInFile[] = $noShipment;

            try {
                $attrs = $this->buildAttributes($row, $noShipment, $tujuan);

                $legacy = $this->skipExisting
                    && isset($this->legacySet[$this->legacyKey($noShipment, $tujuan)]);

                if (!$this->skipExisting || $legacy) {
                    // mode update, atau baris lama yang no_do-nya masih kosong -> jalur lama
                    $this->flushInsert();
                    unset($this->shipCache[$noShipment]);
                    $this->save($noShipment, $tujuan, $noDo, $attrs);
                } else {
                    $this->queueInsert($noShipment, $tujuan, $noDo, $attrs);
                }

                $this->touched[$noShipment] = true;

                if ($doKey !== null) {
                    $this->doSet[$doKey] = true;   // cegah duplikat di dalam file yang sama
                }
            } catch (\Throwable $e) {
                $this->failed++;
                $this->failedList[] = [
                    'no_shipment' => $noShipment,
                    'tujuan'      => $tujuan,
                    'error'       => $e->getMessage(),
                    'line'        => $e->getLine(),
                ];

                logger()->error('LOGISTIK IMPORT GAGAL PER BARIS', [
                    'no_shipment' => $noShipment,
                    'tujuan'      => $tujuan,
                    'error'       => $e->getMessage(),
                    'file'        => $e->getFile(),
                    'line'        => $e->getLine(),
                ]);
            }
        }

        // tulis sisa antrian insert di akhir tiap chunk
        $this->flushInsert();

        if (!empty($this->ignoredHeaders)) {
            logger()->info('LOGISTIK IMPORT: header Excel tanpa kolom DB (diabaikan)', $this->getIgnoredHeaders());
        }
        if (!empty($this->missingColumns)) {
            logger()->warning('LOGISTIK IMPORT: kolom belum ada di tabel (tidak disimpan)', $this->getMissingColumns());
        }
        if (!empty($this->unknownPlannerCodes)) {
            logger()->warning('LOGISTIK IMPORT: code_planner tidak ada di master', $this->getUnknownPlannerCodes());
        }
        if (!empty($this->biayaConflict)) {
            logger()->warning('LOGISTIK IMPORT: angka biaya beda dalam 1 shipment (dipakai yang pertama)', $this->getBiayaConflict());
        }
    }

    // =========================================================
    // BATCH INSERT (baris baru)
    // =========================================================

    /** antre insert; dikelompokkan per kumpulan kolom supaya aman untuk batch insert */
    private function queueInsert(string $noShipment, string $tujuan, ?string $noDo, array $attrs): void
    {
        $attrs = $this->filterToColumns($attrs);

        if (isset($this->dbColumns['no_shipment'])) $attrs['no_shipment'] = $noShipment;
        if (isset($this->dbColumns['tujuan']))      $attrs['tujuan']      = $tujuan;
        if ($noDo !== null && isset($this->dbColumns['no_do'])) $attrs['no_do'] = $noDo;
if (isset($this->shipSet[mb_strtolower(trim($noShipment))])) {
    $this->logChange($noShipment, $tujuan, 'do_baru', $noDo);
}
        $now = now();

        // default hanya untuk baris BARU
        if (isset($this->dbColumns['create_tgl'])) {
            $attrs['create_tgl'] ??= $now->format('Y-m-d H:i:s');
        }
        if (isset($this->dbColumns['ketersediaan_unit'])) {
            $attrs['ketersediaan_unit'] ??= 'BELUM DAPAT';
        }
        if ($this->hasCreatedAt) $attrs['created_at'] = $now;
        if ($this->hasUpdatedAt) $attrs['updated_at'] = $now;
$this->countDate('baru', $attrs['tanggal_shipment'] ?? null);
        $sig = implode(',', array_keys($attrs));
        $this->insertBuf[$sig][] = $attrs;

        if (count($this->insertBuf[$sig]) >= self::INSERT_BATCH) {
            $this->flushInsert($sig);
        }
    }
    private function logChange(string $sh, string $tj, string $type, ?string $noDo = null, array $cols = []): void
{
    $k = mb_strtolower(trim($sh)) . '|' . mb_strtolower(trim($tj));

    $this->changeLog[$k] ??= [
        'no_shipment' => $sh,
        'tujuan'      => $tj,
        'do_baru'     => [],
        'kolom'       => [],
    ];

    if ($type === 'do_baru') {
        $this->changeLog[$k]['do_baru'][] = $noDo ?? '(tanpa No DO)';
    }
foreach ($cols as $c => $pair) {
    $this->changeLog[$k]['kolom'][$c] = $pair;
}
}
private function sameValue($a, $b, string $col = ''): bool
{
    $a = $a === null ? '' : trim((string) $a);
    $b = $b === null ? '' : trim((string) $b);

    if ($a === $b) return true;

    // tanggal: abaikan jam 00:00:00
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $a) && preg_match('/^\d{4}-\d{2}-\d{2}/', $b)) {
        if (substr($a, 0, 10) === substr($b, 0, 10)
            && (strlen($a) <= 10 || strlen($b) <= 10
                || substr($a, 11) === substr($b, 11)
                || substr($a, 11) === '00:00:00' || substr($b, 11) === '00:00:00')) {
            return true;
        }
    }

    // angka: uang 2 desimal, lainnya dibandingkan teliti
    if (is_numeric($a) && is_numeric($b)) {
        $tol = $col === 'nilai_muatan' ? 0.005 : 0.000001;
        return abs((float) $a - (float) $b) < $tol;
    }

    return false;
}

    private function flushInsert(?string $only = null): void
    {
        foreach ($this->insertBuf as $sig => $rows) {
            if ($only !== null && $sig !== $only) continue;

            foreach (array_chunk($rows, self::INSERT_BATCH) as $chunk) {
                try {
                    DB::table(self::TABLE)->insert($chunk);
                    $this->inserted += count($chunk);
                } catch (\Throwable $e) {
                    // 1 batch gagal -> coba satu-satu supaya baris yang bermasalah ketahuan
                    foreach ($chunk as $r) {
                        try {
                            DB::table(self::TABLE)->insert($r);
                            $this->inserted++;
                        } catch (\Throwable $e2) {
                            $this->failed++;
                            $this->failedList[] = [
                                'no_shipment' => $r['no_shipment'] ?? null,
                                'tujuan'      => $r['tujuan'] ?? null,
                                'error'       => $e2->getMessage(),
                                'line'        => $e2->getLine(),
                            ];

                            logger()->error('LOGISTIK IMPORT GAGAL INSERT', [
                                'no_shipment' => $r['no_shipment'] ?? null,
                                'tujuan'      => $r['tujuan'] ?? null,
                                'no_do'       => $r['no_do'] ?? null,
                                'error'       => $e2->getMessage(),
                            ]);

                            // no_do gagal insert -> lepas dari daftar "sudah ada" agar boleh dicoba lagi
                            if (!empty($r['no_do'])) {
                                unset($this->doSet[mb_strtolower(trim((string) $r['no_do']))]);
                            }
                        }
                    }
                }
            }

            unset($this->insertBuf[$sig]);
        }
    }

    // =========================================================
    // SIMPAN JALUR LAMA (insert / update berdasarkan no_shipment + tujuan + no_do)
    // Dipakai di mode update, dan untuk baris lama yang no_do-nya masih kosong.
    // =========================================================
    private function save(string $noShipment, string $tujuan, ?string $noDo, array $attrs): void
    {
        $tglShip = $attrs['tanggal_shipment'] ?? null;
        $attrs = $this->filterToColumns($attrs);

        if (isset($this->dbColumns['no_shipment'])) $attrs['no_shipment'] = $noShipment;
        if (isset($this->dbColumns['tujuan']))      $attrs['tujuan']      = $tujuan;
        if ($noDo !== null && isset($this->dbColumns['no_do'])) $attrs['no_do'] = $noDo;

        $now = now();

        // load semua baris DB untuk shipment ini SEKALI, lalu cari kandidat di PHP
        if (!isset($this->shipCache[$noShipment])) {
            $this->shipCache[$noShipment] = DB::table(self::TABLE)
                ->where('no_shipment', $noShipment)
                ->orderBy('id')
                ->get()
                ->map(fn($r) => (array) $r)
                ->all();
        }
        if (!$this->skipExisting && !isset($this->shipHadRows[$noShipment])) {
    $this->shipHadRows[$noShipment] = !empty($this->shipCache[$noShipment]);
}

        $tKey  = mb_strtolower(trim($tujuan));
        $doKey = $noDo !== null ? mb_strtolower(trim($noDo)) : null;
        $idx = null;
        $fallback = null;

        foreach ($this->shipCache[$noShipment] as $i => $r) {
            if (mb_strtolower(trim((string) ($r['tujuan'] ?? ''))) !== $tKey) continue;

            if ($doKey === null) {
                $idx = $i;
                break;
            }

            // 1) cocok persis no_do; 2) kalau tidak ada, pakai baris lama yang no_do-nya masih kosong
            $rd = mb_strtolower(trim((string) ($r['no_do'] ?? '')));
            if ($rd === $doKey) {
                $idx = $i;
                break;
            }
            if ($rd === '' && $fallback === null) $fallback = $i;
        }
        $idx ??= $fallback;

if ($idx !== null) {
    $existing   = $this->shipCache[$noShipment][$idx];
    $existingId = $existing['id'];
    $changed    = [];

   foreach ($attrs as $col => $v) {
    if (in_array($col, self::ALWAYS_OVERWRITE, true)) {
if (!$this->sameValue($existing[$col] ?? null, $v, $col)) {
            $changed[$col] = [$existing[$col] ?? null, $v];   // [lama, baru]
        }
        continue;
    }

    $cur = $existing[$col] ?? null;
    if ($cur !== null && trim((string) $cur) !== '') {
        unset($attrs[$col]);                // sudah terisi, jangan ditimpa
    } else {
        $changed[$col] = [null, $v];        // tadinya kosong, sekarang diisi
    }
}
if (empty($changed)) {
    $this->unchanged++;
    $this->countDate('tidak_berubah', $tglShip);
    return;
}

    if ($this->hasUpdatedAt) $attrs['updated_at'] = $now;
    DB::table(self::TABLE)->where('id', $existingId)->update($attrs);
    $this->shipCache[$noShipment][$idx] = array_merge($existing, $attrs);

    $this->logChange($noShipment, $tujuan, 'update', $noDo, $changed);
    $this->countDate('diperbarui', $tglShip);
    $this->updated++;
    return;
}
        // default hanya untuk baris BARU
        if (isset($this->dbColumns['create_tgl'])) {
            $attrs['create_tgl'] ??= $now->format('Y-m-d H:i:s');
        }
        if (isset($this->dbColumns['ketersediaan_unit'])) {
            $attrs['ketersediaan_unit'] ??= 'BELUM DAPAT';
        }
        if ($this->hasCreatedAt) $attrs['created_at'] = $now;
        if ($this->hasUpdatedAt) $attrs['updated_at'] = $now;
        $shipmentLama = isset($this->shipSet[mb_strtolower(trim($noShipment))])
    || ($this->shipHadRows[$noShipment] ?? false);

if ($shipmentLama) {
    $this->logChange($noShipment, $tujuan, 'do_baru', $noDo);
}


$this->countDate('baru', $tglShip);
        $newId = DB::table(self::TABLE)->insertGetId($attrs);
        $this->shipCache[$noShipment][] = array_merge($attrs, ['id' => $newId]);
        $this->inserted++;
    }

    /** buang nilai null (supaya data lama tidak tertimpa) dan key yang belum ada di tabel */
    private function filterToColumns(array $attrs): array
    {
        $out = [];

        foreach ($attrs as $col => $v) {
            if ($v === null) continue;

            if (!isset($this->dbColumns[$col])) {
                $this->missingColumns[$col] = true;
                continue;
            }

            $out[$col] = $v;
        }

        return $out;
    }

    // =========================================================
    // BANGUN ATRIBUT
    // =========================================================
    private function buildAttributes(array $row, string $noShipment, string $tujuan): array
    {
        // ================= GUDANG 1 (KACS) =================
        $planningLoading     = $this->convertDateTime($row['planning_loading'] ?? null);
        $tanggalTibaGudang   = $this->convertDateTime($this->pick($row, ['tanggal_tiba_gudang', 'tanggal_tiba_di_gudang']));
        $tanggalKeluarGudang = $this->convertDateTime($row['tanggal_keluar_gudang'] ?? null);

        $lamaDigudang = null;
        $slaLoading   = null;

        if ($tanggalTibaGudang && $tanggalKeluarGudang) {
            $selisihGudang = (int) date_diff(date_create($tanggalTibaGudang), date_create($tanggalKeluarGudang))->format('%a');
            $lamaDigudang  = $selisihGudang . ' Hari';
            $slaLoading    = ($selisihGudang == 0) ? 'On Time' : 'Delay';
        }

        // ================= GUDANG 2 (SENTUL) =================
        $planningLoading2     = $this->convertDateTime($row['planning_loading_2'] ?? null);
        $tanggalTibaGudang2   = $this->convertDateTime($row['tanggal_tiba_gudang_2'] ?? null);
        $tanggalKeluarGudang2 = $this->convertDateTime($row['tanggal_keluar_gudang_2'] ?? null);
        $statusGudang2        = $this->cleanText($row['status_gudang_2'] ?? null);

        $lama_digudang_2 = null;
        $sla_loading_2   = null;

        if ($tanggalTibaGudang2 && $tanggalKeluarGudang2) {
            $jam2 = (strtotime($tanggalKeluarGudang2) - strtotime($tanggalTibaGudang2)) / 3600;
            $lama_digudang_2 = round($jam2, 1) . ' Jam';
            $sla_loading_2   = $jam2 <= 24 ? 'H+0' : ($jam2 <= 48 ? 'H+1' : 'H>1');
        }

        // ================= GUDANG 3 (CCIE) =================
        $planningLoading3     = $this->convertDateTime($row['planning_loading_3'] ?? null);
        $tanggalTibaGudang3   = $this->convertDateTime($row['tanggal_tiba_gudang_3'] ?? null);
        $tanggalKeluarGudang3 = $this->convertDateTime($row['tanggal_keluar_gudang_3'] ?? null);
        $statusGudang3        = $this->cleanText($row['status_gudang_3'] ?? null);

        $lama_digudang_3 = null;
        $sla_loading_3   = null;

        if ($tanggalTibaGudang3 && $tanggalKeluarGudang3) {
            $jam3 = (strtotime($tanggalKeluarGudang3) - strtotime($tanggalTibaGudang3)) / 3600;
            $lama_digudang_3 = round($jam3, 1) . ' Jam';
            $sla_loading_3   = $jam3 <= 24 ? 'H+0' : ($jam3 <= 48 ? 'H+1' : 'H>1');
        }

        // ================= DATE =================
        $rencanaKirim        = $this->convertDateTime($this->pick($row, ['rencana_kirim', 'tgl_rencana_kirim']));
        $tanggalNaikLogistik = $this->convertDateTime($this->pick($row, ['tanggal_naik_logistik', 'tgl_terima_dari_admin']));
        $tanggalDptUnit      = $this->convertDate($row['tanggal_dpt_unit'] ?? null);
        $tanggalTibaAktual   = $this->convertDate($row['tanggal_tiba'] ?? null);
        $tanggalBongkar      = $this->convertDate($row['tanggal_bongkar'] ?? null);
        $actPgiDate          = $this->convertDate($row['act_pgi_date'] ?? null);

        // ================= SLA DAPAT MOBIL =================
        $lamaWaktuPencarian = null;
        $slaDapatMobil      = null;

        if ($tanggalDptUnit && $tanggalTibaGudang) {
            $selisihCariMobil   = (int) date_diff(date_create($tanggalDptUnit), date_create($tanggalTibaGudang))->format('%a');
            $lamaWaktuPencarian = $selisihCariMobil . ' Hari';
            $slaDapatMobil      = ($selisihCariMobil == 0) ? 'On Time' : 'Delay';
        }

        // ================= FORWARD-FILL (route/mobil/ekpedisi) =================
        if ($noShipment !== $this->lastNoShipment) {
            $this->lastRoute    = null;
            $this->lastMobil    = null;
            $this->lastEkpedisi = null;
        }

        $route    = $this->applyRouteAlias($this->cleanText($row['route'] ?? null) ?: $this->lastRoute);
        $mobil    = $this->cleanText($row['mobil'] ?? null)    ?: $this->lastMobil;
        $ekpedisi = $this->cleanText($row['ekpedisi'] ?? null) ?: $this->lastEkpedisi;

        if ($route)    $this->lastRoute    = $route;
        if ($mobil)    $this->lastMobil    = $mobil;
        if ($ekpedisi) $this->lastEkpedisi = $ekpedisi;

        $this->lastNoShipment = $noShipment;

        // ================= ANGKA DARI EXCEL =================
        $totalDoCar  = $this->parseNumber($row['total_do_qty_car'] ?? null);
        $totalKubik  = $this->parseNumber($this->pick($row, ['total_kubik', 'total_kubik_m3', 'total_kubik_m_3', 'totalkubik']));
        $totalTonase = $this->parseNumber($this->pick($row, ['total_tonase', 'total_tonase_ton', 'totaltonase']));
        $nilaiMuatan = $this->parseNumber($this->pick($row, ['nilai_muatan', 'nilai_muatan_rp', 'nilai_muatan_rp_', 'nilai_muatan_pasuruan']));

        // ================= NAMA MOBIL LENGKAP + KAPASITAS DARI TARIF (di-cache per kombinasi) =================
        $memoKey = $route . '|' . $ekpedisi . '|' . $mobil;
        if (!isset($this->tarifMemo[$memoKey])) {
            $mobilFull = $this->completeMobilName($mobil, $route, $ekpedisi);
            $tarifRow  = $this->findTarif($route, $ekpedisi, $mobilFull);

            $this->tarifMemo[$memoKey] = [
                $mobilFull,
                $tarifRow ? $this->parseNumber($tarifRow->kubikasi ?? null) : null,
                $tarifRow ? $this->parseNumber($tarifRow->tonase ?? null)   : null,
            ];
        }
        [$mobil, $kubikasi, $tonase] = $this->tarifMemo[$memoKey];

        // ================= BIAYA 1 MOBIL DARI EXCEL (1 per no_shipment) =================
        // angka bisa diulang di banyak baris atau cuma di 1 baris; yang dipakai baris PERTAMA yang terisi
        $biayaExcel = $this->parseNumber($this->pick($row, ['kirim_kontribusi', 'biaya_kirim', 'biaya_kirim_rp']));

        if ($biayaExcel !== null && $biayaExcel > 0) {
            if (
                isset($this->groupBiaya[$noShipment])
                && abs($this->groupBiaya[$noShipment][1] - $biayaExcel) > 0.01
            ) {
                $this->biayaConflict[$noShipment] = true;
            }
            $this->groupBiaya[$noShipment] ??= [$noShipment, $biayaExcel];
        }

        // ================= LOOKUP MASTER TUJUAN =================
        $tujuanKey    = $this->normTujuan($tujuan);
        $customerData = self::$customerMap[$tujuanKey] ?? null;

        // ================= PLANNER & PIC MONITORING =================
        $codeExcel = $this->cleanText($this->pick($row, ['code_planner', 'kode_planner']));

        if ($codeExcel !== null) {
            $plannerRow = self::$customerByCode[strtolower($codeExcel) . '|' . $tujuanKey] ?? null;

            if ($plannerRow === null) {
                $this->unknownPlannerCodes[] = $codeExcel . ' @ ' . $tujuan;
            }

            $codePlanner   = $codeExcel;
            $planner       = $this->cleanText($plannerRow->Planner ?? null)
                ?: $this->cleanText($row['planner'] ?? null);
            $picMonitoring = $this->cleanText($plannerRow->Monitoring ?? null);
        } else {
            $codePlanner   = $this->cleanText($customerData->code_planner ?? null);
            $planner       = $this->cleanText($customerData->Planner ?? null)
                ?: $this->cleanText($row['planner'] ?? null);
            $picMonitoring = $this->cleanText($customerData->Monitoring ?? null);
        }

        $distChannel         = $customerData->dist_channel ?? null;
        $area                = $customerData->area ?? null;
        $biayaKuli           = $customerData->biaya_kuli ?? null;
        $transport_lead_time = $customerData->transport_lead_time ?? null;
        $pulau               = ($customerData->pulau ?? null) ?: $this->cleanText($row['pulau'] ?? null);
        $picMonitoring       = $this->fixPic($picMonitoring, $area);

        // ================= MONITORING =================
        $keluar = collect([$tanggalKeluarGudang, $tanggalKeluarGudang2, $tanggalKeluarGudang3])
            ->filter()->map(fn($d) => strtotime($d))->max();

        $tiba    = $tanggalTibaAktual ? strtotime($tanggalTibaAktual) : null;
        $bongkar = $tanggalBongkar ? strtotime($tanggalBongkar) : null;

        $leadRaw  = $transport_lead_time ?? ($row['transport_lead_time'] ?? null);
        $leadtime = ($leadRaw !== null && $leadRaw !== '' && is_numeric($leadRaw)) ? (int) $leadRaw : null;
        $estimasi = ($keluar && $leadtime !== null) ? strtotime("+{$leadtime} days", $keluar) : null;

        $lamaPerjalanan = ($keluar && $tiba) ? max(0, floor(($tiba - $keluar) / 86400)) : null;
        $slaTiba        = ($tiba && $estimasi) ? (($tiba <= $estimasi) ? 'On Time' : 'Delay') : null;

        $overstay   = ($tiba && $bongkar) ? max(0, floor(($bongkar - $tiba) / 86400)) : null;
        $slaBongkar = ($tiba && $bongkar) ? (($overstay <= 0) ? 'On Time' : 'Delay') : null;

        $logic           = $this->generateStatusAlert($slaTiba, $slaBongkar);
        $statusAkhir     = $logic['status_akhir'];
        $monitoringAlert = $logic['alert'];

        // ================= KETERSEDIAAN UNIT (hanya kalau ada di Excel) =================
        $ketersediaanUnit = $this->cleanText($row['ketersediaan_unit'] ?? null);

        if ($ketersediaanUnit !== null) {
            $ketersediaanUnit = strtoupper($ketersediaanUnit);

            if (in_array($ketersediaanUnit, ['SUDAH DAPAT MOBIL', 'READY MOBIL', 'READY'])) {
                $ketersediaanUnit = 'SUDAH DAPAT';
            }
            if (in_array($ketersediaanUnit, ['BELUM DAPAT MOBIL', 'PENDING'])) {
                $ketersediaanUnit = 'BELUM DAPAT';
            }
        }

        // kirim_kontribusi / biaya_kirim / cr / hasil_kubik / hasil_tonase / pengiriman_optimal
        // dihitung di recalcShipments(), tidak diisi dari sini
        $attrs = [
            'no'                  => $this->cleanText($row['no'] ?? null),
            'create_on'           => $this->convertDate($this->pick($row, ['create_on', 'created_on', 'create_date'])),
            'transport_lead_time' => $transport_lead_time,
            'code_planner'        => $codePlanner,
            'planner'             => $planner,
            'dist_channel'        => $distChannel,
            'area'                => $area,
            'ketersediaan_unit'   => $ketersediaanUnit,

            'pulau'     => $pulau,
            'route'     => $route,
            'via_kirim' => $this->cleanText($row['via_kirim'] ?? $row['via'] ?? null),
            'mobil'     => $mobil,

            'perubahan_mobil'    => $this->cleanText($row['perubahan_mobil'] ?? null),
            'kategori_ekspedisi' => $this->getKategoriEkspedisi($noShipment)
                ?? $this->cleanText($row['kategori_ekspedisi'] ?? null),
            'ekpedisi'           => $ekpedisi,
            'kubikasi'           => $kubikasi,
            'tonase'             => $tonase,
            'total_kubik'        => $totalKubik,
            'total_tonase'       => $totalTonase,

            'nama_driver' => $this->cleanText($row['nama_driver'] ?? null),
            'no_pol'      => $this->cleanText($row['no_pol'] ?? ($row['nopol'] ?? null)),

            'status_pengiriman' => $this->cleanText($row['status'] ?? null),
            'status'            => $this->cleanText($row['status'] ?? null),

            'nilai_muatan' => $nilaiMuatan,
            'biaya_kuli'   => $biayaKuli,

            'tanggal_naik_logistik' => $tanggalNaikLogistik,
            'rencana_kirim'         => $rencanaKirim,
            'tanggal_dpt_unit'      => $tanggalDptUnit,
            'planning_loading'      => $planningLoading,
            'tanggal_tiba_gudang'   => $tanggalTibaGudang,
            'tanggal_keluar_gudang' => $tanggalKeluarGudang,
            'tanggal_tiba'          => $tanggalTibaAktual,
            'tanggal_bongkar'       => $tanggalBongkar,

            // GUDANG 2 (SENTUL)
            'planning_loading_2'      => $planningLoading2,
            'tanggal_tiba_gudang_2'   => $tanggalTibaGudang2,
            'tanggal_keluar_gudang_2' => $tanggalKeluarGudang2,
            'lama_digudang_2'         => $lama_digudang_2,
            'sla_loading_2'           => $sla_loading_2,
            'status_gudang_2'         => $statusGudang2,

            // GUDANG 3 (CCIE)
            'planning_loading_3'      => $planningLoading3,
            'tanggal_tiba_gudang_3'   => $tanggalTibaGudang3,
            'tanggal_keluar_gudang_3' => $tanggalKeluarGudang3,
            'lama_digudang_3'         => $lama_digudang_3,
            'sla_loading_3'           => $sla_loading_3,
            'status_gudang_3'         => $statusGudang3,

            'estimasi_tiba' => $estimasi ? date('Y-m-d', $estimasi) : null,

            'lama_perjalanan'  => $lamaPerjalanan,
            'sla_tiba'         => $slaTiba,
            'overstay_days'    => $overstay,
            'sla_bongkar'      => $slaBongkar,
            'status_akhir'     => $statusAkhir,
            'monitoring_alert' => $monitoringAlert,

            'lama_waktu_pencarian' => $lamaWaktuPencarian,
            'sla_dapat_mobil'      => $slaDapatMobil,
            'lama_digudang'        => $lamaDigudang,
            'sla_loading'          => $slaLoading,

            'keterangan'       => $this->cleanText($row['keterangan'] ?? null),
            'pic_monitoring'   => $picMonitoring,
            'status_kendaraan' => $this->cleanText($row['status_kendaraan'] ?? null),
            'action_required'  => $this->cleanText($row['action_required'] ?? null),

            'act_urutan_bongkar' => $this->cleanText($this->pick($row, ['act_urutan_bongkar', 'ac_turutan_bongkar'])),

            'reason_tiba'    => $this->cleanText($this->pick($row, ['reason_tiba', 'reason_waktu_tiba'])),
            'reason_bongkar' => $this->cleanText($this->pick($row, ['reason_bongkar', 'reason_waktu_bongkar'])),

            'act_pgi_date'     => $actPgiDate,
            'cust_grp_5_desc'  => $this->cleanText($row['cust_grp_5_desc'] ?? null),
            'created_by'       => $this->cleanText($row['created_by'] ?? null),
            'cust_grp_3_desc'  => $this->cleanText($row['cust_grp_3_desc'] ?? null),
            'ship_no'          => $this->cleanText($row['ship_no'] ?? null),
            'cust_desc'        => $this->cleanText($row['cust_desc'] ?? null),
            'addt_text_4'      => $this->cleanText($row['addt_text'] ?? null),
            'service_agent'    => $this->cleanText($row['service_agent'] ?? null),
            'total_do_qty_car' => $totalDoCar,

            // ===== RINCIAN PER DO =====
            'customer_reference' => $this->cleanText($row['customer_reference'] ?? null),
            'sales_document'     => $this->cleanText($row['sales_document'] ?? null),
            'contract_end_date'  => $this->convertDateUs($row['contract_end_date'] ?? null),   // format m/d/Y
            'no_do'              => $this->cleanText($row['no_do'] ?? null),
            'tanggal_shipment'   => $this->convertDateUs($row['tanggal_shipment'] ?? null),    // format m/d/Y
            'periode_shipment'   => $this->convertPeriode($row['periode_shipment'] ?? null),   // teks MM-YYYY
        ];

        // ================= SISA HEADER EXCEL (apa adanya) =================
        // header yang cocok dengan kolom DB tapi belum diisi di atas.
        // Kolom hasil hitung ulang tidak pernah diisi dari Excel.
        foreach ($this->map as $header => [$col, $kind]) {
            if (in_array($col, self::RECALC_COLUMNS, true)) continue;
            if (($attrs[$col] ?? null) !== null) continue;

            $v = $this->convert($row[$header] ?? null, $kind, $col);
            if ($v !== null) {
                $attrs[$col] = $v;
            }
        }

        return $attrs;
    }

    private function applyRouteAlias(?string $route): ?string
    {
        if ($route === null || $route === '') return $route;

        return self::ROUTE_ALIASES[$this->normalize($route)] ?? $route;
    }

    private function generateStatusAlert($sla_tiba, $sla_bongkar): array
    {
        if ($sla_tiba === null || $sla_bongkar === null) {
            return ['status_akhir' => null, 'alert' => null];
        }

        $sla_tiba    = strtolower(trim($sla_tiba));
        $sla_bongkar = strtolower(trim($sla_bongkar));

        if ($sla_tiba == 'on time' && $sla_bongkar == 'on time') {
            return ['status_akhir' => 'On Time Total', 'alert' => 'Delivered On Time'];
        }
        if ($sla_tiba == 'delay' && $sla_bongkar == 'on time') {
            return ['status_akhir' => 'Delay Perjalanan', 'alert' => 'Delay Perjalanan'];
        }
        if ($sla_tiba == 'on time' && $sla_bongkar == 'delay') {
            return ['status_akhir' => 'Delay Pembongkaran', 'alert' => 'Delay Pembongkaran'];
        }
        return ['status_akhir' => 'Delay Total', 'alert' => 'Delivered Delay'];
    }

    // =========================================================
    // MAPPING HEADER -> KOLOM DB
    // =========================================================
    private function buildMap(array $headers): void
    {
        $this->map = [];

        foreach ($headers as $h) {
            if (!is_string($h) || $h === '') continue;

            $col = isset($this->dbColumns[$h]) ? $h : (self::HEADER_ALIASES[$h] ?? null);

            if ($col === null || !isset($this->dbColumns[$col])) {
                $this->ignoredHeaders[] = $h;
                continue;
            }

            $this->map[$h] = [$col, $this->kind($this->dbColumns[$col])];
        }
    }

    private function kind(string $type): string
    {
        if (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) return 'datetime';
        if (str_starts_with($type, 'date')) return 'date';
        if (str_starts_with($type, 'time')) return 'time';
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $type)) return 'int';
        if (preg_match('/^(decimal|numeric|float|double)/', $type)) return 'decimal';
        return 'string';
    }

    // =========================================================
    // HELPERS
    // =========================================================
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $v) {
            if (!$this->blank($v)) return false;
        }
        return true;
    }

    /** PIC kosong -> isi dari area; nama lama diganti (Asti -> Emyr) */
    private function fixPic(?string $pic, ?string $area): ?string
    {
        $pic = $pic !== null ? trim($pic) : null;

        if ($pic === null || $pic === '') {
            $pic = self::AREA_PIC[strtoupper(trim((string) $area))] ?? null;
        }

        return $pic !== null ? (self::PIC_RENAME[strtolower($pic)] ?? $pic) : null;
    }

    private function blank($value): bool
    {
        if ($value === null) return true;

        if (is_string($value)) {
            $s = trim($value);
            return $s === '' || in_array($s, ['#VALUE!', '#N/A', '#REF!', '#DIV/0!'], true);
        }

        return false;
    }

    private function cleanText($value): ?string
    {
        if ($value === null || is_array($value)) return null;

        if (is_float($value) && floor($value) == $value && abs($value) < 1e15) {
            return (string) (int) $value;           // 4200067817.0 -> "4200067817"
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '-' || in_array($value, ['#VALUE!', '#N/A', '#REF!', '#DIV/0!'], true)) {
            return null;
        }
        if (stripos($value, '(blank)') !== false) return null;
        if (str_starts_with($value, '=')) return null;   // formula yang tidak terhitung

        return $value;
    }

    private function pick(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && !$this->blank($row[$k]) && $row[$k] !== '-') {
                return $row[$k];
            }
        }
        return null;
    }

    private function getKategoriEkspedisi(?string $noShipment): ?string
    {
        $no = trim((string) $noShipment);

        if (str_starts_with($no, '45')) return 'Kontrak';
        if (str_starts_with($no, '42')) return 'Oncall';

        return null;
    }

    private function convertDate($value)
    {
        return $this->toDate($value, 'Y-m-d');
    }

    private function convertDateTime($value)
    {
        return $this->toDate($value, 'Y-m-d H:i:s');
    }

    /** periode shipment = teks "MM-YYYY", bukan tanggal */
    private function convertPeriode($value): ?string
    {
        if ($this->blank($value) || $value === '-' || stripos((string) $value, 'blank') !== false) return null;

        // kalau sel Excel bertipe tanggal (serial number)
        if (is_numeric($value)) {
            if ((float) $value <= 0) return null;
            return Date::excelToDateTimeObject($value)->format('m-Y');
        }

        $v = trim((string) $value);

        // 11-2026 / 11/2026
        if (preg_match('#^(\d{1,2})[-/](\d{4})$#', $v, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
            return sprintf('%02d-%04d', (int) $m[1], (int) $m[2]);
        }

        // 2026-11
        if (preg_match('#^(\d{4})[-/](\d{1,2})$#', $v, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return sprintf('%02d-%04d', (int) $m[2], (int) $m[1]);
        }

        // format lain (misal tanggal lengkap) -> ambil bulan & tahunnya
        $ts = strtotime($v);
        return $ts ? date('m-Y', $ts) : null;
    }

    /** tanggal format US m/d/Y (Contract End Date, Tanggal Shipment), "(blank)" -> null */
    private function convertDateUs($value): ?string
    {
        if ($this->blank($value) || $value === '-' || stripos((string) $value, 'blank') !== false) return null;

        // serial number dari Excel (sel bertipe Date)
        if (is_numeric($value)) {
            if ((float) $value <= 0) return null;
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $v = trim((string) $value);

        // teks 10/28/2026 (m/d/Y); kalau angka pertama > 12 berarti d/m/Y
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            [$a, $b, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            [$mo, $d] = $a > 12 ? [$b, $a] : [$a, $b];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }

        // format lain (2026-10-28 dst)
        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function toDate($value, string $format): ?string
    {
        if ($this->blank($value) || $value === '-') return null;

        if (is_numeric($value)) {
            if ((float) $value <= 0) return null;
            return Date::excelToDateTimeObject($value)->format($format);
        }

        // 04-09-2026 / 04/09/2026 -> d-m-Y
        $ts = strtotime(str_replace('/', '-', trim((string) $value)));
        return $ts ? date($format, $ts) : null;
    }

    private function toTime($value): ?string
    {
        if ($this->blank($value) || $value === '-') return null;

        if (is_numeric($value)) {
            return gmdate('H:i:s', (int) round(fmod((float) $value, 1) * 86400));
        }

        $ts = strtotime(trim((string) $value));
        return $ts ? date('H:i:s', $ts) : null;
    }

    private function parseNumber($value): ?float
    {
        if ($value === null) return null;
        if (is_int($value) || is_float($value)) return (float) $value;

        $s = trim(str_ireplace(['Rp', ' ', '%'], '', (string) $value));
        if ($s === '' || $s === '-') return null;

        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {
            $s = str_replace(',', '', $s);                         // 76,559,246
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {
            $s = str_replace(['.', ','], ['', '.'], $s);           // 76.559.246,5
        } elseif (preg_match('/^-?\d+,\d+$/', $s)) {
            $s = str_replace(',', '.', $s);                        // 12,5
        } else {
            $s = str_replace(',', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /** konversi nilai Excel sesuai tipe kolom DB (untuk header yang dicocokkan otomatis) */
    private function convert($value, string $kind, string $col)
    {
        if ($this->blank($value)) return null;
        if (is_string($value)) $value = trim($value);

        switch ($kind) {
            case 'date':
                return $col === 'contract_end_date'
                    ? $this->convertDateUs($value)
                    : $this->toDate($value, 'Y-m-d');

            case 'datetime':
                return $this->toDate($value, 'Y-m-d H:i:s');

            case 'time':
                return $this->toTime($value);

            case 'int':
                $n = $this->parseNumber($value);
                return $n === null ? null : (int) round($n);

            case 'decimal':
                return $this->parseNumber($value);

            default: // string
                if (is_numeric($value)) {
                    if (preg_match('/^waktu_/', $col)) {
                        return $this->toTime($value);
                    }
                    if (
                        $value > 20000 && $value < 80000
                        && preg_match('/(^|_)(tanggal|date|etd|eta|atd|ata|estimasi)(_|$)|^planning|^rencana|^create_on/', $col)
                    ) {
                        return $this->toDate($value, 'Y-m-d');
                    }
                }
                return $this->cleanText($value);
        }
    }

    private function normalize(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s*-\s*/', '-', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));
        return strtolower($value);
    }

    private function normalizeMobil(?string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $value);
        return strtolower(preg_replace('/\s+/', ' ', trim($value)));
    }

    /** huruf kecil, buang badan usaha di depan (PT/CV/UD/PD), buang spasi, titik, tanda baca */
    private function ekspedisiKey(?string $v): string
    {
        $k = strtolower($this->normalize($v));
        $k = preg_replace('/^(pt|cv|ud|pd)\b[\s\.]*/', '', $k);
        return preg_replace('/[^a-z0-9]+/', '', $k);
    }

    /** cari nama ekspedisi di master (khusus route ini) yang paling mirip dengan nama di Excel */
    private function resolveEkspedisiName(?string $name, $candidates): ?string
    {
        $key = $this->ekspedisiKey($name);
        if ($key === '') return null;

        if (isset(self::EKSPEDISI_ALIASES[$key])) {
            return self::EKSPEDISI_ALIASES[$key];
        }

        $best = null;
        $bestScore = 0.0;
        $second = 0.0;

        foreach ($candidates->pluck('ekpedisi')->filter()->unique() as $master) {
            $mk = $this->ekspedisiKey($master);
            if ($mk === '') continue;

            if ($mk === $key) return $master;

            similar_text($key, $mk, $pct);
            $score = $pct / 100;

            if (
                min(strlen($key), strlen($mk)) >= 6
                && (str_contains($mk, $key) || str_contains($key, $mk))
            ) {
                $score = max($score, 0.9);
            }

            if ($score > $bestScore) {
                $second = $bestScore;
                $bestScore = $score;
                $best = $master;
            } elseif ($score > $second) {
                $second = $score;
            }
        }

        return ($best !== null && $bestScore >= 0.85 && ($bestScore - $second) >= 0.05) ? $best : null;
    }

    private function findTarif(?string $route, ?string $ekpedisi, ?string $mobil)
    {
        $mobilExcel = $this->normalizeMobil($mobil);
        $routeKey   = $this->normalize($route);
        $candidates = self::$tarifByRoute[$routeKey] ?? null;

        if (!$candidates || $mobilExcel === '' || $this->ekspedisiKey($ekpedisi) === '') {
            return null;
        }

        $cacheKey = $routeKey . '|' . $ekpedisi;
        if (!array_key_exists($cacheKey, $this->ekspedisiResolved)) {
            $master = $this->resolveEkspedisiName($ekpedisi, $candidates);
            $this->ekspedisiResolved[$cacheKey] = $master;

            if ($master !== null && $this->ekspedisiKey($master) !== $this->ekspedisiKey($ekpedisi)) {
                logger()->info('LOGISTIK IMPORT: nama ekspedisi disamakan dengan master', [
                    'route'  => $route,
                    'excel'  => $ekpedisi,
                    'master' => $master,
                ]);
            }
        }

        $master = $this->ekspedisiResolved[$cacheKey];
        $found  = null;

        if ($master !== null) {
            $found = $candidates->first(
                fn($row) =>
                $row->ekpedisi === $master
                    && str_starts_with($this->normalizeMobil($row->mobil), $mobilExcel)
            );
        }

        if (!$found) {
            // log cukup 1x per kombinasi (bukan per baris Excel)
            $warnKey = $cacheKey . '|' . $mobilExcel;
            if (!isset($this->tarifWarned[$warnKey])) {
                $this->tarifWarned[$warnKey] = true;
                logger()->warning('LOGISTIK IMPORT: tarif tidak ditemukan', [
                    'route'    => $route,
                    'ekpedisi' => $ekpedisi,
                    'mobil'    => $mobil,
                ]);
            }
        }

        return $found;
    }

    // =========================================================
    // SETELAH IMPORT
    // =========================================================
    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                try {
                    // hanya shipment yang benar-benar ditambah / diubah
                    $shipments = array_map('strval', array_keys($this->touched));

                    // cache sudah tidak dipakai, bebaskan memori
                    $this->shipCache = [];
                    $this->doSet     = [];
                    $this->legacySet = [];

                    if (empty($shipments)) {
                        return;   // tidak ada baris baru, tidak ada yang perlu dihitung ulang
                    }

                    $this->fillShipmentGaps($shipments);
                    $this->fillAreaAndPlannerGaps($shipments);
                    $this->fillMasterGapsFromSiblings($shipments);
                    $this->applyJabodetabekPicRule($shipments);
                    $this->recalcShipments($shipments);
                } catch (\Throwable $e) {
                    logger()->error('LOGISTIK IMPORT AFTER-IMPORT GAGAL', [
                        'error' => $e->getMessage(),
                        'line'  => $e->getLine(),
                    ]);
                    throw $e;   // supaya DB::transaction ikut rollback
                }
            },
        ];
    }

    /**
     * Aturan khusus area JABODETABEK:
     *  - no_shipment diawali 42000 -> pic_monitoring = 'Dani'
     *  - no_shipment diawali 45000 -> pic_monitoring = planner
     */
    private function applyJabodetabekPicRule(array $shipments): void
    {
        if (!isset($this->dbColumns['pic_monitoring'])) return;

        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));

            // 42000xxxxx -> Dani
            DB::update("
                UPDATE logistik_pengiriman
                SET pic_monitoring = 'Dani'
                WHERE no_shipment IN ($in)
                  AND UPPER(TRIM(area)) = 'JABODETABEK'
                  AND no_shipment LIKE '42000%'
            ", $chunk);

            // 45000xxxxx -> sama dengan planner (hanya kalau planner terisi)
            if (isset($this->dbColumns['planner'])) {
                DB::update("
                    UPDATE logistik_pengiriman
                    SET pic_monitoring = TRIM(planner)
                    WHERE no_shipment IN ($in)
                      AND UPPER(TRIM(area)) = 'JABODETABEK'
                      AND no_shipment LIKE '45000%'
                      AND NULLIF(TRIM(planner), '') IS NOT NULL
                ", $chunk);
            }
        }
    }

    /**
     * kolom master yang kosong -> ikut baris lain di no_shipment yang sama.
     * Hanya diisi kalau nilai di shipment itu seragam (1 nilai unik).
     */
    private function fillMasterGapsFromSiblings(array $shipments): void
    {
        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));

            foreach (self::SIBLING_FILL_COLUMNS as $col) {
                if (!isset($this->dbColumns[$col])) continue;

                DB::update("
                    UPDATE logistik_pengiriman lp
                    JOIN (
                        SELECT no_shipment, MIN(TRIM($col)) AS val
                        FROM logistik_pengiriman
                        WHERE NULLIF(TRIM($col), '') IS NOT NULL
                          AND no_shipment IN ($in)
                        GROUP BY no_shipment
                        HAVING COUNT(DISTINCT TRIM($col)) = 1
                    ) x ON lp.no_shipment = x.no_shipment
                    SET lp.$col = x.val
                    WHERE NULLIF(TRIM(lp.$col), '') IS NULL
                      AND lp.no_shipment IN ($in)
                ", array_merge($chunk, $chunk));
            }
        }
    }

    /**
     * 1) area kosong -> ikut area baris lain di no_shipment yang sama (kalau seragam)
     * 2) planner / pic_monitoring kosong -> isi dari master berdasarkan area
     *    (digabung: 1 UPDATE per kolom pakai CASE, bukan 1 UPDATE per area)
     */
    private function fillAreaAndPlannerGaps(array $shipments): void
    {
        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));

            DB::update("
                UPDATE logistik_pengiriman lp
                JOIN (
                    SELECT no_shipment, MIN(TRIM(area)) AS val
                    FROM logistik_pengiriman
                    WHERE NULLIF(TRIM(area), '') IS NOT NULL
                      AND no_shipment IN ($in)
                    GROUP BY no_shipment
                    HAVING COUNT(DISTINCT TRIM(area)) = 1
                ) x ON lp.no_shipment = x.no_shipment
                SET lp.area = x.val
                WHERE NULLIF(TRIM(lp.area), '') IS NULL
                  AND lp.no_shipment IN ($in)
            ", array_merge($chunk, $chunk));

            foreach (['planner' => 'planner', 'pic_monitoring' => 'pic'] as $col => $key) {
                if (!isset($this->dbColumns[$col])) continue;

                $cases     = [];
                $caseBind  = [];
                $areasUsed = [];

                foreach ((self::$areaMap ?? []) as $area => $info) {
                    if (empty($info[$key])) continue;

                    $cases[] = 'WHEN ? THEN ?';
                    array_push($caseBind, (string) $area, $info[$key]);
                    $areasUsed[] = (string) $area;
                }

                if (empty($cases)) continue;

                $inArea = implode(',', array_fill(0, count($areasUsed), '?'));

                DB::update(
                    "UPDATE logistik_pengiriman
                     SET {$col} = CASE area " . implode(' ', $cases) . " END
                     WHERE no_shipment IN ($in)
                       AND NULLIF(TRIM({$col}), '') IS NULL
                       AND area IN ($inArea)",
                    array_merge($caseBind, $chunk, $areasUsed)
                );
            }
        }
    }

    /** isi route/mobil/ekpedisi yang kosong dari baris lain dalam shipment yang sama */
    private function fillShipmentGaps(array $shipments): void
    {
        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));

            foreach (['route', 'mobil', 'ekpedisi'] as $col) {
                DB::update("
                    UPDATE logistik_pengiriman lp
                    JOIN (
                        SELECT no_shipment, MIN($col) AS val
                        FROM logistik_pengiriman
                        WHERE $col IS NOT NULL AND $col != ''
                          AND no_shipment IN ($in)
                        GROUP BY no_shipment
                    ) x ON lp.no_shipment = x.no_shipment
                    SET lp.$col = x.val
                    WHERE (lp.$col IS NULL OR lp.$col = '')
                      AND lp.no_shipment IN ($in)
                ", array_merge($chunk, $chunk));
            }
        }
    }

    /**
     * kirim_kontribusi DO : biaya 1 mobil x total_kubik DO / SUM total_kubik shipment
     * biaya_kirim tujuan  : SUM kirim_kontribusi DO di tujuan itu
     * CR                  : biaya_kirim / SUM nilai_muatan tujuan x 100
     * hasil_kubik/tonase  : per baris = total / kapasitas x 100
     * pengiriman_optimal  : per shipment (SUM total / kapasitas >= 85%)
     * Kalau kapasitas tidak ketahui, nilai lama dipertahankan (tidak di-NULL-kan).
     */
    private function recalcShipments(array $shipments): void
    {
        foreach (array_chunk($shipments, 500) as $chunk) {
            $in       = implode(',', array_fill(0, count($chunk), '?'));
            $bindings = array_merge($chunk, $chunk);

            // ---- 1) KIRIM KONTRIBUSI per DO ----
            if (isset($this->dbColumns['kirim_kontribusi'])) {
                foreach ($this->groupBiaya as [$sh, $pool]) {
                    if (!in_array((string) $sh, $chunk, true)) continue;

                    DB::update("
                        UPDATE logistik_pengiriman lp
                        CROSS JOIN (
                            SELECT SUM(COALESCE(total_kubik,0)) AS sum_kubik, COUNT(*) AS cnt
                            FROM logistik_pengiriman
                            WHERE no_shipment = ?
                        ) g
                        SET lp.kirim_kontribusi = CASE
                            WHEN g.sum_kubik > 0 THEN ROUND(? * COALESCE(lp.total_kubik,0) / g.sum_kubik, 2)
                            ELSE ROUND(? / g.cnt, 2)
                        END
                        WHERE lp.no_shipment = ?
                    ", [$sh, $pool, $pool, $sh]);
                }

                // ---- 2) BIAYA KIRIM per tujuan ----
                DB::update("
                    UPDATE logistik_pengiriman lp
                    JOIN (
                        SELECT no_shipment, COALESCE(tujuan,'') AS tj,
                               SUM(COALESCE(kirim_kontribusi,0)) AS total
                        FROM logistik_pengiriman
                        WHERE no_shipment IN ($in)
                        GROUP BY no_shipment, COALESCE(tujuan,'')
                    ) g ON lp.no_shipment = g.no_shipment AND COALESCE(lp.tujuan,'') = g.tj
                    SET lp.biaya_kirim = g.total
                    WHERE lp.no_shipment IN ($in)
                      AND g.total > 0
                ", $bindings);
            }

            // ---- 3) CR per shipment + tujuan ----
            DB::update("
                UPDATE logistik_pengiriman lp
                JOIN (
                    SELECT no_shipment, COALESCE(tujuan,'') AS tj,
                           SUM(COALESCE(nilai_muatan,0)) AS sum_nilai
                    FROM logistik_pengiriman
                    WHERE no_shipment IN ($in)
                    GROUP BY no_shipment, COALESCE(tujuan,'')
                ) g ON lp.no_shipment = g.no_shipment AND COALESCE(lp.tujuan,'') = g.tj
                SET lp.cr = IF(g.sum_nilai <= 0, 0,
                               ROUND(COALESCE(lp.biaya_kirim,0) / g.sum_nilai * 100, 4))
                WHERE lp.no_shipment IN ($in)
            ", $bindings);

            // ---- 4) HASIL KUBIK / TONASE (per baris) + OPTIMAL (per shipment) ----
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
                        WHEN x.kubikasi > 0 THEN ROUND(COALESCE(lp.total_kubik, 0) / x.kubikasi * 100, 2)
                        ELSE lp.hasil_kubik
                    END,
                    lp.hasil_tonase = CASE
                        WHEN x.tonase > 0 THEN ROUND(COALESCE(lp.total_tonase, 0) / x.tonase * 100, 2)
                        ELSE lp.hasil_tonase
                    END,
                    lp.pengiriman_optimal = CASE
                        WHEN (x.kubikasi > 0 AND (x.sum_kubik  / x.kubikasi * 100) >= 85)
                          OR (x.tonase   > 0 AND (x.sum_tonase / x.tonase   * 100) >= 85)
                            THEN 'OPTIMAL'
                        WHEN x.kubikasi > 0 OR x.tonase > 0
                            THEN 'TIDAK OPTIMAL'
                        ELSE lp.pengiriman_optimal
                    END
                WHERE lp.no_shipment IN ($in)
            ", $bindings);

            // ---- 5) OPTIMAL PER CUSTOMER ----
            if (isset($this->dbColumns['pengiriman_optimal_customer'])) {
                DB::update("
                    UPDATE logistik_pengiriman lp
                    SET lp.pengiriman_optimal_customer = CASE
                        WHEN COALESCE(lp.hasil_kubik, 0)  >= 85
                          OR COALESCE(lp.hasil_tonase, 0) >= 85 THEN 'OPTIMAL'
                        WHEN lp.hasil_kubik IS NOT NULL
                          OR lp.hasil_tonase IS NOT NULL THEN 'TIDAK OPTIMAL'
                        ELSE lp.pengiriman_optimal_customer
                    END
                    WHERE lp.no_shipment IN ($in)
                ", $chunk);
            }
        }
    }
}