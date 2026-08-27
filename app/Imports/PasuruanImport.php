<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use App\Models\LogistikPengirimanPasuruan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;

class PasuruanImport implements ToModel, WithHeadingRow, WithEvents
{
    private static $customerMap = null;

    // =====================================================
    // $tarifByRoute: dikelompokkan per ROUTE, supaya bisa matching
    // Route + Ekpedisi (exact) + Mobil (prefix match, karena kolom
    // Mobil di Excel sering kepotong dibanding yang lengkap di
    // master_harga). Structure:
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
    // Cara kerja sama persis seperti LogistikImport: setiap baris
    // diproses, kalau No Shipment SAMA dengan baris sebelumnya, dan
    // kolom Route/Mobil/Ekpedisi di baris ini kosong, dipakai nilai
    // terakhir yang pernah terisi untuk No Shipment tsb. Begitu No
    // Shipment berubah, cache di-reset supaya tidak "bocor" ke
    // shipment lain.
    // =====================================================
    private $lastNoShipment = null;
    private $lastRoute      = null;
    private $lastMobil      = null;
    private $lastEkpedisi   = null;

    public function __construct()
    {
        // =====================================================
        // MASTER DATA: tujuan -> dist_channel, pulau, area, planner,
        // pic monitoring, biaya_kuli, transport_lead_time.
        //
        // PENTING - FILTER Div = 'Pasuruan':
        // Tabel tujuanfillterr berisi data gabungan dari BEBERAPA divisi.
        // Nama tujuan yang SAMA bisa muncul di div yang berbeda dengan
        // planner/PIC/biaya_kuli yang berbeda pula. Karena import ini
        // khusus untuk data Pasuruan, query master WAJIB difilter
        // where('Div', 'Pasuruan') dulu sebelum di-keyBy(tujuan).
        // =====================================================
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
                ->keyBy(fn($row) => strtolower(trim($row->tujuan)));
        }

        // =====================================================
        // MASTER HARGA: route -> daftar kandidat (ekpedisi, mobil, biaya_kirim)
        //
        // Matching dilakukan per Route (grouped), lalu di dalam grup itu
        // dicocokkan Ekpedisi (exact match kalau Excel terisi) dan Mobil
        // (PREFIX MATCH, karena kolom Mobil di file Excel sering kepotong
        // dibanding yang lengkap di master_harga, mis. "Contnr 40 Ft Re"
        // vs "Contnr 40 Ft Reefer").
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

        $actPgiDate = isset($row['act_pgi_date_pasuruan']) && is_numeric($row['act_pgi_date_pasuruan'])
            ? Date::excelToDateTimeObject($row['act_pgi_date_pasuruan'])->format('Y-m-d')
            : $this->convertDate($row['act_pgi_date_pasuruan'] ?? null);

        // ================= TEXT =================
        $viaKirim           = $this->cleanText($row['via_kirim_pasuruan'] ?? $row['via_pasuruan'] ?? null); // fleksibel jika header excel hanya 'via_pasuruan'
        $shippingPoint      = $this->cleanText($row['shipping_point_pasuruan'] ?? null);
        $ketersediaanUnit   = $this->cleanText($row['ketersediaan_unit_pasuruan'] ?? null);
        $perubahanMobil     = $this->cleanText($row['perubahan_mobil_pasuruan'] ?? null);
        $kategoriEkspedisi  = $this->cleanText($row['kategori_ekspedisi_pasuruan'] ?? null);
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

        // Nilai pulau/area/planner/pic dari file Excel (fallback kalau
        // tujuan tidak ketemu di master tujuanfillterr, atau master kosong)
        $pulauFromFile      = $this->cleanText($row['pulau_pasuruan'] ?? null);
        $areaFromFile       = $this->cleanText($row['area_pasuruan'] ?? null);
        $plannerFromFile    = $this->cleanText($row['planner_pasuruan'] ?? null);
        $picMonitoringExcel = $this->cleanText($row['pic_monitoring_pasuruan'] ?? null);

        // ================= NUMBER =================
        $leadTimeFromFile  = (int) $this->cleanNumber($row['transport_lead_time_pasuruan'] ?? 0);
        $nilaiMuatan       = $this->cleanNumber($row['nilai_muatan_pasuruan'] ?? null);
        $totalDo           = $this->cleanNumber($row['total_do_pasuruan'] ?? null);
        $actualDeliveryQty = $this->cleanNumber($row['actual_delivery_quantity_pasuruan'] ?? null);
        $actUrutanBongkar  = $this->cleanNumber($row['act_urutan_bongkar_pasuruan'] ?? null);
        $qtyMonitoring     = $this->cleanNumber($row['qty_monitoring_pasuruan'] ?? null);

