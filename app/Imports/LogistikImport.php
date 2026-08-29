<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;
use App\Models\TarifPengiriman;

class LogistikImport implements ToModel, WithHeadingRow, WithEvents
{

    private static $customerMap = null;

    // =====================================================
    // $tarifByRoute: dikelompokkan per ROUTE (bukan per route+mobil
    // seperti sebelumnya), supaya bisa dilakukan matching yang lebih
    // fleksibel untuk kolom mobil & ekpedisi. Structure:
    // [ normalized_route => Collection of {ekpedisi, mobil, biaya_kirim} ]
    // =====================================================
    private static $tarifByRoute = null;

    // Nama tabel master tarif di database
    private const TARIF_TABLE = 'tarif_pengiriman';

    // =====================================================
    // FORWARD-FILL STATE (untuk kolom yang di Excel-nya hasil MERGED
    // CELL: hanya baris pertama dari sebuah No Shipment yang terisi
    // Route / Mobil / Ekpedisi, baris-baris berikutnya untuk No
    // Shipment yang sama KOSONG karena efek merge visual di Excel).
    //
    // Cara kerja: setiap kali baris diproses, kalau No Shipment SAMA
    // dengan baris sebelumnya, dan kolom Route/Mobil/Ekpedisi di baris
    // ini kosong, maka dipakai nilai terakhir yang pernah terisi untuk
    // No Shipment tsb. Kalau No Shipment BERUBAH, cache di-reset supaya
    // tidak "bocor" ke shipment lain.
    //
    // CATATAN: ini hanya valid kalau baris-baris dengan No Shipment
    // yang sama letaknya BERURUTAN di file Excel (kondisi normal untuk
    // hasil export dengan merged cell). Kalau suatu saat baris dengan
    // No Shipment yang sama ternyata TIDAK berurutan, logic ini perlu
    // diganti jadi post-process per grup (lihat AfterImport).
    // =====================================================
    private $lastNoShipment = null;
    private $lastRoute      = null;
    private $lastMobil      = null;
    private $lastEkpedisi   = null;

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
                    'biaya_kuli',
                    'transport_lead_time',
                    'Monitoring'
                )
                 ->where('Div', 'HO Meruya')  
                ->get()
                ->keyBy(fn($row) => strtolower(trim($row->tujuan)));
        }

        // =====================================================
        // MASTER HARGA: route -> daftar kandidat (ekpedisi, mobil, biaya_kirim)
        //
        // CATATAN PENTING (UPDATE):
        // Sebelumnya matching cuma pakai Route + Mobil (exact match),
        // dengan asumsi kolom Ekpedisi di Excel selalu kosong dan kolom
        // Mobil di Excel selalu identik persis dengan yang ada di
        // master_harga.
        //
        // Setelah dicek ulang, DUA asumsi itu SALAH untuk data real:
        //   1. Kolom Ekpedisi di Excel TERISI (mis. "RUKMA PADAYA TRANS"),
        //      dan untuk Route+Mobil yang sama, harga BEDA-BEDA tergantung
        //      ekspedisinya. Kalau match cuma pakai Route+Mobil, sistem
        //      bisa salah ambil harga dari ekspedisi lain.
        //   2. Kolom Mobil di file Excel yang diimport SELALU KEPOTONG
        //      di sekitar 16 karakter (mis. "Contnr 40 Ft Re" padahal di
        //      master_harga lengkapnya "Contnr 40 Ft Reefer"; atau
        //      "Tronton 15Ton R" vs "Tronton 15Ton Reefer"). Ini kemungkinan
        //      keterbatasan lebar kolom di sistem sumber Excel-nya.
        //      Exact match jadi SELALU GAGAL walau datanya sebenarnya ada.
        //
        // FIX: matching sekarang dilakukan per Route (grouped), lalu di
        // dalam grup itu:
        //   a) kalau Ekpedisi di Excel terisi -> WAJIB cocok persis
        //      (setelah normalisasi) dengan Ekpedisi di master_harga.
        //   b) Mobil dicocokkan pakai PREFIX MATCH: master.mobil (yang
        //      lengkap) harus DIAWALI oleh mobil dari Excel (yang mungkin
        //      kepotong). Ini otomatis juga match kalau mobil-nya sama
        //      persis (karena string selalu "starts with" dirinya sendiri).
        //   c) Kalau Ekpedisi Excel kosong, fallback cari row manapun di
        //      route yang sama dengan mobil prefix-match (ambil yang
        //      pertama ketemu).
        // =====================================================
        if (self::$tarifByRoute === null) {
            self::$tarifByRoute = DB::table(self::TARIF_TABLE)
                ->select('ekpedisi', 'route', 'mobil', 'biaya_kirim')
                ->get()
                ->groupBy(fn($row) => $this->normalize($row->route));
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
        $viaKirim     = $this->cleanText($row['via_kirim'] ?? $row['via'] ?? null); // fleksibel jika header excel hanya 'via'
        $custDesc     = $this->cleanText($row['cust_desc'] ?? null);
        $serviceAgent = $this->cleanText($row['service_agent'] ?? null);

        // =====================================================
        // FORWARD-FILL: No Shipment, Route, Mobil, Ekpedisi
        //
        // Di file Excel sumbernya, untuk beberapa No Shipment, kolom
        // Route / Mobil / Ekpedisi hanya terisi di baris PERTAMA dari
        // shipment tsb (efek merged cell). Baris-baris berikutnya untuk
        // No Shipment yang SAMA datang KOSONG di kolom-kolom tsb.
        //
        // Supaya semua baris untuk No Shipment yang sama tetap punya
        // Route/Mobil/Ekpedisi yang benar (bukan null), kita forward-fill
        // dari nilai terakhir yang terisi, SELAMA masih di No Shipment
        // yang sama. Begitu No Shipment berubah, cache-nya di-reset,
        // supaya nilai lama tidak "bocor" ke shipment berikutnya.
        // =====================================================
        $noShipment = $this->cleanText($row['no_shipment'] ?? null);

        // No Shipment baru/berbeda dari baris sebelumnya -> reset cache
        if ($noShipment !== $this->lastNoShipment) {
            $this->lastRoute    = null;
            $this->lastMobil    = null;
            $this->lastEkpedisi = null;
        }

        // ambil nilai baris ini; kalau kosong, pakai nilai terakhir yg valid
        // untuk No Shipment yang sama (forward-fill)
        $route    = $this->cleanText($row['route'] ?? null)    ?: $this->lastRoute;
        $mobil    = $this->cleanText($row['mobil'] ?? null)    ?: $this->lastMobil;
        $ekpedisi = $this->cleanText($row['ekpedisi'] ?? null) ?: $this->lastEkpedisi;

        // simpan sebagai referensi untuk baris berikutnya
        if ($route)    $this->lastRoute    = $route;
        if ($mobil)    $this->lastMobil    = $mobil;
        if ($ekpedisi) $this->lastEkpedisi = $ekpedisi;

        $this->lastNoShipment = $noShipment;

        // =====================================================
        // BIAYA KIRIM: lookup ke master_harga berdasarkan Route (exact,
        // setelah normalisasi), Ekpedisi (exact match kalau Excel terisi),
        // dan Mobil (PREFIX MATCH, karena kolom mobil di Excel sering
        // kepotong dibanding yang ada di master_harga). Fallback ke nilai
        // "Biaya Kirim (Rp)" dari Excel kalau tidak ada yang cocok.
        // =====================================================\
     
        $tarifRow   = $this->findTarif($route, $ekpedisi, $mobil);
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
           $biayaKuli = $customerData->biaya_kuli ?? null;
                      $transport_lead_time = $customerData->transport_lead_time ?? null;

        // Kolom pulau/planner dari file Excel (dipakai sebagai fallback
        // kalau tujuan tidak ketemu di master, atau master kosong)
        $pulauFromFile   = $this->cleanText($row['pulau'] ?? null);
        $plannerFromFile = $this->cleanText($row['planner'] ?? null);

        $pulau   = $pulauMaster ?: $pulauFromFile;
        $planner = $plannerMaster ?: $plannerFromFile;

        // ================= LEAD TIME =================
     
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
            'transport_lead_time'             => $transport_lead_time,
            'planner'             => $planner,
            'no_shipment'         => $noShipment,
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
            'biaya_kuli' => $biayaKuli,
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
     * Normalisasi umum untuk string: hapus NBSP, seragamkan spasi di
     * sekitar tanda "-", collapse spasi ganda, lowercase.
     * Dipakai untuk Route (dan bahan dasar untuk Mobil/Ekpedisi).
     */
    private function normalize(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);   // NBSP -> spasi biasa
        $value = preg_replace('/\s*-\s*/', '-', $value); // "A - B" -> "A-B"
        $value = preg_replace('/\s+/', ' ', trim($value));

        return strtolower($value);
    }

    /**
     * Normalisasi khusus untuk Mobil: sama seperti normalize(), tapi
     * TANPA menyentuh tanda "-" (karena nama mobil biasanya tidak
     * mengandung dash bermakna route). Cukup rapikan spasi & lowercase.
     */
    private function normalizeMobil(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return strtolower($value);
    }

    /**
     * Cari baris tarif yang paling cocok untuk kombinasi Route + Ekpedisi
     * + Mobil dari Excel.
     *
     * Strategi:
     * 1. Ambil semua kandidat di master_harga dengan Route yang sama
     *    (exact match setelah normalisasi).
     * 2. Kalau Ekpedisi dari Excel terisi, WAJIB cocok persis dengan
     *    Ekpedisi di master_harga (supaya harga yang diambil benar-benar
     *    milik vendor yang sama, bukan vendor lain dengan Route+Mobil
     *    kebetulan sama).
     * 3. Mobil dicocokkan dengan PREFIX MATCH: mobil di master (yang
     *    lengkap) harus DIAWALI oleh mobil dari Excel (yang mungkin
     *    kepotong, mis. "Contnr 40 Ft Re" vs "Contnr 40 Ft Reefer").
     * 4. Kalau Ekpedisi Excel kosong / tidak ada yang cocok persis,
     *    fallback: abaikan syarat Ekpedisi, cukup Route + Mobil prefix.
     */

   private function findTarif(?string $route, ?string $ekpedisi, ?string $mobil)
{
    $routeKey    = $this->normalize($route);
    $mobilExcel  = $this->normalizeMobil($mobil);
    $ekpedisiKey = $ekpedisi !== null
        ? $this->normalize($ekpedisi)
        : '';

    $candidates = self::$tarifByRoute[$routeKey] ?? null;

    // Debug
    logger()->info('FIND TARIF', [
        'route' => $route,
        'routeKey' => $routeKey,
        'ekpedisi' => $ekpedisi,
        'ekpedisiKey' => $ekpedisiKey,
        'mobil' => $mobil,
        'mobilExcel' => $mobilExcel,
        'candidate_count' => $candidates ? $candidates->count() : 0,
    ]);

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
            logger()->info('TARIF KETEMU', [
                'route' => $strict->route,
                'ekpedisi' => $strict->ekpedisi,
                'mobil' => $strict->mobil,
                'biaya_kirim' => $strict->biaya_kirim,
            ]);

            return $strict;
        }
    }

    return $candidates->first(function ($row) use ($mobilExcel) {
        $mobilMaster = $this->normalizeMobil($row->mobil);
        return str_starts_with($mobilMaster, $mobilExcel);
    });
}

    /**
     * Kolom biaya_kirim di master_harga formatnya "8,500,000"
     * (koma = pemisah ribuan), BEDA dari cleanNumber() yang ada
     * (didesain untuk format Rupiah "Rp1.000.000" dengan titik).
     */
private function cleanNumberTarif($value): float
{
    if ($value === null || $value === '' || $value == '-') {
        return 0;
    }

    $value = str_replace(
        ['Rp', 'rp', ' ', '.', ','],
        '',
        (string) $value
    );

    return (float) $value;
}
    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {

                // =====================================================
                // SAFETY NET: kalau ternyata baris-baris dengan No
                // Shipment yang sama TIDAK berurutan di file Excel
                // (sehingga forward-fill saat model() tidak sempat
                // menangkap semuanya), lakukan post-process di sini:
                // isi Route / Mobil / Ekpedisi yang masih NULL/kosong
                // dengan nilai non-kosong lain dari No Shipment yang
                // sama (ambil salah satu yang ada).
                //
                // Ini jaring pengaman tambahan, bukan pengganti
                // forward-fill di model() -- forward-fill tetap jalan
                // duluan supaya kasus paling umum (baris berurutan)
                // langsung benar dari awal.
                // =====================================================
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