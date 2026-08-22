<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;

class LogistikImport implements ToModel, WithHeadingRow, WithEvents
{

    private static $customerMap = null;
    private static $tarifMap = null;

    // Nama tabel master tarif di database
    private const TARIF_TABLE = 'master_harga';

    public function __construct()
    {
        // =====================================================
        // MASTER DATA: tujuan -> dist_channel, pulau, area, planner, pic monitoring
        // =====================================================
        if (self::$customerMap === null) {
            self::$customerMap = DB::table('tujuanfillterr')
                ->select(
                    'tujuan',
                    'dist_channel',
                    'pulau',
                    'area',
                    'Planner',
                    'Monitoring'
                )
                ->get()
                ->keyBy(fn($row) => strtolower(trim($row->tujuan)));
        }

        // =====================================================
        // MASTER HARGA: route + mobil -> biaya_kirim
        //
        // CATATAN PENTING: matching SENGAJA cuma pakai Route + Mobil
        // (TANPA Ekpedisi), karena kolom "Ekpedisi" di file Excel yang
        // biasa kamu import selalu KOSONG. Kalau dipaksa ikut kolom
        // ekpedisi, key jadi "|route|mobil" (bagian ekpedisi kosong)
        // dan TIDAK PERNAH match ke master_harga (yang ekpedisinya
        // terisi), sehingga biaya_kirim selalu jatuh ke 0.
        //
        // KONSEKUENSI: kalau di master_harga ternyata ada beberapa
        // ekspedisi berbeda dengan kombinasi Route+Mobil yang SAMA
        // (harga beda-beda tergantung ekspedisi), keyBy() di bawah ini
        // cuma akan menyimpan baris TERAKHIR yang ketemu untuk
        // kombinasi itu — baris sebelumnya akan ketimpa/diabaikan.
        // Kalau kasus ini terjadi di data kamu, kabari saya supaya
        // logic-nya disesuaikan (misalnya perlu kolom tambahan sebagai
        // pembeda, atau ambil harga termurah/terbaru, dll).
        // =====================================================
        if (self::$tarifMap === null) {
            self::$tarifMap = DB::table(self::TARIF_TABLE)
                ->select('route', 'mobil', 'biaya_kirim')
                ->get()
                ->keyBy(fn($row) => $this->buildTarifKey($row->route, $row->mobil));
        }
    }

