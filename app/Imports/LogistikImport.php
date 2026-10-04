<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class LogistikImport implements ToCollection, WithHeadingRow, WithEvents, WithCalculatedFormulas
{
    private const TABLE       = 'logistik_pengiriman';
    private const TARIF_TABLE = 'tarif_pengiriman';

    // kolom yang tidak pernah diisi dari Excel
    private const SKIP_COLUMNS = ['id', 'created_at', 'updated_at'];

    private const ROUTE_ALIASES = [
        'jabodetabek' => 'Sentul-Jabodetabek',
    ];

    // header Excel (sudah di-slug) -> kolom DB, hanya untuk yang namanya BEDA
    private const HEADER_ALIASES = [
        'tgl_terima_dari_admin'  => 'tanggal_naik_logistik',
        'tgl_rencana_kirim'      => 'rencana_kirim',
        'tanggal_tiba_di_gudang' => 'tanggal_tiba_gudang',
        'ac_turutan_bongkar'     => 'act_urutan_bongkar',
        'reason_waktu_tiba'      => 'reason_tiba',
        'reason_waktu_bongkar'   => 'reason_bongkar',
        'nilai_muatan_rp'        => 'nilai_muatan',
        'biaya_kirim_rp'         => 'biaya_kirim',
        'nopol'                  => 'no_pol',
        'via'                    => 'via_kirim',
        'kode_planner'           => 'code_planner',
    ];

    private static $customerMap  = null;
    private static $tarifByRoute = null;
    private static $plannerMap   = null;   // code_planner => nama planner

    private array $allColumns = [];        // semua kolom tabel
    private array $dbColumns  = [];        // [kolom => tipe], tanpa SKIP_COLUMNS
    private ?array $map = null;            // [header => [kolom, kind]]
    private bool $hasCreatedAt = false;
    private bool $hasUpdatedAt = false;

    // forward-fill (sel merge di Excel)
    private $lastNoShipmentRaw = null;     // no_shipment merge
    private $lastNoShipment    = null;     // reset route/mobil/ekpedisi per shipment
    private $lastRoute         = null;
    private $lastMobil         = null;
    private $lastEkpedisi      = null;

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

    public function getInsertedCount(): int { return $this->inserted; }
    public function getUpdatedCount(): int { return $this->updated; }
    public function getImportedCount(): int { return $this->inserted + $this->updated; }
    public function getSkippedCount(): int { return $this->skipped; }
    public function getFailedCount(): int { return $this->failed; }
    public function getFailedList(): array { return $this->failedList; }
    public function getAllNoShipmentInFile(): array { return $this->allNoShipmentInFile; }
    public function getIgnoredHeaders(): array { return array_values(array_unique($this->ignoredHeaders)); }
    public function getMissingColumns(): array { return array_keys($this->missingColumns); }
    public function getUnknownPlannerCodes(): array { return array_values(array_unique($this->unknownPlannerCodes)); }

    public function __construct()
    {
        foreach (DB::select('SHOW COLUMNS FROM `' . self::TABLE . '`') as $c) {
            $this->allColumns[] = $c->Field;

            if (!in_array($c->Field, self::SKIP_COLUMNS, true)) {
                $this->dbColumns[$c->Field] = strtolower($c->Type);
            }
        }

        $this->hasCreatedAt = in_array('created_at', $this->allColumns, true);
        $this->hasUpdatedAt = in_array('updated_at', $this->allColumns, true);

        if (self::$customerMap === null) {
            self::$customerMap = DB::table('tujuanfillterr')
                ->select('tujuan', 'dist_channel', 'pulau', 'area', 'biaya_kuli', 'transport_lead_time', 'Monitoring')
                ->where('Div', 'HO Meruya')
                ->get()
                ->keyBy(fn($row) => strtolower(trim($row->tujuan)));
        }

        if (self::$tarifByRoute === null) {
            self::$tarifByRoute = DB::table(self::TARIF_TABLE)
                ->select('ekpedisi', 'route', 'mobil', 'biaya_kirim', 'kubikasi', 'tonase')
                ->get()
                ->groupBy(fn($row) => $this->normalize($row->route));
        }

        if (self::$plannerMap === null) {
            self::$plannerMap = DB::table('tujuanfillterr')
                ->select('code_planner', 'Planner')
                ->whereNotNull('code_planner')->where('code_planner', '!=', '')
                ->whereNotNull('Planner')->where('Planner', '!=', '')
                ->get()
                ->keyBy(fn($r) => strtolower(trim($r->code_planner)))
                ->map(fn($r) => $r->Planner);
        }
    }

    // =========================================================
    // IMPORT PER BARIS
    // =========================================================
    public function collection(Collection $rows)
    {
        foreach ($rows as $rowCollection) {
            $row = $rowCollection->toArray();

            if ($this->map === null) {
                $this->buildMap(array_keys($row));
            }

            // 1) baris kosong total -> tidak masuk
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

            // 2) no_shipment atau tujuan kosong -> tidak masuk
            if ($noShipment === null || $tujuan === null) {
                $this->skipped++;
                continue;
            }

            $this->allNoShipmentInFile[] = $noShipment;

            try {
                $attrs = $this->buildAttributes($row, $noShipment, $tujuan);
                $this->save($noShipment, $tujuan, $attrs);
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

        if (!empty($this->ignoredHeaders)) {
            logger()->info('LOGISTIK IMPORT: header Excel tanpa kolom DB (diabaikan)', $this->getIgnoredHeaders());
        }
        if (!empty($this->missingColumns)) {
            logger()->warning('LOGISTIK IMPORT: kolom belum ada di tabel (tidak disimpan)', $this->getMissingColumns());
        }
        if (!empty($this->unknownPlannerCodes)) {
            logger()->warning('LOGISTIK IMPORT: code_planner tidak ada di master', $this->getUnknownPlannerCodes());
        }
    }

    // =========================================================
    // SIMPAN (insert / update berdasarkan no_shipment + tujuan)
    // =========================================================
    private function save(string $noShipment, string $tujuan, array $attrs): void
    {
        $attrs = $this->filterToColumns($attrs);

        if (isset($this->dbColumns['no_shipment'])) $attrs['no_shipment'] = $noShipment;
        if (isset($this->dbColumns['tujuan']))      $attrs['tujuan']      = $tujuan;

        $now = now();

        $existingId = DB::table(self::TABLE)
            ->where('no_shipment', $noShipment)
            ->where('tujuan', $tujuan)
            ->value('id');

        if ($existingId) {
            if ($this->hasUpdatedAt) $attrs['updated_at'] = $now;

            DB::table(self::TABLE)->where('id', $existingId)->update($attrs);
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

        DB::table(self::TABLE)->insert($attrs);
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

        // ================= BIAYA KIRIM & KAPASITAS DARI TARIF =================
        $tarifRow    = $this->findTarif($route, $ekpedisi, $mobil);
        $biayaExcel  = $this->parseNumber($this->pick($row, ['biaya_kirim', 'biaya_kirim_rp']));
        $biayaKirim  = ($biayaExcel !== null && $biayaExcel > 0)
            ? $biayaExcel
            : ($tarifRow ? $this->parseNumber($tarifRow->biaya_kirim) : null);

        $kubikasi = $tarifRow ? $this->parseNumber($tarifRow->kubikasi ?? null) : null;
        $tonase   = $tarifRow ? $this->parseNumber($tarifRow->tonase ?? null)   : null;

        // ================= LOOKUP MASTER TUJUAN =================
        $tujuanKey    = preg_replace('/\s+/', ' ', trim(strtolower($tujuan)));
        $customerData = self::$customerMap[$tujuanKey] ?? null;

        $distChannel         = $customerData->dist_channel ?? null;
        $area                = $customerData->area ?? null;
        $picMonitoring       = $customerData->Monitoring ?? null;
        $biayaKuli           = $customerData->biaya_kuli ?? null;
        $transport_lead_time = $customerData->transport_lead_time ?? null;
        $pulau               = ($customerData->pulau ?? null) ?: $this->cleanText($row['pulau'] ?? null);

        // ================= PLANNER (dari code_planner, BUKAN dari tujuan) =================
        $codePlanner = $this->cleanText($this->pick($row, ['code_planner', 'kode_planner']));
        $planner     = null;

        if ($codePlanner !== null) {
            $planner = self::$plannerMap[strtolower($codePlanner)] ?? null;

            if ($planner === null) {
                $this->unknownPlannerCodes[] = $codePlanner;
            }
        }

        $planner = $planner ?: $this->cleanText($row['planner'] ?? null);

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

        // cr / hasil_kubik / hasil_tonase / pengiriman_optimal dihitung di recalcShipments()
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
            'biaya_kirim'  => $biayaKirim,

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

            'act_pgi_date'    => $actPgiDate,
            'cust_grp_5_desc' => $this->cleanText($row['cust_grp_5_desc'] ?? null),
            'created_by'      => $this->cleanText($row['created_by'] ?? null),
            'cust_grp_3_desc' => $this->cleanText($row['cust_grp_3_desc'] ?? null),
            'ship_no'         => $this->cleanText($row['ship_no'] ?? null),
            'cust_desc'       => $this->cleanText($row['cust_desc'] ?? null),
            'addt_text_4'     => $this->cleanText($row['addt_text'] ?? null),
            'service_agent'   => $this->cleanText($row['service_agent'] ?? null),
            'total_do_qty_car' => $totalDoCar,
        ];

        // ================= SISA HEADER EXCEL (apa adanya) =================
        // header yang cocok dengan kolom DB tapi belum diisi di atas
        // (waktu_*, reason_gudang*, remarks, nama_kapal, etd/eta/atd/ata, dst)
        foreach ($this->map as $header => [$col, $kind]) {
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
            $s = str_replace(',', '', $s);                         // 76,559,246 / 911,032
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
                return $this->toDate($value, 'Y-m-d');

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
                    if ($value > 20000 && $value < 80000
                        && preg_match('/(^|_)(tanggal|date|etd|eta|atd|ata|estimasi)(_|$)|^planning|^rencana|^create_on/', $col)) {
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

    private function findTarif(?string $route, ?string $ekpedisi, ?string $mobil)
    {
        $mobilExcel  = $this->normalizeMobil($mobil);
        $ekpedisiKey = $ekpedisi !== null ? $this->normalize($ekpedisi) : '';

        $candidates = self::$tarifByRoute[$this->normalize($route)] ?? null;

        if (!$candidates || $mobilExcel === '') {
            return null;
        }

        if ($ekpedisiKey !== '') {
            $strict = $candidates->first(function ($row) use ($ekpedisiKey, $mobilExcel) {
                return $this->normalize($row->ekpedisi) === $ekpedisiKey
                    && str_starts_with($this->normalizeMobil($row->mobil), $mobilExcel);
            });
            if ($strict) return $strict;
        }

        return $candidates->first(function ($row) use ($mobilExcel) {
            return str_starts_with($this->normalizeMobil($row->mobil), $mobilExcel);
        });
    }

    // =========================================================
    // SETELAH IMPORT
    // =========================================================
    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                try {
                    $shipments = array_map('strval', array_values(array_unique($this->allNoShipmentInFile)));

                    $this->fillShipmentGaps($shipments);
                    $this->recalcShipments($shipments);
                } catch (\Throwable $e) {
                    logger()->error('LOGISTIK IMPORT AFTER-IMPORT GAGAL', [
                        'error' => $e->getMessage(),
                        'line'  => $e->getLine(),
                    ]);
                }
            },
        ];
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
     * CR: per baris sesuai kontribusi muatan.
     * Hasil kubik/tonase/optimal: SUM total per shipment / kapasitas.
     */
    private function recalcShipments(array $shipments): void
    {
        foreach (array_chunk($shipments, 500) as $chunk) {
            $in       = implode(',', array_fill(0, count($chunk), '?'));
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

            // ---- HASIL KUBIK / TONASE / OPTIMAL ----
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
}