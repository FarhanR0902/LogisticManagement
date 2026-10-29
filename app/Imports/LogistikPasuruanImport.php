<?php

namespace App\Imports;

use App\Models\LogistikPengirimanPasuruan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import data pengiriman Pasuruan.
 *
 * KUNCI UPSERT (1 baris = 1 DO):
 *   no_shipment_pasuruan + tujuan_pasuruan + sales_document_pasuruan
 *   (kalau sales_document kosong, dipakai no_delivery, lalu no_do)
 *
 * KALAU SEMUA ID DO KOSONG:
 *   baris ke-N di file (untuk shipment + tujuan yang sama) dicocokkan dengan
 *   baris ke-N di DB (urut id) yang sales_document-nya kosong. Jadi 3 baris
 *   MATARAM tanpa DO tetap jadi 3 baris, tidak saling menimpa.
 *
 * NAMA EKSPEDISI:
 *   nama dari Excel disamakan dengan nama di master (tarif_pengiriman) kalau
 *   maksudnya sama (beda awalan PT/CV, titik, spasi, huruf besar-kecil, atau
 *   salah ketik kecil). Kalau tidak yakin, nama dari Excel dipakai apa adanya.
 *
 * BIAYA KIRIM:
 *   hanya dari kirim_kontribusi (dibagi per porsi total_kubik). Kalau kontribusi
 *   kosong, biaya_kirim = 0. Tabel tarif_pengiriman dipakai hanya untuk
 *   kubikasi dan tonase.
 *
 * Aturan hitung (di AfterImport, bukan per baris):
 *  - CR                : per baris sesuai kontribusi nilai_muatan dalam satu no_shipment
 *  - hasil_kubik/tonase: SUM(total per shipment + tujuan) / kapasitas * 100
 *  - pengiriman_optimal: per shipment, OPTIMAL jika salah satu hasil >= 85
 *  - optimal customer  : per shipment + tujuan, OPTIMAL jika salah satu >= 85
 */
class LogistikPasuruanImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas, WithEvents
{
    private const TABLE       = 'logistik_pengiriman_pasuruan';
    private const TARIF_TABLE = 'tarif_pengiriman';
    private const BATCH_ROWS  = 500;

    private const ROUTE_ALIASES = [
        'jabodetabek' => 'Sentul-Jabodetabek',
    ];

    // alias manual nama ekspedisi (kunci = hasil ekspedisiKey(), nilai = nama di master)
    private const EKSPEDISI_ALIASES = [
        // 'primatranslogistic' => 'Prima Trans Logistik',
    ];

    // alias header Excel -> nama kolom standar (selain aturan otomatis "+_pasuruan")
    private const HEADER_ALIASES = [
        'ekpedisi_pasuruan'            => 'ekspedisi_pasuruan',
        'total_do_qty_car_pasuruan'    => 'total_do_pasuruan',
        'total_kubikasi_pasuruan'      => 'total_kubik_pasuruan',
        'nilai_muatan_rp_pasuruan'     => 'nilai_muatan_pasuruan',
        'via_pasuruan'                 => 'via_kirim_pasuruan',
    ];

    private static $customerMap     = null;
    private static $tarifByRoute    = null;
    private static $ekspedisiMaster = null;   // daftar nama ekspedisi di master tarif

    // cache: nama ekspedisi dari Excel -> nama di master
    private array $ekspedisiCanon = [];

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
    private array $noLeadTime = [];
    private array $unmatchedTujuan = [];

    // no_shipment yang ada di file ini -> dipakai buat recalc di AfterImport
    private array $touchedShipments = [];

    // agregat dari file (dihitung sebelum baris diproses)
    private array $shipmentKubik = [];   // no_shipment            => total kubik
    private array $shipmentKK    = [];   // no_shipment            => total kirim kontribusi
    private array $shipmentRows  = [];   // no_shipment            => jumlah baris
    private array $kkSeen        = [];   // no_shipment            => baris yang sudah diproses
    private array $kkAlloc       = [];   // no_shipment            => total yang sudah dialokasikan

    // urutan kemunculan baris tanpa ID DO: "no_shipment|tujuan" => jumlah baris yang sudah diproses
    private array $nullDoSeen = [];

    public function getInsertedCount(): int { return $this->inserted; }
    public function getUpdatedCount(): int { return $this->updated; }
    public function getImportedCount(): int { return $this->inserted + $this->updated; }
    public function getSkippedCount(): int { return $this->skipped; }
    public function getFailedCount(): int { return $this->failed; }
    public function getFailedList(): array { return $this->failedList; }
    public function getNoLeadTime(): array { return $this->noLeadTime; }
    public function getUnmatchedTujuan(): array { return $this->unmatchedTujuan; }

