<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Collection;

class LogistikImport implements ToCollection, WithHeadingRow, WithEvents, WithCalculatedFormulas
{
    private static $customerMap = null;
    private static $tarifByRoute = null;
    private const TARIF_TABLE = 'tarif_pengiriman';
    private const ROUTE_ALIASES = [
    'jabodetabek' => 'Sentul-Jabodetabek',
    ];

    // forward-fill state (merged cell di Excel)
    private $lastNoShipment  = null;
    private $lastRoute       = null;
    private $lastMobil       = null;
    private $lastEkpedisi    = null;
    private $lastTotalKubik  = null;
    private $lastTotalTonase = null;

    // ===== hasil proses =====
    private $inserted = 0;   // baris baru
    private $updated  = 0;   // baris lama, ditimpa data terbaru
    private $skipped  = 0;   // no_shipment/tujuan kosong
    private $failed   = 0;   // error/exception
    private array $failedList = [];
    private array $allNoShipmentInFile = [];

    public function getInsertedCount(): int { return $this->inserted; }
    public function getUpdatedCount(): int { return $this->updated; }
    public function getImportedCount(): int { return $this->inserted + $this->updated; }
    public function getSkippedCount(): int { return $this->skipped; }
    public function getFailedCount(): int { return $this->failed; }
    public function getFailedList(): array { return $this->failedList; }
    public function getAllNoShipmentInFile(): array { return $this->allNoShipmentInFile; }

    public function __construct()
    {
        if (self::$customerMap === null) {
            self::$customerMap = DB::table('tujuanfillterr')
                ->select('tujuan', 'dist_channel', 'pulau', 'area', 'Planner', 'biaya_kuli', 'transport_lead_time', 'Monitoring')
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
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $row = $row->toArray();

            $noShipmentCheck = $this->cleanText($row['no_shipment'] ?? null);
            $tujuanCheck     = $this->cleanText($row['tujuan'] ?? null);

            if (empty($noShipmentCheck) || empty($tujuanCheck)) {
                $this->skipped++;
                continue;
            }

            $this->allNoShipmentInFile[] = $noShipmentCheck;

            try {
                $attributes = $this->buildAttributes($row, $noShipmentCheck);

                $model = LogistikPengiriman::updateOrCreate(
                    [
                        'no_shipment' => $noShipmentCheck,
                        'tujuan'      => $tujuanCheck,
                    ],
                    $attributes
                );

                if ($model->wasRecentlyCreated) {
                    $this->inserted++;
                } else {
                    $this->updated++;
                }

            } catch (\Throwable $e) {
                $this->failed++;
                $this->failedList[] = [
                    'no_shipment' => $noShipmentCheck,
                    'tujuan'      => $tujuanCheck,
                    'error'       => $e->getMessage(),
                    'line'        => $e->getLine(),
                ];

                logger()->error('LOGISTIK IMPORT GAGAL PER BARIS', [
                    'no_shipment' => $noShipmentCheck,
                    'tujuan'      => $tujuanCheck,
                    'error'       => $e->getMessage(),
                    'file'        => $e->getFile(),
                    'line'        => $e->getLine(),
                ]);
            }
        }
    }

