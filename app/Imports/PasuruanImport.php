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

    public function __construct()
    {
        // =====================================================
        // MASTER DATA: tujuan -> dist_channel, pulau, area, planner, pic monitoring
        //
        // FIXED: sebelumnya nama tabel salah ketik ('tujuanfilterr',
        // harusnya 'tujuanfillterr') dan kolom key-nya salah
        // ('name_customer_1', padahal kolom aslinya bernama 'tujuan').
        //
        // FIXED juga: tabel pic_monitoring terpisah dihapus karena kolom
        // Planner & Monitoring sudah ada di tabel tujuanfillterr yang sama.
        //
        // PENTING - FILTER Div = 'Pasuruan':
        // Tabel tujuanfillterr berisi data gabungan dari BEBERAPA divisi
        // (mis. 'HO Meruya' dan 'Pasuruan'). Nama tujuan yang SAMA bisa
        // muncul di div yang berbeda dengan planner/PIC yang berbeda pula.
        // Karena import ini khusus untuk data Pasuruan, query master WAJIB
        // difilter where('Div', 'Pasuruan') dulu sebelum di-keyBy(tujuan),
        // supaya tidak ke-lookup planner/PIC milik divisi lain yang salah.
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
                ->where('Div', 'Pasuruan')
                ->get()
                ->keyBy(fn($row) => strtolower(trim($row->tujuan)));
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
        $noShipment         = $this->cleanText($row['no_shipment_pasuruan'] ?? null);
        $tujuan             = $this->cleanText($row['tujuan_pasuruan'] ?? null);
        $route              = $this->cleanText($row['route_pasuruan'] ?? null);
        $viaKirim           = $this->cleanText($row['via_kirim_pasuruan'] ?? null);
        $shippingPoint      = $this->cleanText($row['shipping_point_pasuruan'] ?? null);
        $ketersediaanUnit   = $this->cleanText($row['ketersediaan_unit_pasuruan'] ?? null);
        $mobil              = $this->cleanText($row['mobil_pasuruan'] ?? null);
        $perubahanMobil     = $this->cleanText($row['perubahan_mobil_pasuruan'] ?? null);
        $kategoriEkspedisi  = $this->cleanText($row['kategori_ekspedisi_pasuruan'] ?? null);
        $ekspedisi          = $this->cleanText($row['ekspedisi_pasuruan'] ?? null);
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

        // Nilai pulau/planner/pic dari file Excel (dipakai sebagai fallback
        // kalau tujuan tidak ketemu di master tujuanfillterr, atau master
        // kosong untuk field tersebut)
        $pulauFromFile        = $this->cleanText($row['pulau_pasuruan'] ?? null);
        $plannerFromFile      = $this->cleanText($row['planner_pasuruan'] ?? null);
        $picMonitoringExcel   = $this->cleanText($row['pic_monitoring_pasuruan'] ?? null);

        // ================= NUMBER =================
        $leadTime          = (int) $this->cleanNumber($row['transport_lead_time_pasuruan'] ?? 0);
        $nilaiMuatan       = $this->cleanNumber($row['nilai_muatan_pasuruan'] ?? null);
        $biayaKirim        = $this->cleanNumber($row['biaya_kirim_pasuruan'] ?? null);
        $totalDo           = $this->cleanNumber($row['total_do_pasuruan'] ?? null);
        $actualDeliveryQty = $this->cleanNumber($row['actual_delivery_quantity_pasuruan'] ?? null);
        $actUrutanBongkar  = $this->cleanNumber($row['act_urutan_bongkar_pasuruan'] ?? null);
        $qtyMonitoring     = $this->cleanNumber($row['qty_monitoring_pasuruan'] ?? null);

        // =====================================================
        // LOOKUP MASTER (SUDAH DIFILTER Div = 'Pasuruan' DI CONSTRUCTOR)
        // tujuan -> dist_channel, pulau, area, planner, pic monitoring
        // =====================================================
        $tujuanKey = preg_replace('/\s+/', ' ', trim(strtolower($tujuan ?? '')));

        $customerData = self::$customerMap[$tujuanKey] ?? null;

        $distChannel   = $customerData->dist_channel ?? null;
        $area          = $customerData->area ?? null;
        $pulauMaster   = $customerData->pulau ?? null;
        $plannerMaster = $customerData->Planner ?? null;
        $picMaster     = $customerData->Monitoring ?? null;

        // Prioritaskan data master (khusus div Pasuruan), fallback ke
        // kolom Excel kalau tujuan tidak ketemu / master kosong
        $pulau         = $pulauMaster ?: $pulauFromFile;
        $planner       = $plannerMaster ?: $plannerFromFile;
        $picMonitoring = $picMaster ?: $picMonitoringExcel;

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
        // (logic disamain persis kaya generateMonitoringPasuruan() di controller)
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
            'tanggal_tiba_estimasi_pasuruan' => $estimasi ? date('Y-m-d', $estimasi) : null,

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

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {

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