    public function model(array $row)
    {

        // =============================
        // SENTUL (GUDANG 2)
        // =============================

        $lama_digudang_2 = null;
        $sla_loading_2 = null;

        if (
            !empty($row['tanggal_tiba_gudang_2']) &&
            !empty($row['tanggal_keluar_gudang_2'])
        ) {

            $in2  = strtotime($row['tanggal_tiba_gudang_2']);
            $out2 = strtotime($row['tanggal_keluar_gudang_2']);

            $jam2 = ($out2 - $in2) / 3600;

            $lama_digudang_2 = round($jam2, 1) . ' Jam';

            if ($jam2 <= 24) {
                $sla_loading_2 = 'H+0';
            } elseif ($jam2 <= 48) {
                $sla_loading_2 = 'H+1';
            } else {
                $sla_loading_2 = 'H>1';
            }
        }

        // =============================
        // CCIE (GUDANG 3)
        // =============================

        $lama_digudang_3 = null;
        $sla_loading_3 = null;

        if (
            !empty($row['tanggal_tiba_gudang_3']) &&
            !empty($row['tanggal_keluar_gudang_3'])
        ) {

            $in3  = strtotime($row['tanggal_tiba_gudang_3']);
            $out3 = strtotime($row['tanggal_keluar_gudang_3']);

            $jam3 = ($out3 - $in3) / 3600;

            $lama_digudang_3 = round($jam3, 1) . ' Jam';

            if ($jam3 <= 24) {
                $sla_loading_3 = 'H+0';
            } elseif ($jam3 <= 48) {
                $sla_loading_3 = 'H+1';
            } else {
                $sla_loading_3 = 'H>1';
            }
        }

        // MONITORING (kolom tambahan)
        $totalDoCar = $this->cleanNumber($row['total_do_qty_car'] ?? null);
        $addtText4  = $this->cleanText($row['addt_text'] ?? null);

        // ================= DATE =================
        $rencanaKirim        = $this->convertDate($row['rencana_kirim'] ?? null);
        $tanggalKeluarGudang = $this->convertDate($row['tanggal_keluar_gudang'] ?? null);
        $tanggalTibaAktual   = $this->convertDate($row['tanggal_tiba'] ?? null);
        $tanggalNaikLogistik = $this->convertDate($row['tanggal_naik_logistik'] ?? null);
        $tanggalDptUnit      = $this->convertDate($row['tanggal_dpt_unit'] ?? null);
        $planningLoading     = $this->convertDate($row['planning_loading'] ?? null);
        $tanggalTibaGudang   = $this->convertDate($row['tanggal_tiba_di_gudang'] ?? null);
        $tanggalBongkar      = $this->convertDate($row['tanggal_bongkar'] ?? null);

        // =====================================================
        // SLA DAPAT MOBIL
        // tanggal_dpt_unit -> tanggal_tiba_gudang
        // =====================================================

        $lamaWaktuPencarian = null;
        $slaDapatMobil      = null;

        if ($tanggalDptUnit && $tanggalTibaGudang) {

            $selisihCariMobil = (int) date_diff(
                date_create($tanggalDptUnit),
                date_create($tanggalTibaGudang)
            )->format('%a');

            $lamaWaktuPencarian = $selisihCariMobil . ' Hari';

            $slaDapatMobil = ($selisihCariMobil == 0)
                ? 'On Time'
                : 'Delay';
        }

        // =====================================================
        // SLA LOADING
        // tanggal_tiba_gudang -> tanggal_keluar_gudang
        // =====================================================

        $lamaDigudang = null;
        $slaLoading   = null;

        if ($tanggalTibaGudang && $tanggalKeluarGudang) {

            $selisihGudang = (int) date_diff(
                date_create($tanggalTibaGudang),
                date_create($tanggalKeluarGudang)
            )->format('%a');

            $lamaDigudang = $selisihGudang . ' Hari';

            $slaLoading = ($selisihGudang == 0)
                ? 'On Time'
                : 'Delay';
        }

        $act_pgi_date = isset($row['act_pgi_date']) && is_numeric($row['act_pgi_date'])
            ? Date::excelToDateTimeObject($row['act_pgi_date'])->format('Y-m-d')
            : null;

        $custGrp5     = $this->cleanText($row['cust_grp_5_desc'] ?? null);
        $custGrp3     = $this->cleanText($row['cust_grp_3_desc'] ?? null);
        $shipNo       = $this->cleanText($row['ship_no'] ?? null);
        $ekpedisi     = $this->cleanText($row['ekpedisi'] ?? null);
        $viaKirim     = $this->cleanText($row['via_kirim'] ?? $row['via'] ?? null); // fleksibel jika header excel hanya 'via'
        $custDesc     = $this->cleanText($row['cust_desc'] ?? null);
        $serviceAgent = $this->cleanText($row['service_agent'] ?? null);

        // =====================================================
        // BIAYA KIRIM: lookup otomatis ke master_harga berdasarkan
        // Route + Mobil. Kalau tidak ketemu match, fallback ke nilai
        // "Biaya Kirim (Rp)" yang ada di file Excel (kalau ada).
        // =====================================================
        $route  = $this->cleanText($row['route'] ?? null);
        $mobil  = $this->cleanText($row['mobil'] ?? null);

        $tarifKey = $this->buildTarifKey($route, $mobil);
        $tarifRow = self::$tarifMap[$tarifKey] ?? null;

        $biayaKirim = $tarifRow
            ? $this->cleanNumberTarif($tarifRow->biaya_kirim)
            : $this->cleanNumber($row['biaya_kirim_rp'] ?? null);

        $tujuan    = $this->cleanText($row['tujuan'] ?? null);
        $tujuanKey = preg_replace('/\s+/', ' ', trim(strtolower($tujuan)));

        // =====================================================
        // LOOKUP MASTER: tujuan -> dist_channel, pulau, area, planner, pic
        // =====================================================
        $customerData = self::$customerMap[$tujuanKey] ?? null;

        $distChannel   = $customerData->dist_channel ?? null;
        $pulauMaster   = $customerData->pulau ?? null;
        $area          = $customerData->area ?? null;
        $plannerMaster = $customerData->Planner ?? null;
        $picMonitoring = $customerData->Monitoring ?? null;

        // Kolom pulau/planner dari file Excel (dipakai sebagai fallback
        // kalau tujuan tidak ketemu di master, atau master kosong)
        $pulauFromFile   = $this->cleanText($row['pulau'] ?? null);
        $plannerFromFile = $this->cleanText($row['planner'] ?? null);

        $pulau   = $pulauMaster ?: $pulauFromFile;
        $planner = $plannerMaster ?: $plannerFromFile;

        // ================= LEAD TIME =================
        $leadTime = is_numeric($row['transport_lead_time'] ?? null)
            ? (int) $row['transport_lead_time']
            : 0;

        // =====================================================
        // MONITORING (SAMA PERSIS DENGAN CONTROLLER)
        // =====================================================

        // ambil tanggal keluar terakhir
        $keluar = collect([
            $tanggalKeluarGudang,
            $this->convertDate($row['tanggal_keluar_gudang_2'] ?? null),
            $this->convertDate($row['tanggal_keluar_gudang_3'] ?? null),
        ])
            ->filter()
            ->map(fn($d) => strtotime($d))
            ->max();

        $tiba = $tanggalTibaAktual
            ? strtotime($tanggalTibaAktual)
            : null;

        $bongkar = $tanggalBongkar
            ? strtotime($tanggalBongkar)
            : null;

        $leadtime = (int) ($row['transport_lead_time'] ?? 0);

        // ================= ESTIMASI =================
        $estimasi = $keluar
            ? strtotime("+{$leadtime} days", $keluar)
            : null;

        // ================= LAMA PERJALANAN =================
        $lamaPerjalanan = ($keluar && $tiba)
            ? max(0, floor(($tiba - $keluar) / 86400))
            : null;

        // ================= SLA TIBA =================
        $slaTiba = ($tiba && $estimasi)
            ? (($tiba <= $estimasi) ? 'On Time' : 'Delay')
            : '-';

        // ================= OVERSTAY =================
        $overstay = ($tiba && $bongkar)
            ? max(0, floor(($bongkar - $tiba) / 86400))
            : null;

        // ================= SLA BONGKAR =================
        $slaBongkar = ($tiba && $bongkar)
            ? (($overstay <= 0) ? 'On Time' : 'Delay')
            : '-';

        // ================= STATUS AKHIR =================
        $logic = $this->generateStatusAlert($slaTiba, $slaBongkar);

        $statusAkhir     = $logic['status_akhir'];
        $monitoringAlert = $logic['alert'];

        // ================= NORMALISASI KETERSEDIAAN UNIT =================
        $ketersediaanUnit = $this->cleanText($row['ketersediaan_unit'] ?? null);

        if ($ketersediaanUnit === null || $ketersediaanUnit === '' || $ketersediaanUnit === '-') {
            $ketersediaanUnit = 'BELUM DAPAT';
        } else {
            $ketersediaanUnit = strtoupper(trim($ketersediaanUnit));
        }

        // variasi data
        if (in_array($ketersediaanUnit, [
            'SUDAH DAPAT MOBIL',
            'READY MOBIL',
            'READY'
        ])) {
            $ketersediaanUnit = 'SUDAH DAPAT';
        }

        if (in_array($ketersediaanUnit, [
            'BELUM DAPAT MOBIL',
            'PENDING'
        ])) {
            $ketersediaanUnit = 'BELUM DAPAT';
        }

        // CREATE DATE
        $create_tgl = date('Y-m-d H:i:s');

        return new LogistikPengiriman([

            // ================= BASIC =================
            'no'                  => $this->cleanText($row['no'] ?? null),
            'create_tgl'          => $create_tgl,
            'transport_lead_time' => $this->cleanNumber($row['transport_lead_time'] ?? null),
            'planner'             => $planner,
            'no_shipment'         => $this->cleanText($row['no_shipment'] ?? null),
            'tujuan'              => $tujuan,
            'dist_channel'        => $distChannel,
            'area'                => $area,
            'ketersediaan_unit'   => $ketersediaanUnit,

            'pulau'      => $pulau,
            'route'      => $route,
            'via_kirim'  => $viaKirim,
            'mobil'      => $mobil,

            'perubahan_mobil'    => $this->cleanText($row['perubahan_mobil'] ?? null),
            'cr'                 => $this->cleanText($row['cr'] ?? null),
            'kategori_ekspedisi' => $this->cleanText($row['kategori_ekspedisi'] ?? null),
            'ekpedisi'           => $ekpedisi,
            'nama_driver'        => $this->cleanText($row['nama_driver'] ?? null),
            'no_pol'             => $this->cleanText($row['no_pol'] ?? ($row['nopol'] ?? null)),

            'status_pengiriman' => $this->cleanText($row['status'] ?? null),

            // ================= NUMBER =================
            'nilai_muatan' => $this->cleanNumber($row['nilai_muatan_rp'] ?? null),
            'biaya_kirim'  => $biayaKirim,

            // ================= DATE =================
            'tanggal_naik_logistik' => $tanggalNaikLogistik,
            'rencana_kirim'         => $rencanaKirim,
            'tanggal_dpt_unit'      => $tanggalDptUnit,
            'planning_loading'      => $planningLoading,
            'tanggal_tiba_gudang'   => $tanggalTibaGudang,
            'tanggal_keluar_gudang' => $tanggalKeluarGudang,
            'tanggal_tiba'          => $tanggalTibaAktual,
            'tanggal_bongkar'       => $tanggalBongkar,

            // ================= AUTO =================
            'estimasi_tiba' => $estimasi
                ? date('Y-m-d', $estimasi)
                : null,

            'lama_perjalanan'  => $lamaPerjalanan,
            'sla_tiba'         => $slaTiba,
            'overstay_days'    => $overstay,
            'sla_bongkar'      => $slaBongkar,
            'status_akhir'     => $statusAkhir,
            'monitoring_alert' => $monitoringAlert,

            // ================= GUDANG PERTAMA =================
            'lama_waktu_pencarian' => $lamaWaktuPencarian,
            'sla_dapat_mobil'      => $slaDapatMobil,

            'lama_digudang' => $lamaDigudang,
            'sla_loading'   => $slaLoading,

            // ================= OTHER =================
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

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function generateStatusAlert($sla_tiba, $sla_bongkar)
    {
        $sla_tiba    = strtolower(trim($sla_tiba ?? '-'));
        $sla_bongkar = strtolower(trim($sla_bongkar ?? '-'));

        if ($sla_tiba == '-' || $sla_bongkar == '-') {
            return [
                'status_akhir' => '-',
                'alert' => '-'
            ];
        }

        if ($sla_tiba == 'on time' && $sla_bongkar == 'on time') {
            return [
                'status_akhir' => 'On Time Total',
                'alert' => 'Delivered On Time'
            ];
        }

        if ($sla_tiba == 'delay' && $sla_bongkar == 'on time') {
            return [
                'status_akhir' => 'Delay Perjalanan',
                'alert' => 'Delay Perjalanan'
            ];
        }

        if ($sla_tiba == 'on time' && $sla_bongkar == 'delay') {
            return [
                'status_akhir' => 'Delay Pembongkaran',
                'alert' => 'Delay Pembongkaran'
            ];
        }

        return [
            'status_akhir' => 'Delay Total',
            'alert' => 'Delivered Delay'
        ];
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

    private function convertDate($value)
    {
        if (!$value || $value == '-' || $value == '#VALUE!') return null;

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $timestamp = strtotime(str_replace('/', '-', trim($value)));

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * Key untuk lookup tabel master_harga.
     * SENGAJA cuma pakai Route + Mobil (tanpa Ekpedisi) — lihat catatan
     * panjang di __construct().
     */
  private function buildTarifKey(?string $route, ?string $mobil): string
{
    $norm = function ($v) {
        $v = (string) $v;
        $v = str_replace("\xC2\xA0", ' ', $v);      // NBSP -> spasi biasa
        $v = preg_replace('/\s*-\s*/', '-', $v);     // "A - B" / "A-  B" -> "A-B"
        $v = preg_replace('/\s+/', ' ', trim($v));
        return strtolower($v);
    };

    return $norm($route) . '|' . $norm($mobil);
}

    /**
     * Kolom biaya_kirim di master_harga formatnya "8,500,000"
     * (koma = pemisah ribuan), BEDA dari cleanNumber() yang ada
     * sekarang (didesain untuk format Rupiah "Rp1.000.000" dengan
     * titik). Kalau pakai cleanNumber() yang lama, hasilnya salah.
     */
    private function cleanNumberTarif($value): float
    {
        if ($value === null || $value === '' || $value == '-') return 0;

        $value = str_replace(['Rp', 'rp', ' ', ','], '', (string) $value);

        return is_numeric($value) ? (float) $value : 0;
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {

                DB::statement("
                    UPDATE logistik_pengiriman lp
                    JOIN (
                        SELECT
                            no_shipment,
                            MAX(biaya_kirim) AS biaya,
                            SUM(nilai_muatan) AS muatan
                        FROM logistik_pengiriman
                        GROUP BY no_shipment
                    ) x ON lp.no_shipment = x.no_shipment
                    SET lp.cr = IF(x.muatan = 0, 0, ROUND((x.biaya / x.muatan) * 100, 4))
                ");

            },
        ];
    }
}