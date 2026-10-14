<?php

namespace App\Imports;

use App\Models\LogistikPengirimanPasuruan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import data pengiriman Pasuruan. Aturan otomatis disamakan dengan
 * PasuruanController (update / autosaveRow / refreshShipmentCalc).
 *
 * Semua kolom input di renderRowColumnsPasuruan bisa diisi dari Excel
 * (header = nama kolom DB, lihat daftar di bawah). Kolom hasil hitungan
 * (hasil_kubik, hasil_tonase, pengiriman_optimal, cr, estimasi_tiba,
 * lama_perjalanan, sla_tiba, overstay_days, sla_bongkar, lama pencarian,
 * total biaya kuli, dll) TIDAK perlu ada di file, dihitung otomatis.
 *
 * FASE 1 (collection, per baris): simpan data mentah + hasil lookup master,
 *   tarif, qty, biaya kuli.
 * FASE 2 (AfterImport, per shipment): isi kolom level-shipment yang kosong,
 *   estimasi_tiba (multi-drop), lama pencarian, SLA, monitoring alert,
 *   action required, CR, hasil kubik/tonase, pengiriman optimal.
 *
 * Kunci upsert: no_shipment_pasuruan + tujuan_pasuruan
 *
 * HEADER TEMPLATE (urutan grid):
 *   no_shipment_pasuruan, tujuan_pasuruan, planner_pasuruan,
 *   tanggal_terima_po_pasuruan, rencana_kirim_pasuruan, tanggal_dpt_unit_pasuruan,
 *   planning_loading_pasuruan, tanggal_tiba_gudang_pasuruan, tanggal_keluar_gudang_pasuruan,
 *   route_pasuruan, pulau_pasuruan, area_pasuruan, via_kirim_pasuruan, dist_channel_pasuruan,
 *   kategori_ekspedisi_pasuruan, ekspedisi_pasuruan, transport_lead_time_pasuruan,
 *   mobil_pasuruan, nama_driver_pasuruan, no_pol_pasuruan, total_do_pasuruan,
 *   nilai_muatan_pasuruan, kubikasi_pasuruan, tonase_pasuruan,
 *   total_kubik_pasuruan, total_tonase_pasuruan, reason_pengiriman_optimal_pasuruan,
 *   biaya_kirim_pasuruan, pic_monitoring_pasuruan, act_pgi_date_pasuruan,
 *   act_urutan_bongkar_pasuruan, selisih_quantity_pasuruan, actual_delivery_quantity_pasuruan,
 *   biaya_kuli_pasuruan, reason_selisih_quantity_pasuruan,
 *   tanggal_tiba_pasuruan, tanggal_bongkar_pasuruan,
 *   reason_waktu_tiba_pasuruan, reason_waktu_bongkar_pasuruan, remarks_pasuruan,
 *   nama_kapal_pasuruan, etd_pasuruan, eta_pasuruan, atd_pasuruan, ata_pasuruan
 *
 * Header kembar (tanggal_tiba_pasuruan 2x, route_pasuruan 2x) tidak bisa
 * dibedakan WithHeadingRow. Ganti kolom ke-5 menjadi
 * tanggal_tiba_gudang_pasuruan dan hapus salah satu route_pasuruan.
 */
class PasuruanImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas, WithEvents
{
    private const TABLE       = 'logistik_pengiriman_pasuruan';
    private const TARIF_TABLE = 'tarif_pengiriman';

    private const ROUTE_ALIASES = [
        'jabodetabek' => 'Sentul-Jabodetabek',
    ];

    // kolom level-shipment (sama dengan $shipmentFields di controller):
    // kalau kosong di satu baris, diisi dari baris lain di no_shipment yang sama
    private const SHIPMENT_FILL = [
        'planner_pasuruan', 'route_pasuruan', 'pulau_pasuruan', 'area_pasuruan',
        'via_kirim_pasuruan', 'pic_monitoring_pasuruan', 'mobil_pasuruan',
        'perubahan_mobil_pasuruan', 'kategori_ekspedisi_pasuruan', 'ekspedisi_pasuruan',
        'no_pol_pasuruan', 'nama_driver_pasuruan', 'nama_kapal_pasuruan',
        'tanggal_terima_po_pasuruan', 'rencana_kirim_pasuruan', 'tanggal_dpt_unit_pasuruan',
        'planning_loading_pasuruan', 'tanggal_tiba_gudang_pasuruan', 'tanggal_keluar_gudang_pasuruan',
        'etd_pasuruan', 'eta_pasuruan', 'atd_pasuruan', 'ata_pasuruan',
    ];

    private static $customerMap  = null;
    private static $tarifByRoute = null;

    // lead time yang pernah tersimpan di tabel per tujuan (fallback kalau master tidak ketemu)
    private static $leadByTujuan = null;

    // forward-fill state (merged cell di Excel): hanya route/mobil/ekspedisi
    private $lastNoShipment = null;
    private $lastRoute      = null;
    private $lastMobil      = null;
    private $lastEkpedisi   = null;

    // ===== hasil proses =====
    private int $inserted = 0;
    private int $updated  = 0;
    private int $skipped  = 0;
    private int $failed   = 0;
    private array $failedList = [];

    // tujuan yang tidak ada di master (lead time/area/planner/PIC jadi kosong)
    private array $unmatchedTujuan = [];

    // no_shipment di file ini -> dihitung ulang di AfterImport
    private array $touchedShipments = [];

    // lead time yang diketahui (master/file) per shipment; null = tidak diketahui
    private array $shipmentLead = [];

    // shipment yang sudah keluar gudang tapi estimasi tiba tidak bisa dihitung (lead time tidak ketemu)
    private array $noLeadTime = [];