    public function __construct()
    {
        // Master tarif: route -> kandidat (ekpedisi, mobil, kapasitas). Biaya tarif tidak dipakai lagi.
        if (self::$tarifByRoute === null) {
            self::$tarifByRoute = DB::table(self::TARIF_TABLE)
                ->select('ekpedisi', 'route', 'mobil', 'kubikasi', 'tonase')
                ->get()
                ->groupBy(fn($row) => $this->normalize($row->route));
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
                ->keyBy(fn($row) => preg_replace('/\s+/', ' ', trim(strtolower($row->tujuan))));
        }
    }

    // ================= MAIN =================

    public function collection(Collection $rows)
    {
        DB::disableQueryLog();

        // rapikan header sekali di awal
        $prepared = [];
        foreach ($rows as $rc) {
            $prepared[] = $this->normalizeRow($rc->toArray());
        }
        unset($rows);

        // ---- PASS 1: agregat dari file (jumlah baris, total kubik, kirim kontribusi) ----
        foreach ($prepared as $r) {
            $no = $this->cleanText($r['no_shipment_pasuruan'] ?? null);
            if (empty($no)) continue;

            $this->shipmentRows[$no] = ($this->shipmentRows[$no] ?? 0) + 1;

            $k = $this->cleanDecimal($r['total_kubik_pasuruan'] ?? null);
            if ($k !== null && $k > 0) {
                $this->shipmentKubik[$no] = ($this->shipmentKubik[$no] ?? 0) + $k;
            }

            $kk = $this->cleanDecimal($r['kirim_kontribusi_pasuruan'] ?? null);
            if ($kk !== null && $kk > 0) {
                $this->shipmentKK[$no] = ($this->shipmentKK[$no] ?? 0) + $kk;
            }
        }

        // ---- PASS 2: simpan per DO, commit per batch (urutan baris tetap sama) ----
        foreach (array_chunk($prepared, self::BATCH_ROWS) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $row) {
                    $this->processRow($row);
                }
            });
        }
    }

    private function processRow(array $row): void
    {
        $noShipment = $this->cleanText($row['no_shipment_pasuruan'] ?? null);

        if (empty($noShipment)) {
            $this->skipped++;
            return;
        }

        $tujuan = $this->cleanText($row['tujuan_pasuruan'] ?? null);

        $this->touchedShipments[(string) $noShipment] = true;

        try {
            // identitas DO: sales_document -> no_delivery -> no_do
            $doKey = $this->cleanText($this->pick($row, [
                'sales_document_pasuruan',
                'no_delivery_pasuruan',
                'no_do_pasuruan',
            ]));

            $attributes = $this->buildAttributes($row, $noShipment, $tujuan);
            $attributes['sales_document_pasuruan'] = $doKey;   // harus sama dengan kunci

            if ($doKey !== null) {
                $model = LogistikPengirimanPasuruan::updateOrCreate(
                    [
                        'no_shipment_pasuruan'    => $noShipment,
                        'tujuan_pasuruan'         => $tujuan,
                        'sales_document_pasuruan' => $doKey,
                    ],
                    $attributes
                );
            } else {
                $model = $this->saveWithoutDoKey($noShipment, $tujuan, $attributes);
            }

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

    /**
     * ID DO kosong: baris ke-N di file (shipment + tujuan yang sama) dicocokkan
     * dengan baris ke-N di DB (urut id) yang sales_document-nya kosong.
     * Kalau belum ada, dibuat baris baru.
     */
    private function saveWithoutDoKey(string $noShipment, ?string $tujuan, array $attributes)
    {
        $k   = $noShipment . '|' . (string) $tujuan;
        $nth = $this->nullDoSeen[$k] = ($this->nullDoSeen[$k] ?? 0) + 1;

        $q = LogistikPengirimanPasuruan::where('no_shipment_pasuruan', $noShipment);

        $tujuan === null
            ? $q->whereNull('tujuan_pasuruan')
            : $q->where('tujuan_pasuruan', $tujuan);

        $q->where(fn($x) => $x->whereNull('sales_document_pasuruan')
                              ->orWhere('sales_document_pasuruan', ''));

        $existing = $q->orderBy('id')->skip($nth - 1)->take(1)->first();

        if ($existing) {
            $existing->update($attributes);
            return $existing;
        }

        return LogistikPengirimanPasuruan::create(array_merge([
            'no_shipment_pasuruan' => $noShipment,
            'tujuan_pasuruan'      => $tujuan,
        ], $attributes));
    }

    /**
     * Header Excel boleh "sales_document" ATAU "sales_document_pasuruan".
     * Semua key tanpa suffix otomatis dibuatkan versi "_pasuruan".
     */
    private function normalizeRow(array $row): array
    {
        foreach ($row as $k => $v) {
            if (!is_string($k) || str_ends_with($k, '_pasuruan')) continue;
            if (!array_key_exists($k . '_pasuruan', $row)) {
                $row[$k . '_pasuruan'] = $v;
            }
        }

        foreach (self::HEADER_ALIASES as $from => $to) {
            if (isset($row[$from]) && $row[$from] !== '' && (!isset($row[$to]) || $row[$to] === '')) {
                $row[$to] = $row[$from];
            }
        }

        return $row;
    }

    private function buildAttributes(array $row, string $noShipment, ?string $tujuan): array
    {
        // ================= DATE =================
        $tanggalTerimaPo     = $this->convertDate($row['tanggal_terima_po_pasuruan'] ?? null);
        $rencanaKirim        = $this->convertDate($row['rencana_kirim_pasuruan'] ?? null);
        $tanggalDptUnit      = $this->convertDate($row['tanggal_dpt_unit_pasuruan'] ?? null);
        $planningLoading     = $this->convertDate($row['planning_loading_pasuruan'] ?? null);
        $tanggalTibaGudang   = $this->convertDate($row['tanggal_tiba_gudang_pasuruan'] ?? null);
        $tanggalKeluarGudang = $this->convertDate($row['tanggal_keluar_gudang_pasuruan'] ?? null);
        $tanggalTiba         = $this->convertDate($row['tanggal_tiba_pasuruan'] ?? null);
        $tanggalBongkar      = $this->convertDate($row['tanggal_bongkar_pasuruan'] ?? null);

        $etd = $this->convertDate($row['etd_pasuruan'] ?? null);
        $eta = $this->convertDate($row['eta_pasuruan'] ?? null);
        $atd = $this->convertDate($row['atd_pasuruan'] ?? null);
        $ata = $this->convertDate($row['ata_pasuruan'] ?? null);

        $actPgiDate      = $this->convertDate($row['act_pgi_date_pasuruan'] ?? null);
        $contractEndDate = $this->convertDate($row['contract_end_date_pasuruan'] ?? null);

        // ================= TEXT =================
        $viaKirim          = $this->cleanText($row['via_kirim_pasuruan'] ?? null);
        $shippingPoint     = $this->cleanText($row['shipping_point_pasuruan'] ?? null);
        $ketersediaanUnit  = $this->cleanText($row['ketersediaan_unit_pasuruan'] ?? null);
        $perubahanMobil    = $this->cleanText($row['perubahan_mobil_pasuruan'] ?? null);
        $kategoriEkspedisi = $this->getKategoriEkspedisi($noShipment)
                             ?? $this->cleanText($row['kategori_ekspedisi_pasuruan'] ?? null);
        $statusKendaraan    = $this->cleanText($row['status_kendaraan_pasuruan'] ?? null);
        $namaKapal          = $this->cleanText($row['nama_kapal_pasuruan'] ?? null);
        $transportLaut      = $this->cleanText($row['transport_laut_pasuruan'] ?? null);
        $reasonSelisihQty   = $this->cleanText($row['reason_selisih_quantity_pasuruan'] ?? null);
        $reasonWaktuTiba    = $this->cleanText($row['reason_waktu_tiba_pasuruan'] ?? null);
        $reasonWaktuBongkar = $this->cleanText($row['reason_waktu_bongkar_pasuruan'] ?? null);
        $remarks            = $this->cleanText($row['remarks_pasuruan'] ?? null);
        $remarksQty         = $this->cleanText($row['remarks_qty_pasuruan'] ?? null);
        $createdBy          = $this->cleanText($row['created_by_pasuruan'] ?? null);
        $noPol              = $this->cleanText($row['no_pol_pasuruan'] ?? null);
        $namaDriver         = $this->cleanText($row['nama_driver_pasuruan'] ?? null);

        // identitas per-DO
        $customerReference = $this->cleanText($row['customer_reference_pasuruan'] ?? null);
        $noDelivery        = $this->cleanText($row['no_delivery_pasuruan'] ?? null);
        $noDo              = $this->cleanText($row['no_do_pasuruan'] ?? null);

        // fallback dari Excel kalau tujuan tidak ketemu di master
        $pulauFromFile      = $this->cleanText($row['pulau_pasuruan'] ?? null);
        $areaFromFile       = $this->cleanText($row['area_pasuruan'] ?? null);
        $plannerFromFile    = $this->cleanText($row['planner_pasuruan'] ?? null);
        $picMonitoringExcel = $this->cleanText($row['pic_monitoring_pasuruan'] ?? null);

        // ================= NUMBER =================
        $leadTimeFromFile  = (int) $this->cleanNumber($row['transport_lead_time_pasuruan'] ?? 0);
        $nilaiMuatan       = $this->cleanNumber($row['nilai_muatan_pasuruan'] ?? null);
        $totalDo           = $this->cleanNumber($row['total_do_pasuruan'] ?? null);
        $actUrutanBongkar  = $this->cleanNumber($row['act_urutan_bongkar_pasuruan'] ?? null);
        $qtyMonitoring     = $this->cleanNumber($row['qty_monitoring_pasuruan'] ?? null);

        // actual qty: hanya dianggap terisi kalau memang ada di Excel
        $actualRaw = $row['actual_delivery_quantity_pasuruan'] ?? null;
        $actualDeliveryQty = ($actualRaw === null || $actualRaw === '' || $actualRaw === '-')
            ? null
            : $this->cleanNumber($actualRaw);

        // total kubik/tonase per DO apa adanya, dijumlahkan di recalc
        $totalKubik  = $this->cleanDecimal($row['total_kubik_pasuruan'] ?? null);
        $totalTonase = $this->cleanDecimal($row['total_tonase_pasuruan'] ?? null);

        // ================= FORWARD-FILL (route/mobil/ekspedisi) =================
        if ($noShipment !== $this->lastNoShipment) {
            $this->lastRoute    = null;
            $this->lastMobil    = null;
            $this->lastEkpedisi = null;
        }

        $route = $this->applyRouteAlias(
            $this->cleanText($row['route_pasuruan'] ?? null) ?: $this->lastRoute
        );
        $mobil = $this->cleanText($row['mobil_pasuruan'] ?? null) ?: $this->lastMobil;

        // nama ekspedisi dari Excel disamakan dengan nama di master (kalau maksudnya sama)
        $ekspedisi = $this->canonEkspedisi(
            $this->cleanText($row['ekspedisi_pasuruan'] ?? null)
        ) ?: $this->lastEkpedisi;

        if ($route)     $this->lastRoute    = $route;
        if ($mobil)     $this->lastMobil    = $mobil;
        if ($ekspedisi) $this->lastEkpedisi = $ekspedisi;

        $this->lastNoShipment = $noShipment;

        // ================= KAPASITAS DARI TARIF (kubikasi & tonase saja) =================
        $tarifRow = $this->findTarif($route, $ekspedisi, $mobil);
        $kubikasi = $tarifRow ? $this->cleanDecimal($tarifRow->kubikasi ?? null) : null;
        $tonase   = $tarifRow ? $this->cleanDecimal($tarifRow->tonase ?? null)   : null;

        // ---- kirim kontribusi: total per shipment dibagi sesuai porsi total_kubik ----
        $kirimKontribusi = null;
        $totKK = $this->shipmentKK[$noShipment] ?? 0;

        if ($totKK > 0) {
            $sumKubik = $this->shipmentKubik[$noShipment] ?? 0;
            $nRows    = $this->shipmentRows[$noShipment] ?? 1;

            $this->kkSeen[$noShipment] = ($this->kkSeen[$noShipment] ?? 0) + 1;

            if ($this->kkSeen[$noShipment] >= $nRows) {
                // baris terakhir ambil sisa, supaya total pas tanpa selisih pembulatan
                $kirimKontribusi = round($totKK - ($this->kkAlloc[$noShipment] ?? 0), 2);
            } else {
                $share = $sumKubik > 0 ? (($totalKubik ?? 0) / $sumKubik) : (1 / $nRows);
                $kirimKontribusi = round($totKK * $share, 2);
            }

            $this->kkAlloc[$noShipment] = ($this->kkAlloc[$noShipment] ?? 0) + $kirimKontribusi;
        }

        // biaya_kirim HANYA dari kirim_kontribusi. Kosong kalau kontribusi kosong (disimpan 0,
        // supaya aman kalau kolomnya NOT NULL; tampilan dan CR tetap 0).
        $biayaKirim = $kirimKontribusi ?? 0;

        // ================= LOOKUP MASTER (Div = Pasuruan) =================
        $tujuanKey    = preg_replace('/\s+/', ' ', trim(strtolower((string) $tujuan)));
        $customerData = self::$customerMap[$tujuanKey] ?? null;

        if (!$customerData && $tujuan) {
            $this->unmatchedTujuan[$tujuan] = $tujuan;
        }

        $distChannel    = ($customerData->dist_channel ?? null)
            ?: $this->cleanText($row['dist_channel_pasuruan'] ?? null);
        $areaMaster     = $customerData->area ?? null;
        $pulauMaster    = $customerData->pulau ?? null;
        $plannerMaster  = $customerData->Planner ?? null;
        $picMaster      = $customerData->Monitoring ?? null;
        $biayaKuli      = $customerData->biaya_kuli ?? null;
        $leadTimeMaster = $customerData->transport_lead_time ?? null;

        $area          = $areaMaster ?: $areaFromFile;
        $pulau         = $pulauMaster ?: $pulauFromFile;
        $planner       = $plannerMaster ?: $plannerFromFile;
        $picMonitoring = $picMaster ?: $picMonitoringExcel;
        $leadTime      = ($leadTimeMaster !== null && $leadTimeMaster !== '')
            ? (int) $leadTimeMaster
            : $leadTimeFromFile;

        if ($leadTime <= 0 && $tanggalKeluarGudang) {
            $this->noLeadTime[] = "{$noShipment} - {$tujuan}";
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

        // ================= SLA DAPAT MOBIL =================
        $lamaWaktuPencarian = null;
        $slaDapatMobil      = null;

        if ($rencanaKirim && $tanggalDptUnit) {
            $selisihCariMobil = (int) date_diff(
                date_create($rencanaKirim),
                date_create($tanggalDptUnit)
            )->format('%a');

            $lamaWaktuPencarian = $selisihCariMobil;
            $slaDapatMobil      = ($selisihCariMobil <= 0) ? 'On Time' : 'Delay';
        }

        // ================= SLA LOADING / LAMA DI GUDANG =================
        $lamaDigudang = null;
        $slaLoading   = null;

        if ($tanggalTibaGudang && $tanggalKeluarGudang) {
            $selisihGudang = (int) date_diff(
                date_create($tanggalTibaGudang),
                date_create($tanggalKeluarGudang)
            )->format('%a');

            $lamaDigudang = $selisihGudang;
            $slaLoading   = $selisihGudang > 0 ? 'H+' . $selisihGudang : 'Sesuai SLA';
        }

        // ================= ESTIMASI, PERJALANAN, SLA TIBA, OVERSTAY, SLA BONGKAR =================
        $keluar  = $tanggalKeluarGudang ? strtotime($tanggalKeluarGudang) : null;
        $tiba    = $tanggalTiba ? strtotime($tanggalTiba) : null;
        $bongkar = $tanggalBongkar ? strtotime($tanggalBongkar) : null;

        $estimasi = ($keluar && $leadTime > 0)
            ? strtotime("+{$leadTime} days", $keluar)
            : null;

        $lamaPerjalanan = ($keluar && $tiba)
            ? max(0, ceil(($tiba - $keluar) / 86400))
            : null;

        $slaTiba = ($tiba && $estimasi)
            ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
            : null;

        $overstay = ($tiba && $bongkar)
            ? max(0, ceil(($bongkar - $tiba) / 86400))
            : null;

        $slaBongkar = ($tiba && $bongkar)
            ? (($overstay <= 0) ? 'On Time' : 'Delay')
            : null;

        // ================= STATUS AKHIR / MONITORING ALERT =================
        $logic           = $this->generateStatusAlert($slaTiba, $slaBongkar);
        $statusAkhir     = $logic['status_akhir'];
        $monitoringAlert = $logic['alert'];

        switch ($monitoringAlert) {
            case 'TERLAMBAT':
                $actionRequired = 'Follow Up Driver';
                break;
            case 'WARNING H-2':
                $actionRequired = 'Monitoring';
                break;
            case 'TIBA DI TUJUAN':
                $actionRequired = 'Menunggu Bongkar';
                break;
            case 'SELESAI':
            case 'On Time Total':
            case 'Delay Total':
                $actionRequired = 'Closed';
                break;
            default:
                $actionRequired = '-';
                break;
        }

        // ================= SELISIH QTY DO =================
        // hanya diisi kalau Excel memang punya actual qty (kalau tidak, dibiarkan kosong
        // supaya controller tidak menganggap selisih = seluruh qty)
        $selisihQty = $actualDeliveryQty !== null
            ? (float) $totalDo - (float) $actualDeliveryQty
            : null;

        return [
            // ================= BASIC =================
            'transport_lead_time_pasuruan' => $leadTime,
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
            'biaya_kuli_pasuruan'   => $biayaKuli,

            'kategori_ekspedisi_pasuruan' => $kategoriEkspedisi,
            'ekspedisi_pasuruan'          => $ekspedisi,

            'no_pol_pasuruan'      => $noPol,
            'nama_driver_pasuruan' => $namaDriver,

            // ================= DATE =================
            'tanggal_terima_po_pasuruan'     => $tanggalTerimaPo,
            'rencana_kirim_pasuruan'         => $rencanaKirim,
            'tanggal_dpt_unit_pasuruan'      => $tanggalDptUnit,
            'planning_loading_pasuruan'      => $planningLoading,
            'tanggal_tiba_gudang_pasuruan'   => $tanggalTibaGudang,
            'tanggal_keluar_gudang_pasuruan' => $tanggalKeluarGudang,
            'tanggal_tiba_pasuruan'          => $tanggalTiba,
            'tanggal_bongkar_pasuruan'       => $tanggalBongkar,

            // ================= GUDANG =================
            'lama_digudang_pasuruan'         => $lamaDigudang,
            'sla_ketepatan_loading_pasuruan' => $slaLoading,
            'lama_waktu_pencarian_pasuruan'  => $lamaWaktuPencarian,
            'sla_dapat_mobil_pasuruan'       => $slaDapatMobil,

            // ================= MONITORING =================
            'pic_monitoring_pasuruan'   => $picMonitoring,
            'status_kendaraan_pasuruan' => $statusKendaraan,
            'monitoring_alert_pasuruan' => $monitoringAlert,
            'action_required_pasuruan'  => $actionRequired,

            'estimasi_tiba_pasuruan'    => $estimasi ? date('Y-m-d', $estimasi) : null,
            'tanggal_estimasi_pasuruan' => $estimasi ? date('Y-m-d', $estimasi) : null,

            'lama_perjalanan_pasuruan' => $lamaPerjalanan,
            'sla_tiba_pasuruan'        => $slaTiba,

            'overstay_days_pasuruan' => $overstay,
            'sla_bongkar_pasuruan'   => $slaBongkar,

            'status_akhir_pasuruan' => $statusAkhir,

            // ================= KAPAL =================
            'nama_kapal_pasuruan'     => $namaKapal,
            'etd_pasuruan'            => $etd,
            'eta_pasuruan'            => $eta,
            'atd_pasuruan'            => $atd,
            'ata_pasuruan'            => $ata,
            'transport_laut_pasuruan' => $transportLaut,

            // ================= DELIVERY =================
            'actual_delivery_quantity_pasuruan' => $actualDeliveryQty,
            'selisih_quantity_pasuruan'         => $selisihQty,
            'reason_selisih_quantity_pasuruan'  => $reasonSelisihQty,

            // ================= REASON =================
            'reason_waktu_tiba_pasuruan'    => $reasonWaktuTiba,
            'reason_waktu_bongkar_pasuruan' => $reasonWaktuBongkar,
            'remarks_pasuruan'              => $remarks,
            'remarks_qty_pasuruan'          => $remarksQty,
            'selisih_qty_pasuruan'          => $selisihQty,

            // ================= PER-DO =================
            'customer_reference_pasuruan' => $customerReference,
            'contract_end_date_pasuruan'  => $contractEndDate,
            'no_delivery_pasuruan'        => $noDelivery,
            'no_do_pasuruan'              => $noDo,
            'kirim_kontribusi_pasuruan'   => $kirimKontribusi,

            // ================= OTHER =================
            'act_pgi_date_pasuruan'       => $actPgiDate,
            'act_urutan_bongkar_pasuruan' => $actUrutanBongkar,
            'shipping_point_pasuruan'     => $shippingPoint,
            'qty_monitoring_pasuruan'     => $qtyMonitoring,
            'created_by_pasuruan'         => $createdBy,
            'create_tgl_pasuruan'         => date('Y-m-d H:i:s'),
        ];
    }

    // ================= AFTER IMPORT =================

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
        if (empty($this->touchedShipments)) return;

        $table     = self::TABLE;
        $shipments = array_map('strval', array_keys($this->touchedShipments));

        $n   = fn($c) => "COALESCE(CAST(NULLIF(TRIM({$c}), '') AS DECIMAL(18,4)), 0)";
        $tk  = $n('total_kubik_pasuruan');
        $tt  = $n('total_tonase_pasuruan');
        $nm  = $n('nilai_muatan_pasuruan');
        $bk  = $n('biaya_kirim_pasuruan');
        $kk  = $n('kirim_kontribusi_pasuruan');
        $kub = "MAX(CAST(NULLIF(TRIM(kubikasi_pasuruan), '') AS DECIMAL(18,4)))";
        $ton = "MAX(CAST(NULLIF(TRIM(tonase_pasuruan), '')   AS DECIMAL(18,4)))";

        foreach (array_chunk($shipments, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $b2 = array_merge($chunk, $chunk);
            $b3 = array_merge($chunk, $chunk, $chunk);

            // ---- safety net: isi route/mobil/ekspedisi kosong dari baris lain ----
            foreach (['route_pasuruan', 'mobil_pasuruan', 'ekspedisi_pasuruan'] as $col) {
                DB::update("
                    UPDATE {$table} lp
                    JOIN (
                        SELECT no_shipment_pasuruan, MIN($col) AS val
                        FROM {$table}
                        WHERE $col IS NOT NULL AND $col != ''
                          AND no_shipment_pasuruan IN ($in)
                        GROUP BY no_shipment_pasuruan
                    ) x ON lp.no_shipment_pasuruan = x.no_shipment_pasuruan
                    SET lp.$col = x.val
                    WHERE (lp.$col IS NULL OR lp.$col = '')
                      AND lp.no_shipment_pasuruan IN ($in)
                ", $b2);
            }

            // ---- CR: per baris sesuai kontribusi nilai_muatan ----
            // biaya shipment = SUM(biaya) kalau ada kirim_kontribusi,
            // selain itu SUM dari MAX(biaya) tiap tujuan (DO satu tujuan tidak dihitung dobel)
            DB::update("
                UPDATE {$table} lp
                JOIN (
                    SELECT ns,
                           IF(SUM(kk) > 0, SUM(sb), SUM(mb)) AS biaya,
                           SUM(nm) AS muatan
                    FROM (
                        SELECT no_shipment_pasuruan AS ns,
                               COALESCE(tujuan_pasuruan, '') AS tj,
                               SUM({$kk}) AS kk,
                               SUM({$bk}) AS sb,
                               MAX({$bk}) AS mb,
                               SUM({$nm}) AS nm
                        FROM {$table}
                        WHERE no_shipment_pasuruan IN ($in)
                        GROUP BY no_shipment_pasuruan, COALESCE(tujuan_pasuruan, '')
                    ) t
                    GROUP BY ns
                ) x ON lp.no_shipment_pasuruan = x.ns
                SET lp.cr_pasuruan = IF(
                    x.muatan = 0 OR {$n('lp.nilai_muatan_pasuruan')} <= 0,
                    0,
                    ROUND(({$n('lp.nilai_muatan_pasuruan')} * COALESCE(x.biaya, 0)) / (x.muatan * x.muatan) * 100, 4)
                )
                WHERE lp.no_shipment_pasuruan IN ($in)
            ", $b2);

            // ---- hasil kubik/tonase (per shipment + tujuan) & pengiriman optimal (per shipment) ----
            DB::update("
                UPDATE {$table} lp
                JOIN (
                    SELECT no_shipment_pasuruan AS ns,
                           SUM({$tk}) AS sum_kubik,
                           SUM({$tt}) AS sum_tonase,
                           {$kub} AS kubikasi,
                           {$ton} AS tonase
                    FROM {$table}
                    WHERE no_shipment_pasuruan IN ($in)
                    GROUP BY no_shipment_pasuruan
                ) s ON lp.no_shipment_pasuruan = s.ns
                JOIN (
                    SELECT no_shipment_pasuruan AS ns,
                           COALESCE(tujuan_pasuruan, '') AS tj,
                           SUM({$tk}) AS t_kubik,
                           SUM({$tt}) AS t_tonase
                    FROM {$table}
                    WHERE no_shipment_pasuruan IN ($in)
                    GROUP BY no_shipment_pasuruan, COALESCE(tujuan_pasuruan, '')
                ) t ON lp.no_shipment_pasuruan = t.ns
                   AND COALESCE(lp.tujuan_pasuruan, '') = t.tj
                SET
                    lp.hasil_kubik_pasuruan = CASE WHEN s.kubikasi > 0
                        THEN ROUND(t.t_kubik / s.kubikasi * 100, 2) ELSE NULL END,
                    lp.hasil_tonase_pasuruan = CASE WHEN s.tonase > 0
                        THEN ROUND(t.t_tonase / s.tonase * 100, 2) ELSE NULL END,
                    lp.pengiriman_optimal_pasuruan = CASE
                        WHEN (s.kubikasi > 0 AND (s.sum_kubik  / s.kubikasi * 100) >= 85)
                          OR (s.tonase   > 0 AND (s.sum_tonase / s.tonase   * 100) >= 85) THEN 'OPTIMAL'
                        WHEN s.kubikasi > 0 OR s.tonase > 0 THEN 'TIDAK OPTIMAL'
                        ELSE NULL END
                WHERE lp.no_shipment_pasuruan IN ($in)
            ", $b3);

            // ---- optimal per customer (per shipment + tujuan) ----
            DB::update("
                UPDATE {$table} lp
                JOIN (
                    SELECT no_shipment_pasuruan AS ns,
                           COALESCE(tujuan_pasuruan, '') AS tj,
                           SUM({$tk}) AS sum_kubik,
                           SUM({$tt}) AS sum_tonase,
                           COALESCE({$kub}, 0) AS kubikasi,
                           COALESCE({$ton}, 0) AS tonase
                    FROM {$table}
                    WHERE no_shipment_pasuruan IN ($in)
                    GROUP BY no_shipment_pasuruan, COALESCE(tujuan_pasuruan, '')
                ) x ON lp.no_shipment_pasuruan = x.ns
                   AND COALESCE(lp.tujuan_pasuruan, '') = x.tj
                SET lp.pengiriman_optimal_customer_pasuruan = CASE
                    WHEN x.kubikasi <= 0 AND x.tonase <= 0 THEN NULL
                    WHEN (x.kubikasi > 0 AND x.sum_kubik  / x.kubikasi * 100 >= 85)
                      OR (x.tonase   > 0 AND x.sum_tonase / x.tonase   * 100 >= 85) THEN 'OPTIMAL'
                    ELSE 'TIDAK OPTIMAL'
                END
                WHERE lp.no_shipment_pasuruan IN ($in)
            ", $b2);
        }
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

    private function generateStatusAlert($sla_tiba, $sla_bongkar)
    {
        $sla_tiba    = strtolower(trim($sla_tiba ?? '-'));
        $sla_bongkar = strtolower(trim($sla_bongkar ?? '-'));

        if ($sla_tiba == '-' || $sla_bongkar == '-') {
            return ['status_akhir' => '-', 'alert' => '-'];
        }
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

    // ================= NAMA EKSPEDISI: SAMAKAN DENGAN MASTER =================

    /**
     * Huruf kecil, tanpa awalan PT/CV/UD/PD, tanpa tanda baca dan spasi.
     * "PT. Prima Trans Logistik" dan "Prima Trans Logistik" -> "primatranslogistik".
     */
    private function ekspedisiKey(?string $v): string
    {
        $k = strtolower($this->normalize($v));
        $k = preg_replace('/^(pt|cv|ud|pd)\b[\s\.]*/', '', $k);
        return preg_replace('/[^a-z0-9]+/', '', $k);
    }

    /**
     * Cari nama di master yang maksudnya sama dengan $name.
     * Dipakai kalau cocok persis (setelah normalisasi), atau sangat mirip
     * dan jelas lebih mirip ke satu nama dibanding yang lain. Selain itu null.
     */
    private function resolveEkspedisiName(?string $name, $candidates): ?string
    {
        $key = $this->ekspedisiKey($name);
        if ($key === '') return null;

        if (isset(self::EKSPEDISI_ALIASES[$key])) {
            return self::EKSPEDISI_ALIASES[$key];
        }

        $best = null; $bestScore = 0.0; $second = 0.0;

        foreach ($candidates->pluck('ekpedisi')->filter()->unique() as $master) {
            $mk = $this->ekspedisiKey($master);
            if ($mk === '') continue;

            if ($mk === $key) return $master;

            similar_text($key, $mk, $pct);
            $score = $pct / 100;

            if (min(strlen($key), strlen($mk)) >= 6
                && (str_contains($mk, $key) || str_contains($key, $mk))) {
                $score = max($score, 0.9);
            }

            if ($score > $bestScore)  { $second = $bestScore; $bestScore = $score; $best = $master; }
            elseif ($score > $second) { $second = $score; }
        }

        return ($best !== null && $bestScore >= 0.85 && ($bestScore - $second) >= 0.05) ? $best : null;
    }

    /** Nama ekspedisi dari Excel -> nama di master; kalau tidak yakin, apa adanya. */
    private function canonEkspedisi(?string $v): ?string
    {
        if ($v === null || $v === '') return $v;

        if (array_key_exists($v, $this->ekspedisiCanon)) {
            return $this->ekspedisiCanon[$v];
        }

        if (self::$ekspedisiMaster === null) {
            self::$ekspedisiMaster = self::$tarifByRoute->flatten(1)
                ->pluck('ekpedisi')->filter()->unique()->values()
                ->map(fn($n) => (object) ['ekpedisi' => $n]);
        }

        $match = $this->resolveEkspedisiName($v, self::$ekspedisiMaster);

        return $this->ekspedisiCanon[$v] = ($match ?? $v);
    }

    /**
     * Cari baris tarif: Route (exact setelah normalisasi) + Ekpedisi (exact,
     * kalau terisi) + Mobil (PREFIX match karena kolom Excel sering kepotong).
     * Fallback: abaikan Ekpedisi, cukup Route + Mobil prefix.
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

    /** Nilai pertama yang terisi dari beberapa kemungkinan nama header. */
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
        if ($value === null || $value === '' || $value === '-' || $value === '#VALUE!') return null;
        if (is_array($value)) return null;

        // angka dari Excel (mis. 1201222007) jangan jadi notasi ilmiah / desimal
        if (is_float($value) && floor($value) == $value) {
            $value = (string) (int) $value;
        }

        $value = str_replace("\xC2\xA0", ' ', (string) $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));

        if ($value === '' || str_contains($value, '=')) return null;

        return $value;
    }

    private function cleanNumber($value)
    {
        if ($value === null || $value === '' || $value == '-') return 0;

        if (is_numeric($value)) return (float) $value;

        $value = str_replace(['Rp', 'rp', ' '], '', (string) $value);
        $value = str_replace(['.', ','], '', $value);

        return (float) $value;
    }

    /**
     * Angka desimal polos (bukan persen, bukan Rupiah).
     * Dipakai untuk total kubik/tonase dan kapasitas kubikasi/tonase.
     */
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

    /**
     * Tanggal: serial Excel, atau teks.
     * Teks "a/b/Y": kalau a > 12 dianggap d/m/Y, selain itu m/d/Y
     * (sesuai file kamu, mis. 10/31/2026 dan 11/2/2026).
     */
    private function convertDate($value)
    {
        if (!$value || $value == '-' || $value == '#VALUE!') return null;

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $value = trim((string) $value);

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $value, $m)) {
            [$a, $b, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            [$month, $day] = $a > 12 ? [$b, $a] : [$a, $b];

            return checkdate($month, $day, $y)
                ? Carbon::create($y, $month, $day)->format('Y-m-d')
                : null;
        }

        $timestamp = strtotime(str_replace('/', '-', $value));

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * Normalisasi Route: hapus NBSP, rapikan spasi di sekitar "-",
     * collapse spasi ganda, lowercase.
     */
    private function normalize(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s*-\s*/', '-', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return strtolower($value);
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