    /**
     * Bangun array atribut untuk 1 baris. Logic PERSIS SAMA seperti
     * model() versi lama, cuma sekarang return array (untuk
     * updateOrCreate), bukan objek Model baru.
     */
    private function buildAttributes(array $row, string $noShipmentCheck): array
    {
        // ================= SENTUL (GUDANG 2) =================
        $lama_digudang_2 = null;
        $sla_loading_2 = null;

        if (!empty($row['tanggal_tiba_gudang_2']) && !empty($row['tanggal_keluar_gudang_2'])) {
            $in2  = strtotime($row['tanggal_tiba_gudang_2']);
            $out2 = strtotime($row['tanggal_keluar_gudang_2']);
            $jam2 = ($out2 - $in2) / 3600;
            $lama_digudang_2 = round($jam2, 1) . ' Jam';
            $sla_loading_2 = $jam2 <= 24 ? 'H+0' : ($jam2 <= 48 ? 'H+1' : 'H>1');
        }

        // ================= CCIE (GUDANG 3) =================
        $lama_digudang_3 = null;
        $sla_loading_3 = null;

        if (!empty($row['tanggal_tiba_gudang_3']) && !empty($row['tanggal_keluar_gudang_3'])) {
            $in3  = strtotime($row['tanggal_tiba_gudang_3']);
            $out3 = strtotime($row['tanggal_keluar_gudang_3']);
            $jam3 = ($out3 - $in3) / 3600;
            $lama_digudang_3 = round($jam3, 1) . ' Jam';
            $sla_loading_3 = $jam3 <= 24 ? 'H+0' : ($jam3 <= 48 ? 'H+1' : 'H>1');
        }

        $totalDoCar = $this->cleanNumber($row['total_do_qty_car'] ?? null);
        $addtText4  = $this->cleanText($row['addt_text'] ?? null);

        $kubikasi = $this->cleanPersen($this->pick($row, ['kubikasi', 'kubikasi_persen', 'kubikasi_1']));
        $tonase   = $this->cleanPersen($this->pick($row, ['tonase', 'tonase_persen']));

        // ================= DATE =================
        $rencanaKirim        = $this->convertDate($row['rencana_kirim'] ?? null);
        $tanggalKeluarGudang = $this->convertDate($row['tanggal_keluar_gudang'] ?? null);
        $tanggalTibaAktual   = $this->convertDate($row['tanggal_tiba'] ?? null);
        $tanggalNaikLogistik = $this->convertDate($row['tanggal_naik_logistik'] ?? null);
        $tanggalDptUnit      = $this->convertDate($row['tanggal_dpt_unit'] ?? null);
        $planningLoading     = $this->convertDate($row['planning_loading'] ?? null);
        $tanggalTibaGudang   = $this->convertDate($row['tanggal_tiba_di_gudang'] ?? null);
        $tanggalBongkar      = $this->convertDate($row['tanggal_bongkar'] ?? null);

        // ================= SLA DAPAT MOBIL =================
        $lamaWaktuPencarian = null;
        $slaDapatMobil      = null;

        if ($tanggalDptUnit && $tanggalTibaGudang) {
            $selisihCariMobil = (int) date_diff(date_create($tanggalDptUnit), date_create($tanggalTibaGudang))->format('%a');
            $lamaWaktuPencarian = $selisihCariMobil . ' Hari';
            $slaDapatMobil = ($selisihCariMobil == 0) ? 'On Time' : 'Delay';
        }

        // ================= SLA LOADING =================
        $lamaDigudang = null;
        $slaLoading   = null;

        if ($tanggalTibaGudang && $tanggalKeluarGudang) {
            $selisihGudang = (int) date_diff(date_create($tanggalTibaGudang), date_create($tanggalKeluarGudang))->format('%a');
            $lamaDigudang = $selisihGudang . ' Hari';
            $slaLoading = ($selisihGudang == 0) ? 'On Time' : 'Delay';
        }

        $act_pgi_date = $this->convertDate($row['act_pgi_date'] ?? null);

        $custGrp5     = $this->cleanText($row['cust_grp_5_desc'] ?? null);
        $custGrp3     = $this->cleanText($row['cust_grp_3_desc'] ?? null);
        $shipNo       = $this->cleanText($row['ship_no'] ?? null);
        $viaKirim     = $this->cleanText($row['via_kirim'] ?? $row['via'] ?? null);
        $custDesc     = $this->cleanText($row['cust_desc'] ?? null);
        $serviceAgent = $this->cleanText($row['service_agent'] ?? null);

        // ================= FORWARD-FILL =================
        $noShipment = $noShipmentCheck;

        if ($noShipment !== $this->lastNoShipment) {
            $this->lastRoute       = null;
            $this->lastMobil       = null;
            $this->lastEkpedisi    = null;
            $this->lastTotalKubik  = null;
            $this->lastTotalTonase = null;
        }

     $route    = $this->applyRouteAlias(
                $this->cleanText($row['route'] ?? null) ?: $this->lastRoute
            );
        $mobil    = $this->cleanText($row['mobil'] ?? null)    ?: $this->lastMobil;
        $ekpedisi = $this->cleanText($row['ekpedisi'] ?? null) ?: $this->lastEkpedisi;

        $totalKubik = $this->cleanDecimal($this->pick($row, [
            'total_kubik', 'total_kubik_m3', 'total_kubik_m_3', 'totalkubik'
        ])) ?? $this->lastTotalKubik;

        $totalTonase = $this->cleanDecimal($this->pick($row, [
            'total_tonase', 'total_tonase_ton', 'totaltonase'
        ])) ?? $this->lastTotalTonase;

        if ($route)    $this->lastRoute    = $route;
        if ($mobil)    $this->lastMobil    = $mobil;
        if ($ekpedisi) $this->lastEkpedisi = $ekpedisi;
        if ($totalKubik !== null)  $this->lastTotalKubik  = $totalKubik;
        if ($totalTonase !== null) $this->lastTotalTonase = $totalTonase;

        $this->lastNoShipment = $noShipment;

        // ================= BIAYA KIRIM & TARIF =================
        $tarifRow   = $this->findTarif($route, $ekpedisi, $mobil);
        $biayaKirim = $tarifRow
            ? $this->cleanNumberTarif($tarifRow->biaya_kirim)
            : $this->cleanNumber($row['biaya_kirim_rp'] ?? null);

        $kubikasi = $tarifRow ? $this->cleanPersen($tarifRow->kubikasi ?? null) : null;
        $tonase   = $tarifRow ? $this->cleanPersen($tarifRow->tonase ?? null)   : null;

        $hasilKubik = ($kubikasi !== null && $kubikasi > 0 && $totalKubik !== null)
            ? round(($totalKubik / $kubikasi) * 100, 2)
            : null;

        $hasilTonase = ($tonase !== null && $tonase > 0 && $totalTonase !== null)
            ? round(($totalTonase / $tonase) * 100, 2)
            : null;

        $pengirimanOptimal = null;
        if ($hasilKubik !== null || $hasilTonase !== null) {
            $pengirimanOptimal = (($hasilKubik >= 85) || ($hasilTonase >= 85)) ? 'OPTIMAL' : 'TIDAK OPTIMAL';
        }

        $tujuan    = $this->cleanText($row['tujuan'] ?? null);
        $tujuanKey = preg_replace('/\s+/', ' ', trim(strtolower($tujuan)));

        // ================= LOOKUP MASTER =================
        $customerData = self::$customerMap[$tujuanKey] ?? null;

        $distChannel   = $customerData->dist_channel ?? null;
        $pulauMaster   = $customerData->pulau ?? null;
        $area          = $customerData->area ?? null;
        $plannerMaster = $customerData->Planner ?? null;
        $picMonitoring = $customerData->Monitoring ?? null;
        $biayaKuli     = $customerData->biaya_kuli ?? null;
        $transport_lead_time = $customerData->transport_lead_time ?? null;

        $pulauFromFile   = $this->cleanText($row['pulau'] ?? null);
        $plannerFromFile = $this->cleanText($row['planner'] ?? null);

        $pulau   = $pulauMaster ?: $pulauFromFile;
        $planner = $plannerMaster ?: $plannerFromFile;

        // ================= MONITORING =================
        $keluar = collect([
            $tanggalKeluarGudang,
            $this->convertDate($row['tanggal_keluar_gudang_2'] ?? null),
            $this->convertDate($row['tanggal_keluar_gudang_3'] ?? null),
        ])->filter()->map(fn($d) => strtotime($d))->max();

        $tiba    = $tanggalTibaAktual ? strtotime($tanggalTibaAktual) : null;
        $bongkar = $tanggalBongkar ? strtotime($tanggalBongkar) : null;

        $leadtime = (int) ($row['transport_lead_time'] ?? 0);
        $estimasi = $keluar ? strtotime("+{$leadtime} days", $keluar) : null;

        $lamaPerjalanan = ($keluar && $tiba) ? max(0, floor(($tiba - $keluar) / 86400)) : null;
        $slaTiba = ($tiba && $estimasi) ? (($tiba <= $estimasi) ? 'On Time' : 'Delay') : '-';

        $overstay = ($tiba && $bongkar) ? max(0, floor(($bongkar - $tiba) / 86400)) : null;
        $slaBongkar = ($tiba && $bongkar) ? (($overstay <= 0) ? 'On Time' : 'Delay') : '-';

        $logic = $this->generateStatusAlert($slaTiba, $slaBongkar);
        $statusAkhir     = $logic['status_akhir'];
        $monitoringAlert = $logic['alert'];

        // ================= KETERSEDIAAN UNIT =================
        $ketersediaanUnit = $this->cleanText($row['ketersediaan_unit'] ?? null);

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

        return [
            'no'                  => $this->cleanText($row['no'] ?? null),
            'create_tgl'          => date('Y-m-d H:i:s'),
            'transport_lead_time' => $transport_lead_time,
            'planner'             => $planner,
            'dist_channel'        => $distChannel,
            'area'                => $area,
            'ketersediaan_unit'   => $ketersediaanUnit,

            'pulau'      => $pulau,
            'route'      => $route,
            'via_kirim'  => $viaKirim,
            'mobil'      => $mobil,

            'perubahan_mobil'    => $this->cleanText($row['perubahan_mobil'] ?? null),
            'cr'                 => $this->cleanText($row['cr'] ?? null),
          'kategori_ekspedisi' => $this->getKategoriEkspedisi($noShipmentCheck)
                        ?? $this->cleanText($row['kategori_ekspedisi'] ?? null),
            'ekpedisi'           => $ekpedisi,
            'kubikasi'           => $kubikasi,
            'tonase'             => $tonase,
            'total_kubik'        => $totalKubik,
            'total_tonase'       => $totalTonase,
            'hasil_kubik'        => $hasilKubik,
            'hasil_tonase'       => $hasilTonase,
            'pengiriman_optimal' => $pengirimanOptimal,
            'nama_driver'        => $this->cleanText($row['nama_driver'] ?? null),
            'no_pol'             => $this->cleanText($row['no_pol'] ?? ($row['nopol'] ?? null)),

            'status_pengiriman' => $this->cleanText($row['status'] ?? null),

            'nilai_muatan' => $this->cleanNumber($row['nilai_muatan_rp'] ?? null),
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

            'status'      => $this->cleanText($row['status'] ?? null),
            'keterangan'  => $this->cleanText($row['keterangan'] ?? null),

            'pic_monitoring'   => $picMonitoring,
            'status_kendaraan' => $this->cleanText($row['status_kendaraan'] ?? null),
            'action_required'  => $this->cleanText($row['action_required'] ?? null),

            'act_urutan_bongkar' => $row['ac_turutan_bongkar'] ?? null,

            'reason_tiba'    => $this->cleanText($row['reason_waktu_tiba'] ?? null),
            'reason_bongkar' => $this->cleanText($row['reason_waktu_bongkar'] ?? null),

            'act_pgi_date'    => $act_pgi_date,
            'cust_grp_5_desc' => $custGrp5,
            'created_by'      => $this->cleanText($row['created_by'] ?? null),
            'cust_grp_3_desc' => $custGrp3,
            'ship_no'         => $shipNo,
            'cust_desc'       => $custDesc,
            'addt_text_4'     => $addtText4,
            'service_agent'   => $serviceAgent,
            'total_do_qty_car' => $totalDoCar,

            'updated_at' => now(),
        ];
    }
private function applyRouteAlias(?string $route): ?string
{
    if ($route === null || $route === '') return $route;

    $key = $this->normalize($route);
    return self::ROUTE_ALIASES[$key] ?? $route;
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

    // ================= HELPERS =================

    private function cleanText($value)
    {
        if (!$value || $value == '-' || $value == '#VALUE!') return null;
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

    private function getKategoriEkspedisi(?string $noShipment): ?string
{
    $no = trim((string) $noShipment);

    if (str_starts_with($no, '45')) return 'Kontrak';
    if (str_starts_with($no, '42')) return 'Oncall';

    return null;
}

    private function convertDate($value)
    {
        if (!$value || $value == '-' || $value == '#VALUE!') return null;
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }
        $timestamp = strtotime(str_replace('/', '-', trim($value)));
        return $timestamp ? date('Y-m-d', $timestamp) : null;
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
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));
        return strtolower($value);
    }

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
            if ($strict) return $strict;
        }

        return $candidates->first(function ($row) use ($mobilExcel) {
            $mobilMaster = $this->normalizeMobil($row->mobil);
            return str_starts_with($mobilMaster, $mobilExcel);
        });
    }

    private function pick(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== '-') {
                return $row[$k];
            }
        }
        return null;
    }

    private function cleanNumberTarif($value): float
    {
        if ($value === null || $value === '' || $value == '-') return 0;
        $value = str_replace(['Rp', 'rp', ' ', '.', ','], '', (string) $value);
        return (float) $value;
    }

    // public function registerEvents(): array
    // {
    //     return [
    //         AfterImport::class => function () {
    //             foreach (['route', 'mobil', 'ekpedisi'] as $col) {
    //                 DB::statement("
    //                     UPDATE logistik_pengiriman lp
    //                     JOIN (
    //                         SELECT no_shipment, MIN($col) AS val
    //                         FROM logistik_pengiriman
    //                         WHERE $col IS NOT NULL AND $col != ''
    //                         GROUP BY no_shipment
    //                     ) x ON lp.no_shipment = x.no_shipment
    //                     SET lp.$col = x.val
    //                     WHERE (lp.$col IS NULL OR lp.$col = '')
    //                       AND lp.no_shipment IS NOT NULL
    //                       AND lp.no_shipment != ''
    //                 ");
    //             }

    //             DB::statement("
    //                 UPDATE logistik_pengiriman lp
    //                 JOIN (
    //                     SELECT no_shipment, MAX(biaya_kirim) AS biaya, SUM(nilai_muatan) AS muatan
    //                     FROM logistik_pengiriman
    //                     GROUP BY no_shipment
    //                 ) x ON lp.no_shipment = x.no_shipment
    //                 SET lp.cr = IF(
    //                     x.muatan = 0 OR lp.nilai_muatan <= 0,
    //                     0,
    //                     ROUND((lp.nilai_muatan * x.biaya) / (x.muatan * x.muatan) * 100, 4)
    //                 )
    //             ");
    //         },
    //     ];
    // }

    public function registerEvents(): array
{
    return [
        AfterImport::class => function () {
            foreach (['route', 'mobil', 'ekpedisi'] as $col) {
                DB::statement("
                    UPDATE logistik_pengiriman lp
                    JOIN (
                        SELECT no_shipment, MIN($col) AS val
                        FROM logistik_pengiriman
                        WHERE $col IS NOT NULL AND $col != ''
                        GROUP BY no_shipment
                    ) x ON lp.no_shipment = x.no_shipment
                    SET lp.$col = x.val
                    WHERE (lp.$col IS NULL OR lp.$col = '')
                      AND lp.no_shipment IS NOT NULL
                      AND lp.no_shipment != ''
                ");
            }

            DB::statement("
                UPDATE logistik_pengiriman lp
                JOIN (
                    SELECT no_shipment, MAX(biaya_kirim) AS biaya, SUM(nilai_muatan) AS muatan
                    FROM logistik_pengiriman
                    GROUP BY no_shipment
                ) x ON lp.no_shipment = x.no_shipment
                SET lp.cr = IF(
                    x.muatan = 0 OR lp.nilai_muatan <= 0,
                    0,
                    ROUND((lp.nilai_muatan * x.biaya) / (x.muatan * x.muatan) * 100, 4)
                )
            ");

            // ================================================================
            // HASIL KUBIK / HASIL TONASE / PENGIRIMAN OPTIMAL
            // Dihitung dari PENJUMLAHAN total_kubik & total_tonase seluruh
            // baris dalam satu no_shipment (kalau shipment punya banyak
            // tujuan/baris), dibagi kubikasi & tonase kendaraan (dari tarif).
            // Ini sengaja dilakukan SETELAH semua baris diimport supaya
            // shipment dengan banyak tujuan dihitung sebagai satu kesatuan,
            // bukan per baris.
            // ================================================================
            DB::statement("
                UPDATE logistik_pengiriman lp
                JOIN (
                    SELECT
                        no_shipment,
                        SUM(COALESCE(total_kubik, 0))  AS sum_kubik,
                        SUM(COALESCE(total_tonase, 0)) AS sum_tonase,
                        MAX(kubikasi) AS kubikasi,
                        MAX(tonase)   AS tonase
                    FROM logistik_pengiriman
                    WHERE no_shipment IS NOT NULL AND no_shipment != ''
                    GROUP BY no_shipment
                ) x ON lp.no_shipment = x.no_shipment
                SET
                    lp.hasil_kubik = CASE
                        WHEN x.kubikasi IS NOT NULL AND x.kubikasi > 0
                        THEN ROUND(x.sum_kubik / x.kubikasi * 100, 2)
                        ELSE NULL
                    END,
                    lp.hasil_tonase = CASE
                        WHEN x.tonase IS NOT NULL AND x.tonase > 0
                        THEN ROUND(x.sum_tonase / x.tonase * 100, 2)
                        ELSE NULL
                    END,
                    lp.pengiriman_optimal = CASE
                        WHEN
                            (x.kubikasi > 0 AND (x.sum_kubik / x.kubikasi * 100) >= 85)
                            OR
                            (x.tonase > 0 AND (x.sum_tonase / x.tonase * 100) >= 85)
                        THEN 'OPTIMAL'
                        WHEN
                            (x.kubikasi > 0 AND x.kubikasi IS NOT NULL)
                            OR
                            (x.tonase > 0 AND x.tonase IS NOT NULL)
                        THEN 'TIDAK OPTIMAL'
                        ELSE NULL
                    END
                WHERE lp.no_shipment IS NOT NULL AND lp.no_shipment != ''
            ");
        },
    ];
}
}