        // =====================================================
        // FORWARD-FILL: No Shipment, Route, Mobil, Ekspedisi
        //
        // Sama persis seperti LogistikImport: kalau file Excel Pasuruan
        // hasil merged cell (Route/Mobil/Ekspedisi cuma terisi di baris
        // pertama tiap No Shipment), baris berikutnya untuk shipment yang
        // sama akan kosong. Di-forward-fill dari nilai terakhir yang
        // terisi, SELAMA masih di No Shipment yang sama. Begitu No
        // Shipment berubah, cache di-reset.
        // =====================================================
        $noShipment = $this->cleanText($row['no_shipment_pasuruan'] ?? null);

        if ($noShipment !== $this->lastNoShipment) {
            $this->lastRoute    = null;
            $this->lastMobil    = null;
            $this->lastEkpedisi = null;
        }

        $route    = $this->cleanText($row['route_pasuruan'] ?? null)    ?: $this->lastRoute;
        $mobil    = $this->cleanText($row['mobil_pasuruan'] ?? null)    ?: $this->lastMobil;
        $ekspedisi = $this->cleanText($row['ekspedisi_pasuruan'] ?? null) ?: $this->lastEkpedisi;

        if ($route)     $this->lastRoute    = $route;
        if ($mobil)     $this->lastMobil    = $mobil;
        if ($ekspedisi) $this->lastEkpedisi = $ekspedisi;

        $this->lastNoShipment = $noShipment;

        $tujuan = $this->cleanText($row['tujuan_pasuruan'] ?? null);

        // =====================================================
        // BIAYA KIRIM: lookup ke master_harga berdasarkan Route (exact,
        // setelah normalisasi), Ekpedisi (exact match kalau Excel terisi),
        // dan Mobil (PREFIX MATCH). Fallback ke nilai "Biaya Kirim" dari
        // Excel kalau tidak ada yang cocok. SAMA PERSIS seperti logic
        // biaya_kirim di LogistikImport.
        // =====================================================
        $tarifRow   = $this->findTarif($route, $ekspedisi, $mobil);
        $biayaKirim = $tarifRow
            ? $this->cleanNumberTarif($tarifRow->biaya_kirim)
            : $this->cleanNumber($row['biaya_kirim_pasuruan'] ?? null);

        // =====================================================
        // LOOKUP MASTER (SUDAH DIFILTER Div = 'Pasuruan' DI CONSTRUCTOR)
        // tujuan -> dist_channel, pulau, area, planner, pic monitoring,
        // biaya_kuli
        // =====================================================
        $tujuanKey = preg_replace('/\s+/', ' ', trim(strtolower($tujuan ?? '')));

        $customerData = self::$customerMap[$tujuanKey] ?? null;

        $distChannel   = $customerData->dist_channel ?? null;
        $areaMaster    = $customerData->area ?? null;
        $pulauMaster   = $customerData->pulau ?? null;
        $plannerMaster = $customerData->Planner ?? null;
        $picMaster     = $customerData->Monitoring ?? null;
        $biayaKuli     = $customerData->biaya_kuli ?? null;
        $leadTimeMaster = $customerData->transport_lead_time ?? null;
        // NB: biaya_kirim TIDAK diambil dari sini — sudah benar dari
        // findTarif() di atas (tabel tarif_pengiriman, bukan tujuanfillterr).

        // Prioritaskan data master (khusus div Pasuruan), fallback ke
        // kolom Excel kalau tujuan tidak ketemu / master kosong
        $area          = $areaMaster ?: $areaFromFile;
        $pulau         = $pulauMaster ?: $pulauFromFile;
        $planner       = $plannerMaster ?: $plannerFromFile;
        $picMonitoring = $picMaster ?: $picMonitoringExcel;
        $leadTime = ($leadTimeMaster !== null && $leadTimeMaster !== '')
            ? (int) $leadTimeMaster
            : $leadTimeFromFile;

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

        // =====================================================
        // SLA DAPAT MOBIL (rencana_kirim -> tanggal_dpt_unit)
        // =====================================================
        $lamaWaktuPencarian = null;
        $slaDapatMobil = null;