    public function getInsertedCount(): int { return $this->inserted; }
    public function getUpdatedCount(): int { return $this->updated; }
    public function getImportedCount(): int { return $this->inserted + $this->updated; }
    public function getSkippedCount(): int { return $this->skipped; }
    public function getFailedCount(): int { return $this->failed; }
    public function getFailedList(): array { return $this->failedList; }
    public function getUnmatchedTujuan(): array { return array_keys($this->unmatchedTujuan); }
    public function getNoLeadTime(): array { return array_keys($this->noLeadTime); }

    public function __construct()
    {
        if (self::$tarifByRoute === null) {
            self::$tarifByRoute = DB::table(self::TARIF_TABLE)
                ->select('ekpedisi', 'route', 'mobil', 'biaya_kirim', 'kubikasi', 'tonase')
                ->get()
                ->groupBy(fn($row) => $this->normalize($row->route));
        }

        if (self::$leadByTujuan === null) {
            self::$leadByTujuan = DB::table(self::TABLE)
                ->select('tujuan_pasuruan', 'transport_lead_time_pasuruan')
                ->whereNotNull('tujuan_pasuruan')
                ->where('transport_lead_time_pasuruan', '>', 0)
                ->orderBy('id')
                ->get()
                ->mapWithKeys(fn($r) => [
                    $this->normalizeTujuan($r->tujuan_pasuruan) => (int) $r->transport_lead_time_pasuruan,
                ])
                ->all();
        }

        // Master tujuan: WAJIB difilter Div = 'Pasuruan'
        if (self::$customerMap === null) {
            self::$customerMap = DB::table('tujuanfillterr')
                ->select(
                    'tujuan',
                    'dist_channel',
                    'transport_lead_time',
                    'pulau',
                    'area',
                    'Planner',
                    'biaya_kuli',
                    'Monitoring'
                )
                ->where('Div', 'Pasuruan')
                ->get()
                ->keyBy(fn($row) => $this->normalizeTujuan($row->tujuan));
        }
    }

    // =====================================================
    // FASE 1: PER BARIS
    // =====================================================

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $row = $row->toArray();

            $noShipment = $this->cleanText($row['no_shipment_pasuruan'] ?? null);

            if (empty($noShipment)) {
                $this->skipped++;
                continue;
            }

            $tujuan = $this->cleanText($row['tujuan_pasuruan'] ?? null);

            $this->touchedShipments[(string) $noShipment] = true;