        if ($rencanaKirim && $tanggalDptUnit) {

            $selisihCariMobil = (int) date_diff(
                date_create($rencanaKirim),
                date_create($tanggalDptUnit)
            )->format('%a');

            $lamaWaktuPencarian = $selisihCariMobil;
            $slaDapatMobil = ($selisihCariMobil <= 0) ? 'On Time' : 'Delay';
        }

        // =====================================================
        // SLA LOADING / LAMA DIGUDANG (tiba gudang -> keluar gudang)
        // =====================================================
        $lamaDigudang = null;
        $statusGudang = null;
        $slaLoading   = null;

        if ($tanggalTibaGudang && $tanggalKeluarGudang) {

            $selisihGudang = (int) date_diff(
                date_create($tanggalTibaGudang),
                date_create($tanggalKeluarGudang)
            )->format('%a');

            $lamaDigudang = $selisihGudang;

            if ($selisihGudang > 0) {
                $statusGudang = 'Delay';
                $slaLoading   = 'H+' . $selisihGudang;
            } else {
                $statusGudang = 'On Time';
                $slaLoading   = 'Sesuai SLA';
            }
        }

        // =====================================================
        // ESTIMASI TIBA, LAMA PERJALANAN, SLA TIBA, OVERSTAY, SLA BONGKAR
        // =====================================================
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
        $logic = $this->generateStatusAlert($slaTiba, $slaBongkar);

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

        // ================= CR (%) =================
        $cr = $nilaiMuatan > 0
            ? round(($biayaKirim / $nilaiMuatan) * 100, 4)
            : 0;

        // ================= SELISIH QTY DO =================
        $selisihQty = $totalDo - $actualDeliveryQty;

        // ================= CREATE TGL =================
        $createTgl = date('Y-m-d H:i:s');

        return new LogistikPengirimanPasuruan([

            // ================= BASIC =================
            'transport_lead_time_pasuruan'  => $leadTime,
            'planner_pasuruan'              => $planner,
            'no_shipment_pasuruan'          => $noShipment,
            'tujuan_pasuruan'               => $tujuan,
            'dist_channel_pasuruan'         => $distChannel,
            'area_pasuruan'                 => $area,
            'pulau_pasuruan'                => $pulau,

            'route_pasuruan'                => $route,
            'via_kirim_pasuruan'            => $viaKirim,
            'total_do_pasuruan'             => $totalDo,

            'ketersediaan_unit_pasuruan'    => $ketersediaanUnit,
            'mobil_pasuruan'                => $mobil,
            'perubahan_mobil_pasuruan'      => $perubahanMobil,

            'nilai_muatan_pasuruan'         => $nilaiMuatan,
            'biaya_kirim_pasuruan'          => $biayaKirim,
            'biaya_kuli_pasuruan'           => $biayaKuli,
            'cr_pasuruan'                   => $cr,

            'kategori_ekspedisi_pasuruan'   => $kategoriEkspedisi,
            'ekspedisi_pasuruan'            => $ekspedisi,

            'no_pol_pasuruan'               => $noPol,
            'nama_driver_pasuruan'          => $namaDriver,

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
            'lama_digudang_pasuruan'             => $lamaDigudang,
            'sla_ketepatan_loading_pasuruan'     => $slaLoading,
            'lama_waktu_pencarian_pasuruan'      => $lamaWaktuPencarian,
            'sla_dapat_mobil_pasuruan'           => $slaDapatMobil,

            // ================= MONITORING =================
            'pic_monitoring_pasuruan'        => $picMonitoring,
            'status_kendaraan_pasuruan'      => $statusKendaraan,
            'monitoring_alert_pasuruan'      => $monitoringAlert,
            'action_required_pasuruan'       => $actionRequired,

            'estimasi_tiba_pasuruan'         => $estimasi ? date('Y-m-d', $estimasi) : null,
            'tanggal_estimasi_pasuruan'      => $estimasi ? date('Y-m-d', $estimasi) : null,

            'lama_perjalanan_pasuruan'       => $lamaPerjalanan,
            'sla_tiba_pasuruan'              => $slaTiba,

            'overstay_days_pasuruan'         => $overstay,
            'sla_bongkar_pasuruan'           => $slaBongkar,

            'status_akhir_pasuruan'          => $statusAkhir,

            // ================= KAPAL =================
            'nama_kapal_pasuruan'            => $namaKapal,
            'etd_pasuruan'                   => $etd,
            'eta_pasuruan'                   => $eta,
            'atd_pasuruan'                   => $atd,
            'ata_pasuruan'                   => $ata,
            'transport_laut_pasuruan'        => $transportLaut,

            // ================= DELIVERY =================
            'actual_delivery_quantity_pasuruan' => $actualDeliveryQty,
            'selisih_quantity_pasuruan'         => $selisihQty,
            'reason_selisih_quantity_pasuruan'  => $reasonSelisihQty,

            // ================= REASON =================
            'reason_waktu_tiba_pasuruan'     => $reasonWaktuTiba,
            'reason_waktu_bongkar_pasuruan'  => $reasonWaktuBongkar,
            'remarks_pasuruan'               => $remarks,
            'remarks_qty_pasuruan'           => $remarksQty,
            'selisih_qty_pasuruan'           => $selisihQty,

            // ================= OTHER =================
            'act_pgi_date_pasuruan'          => $actPgiDate,
            'act_urutan_bongkar_pasuruan'    => $actUrutanBongkar,
            'shipping_point_pasuruan'        => $shippingPoint,
            'qty_monitoring_pasuruan'        => $qtyMonitoring,
            'created_by_pasuruan'            => $createdBy,
            'create_tgl_pasuruan'            => $createTgl,

            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
     * Dipakai untuk Route.
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
     * TANPA menyentuh tanda "-". Cukup rapikan spasi & lowercase.
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
     * 1. Ambil semua kandidat di master_harga dengan Route yang sama.
     * 2. Kalau Ekpedisi dari Excel terisi, WAJIB cocok persis dengan
     *    Ekpedisi di master_harga.
     * 3. Mobil dicocokkan dengan PREFIX MATCH (mobil master harus diawali
     *    mobil dari Excel, karena kolom Excel sering kepotong).
     * 4. Kalau Ekpedisi Excel kosong / tidak ada yang cocok persis,
     *    fallback: abaikan syarat Ekpedisi, cukup Route + Mobil prefix.
     */
    private function findTarif(?string $route, ?string $ekpedisi, ?string $mobil)
    {
        $routeKey    = $this->normalize($route);
        $mobilExcel  = $this->normalizeMobil($mobil);
        $ekpedisiKey = $ekpedisi !== null ? $this->normalize($ekpedisi) : '';

        $candidates = self::$tarifByRoute[$routeKey] ?? null;

        logger()->info('FIND TARIF PASURUAN', [
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
     * (koma = pemisah ribuan).
     */
    private function cleanNumberTarif($value): float
    {
        if ($value === null || $value === '' || $value == '-') return 0;

        $value = (string) $value;
        $value = str_replace(['Rp', 'rp', ' '], '', $value);

        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            // Ada titik DAN koma -> titik = ribuan, koma = desimal
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            // Cuma titik ATAU cuma koma -> anggap keduanya pemisah ribuan
            $value = str_replace(['.', ','], '', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
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
                // isi Route / Mobil / Ekspedisi yang masih NULL/kosong
                // dengan nilai non-kosong lain dari No Shipment yang
                // sama (ambil salah satu yang ada). Sama persis seperti
                // safety net di LogistikImport.
                // =====================================================
                foreach (['route_pasuruan', 'mobil_pasuruan', 'ekspedisi_pasuruan'] as $col) {
                    DB::statement("
                        UPDATE logistik_pengiriman_pasuruan lp
                        JOIN (
                            SELECT no_shipment_pasuruan, MIN($col) AS val
                            FROM logistik_pengiriman_pasuruan
                            WHERE $col IS NOT NULL AND $col != ''
                            GROUP BY no_shipment_pasuruan
                        ) x ON lp.no_shipment_pasuruan = x.no_shipment_pasuruan
                        SET lp.$col = x.val
                        WHERE (lp.$col IS NULL OR lp.$col = '')
                          AND lp.no_shipment_pasuruan IS NOT NULL
                          AND lp.no_shipment_pasuruan != ''
                    ");
                }

                DB::statement("
                    UPDATE logistik_pengiriman_pasuruan lp
                    JOIN (
                        SELECT
                            no_shipment_pasuruan,
                            MAX(biaya_kirim_pasuruan) AS biaya,
                            SUM(nilai_muatan_pasuruan) AS muatan
                        FROM logistik_pengiriman_pasuruan
                        GROUP BY no_shipment_pasuruan
                    ) x ON lp.no_shipment_pasuruan = x.no_shipment_pasuruan
                    SET lp.cr_pasuruan = IF(x.muatan = 0, 0, ROUND((x.biaya / x.muatan) * 100, 4))
                ");
            },
        ];
    }
}