            try {
                $model = LogistikPengirimanPasuruan::firstOrNew([
                    'no_shipment_pasuruan' => $noShipment,
                    'tujuan_pasuruan'      => $tujuan,
                ]);

                $model->fill($this->buildAttributes($row, $noShipment, $tujuan, $model))->save();

                if ($model->wasRecentlyCreated) {
                    $this->inserted++;
                } else {
                    $this->updated++;
                }
            } catch (\Throwable $e) {
                $this->failed++;
                $this->failedList[] = [
                    'no_shipment' => $noShipment,
                    'tujuan'      => $tujuan,
                    'error'       => $e->getMessage(),
                    'line'        => $e->getLine(),
                ];

                logger()->error('PASURUAN IMPORT GAGAL PER BARIS', [
                    'no_shipment' => $noShipment,
                    'tujuan'      => $tujuan,
                    'error'       => $e->getMessage(),
                    'file'        => $e->getFile(),
                    'line'        => $e->getLine(),
                ]);
            }
        }
    }

    private function buildAttributes(array $row, string $noShipment, ?string $tujuan, $model): array
    {
        // ================= DATE (dengan alias header template) =================
        $tanggalTerimaPo     = $this->convertDate($row['tanggal_terima_po_pasuruan'] ?? null);
        $rencanaKirim        = $this->convertDate($row['rencana_kirim_pasuruan'] ?? null);
        $tanggalDptUnit      = $this->convertDate($this->pick($row, [
            'tanggal_dpt_unit_pasuruan', 'tanggal_dapat_unit_pasuruan', 'tanggal_dapat_unit',
        ]));
        $planningLoading     = $this->convertDate($row['planning_loading_pasuruan'] ?? null);
        $tanggalTibaGudang   = $this->convertDate($row['tanggal_tiba_gudang_pasuruan'] ?? null);
        $tanggalKeluarGudang = $this->convertDate($this->pick($row, [
            'tanggal_keluar_gudang_pasuruan', 'tanggal_keluar_pasuruan',
        ]));

        // tiba & bongkar: datetime-local di grid, jam ikut disimpan
        $tanggalTiba    = $this->convertDateTime($row['tanggal_tiba_pasuruan'] ?? null);
        $tanggalBongkar = $this->convertDateTime($row['tanggal_bongkar_pasuruan'] ?? null);

        $etd = $this->convertDate($row['etd_pasuruan'] ?? null);
        $eta = $this->convertDate($row['eta_pasuruan'] ?? null);
        $atd = $this->convertDate($row['atd_pasuruan'] ?? null);
        $ata = $this->convertDate($row['ata_pasuruan'] ?? null);

        $actPgiDate = $this->convertDate($row['act_pgi_date_pasuruan'] ?? null);

        // ================= TEXT =================
        $viaKirim          = $this->cleanText($row['via_kirim_pasuruan'] ?? $row['via_pasuruan'] ?? null);
        $shippingPoint     = $this->cleanText($row['shipping_point_pasuruan'] ?? null);
        $ketersediaanUnit  = $this->cleanText($row['ketersediaan_unit_pasuruan'] ?? null);
        $perubahanMobil    = $this->cleanText($row['perubahan_mobil_pasuruan'] ?? null);
        $kategoriEkspedisi = $this->getKategoriEkspedisi($noShipment)
                             ?? $this->cleanText($row['kategori_ekspedisi_pasuruan'] ?? null);
        $statusKendaraan    = $this->cleanText($row['status_kendaraan_pasuruan'] ?? null);
        $namaKapal          = $this->cleanText($row['nama_kapal_pasuruan'] ?? null);
        $transportLaut      = $this->cleanText($row['transport_laut_pasuruan'] ?? null);
        $reasonSelisihQty   = $this->cleanText($this->pick($row, [
            'reason_selisih_quantity_pasuruan', 'reason_selisih_do_pasuruan',
        ]));
        // kolom di tabel: reason_pengiriman_optimal_pasuruan
        $reasonOptimal      = $this->cleanText($this->pick($row, [
            'reason_pengiriman_optimal_pasuruan', 'reason_optimal_pasuruan',
        ]));
        $reasonWaktuTiba    = $this->cleanText($row['reason_waktu_tiba_pasuruan'] ?? null);
        $reasonWaktuBongkar = $this->cleanText($row['reason_waktu_bongkar_pasuruan'] ?? null);
        $remarks            = $this->cleanText($row['remarks_pasuruan'] ?? null);
        $remarksQty         = $this->cleanText($row['remarks_qty_pasuruan'] ?? null);
        $createdBy          = $this->cleanText($row['created_by_pasuruan'] ?? null);
        $noPol              = $this->cleanText($row['no_pol_pasuruan'] ?? null);
        $namaDriver         = $this->cleanText($row['nama_driver_pasuruan'] ?? null);

        // fallback dari Excel kalau tujuan tidak ketemu di master
        $pulauFromFile       = $this->cleanText($row['pulau_pasuruan'] ?? null);
        $areaFromFile        = $this->cleanText($row['area_pasuruan'] ?? null);
        $plannerFromFile     = $this->cleanText($row['planner_pasuruan'] ?? null);
        $picMonitoringExcel  = $this->cleanText($row['pic_monitoring_pasuruan'] ?? null);
        $distChannelFromFile = $this->cleanText($row['dist_channel_pasuruan'] ?? null);

        // ================= NUMBER =================
        $leadTimeRaw      = $row['transport_lead_time_pasuruan'] ?? null;
        $leadTimeFromFile = ($leadTimeRaw === null || $leadTimeRaw === '' || $leadTimeRaw === '-')
            ? null
            : (int) $this->cleanNumber($leadTimeRaw);

        $nilaiMuatan      = $this->cleanNumber($row['nilai_muatan_pasuruan'] ?? null);
        $totalDo          = $this->cleanNumber($row['total_do_pasuruan'] ?? null);
        $actUrutanBongkar = $this->cleanNumber($this->pick($row, [
            'act_urutan_bongkar_pasuruan', 'urutan_bongkar_pasuruan',
        ]));
        $qtyMonitoring    = $this->cleanNumber($row['qty_monitoring_pasuruan'] ?? null);

        // kapasitas/muatan: dibersihkan & dibulatkan 2 desimal (sama dengan
        // cleanDesimalPasuruan di controller)
        $totalKubik  = $this->cleanDesimal($this->pick($row, ['total_kubikasi_pasuruan', 'total_kubik_pasuruan']));
        $totalTonase = $this->cleanDesimal($row['total_tonase_pasuruan'] ?? null);

        // ================= FORWARD-FILL (route/mobil/ekspedisi) =================
        if ($noShipment !== $this->lastNoShipment) {
            $this->lastRoute    = null;
            $this->lastMobil    = null;
            $this->lastEkpedisi = null;
        }

        $route = $this->applyRouteAlias(
            $this->cleanText($row['route_pasuruan'] ?? null) ?: $this->lastRoute
        );
        $mobil     = $this->cleanText($row['mobil_pasuruan'] ?? null)     ?: $this->lastMobil;
        $ekspedisi = $this->cleanText($row['ekspedisi_pasuruan'] ?? null) ?: $this->lastEkpedisi;

        if ($route)     $this->lastRoute    = $route;
        if ($mobil)     $this->lastMobil    = $mobil;
        if ($ekspedisi) $this->lastEkpedisi = $ekspedisi;

        $this->lastNoShipment = $noShipment;

        // ================= BIAYA KIRIM & KAPASITAS DARI TARIF =================
        $tarifRow = $this->findTarif($route, $ekspedisi, $mobil);

        $biayaKirim = $tarifRow
            ? $this->cleanNumberTarif($tarifRow->biaya_kirim)
            : $this->cleanNumber($row['biaya_kirim_pasuruan'] ?? null);

        // kubikasi & tonase: nilai dari file dulu, kalau kosong fallback ke tarif
        $kubikasi = $this->cleanDesimal($row['kubikasi_pasuruan'] ?? null)
            ?? ($tarifRow ? $this->cleanDesimal($tarifRow->kubikasi ?? null) : null);
        $tonase   = $this->cleanDesimal($row['tonase_pasuruan'] ?? null)
            ?? ($tarifRow ? $this->cleanDesimal($tarifRow->tonase ?? null) : null);

        // ================= LOOKUP MASTER (Div = Pasuruan) =================
        $customerData = self::$customerMap[$this->normalizeTujuan($tujuan)] ?? null;

        if (!$customerData && $tujuan) {
            $this->unmatchedTujuan[$tujuan] = true;
        }

        $plannerMaster  = $customerData->Planner ?? null;
        $picMaster      = $customerData->Monitoring ?? null;
        $leadTimeMaster = $customerData->transport_lead_time ?? null;

        $distChannel   = ($customerData->dist_channel ?? null) ?: $distChannelFromFile;
        $area          = ($customerData->area ?? null) ?: $areaFromFile;
        $pulau         = ($customerData->pulau ?? null) ?: $pulauFromFile;
        $planner       = $plannerMaster ?: $plannerFromFile;
        $picMonitoring = $picMaster ?: $picMonitoringExcel;

        // null = lead time tidak diketahui (estimasi & SLA tiba tidak dihitung).
        // Urutan: master (>0) -> file (>0) -> lead time yang pernah tersimpan
        // untuk tujuan yang sama -> 0 eksplisit dari master/file -> null
        $leadTime = $this->resolveLeadTime($leadTimeMaster, $leadTimeFromFile, $tujuan);

        if ($leadTime !== null && (
            !array_key_exists($noShipment, $this->shipmentLead)
            || ($this->shipmentLead[$noShipment] <= 0 && $leadTime > 0)
        )) {
            $this->shipmentLead[$noShipment] = $leadTime;
        }

        // ================= NORMALISASI KETERSEDIAAN UNIT =================
        if ($ketersediaanUnit === null || $ketersediaanUnit === '' || $ketersediaanUnit === '-') {
            $ketersediaanUnit = 'BELUM DAPAT';
        } else {
            $ketersediaanUnit = strtoupper(trim($ketersediaanUnit));
        }

        if (in_array($ketersediaanUnit, ['SUDAH DAPAT MOBIL', 'READY MOBIL', 'READY'])) {
            $ketersediaanUnit = 'SUDAH DAPAT';
        }
        if (in_array($ketersediaanUnit, ['BELUM DAPAT MOBIL', 'PENDING'])) {
            $ketersediaanUnit = 'BELUM DAPAT';
        }

        // ================= QTY: SELISIH <-> ACTUAL (sama dengan controller) =================
        // controller: actual = total_do - selisih. Kalau file hanya punya actual,
        // selisih = total_do - actual. Kalau dua-duanya kosong: baris baru = 0,
        // baris lama dibiarkan (tidak ditimpa).
        $totalDoRaw = $row['total_do_pasuruan'] ?? null;
        $selisihRaw = $this->pick($row, ['selisih_quantity_pasuruan', 'selisih_do_pasuruan']);
        $actualRaw  = $this->pick($row, ['actual_delivery_quantity_pasuruan']);
        $hasTotalDo = $totalDoRaw !== null && $totalDoRaw !== '';

        $qty = [];
        if ($hasTotalDo && $selisihRaw !== null) {
            $selisih = $this->cleanNumber($selisihRaw);
            $qty = ['actual' => $totalDo - $selisih, 'selisih' => $selisih];
        } elseif ($hasTotalDo && $actualRaw !== null) {
            $actual = $this->cleanNumber($actualRaw);
            $qty = ['actual' => $actual, 'selisih' => $totalDo - $actual];
        } elseif (!$model->exists) {
            $qty = ['actual' => 0, 'selisih' => 0];
        }

        $actualForKuli = $qty['actual'] ?? (float) $model->actual_delivery_quantity_pasuruan;

        // ================= BIAYA KULI =================
        // master tujuan dulu, fallback file. total = actual qty x biaya kuli
        $biayaKuli = $this->money($customerData->biaya_kuli ?? null)
                     ?? $this->money($row['biaya_kuli_pasuruan'] ?? null);
        $totalBiayaKuli = $actualForKuli * (float) ($biayaKuli ?? 0);

        $attributes = [
            // ================= BASIC =================
            'transport_lead_time_pasuruan' => $leadTime ?? 0,
            'planner_pasuruan'             => $planner,
            'dist_channel_pasuruan'        => $distChannel,
            'area_pasuruan'                => $area,
            'pulau_pasuruan'               => $pulau,

            'route_pasuruan'     => $route,
            'via_kirim_pasuruan' => $viaKirim,
            'total_do_pasuruan'  => $totalDo,

            'ketersediaan_unit_pasuruan' => $ketersediaanUnit,
            'mobil_pasuruan'             => $mobil,
            'perubahan_mobil_pasuruan'   => $perubahanMobil,

            'nilai_muatan_pasuruan' => $nilaiMuatan,
            'kubikasi_pasuruan'     => $kubikasi,
            'tonase_pasuruan'       => $tonase,
            'total_kubik_pasuruan'  => $totalKubik,
            'total_tonase_pasuruan' => $totalTonase,
            'biaya_kirim_pasuruan'  => $biayaKirim,
            'biaya_kuli_pasuruan'       => $biayaKuli,
            'total_biaya_kuli_pasuruan' => $totalBiayaKuli,

            'kategori_ekspedisi_pasuruan' => $kategoriEkspedisi,
            'ekspedisi_pasuruan'          => $ekspedisi,

            'no_pol_pasuruan'      => $noPol,
            'nama_driver_pasuruan' => $namaDriver,

            // ================= DATE (mentah; SLA dihitung di fase 2) =================
            'tanggal_terima_po_pasuruan'     => $tanggalTerimaPo,
            'rencana_kirim_pasuruan'         => $rencanaKirim,
            'tanggal_dpt_unit_pasuruan'      => $tanggalDptUnit,
            'planning_loading_pasuruan'      => $planningLoading,
            'tanggal_tiba_gudang_pasuruan'   => $tanggalTibaGudang,
            'tanggal_keluar_gudang_pasuruan' => $tanggalKeluarGudang,
            'tanggal_tiba_pasuruan'          => $tanggalTiba,
            'tanggal_bongkar_pasuruan'       => $tanggalBongkar,

            // ================= MONITORING =================
            'pic_monitoring_pasuruan'   => $picMonitoring,
            'status_kendaraan_pasuruan' => $statusKendaraan,

            // ================= KAPAL =================
            'nama_kapal_pasuruan'     => $namaKapal,
            'etd_pasuruan'            => $etd,
            'eta_pasuruan'            => $eta,
            'atd_pasuruan'            => $atd,
            'ata_pasuruan'            => $ata,
            'transport_laut_pasuruan' => $transportLaut,

            // ================= REASON =================
            'reason_pengiriman_optimal_pasuruan' => $reasonOptimal,
            'reason_selisih_quantity_pasuruan'   => $reasonSelisihQty,
            'reason_waktu_tiba_pasuruan'         => $reasonWaktuTiba,
            'reason_waktu_bongkar_pasuruan'      => $reasonWaktuBongkar,
            'remarks_pasuruan'                   => $remarks,
            'remarks_qty_pasuruan'               => $remarksQty,

            // ================= OTHER =================
            'act_pgi_date_pasuruan'       => $actPgiDate,
            'act_urutan_bongkar_pasuruan' => $actUrutanBongkar,
            'shipping_point_pasuruan'     => $shippingPoint,
            'qty_monitoring_pasuruan'     => $qtyMonitoring,
            'created_by_pasuruan'         => $createdBy,
            'create_tgl_pasuruan'         => date('Y-m-d H:i:s'),
        ];

        if ($qty) {
            $attributes['actual_delivery_quantity_pasuruan'] = $qty['actual'];
            $attributes['selisih_quantity_pasuruan']         = $qty['selisih'];
            $attributes['selisih_qty_pasuruan']              = $qty['selisih'];
        }

        return $attributes;
    }

    // =====================================================
    // FASE 2: PER SHIPMENT (AfterImport)
    // =====================================================

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                $this->recalcShipments();
            },
        ];
    }

    private function recalcShipments(): void
    {
        $table     = self::TABLE;
        $shipments = array_map('strval', array_keys($this->touchedShipments));

        foreach (array_chunk($shipments, 500) as $chunk) {
            $grouped = DB::table($table)
                ->whereIn('no_shipment_pasuruan', $chunk)
                ->orderBy('id')
                ->get()
                ->map(fn($r) => (array) $r)
                ->groupBy('no_shipment_pasuruan');

            foreach ($grouped as $no => $rows) {
                $this->recalcShipment((string) $no, $rows->values()->all());
            }

            // hasil kubik / tonase / optimal (rumus sama dengan
            // recalculateHasilKubikTonase di controller)
            $in       = implode(',', array_fill(0, count($chunk), '?'));
            $bindings = array_merge($chunk, $chunk);

            DB::update("
                UPDATE {$table} lp
                JOIN (
                    SELECT no_shipment_pasuruan,
                           SUM(COALESCE(CAST(NULLIF(TRIM(total_kubik_pasuruan), '')  AS DECIMAL(18,4)), 0)) AS sum_kubik,
                           SUM(COALESCE(CAST(NULLIF(TRIM(total_tonase_pasuruan), '') AS DECIMAL(18,4)), 0)) AS sum_tonase,
                           MAX(CAST(NULLIF(TRIM(kubikasi_pasuruan), '') AS DECIMAL(18,4))) AS kubikasi,
                           MAX(CAST(NULLIF(TRIM(tonase_pasuruan), '')   AS DECIMAL(18,4))) AS tonase
                    FROM {$table}
                    WHERE no_shipment_pasuruan IN ($in)
                    GROUP BY no_shipment_pasuruan
                ) x ON lp.no_shipment_pasuruan = x.no_shipment_pasuruan
                SET
                    lp.hasil_kubik_pasuruan = CASE WHEN x.kubikasi > 0
                        THEN ROUND(x.sum_kubik / x.kubikasi * 100, 2) ELSE NULL END,
                    lp.hasil_tonase_pasuruan = CASE WHEN x.tonase > 0
                        THEN ROUND(x.sum_tonase / x.tonase * 100, 2) ELSE NULL END,
                    lp.pengiriman_optimal_pasuruan = CASE
                        WHEN (x.kubikasi > 0 AND (x.sum_kubik  / x.kubikasi * 100) >= 85)
                          OR (x.tonase   > 0 AND (x.sum_tonase / x.tonase   * 100) >= 85) THEN 'OPTIMAL'
                        WHEN x.kubikasi > 0 OR x.tonase > 0 THEN 'TIDAK OPTIMAL'
                        ELSE NULL END
                WHERE lp.no_shipment_pasuruan IN ($in)
            ", $bindings);
        }
    }

    private function recalcShipment(string $no, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        // ---- 1. isi kolom level-shipment yang kosong dari baris lain ----
        foreach (self::SHIPMENT_FILL as $col) {
            $val = null;
            foreach ($rows as $r) {
                if ($this->isFilled($r[$col] ?? null)) {
                    $val = $r[$col];
                    break;
                }
            }
            if ($val === null) {
                continue;
            }
            foreach ($rows as $i => $r) {
                if (!$this->isFilled($r[$col] ?? null)) {
                    $rows[$i][$col] = $val;
                }
            }
        }

        // ---- 2. lead time shipment: baris pertama, lalu yang diketahui dari file ----
        $lead = null;
        if ((int) ($rows[0]['transport_lead_time_pasuruan'] ?? 0) > 0) {
            $lead = (int) $rows[0]['transport_lead_time_pasuruan'];
        } elseif (array_key_exists($no, $this->shipmentLead)) {
            $lead = $this->shipmentLead[$no];
        } else {
            foreach ($rows as $r) {
                if ((int) ($r['transport_lead_time_pasuruan'] ?? 0) > 0) {
                    $lead = (int) $r['transport_lead_time_pasuruan'];
                    break;
                }
            }
        }

        if ($lead !== null && $lead > 0) {
            foreach ($rows as $i => $r) {
                if ((int) ($r['transport_lead_time_pasuruan'] ?? 0) <= 0) {
                    $rows[$i]['transport_lead_time_pasuruan'] = $lead;
                }
            }
        }

        // ---- 3. estimasi tiba per shipment (logika multi-drop controller) ----
        $keluar = $rows[0]['tanggal_keluar_gudang_pasuruan'] ?? null;
        $base   = ($this->isFilled($keluar) && $lead !== null)
            ? date('Y-m-d', strtotime($keluar . " +{$lead} days"))
            : null;

        $lastBongkar = null;
        foreach ($rows as $r) {
            $b = $r['tanggal_bongkar_pasuruan'] ?? null;
            if ($this->isFilled($b)) {
                $t = strtotime($b);
                if ($t && ($lastBongkar === null || $t > $lastBongkar)) {
                    $lastBongkar = $t;
                }
            }
        }

        $nextEstimasi = $lastBongkar
            ? date('Y-m-d', strtotime('+1 day', $lastBongkar))
            : $base;

        // sudah keluar gudang tapi estimasi tidak bisa dihitung -> lead time tidak ketemu
        if ($nextEstimasi === null && $this->isFilled($keluar)) {
            $this->noLeadTime[$no . ' - ' . ($rows[0]['tujuan_pasuruan'] ?? '-')] = true;
        }

        // ---- 4. CR per baris (kontribusi nilai muatan, sama dengan recalculateCr) ----
        $totalMuatan = 0.0;
        foreach ($rows as $r) {
            $totalMuatan += (float) ($r['nilai_muatan_pasuruan'] ?? 0);
        }

        $biayaKirim = 0.0;
        for ($i = count($rows) - 1; $i >= 0; $i--) {
            if ((float) ($rows[$i]['biaya_kirim_pasuruan'] ?? 0) > 0) {
                $biayaKirim = (float) $rows[$i]['biaya_kirim_pasuruan'];
                break;
            }
        }
        $totalCR = ($totalMuatan > 0 && $biayaKirim > 0) ? ($biayaKirim / $totalMuatan) * 100 : 0;

        // ---- 5. hitung & simpan per baris ----
        foreach ($rows as $r) {
            $d = $r;

            // baris yang belum dibongkar -> estimasi shipment; yang sudah dibongkar
            // mempertahankan estimasi lama, atau estimasi dasar kalau masih kosong
            if (!$this->isFilled($r['tanggal_bongkar_pasuruan'] ?? null)) {
                $d['estimasi_tiba_pasuruan'] = $nextEstimasi;
            } elseif (!$this->isFilled($r['estimasi_tiba_pasuruan'] ?? null)) {
                $d['estimasi_tiba_pasuruan'] = $base;
            }

            // lama pencarian & SLA dapat mobil (rencana kirim -> dpt unit)
            $rencana = $this->dayTs($d['rencana_kirim_pasuruan'] ?? null);
            $dpt     = $this->dayTs($d['tanggal_dpt_unit_pasuruan'] ?? null);
            $lamaCari = null;
            $slaMobil = null;
            if ($rencana && $dpt) {
                $sel      = (int) round(($dpt - $rencana) / 86400);
                $lamaCari = max(0, $sel);
                $slaMobil = $sel <= 0 ? 'On Time' : 'Delay';
            }

            // lama di gudang & SLA loading
            $tibaG   = $this->dayTs($d['tanggal_tiba_gudang_pasuruan'] ?? null);
            $keluarG = $this->dayTs($d['tanggal_keluar_gudang_pasuruan'] ?? null);
            $lamaGudang = null;
            $slaLoading = null;
            if ($tibaG && $keluarG) {
                $sel        = max(0, (int) round(($keluarG - $tibaG) / 86400));
                $lamaGudang = $sel;
                $slaLoading = $sel > 0 ? 'H+' . $sel : 'Sesuai SLA';
            }

            // monitoring (sama dengan generateMonitoringPasuruan di controller)
            $d['lama_perjalanan_pasuruan'] = null;
            $d['sla_tiba_pasuruan']        = null;
            $d['overstay_days_pasuruan']   = null;
            $d['sla_bongkar_pasuruan']     = null;
            unset($d['monitoring_alert_pasuruan']);
            $this->generateMonitoring($d);

            $nilai = (float) ($r['nilai_muatan_pasuruan'] ?? 0);
            $cr    = ($totalMuatan > 0 && $biayaKirim > 0 && $nilai > 0)
                ? round(($nilai / $totalMuatan) * $totalCR, 4)
                : 0;

            $upd = [
                'estimasi_tiba_pasuruan'    => $d['estimasi_tiba_pasuruan'] ?? null,
                'tanggal_estimasi_pasuruan' => $d['estimasi_tiba_pasuruan'] ?? null,
                'transport_lead_time_pasuruan' => (int) ($d['transport_lead_time_pasuruan'] ?? 0),

                'lama_waktu_pencarian_pasuruan'  => $lamaCari,
                'sla_dapat_mobil_pasuruan'       => $slaMobil,
                'lama_digudang_pasuruan'         => $lamaGudang,
                'sla_ketepatan_loading_pasuruan' => $slaLoading,

                'lama_perjalanan_pasuruan'  => $d['lama_perjalanan_pasuruan'],
                'sla_tiba_pasuruan'         => $d['sla_tiba_pasuruan'],
                'overstay_days_pasuruan'    => $d['overstay_days_pasuruan'],
                'sla_bongkar_pasuruan'      => $d['sla_bongkar_pasuruan'],
                'monitoring_alert_pasuruan' => $d['monitoring_alert_pasuruan'] ?? '-',
                'action_required_pasuruan'  => $d['action_required_pasuruan'] ?? '-',
                'status_akhir_pasuruan'     => $this->statusAkhir($d['sla_tiba_pasuruan'], $d['sla_bongkar_pasuruan']),

                'cr_pasuruan' => $cr,
            ];

            // kolom level-shipment (termasuk yang barusan diisi dari baris lain)
            foreach (self::SHIPMENT_FILL as $col) {
                if ($this->isFilled($d[$col] ?? null)) {
                    $upd[$col] = $d[$col];
                }
            }

            DB::table(self::TABLE)->where('id', $r['id'])->update($upd);
        }
    }

    /**
     * Salinan generateMonitoringPasuruan() di PasuruanController.
     * Mengisi: lama_perjalanan, sla_tiba, overstay_days, sla_bongkar,
     * monitoring_alert, action_required.
     */
    private function generateMonitoring(array &$data): void
    {
        $keluar = !empty($data['tanggal_keluar_gudang_pasuruan'])
            ? strtotime($data['tanggal_keluar_gudang_pasuruan'])
            : null;

        $tiba = !empty($data['tanggal_tiba_pasuruan'])
            ? strtotime($data['tanggal_tiba_pasuruan'])
            : null;

        $bongkar = !empty($data['tanggal_bongkar_pasuruan'])
            ? strtotime($data['tanggal_bongkar_pasuruan'])
            : null;

        $leadtime = isset($data['transport_lead_time_pasuruan'])
            ? (int) $data['transport_lead_time_pasuruan']
            : 0;

        // ---- Estimasi Tiba ----
        $estimasi = !empty($data['estimasi_tiba_pasuruan'])
            ? strtotime($data['estimasi_tiba_pasuruan'])
            : null;

        if (!$estimasi && $keluar && $leadtime > 0) {
            $estimasi = strtotime("+{$leadtime} days", $keluar);
        }

        // ---- Lama Perjalanan ----
        if ($keluar && $tiba) {
            $keluarDate = strtotime(date('Y-m-d', $keluar));
            $tibaDate   = strtotime(date('Y-m-d', $tiba));

            $data['lama_perjalanan_pasuruan'] = max(0, (int) (($tibaDate - $keluarDate) / 86400));
        }

        // ---- SLA Tiba: tiba <= estimasi = On Time, lebih = Delay ----
        if ($tiba && $estimasi) {
            $tibaDate     = strtotime(date('Y-m-d', $tiba));
            $estimasiDate = strtotime(date('Y-m-d', $estimasi));

            $data['sla_tiba_pasuruan'] = ($tibaDate <= $estimasiDate) ? 'On Time' : 'Delay';
        }

        // ---- Overstay & SLA Bongkar ----
        if ($tiba && $bongkar) {
            $tibaDate    = strtotime(date('Y-m-d', $tiba));
            $bongkarDate = strtotime(date('Y-m-d', $bongkar));

            $overstay = ($bongkarDate - $tibaDate) / 86400;

            $data['overstay_days_pasuruan'] = max(0, $overstay);
            $data['sla_bongkar_pasuruan']   = $overstay <= 0 ? 'On Time' : 'Delay';
        }

        // ---- Alert Monitoring ----
        if ($bongkar) {
            $data['monitoring_alert_pasuruan'] = 'SELESAI';
        } elseif ($tiba) {
            $data['monitoring_alert_pasuruan'] = 'TIBA DI TUJUAN';
        } elseif ($estimasi) {
            $today   = strtotime(date('Y-m-d'));
            $selisih = ceil(($estimasi - $today) / 86400);

            if ($selisih < 0) {
                $data['monitoring_alert_pasuruan'] = 'TERLAMBAT';
            } elseif ($selisih <= 2) {
                $data['monitoring_alert_pasuruan'] = 'WARNING H-2';
            } else {
                $data['monitoring_alert_pasuruan'] = 'AMAN';
            }
        }

        // ---- Action Required ----
        switch ($data['monitoring_alert_pasuruan'] ?? '') {
            case 'TERLAMBAT':
                $data['action_required_pasuruan'] = 'Follow Up Driver';
                break;
            case 'WARNING H-2':
                $data['action_required_pasuruan'] = 'Monitoring';
                break;
            case 'TIBA DI TUJUAN':
                $data['action_required_pasuruan'] = 'Menunggu Bongkar';
                break;
            case 'SELESAI':
                $data['action_required_pasuruan'] = 'Closed';
                break;
            default:
                $data['action_required_pasuruan'] = '-';
                break;
        }
    }

    private function statusAkhir(?string $slaTiba, ?string $slaBongkar): string
    {
        $t = strtolower(trim($slaTiba ?? ''));
        $b = strtolower(trim($slaBongkar ?? ''));

        if ($t === '' || $b === '') return '-';
        if ($t === 'on time' && $b === 'on time') return 'On Time Total';
        if ($t === 'delay' && $b === 'on time')   return 'Delay Perjalanan';
        if ($t === 'on time' && $b === 'delay')   return 'Delay Pembongkaran';

        return 'Delay Total';
    }

    // ================= BUSINESS HELPERS =================

    private function applyRouteAlias(?string $route): ?string
    {
        if ($route === null || $route === '') return $route;

        $key = $this->normalize($route);
        return self::ROUTE_ALIASES[$key] ?? $route;
    }

    private function getKategoriEkspedisi(?string $noShipment): ?string
    {
        $no = trim((string) $noShipment);

        if (str_starts_with($no, '45')) return 'Kontrak';
        if (str_starts_with($no, '42')) return 'Oncall';

        return null;
    }

    /**
     * Cari baris tarif: Route (exact setelah normalisasi) + Ekpedisi (exact,
     * kalau terisi) + Mobil (PREFIX match). Fallback: Route + Mobil prefix.
     */
    private function findTarif(?string $route, ?string $ekpedisi, ?string $mobil)
    {
        $routeKey    = $this->normalize($route);
        $mobilExcel  = $this->normalizeMobil($mobil);
        $ekpedisiKey = $ekpedisi !== null ? $this->normalize($ekpedisi) : '';

        $candidates = self::$tarifByRoute[$routeKey] ?? null;

        if (!$candidates || $mobilExcel === '') {
            return null;
        }

        if ($ekpedisiKey !== '') {
            $strict = $candidates->first(function ($row) use ($ekpedisiKey, $mobilExcel) {
                $mobilMaster = $this->normalizeMobil($row->mobil);
                return $this->normalize($row->ekpedisi) === $ekpedisiKey
                    && str_starts_with($mobilMaster, $mobilExcel);
            });

            if ($strict) {
                return $strict;
            }
        }

        return $candidates->first(function ($row) use ($mobilExcel) {
            $mobilMaster = $this->normalizeMobil($row->mobil);
            return str_starts_with($mobilMaster, $mobilExcel);
        });
    }

    // ================= GENERIC HELPERS =================

    /** Ambil nilai pertama yang terisi dari beberapa kemungkinan nama header. */
    private function pick(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '') {
                return $row[$k];
            }
        }
        return null;
    }

    private function isFilled($v): bool
    {
        if ($v === null) return false;

        $s = trim((string) $v);

        return $s !== '' && !str_starts_with($s, '0000-00-00');
    }

    /** Timestamp tengah malam dari sebuah tanggal, atau null. */
    private function dayTs($v): ?int
    {
        if (!$this->isFilled($v)) return null;

        $t = strtotime((string) $v);

        return $t ? strtotime(date('Y-m-d', $t)) : null;
    }

    private function cleanText($value)
    {
        if (!$value || $value == '-' || $value == '#VALUE!' || $value == '#N/A') return null;
        if (is_array($value)) return null;

        $value = trim((string) $value);

        if (str_contains($value, '=')) return null;

        return $value;
    }

    private function cleanNumber($value)
    {
        if ($value === null || $value === '' || $value == '-') return 0;

        if (is_numeric($value)) return (float) $value;

        $value = str_replace(['Rp', 'rp', ' '], '', $value);
        $value = str_replace(['.', ','], '', $value);

        return (float) $value;
    }

    /** Nominal rupiah (angka saja); null kalau kosong. */
    private function money($value): ?float
    {
        if ($value === null || $value === '' || $value === '-') return null;

        if (is_numeric($value)) return (float) $value;

        $digits = preg_replace('/[^0-9]/', '', (string) $value);

        return $digits === '' ? null : (float) $digits;
    }

    /**
     * Sama dengan cleanDesimalPasuruan di controller: buang %, spasi, format
     * Indonesia "1.234,56", dibulatkan 2 desimal. Error Excel (#N/A) -> null.
     */
    private function cleanDesimal($value): ?float
    {
        if ($value === null || $value === '' || $value === '-') return null;

        if (is_numeric($value)) return round((float) $value, 2);

        $value = str_replace(['%', ' ', "\xc2\xa0"], '', (string) $value);

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9.]/', '', $value);

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /** biaya_kirim di master tarif formatnya "8,500,000" (koma = ribuan). */
    private function cleanNumberTarif($value): float
    {
        if ($value === null || $value === '' || $value == '-') return 0;

        $value = (string) $value;
        $value = str_replace(['Rp', 'rp', ' '], '', $value);

        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(['.', ','], '', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    private function convertDate($value)
    {
        if (!$value || $value == '-' || $value == '#VALUE!' || $value == '#N/A') return null;

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        // format template: dd/mm/yyyy -> dd-mm-yyyy (dibaca strtotime sebagai d-m-Y)
        $timestamp = strtotime(str_replace('/', '-', trim($value)));

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * Sama seperti convertDate tapi jam ikut disimpan (Y-m-d H:i:s).
     * Dipakai untuk tanggal_tiba & tanggal_bongkar (datetime-local di grid).
     */
    private function convertDateTime($value)
    {
        if (!$value || $value == '-' || $value == '#VALUE!' || $value == '#N/A') return null;

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d H:i:s');
        }

        $timestamp = strtotime(str_replace('/', '-', trim($value)));

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    /** Normalisasi Route: hapus NBSP, rapikan spasi di sekitar "-", lowercase. */
    private function normalize(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s*-\s*/', '-', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return strtolower($value);
    }

    /**
     * Normalisasi nama tujuan: buang semua selain huruf/angka, jadi spasi,
     * titik, strip (- / en dash), dan NBSP tidak lagi bikin lookup gagal.
     */
    private function normalizeTujuan(?string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $value);

        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $value));
    }

    /**
     * Tentukan lead time (hari) untuk satu baris. null = tidak diketahui.
     */
    private function resolveLeadTime($master, ?int $file, ?string $tujuan): ?int
    {
        $m = ($master !== null && $master !== '') ? (int) $master : null;

        foreach ([$m, $file] as $v) {
            if ($v !== null && $v > 0) {
                return $v;
            }
        }

        $known = self::$leadByTujuan[$this->normalizeTujuan($tujuan)] ?? null;
        if ($known !== null && $known > 0) {
            return (int) $known;
        }

        // 0 eksplisit dari master/file = memang 0 hari
        foreach ([$m, $file] as $v) {
            if ($v !== null) {
                return $v;
            }
        }

        return null;
    }

    /** Normalisasi Mobil: sama seperti normalize() tapi tanpa menyentuh "-". */
    private function normalizeMobil(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return strtolower($value);
    }
}