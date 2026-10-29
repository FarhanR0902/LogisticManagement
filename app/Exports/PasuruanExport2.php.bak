<?php

namespace App\Exports;

use App\Models\LogistikPengirimanPasuruan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PasuruanExport implements FromCollection, WithHeadings, WithMapping
{
    protected $area;
    protected $date;
    protected $month;
    protected $year;

    /**
     * Terima filter yang sama seperti yang dikirim tombol Export Excel
     * di halaman datatable (area, date, month, year).
     */
    public function __construct(?string $area = null, ?string $date = null, ?string $month = null, ?string $year = null)
    {
        $this->area  = $area;
        $this->date  = $date;
        $this->month = $month;
        $this->year  = $year;
    }

    public function collection()
    {
        $query = LogistikPengirimanPasuruan::query();

        if ($this->area) {
            $query->where('area_pasuruan', $this->area);
        }

        // CATATAN: filter date/month/year di sini memakai tanggal_terima_po_pasuruan
        // (sama seperti asumsi yang dipakai di dashboard). Ganti kolomnya kalau
        // maksud filter date di datatable adalah kolom tanggal lain.
        if ($this->date) {
            $query->whereDate('tanggal_terima_po_pasuruan', $this->date);
        }

        if ($this->month) {
            $query->whereMonth('tanggal_terima_po_pasuruan', $this->month);
        }

        if ($this->year) {
            $query->whereYear('tanggal_terima_po_pasuruan', $this->year);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tanggal Terima PO',
            'Rencana Kirim',
            'Lead Time Transport (Hari)',
            'Tanggal Estimasi',
            'Estimasi Tiba',
            'Planner',
            'No Shipment',
            'Status Pengiriman',
            'Dist Channel',
            'Tujuan',
            'Route',
            'Shipping Point',
            'Via Kirim',
            'Area',
            'Pulau',
            'Ketersediaan Unit',
            'Status Dapat Unit',
            'Mobil',
            'No Polisi',
            'Nama Driver',
            'Perubahan Mobil',
            'Kategori Pengiriman',
            'Total DO (Delivery Quantity)',
            'Actual Delivery Quantity',
            'Selisih Quantity (DB)',
            'Selisih Quantity (Live)',
            'Reason Selisih Quantity',
            'Nilai Muatan',
            'Biaya Kirim',
            'Cost Ratio (DB)',
            'Kategori Ekspedisi',
            'Ekspedisi',
            'Ekspedisi (Legacy)',
            'Tanggal Dapat Unit',
            'Lama Waktu Pencarian (DB)',
            'Lama Waktu Pencarian (Live)',
            'SLA Dapat Mobil (DB)',
            'SLA Dapat Mobil (Live)',
            'Planning Loading',
            'Tanggal Tiba Gudang',
            'Tanggal Keluar Gudang',
            'Lama Digudang (Hari)',
            'SLA Ketepatan Loading',
            'Keterangan Loading',
            'Keterangan Loading 2',
            'SLA Ketibaan Gudang Muat',
            'Keterangan Ketibaan Gudang',
            'PIC Monitoring',
            'Status Kendaraan',
            'Monitoring Alert',
            'Action Required',
            'Nama Kapal',
            'ETD',
            'ETA',
            'ATD',
            'ATA',
            'Act PGI Date',
            'Tanggal Tiba',
            'Lama Perjalanan (Hari)',
            'SLA Tiba (DB)',
            'Keterangan Waktu Tiba',
            'Alert (DB)',
            'Alert (Live)',
            'Tanggal Bongkar',
            'Overstay Days (DB)',
            'Overstay (Live)',
            'SLA Bongkar (DB)',
            'Keterangan Waktu Bongkar',
            'SLA Bongkar (Live)',
            'Reason Waktu Tiba',
            'Reason Waktu Bongkar',
            'Status Akhir',
            'Status Alert',
            'Remarks',
            'Keterangan Monitoring',
            'Act Urutan Bongkar',
            'Created At',
            'Updated At',
        ];
    }

    public function map($r): array
    {
        // ================= HELPER TANGGAL =================
        $fmt = function ($value, $format = 'd-m-Y') {
            if (empty($value) || $value === 'mm/dd/yyyy') {
                return '-';
            }
            $ts = strtotime($value);
            return $ts ? date($format, $ts) : '-';
        };

        // ================= STATUS PENGIRIMAN (live) =================
        $dpt           = $r->tanggal_dpt_unit_pasuruan;
        $tibaGudang    = $r->tanggal_tiba_gudang_pasuruan;
        $keluarGudang  = $r->tanggal_keluar_gudang_pasuruan;
        $tibaTujuan    = $r->tanggal_tiba_pasuruan;
        $bongkarTujuan = $r->tanggal_bongkar_pasuruan;

        if (empty($dpt)) {
            $statusPengiriman = 'MENCARI UNIT';
        } elseif (empty($tibaGudang)) {
            $statusPengiriman = 'PERJALANAN KE GUDANG';
        } elseif (!empty($tibaGudang) && empty($keluarGudang)) {
            $statusPengiriman = 'DI GUDANG';
        } elseif (!empty($keluarGudang) && empty($tibaTujuan)) {
            $statusPengiriman = 'PERJALANAN KE TUJUAN';
        } elseif (!empty($tibaTujuan) && empty($bongkarTujuan)) {
            $statusPengiriman = 'TIBA DI TUJUAN';
        } elseif (!empty($tibaTujuan) && !empty($bongkarTujuan)) {
            $statusPengiriman = 'SUDAH SELESAI';
        } else {
            $statusPengiriman = '-';
        }

        // ================= SHIPPING POINT (live, dari route) =================
        $shippingPoint = $r->route_pasuruan
            ? trim(explode('-', trim($r->route_pasuruan))[0])
            : '-';

        // ================= STATUS DAPAT UNIT (live) =================
        $statusDapatUnit = !empty($r->tanggal_dpt_unit_pasuruan)
            ? 'Sudah Dapat Unit'
            : 'Belum Dapat Unit';

        // ================= SELISIH QUANTITY (live) =================
        $totalDo   = is_numeric($r->total_do_pasuruan) ? (float) $r->total_do_pasuruan : 0;
        $actualQty = is_numeric($r->actual_delivery_quantity_pasuruan) ? (float) $r->actual_delivery_quantity_pasuruan : 0;
        $selisihQty = $totalDo - $actualQty;

        if ($selisihQty == 0) {
            $selisihLive = 'Sesuai (0)';
        } elseif ($selisihQty > 0) {
            $selisihLive = 'Berkurang ' . number_format($selisihQty, 0, ',', '.');
        } else {
            $selisihLive = 'Lebih ' . number_format(abs($selisihQty), 0, ',', '.');
        }

        // ================= LAMA WAKTU PENCARIAN & SLA DAPAT MOBIL (live) =================
        $lamaPencarianLive = '-';
        $slaDapatMobilLive = '-';

        if (!empty($r->rencana_kirim_pasuruan) && !empty($r->tanggal_dpt_unit_pasuruan)) {
            $rencana   = strtotime(date('Y-m-d', strtotime($r->rencana_kirim_pasuruan)));
            $dapatUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit_pasuruan)));
            $selisih   = floor(($dapatUnit - $rencana) / 86400);

            $lamaPencarianLive = $selisih <= 0 ? 'H+0' : 'H+' . $selisih;
            $slaDapatMobilLive = $selisih <= 0 ? 'On Time' : 'Delay';
        }

        // ================= ALERT (live) =================
        $alertLive = '-';

        if (!empty($r->tanggal_tiba_pasuruan)) {
            $alertLive = 'Tiba';
        } elseif (!empty($r->estimasi_tiba_pasuruan)) {
            $estimasi  = strtotime(date('Y-m-d', strtotime($r->estimasi_tiba_pasuruan)));
            $today     = strtotime(date('Y-m-d'));
            $sisaHari  = floor(($estimasi - $today) / 86400);

            if ($sisaHari < 0) {
                $alertLive = 'OVERDUE';
            } elseif ($sisaHari <= 3) {
                $alertLive = 'H-' . $sisaHari;
            } elseif ($sisaHari <= 7) {
                $alertLive = 'H-' . $sisaHari;
            } else {
                $alertLive = 'ON TRACK';
            }
        }

        // ================= OVERSTAY (live) =================
        $overstayLive = '-';

        if (!empty($r->tanggal_tiba_pasuruan) && !empty($r->tanggal_bongkar_pasuruan)) {
            $tiba     = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $bongkar  = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
            $overstay = max(0, floor(($bongkar - $tiba) / 86400));

            $overstayLive = $overstay == 0 ? '0 Hari' : "H+{$overstay} Hari";
        }

        // ================= SLA BONGKAR (live) =================
        $slaBongkarLive = '-';

        if (!empty($r->tanggal_tiba_pasuruan) && !empty($r->tanggal_bongkar_pasuruan)) {
            $tiba    = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba_pasuruan)));
            $bongkar = strtotime(date('Y-m-d', strtotime($r->tanggal_bongkar_pasuruan)));
            $selisih = floor(($bongkar - $tiba) / 86400);

            $slaBongkarLive = $selisih <= 0 ? 'On Time' : 'Delay';
        }

        // ================= STATUS AKHIR (live) =================
        $slaTibaUpper    = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
        $slaBongkarUpper = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));

        if (empty($r->tanggal_tiba_pasuruan)) {
            $statusAkhir = 'Dalam Perjalanan';
        } elseif (!empty($r->tanggal_tiba_pasuruan) && empty($r->tanggal_bongkar_pasuruan)) {
            $statusAkhir = 'Sudah Tiba, Dalam Pembongkaran';
        } elseif ($slaTibaUpper == 'ON TIME' && $slaBongkarUpper == 'ON TIME') {
            $statusAkhir = 'Pengiriman On Time';
        } else {
            $statusAkhir = 'Pengiriman Delay';
        }

        // ================= STATUS ALERT (live) =================
        if ($slaTibaUpper == 'ON TIME' && $slaBongkarUpper == 'ON TIME') {
            $statusAlert = 'Delivered Ontime';
        } elseif ($slaTibaUpper == 'DELAY' && $slaBongkarUpper == 'ON TIME') {
            $statusAlert = 'Delay Perjalanan';
        } elseif ($slaTibaUpper == 'ON TIME' && $slaBongkarUpper == 'DELAY') {
            $statusAlert = 'Delay Pembongkaran';
        } elseif ($slaTibaUpper == 'DELAY' && $slaBongkarUpper == 'DELAY') {
            $statusAlert = 'Delivered Delay';
        } else {
            $statusAlert = 'Belum Selesai';
        }

        // ================= RETURN (harus urut sama dengan headings()) =================
        return [
            $r->id,
            $fmt($r->tanggal_terima_po_pasuruan),
            $fmt($r->rencana_kirim_pasuruan),
            $r->transport_lead_time_pasuruan,
            $fmt($r->tanggal_estimasi_pasuruan),
            $fmt($r->estimasi_tiba_pasuruan),
            $r->planner_pasuruan,
            $r->no_shipment_pasuruan,
            $statusPengiriman,
            $r->dist_channel_pasuruan,
            $r->tujuan_pasuruan,
            $r->route_pasuruan,
            $shippingPoint,
            $r->via_kirim_pasuruan,
            $r->area_pasuruan,
            $r->pulau_pasuruan,
            $r->ketersediaan_unit_pasuruan,
            $statusDapatUnit,
            $r->mobil_pasuruan,
            $r->no_pol_pasuruan,
            $r->nama_driver_pasuruan,
            $r->perubahan_mobil_pasuruan,
            $r->kategori_pengiriman_pasuruan,
            $r->total_do_pasuruan,
            $r->actual_delivery_quantity_pasuruan,
            $r->selisih_quantity_pasuruan,
            $selisihLive,
            $r->reason_selisih_quantity_pasuruan,
            $r->nilai_muatan_pasuruan,
            $r->biaya_kirim_pasuruan,
            is_numeric($r->cr_pasuruan) ? number_format((float) $r->cr_pasuruan, 4, ',', '.') . '%' : '-',
            $r->kategori_ekspedisi_pasuruan,
            $r->ekspedisi_pasuruan,
            $r->ekpedisi_pasuruan,
            $fmt($r->tanggal_dpt_unit_pasuruan),
            $r->lama_waktu_pencarian_pasuruan,
            $lamaPencarianLive,
            $r->sla_dapat_mobil_pasuruan,
            $slaDapatMobilLive,
            $fmt($r->planning_loading_pasuruan),
            $fmt($r->tanggal_tiba_gudang_pasuruan),
            $fmt($r->tanggal_keluar_gudang_pasuruan),
            $r->lama_digudang_pasuruan,
            $r->sla_ketepatan_loading_pasuruan,
            $r->keterangan_loading_pasuruan,
            $r->keterangan_loading2_pasuruan,
            $r->sla_ketibaan_gudang_muat_pasuruan,
            $r->keterangan_ketibaan_gudang_pasuruan,
            $r->pic_monitoring_pasuruan,
            $r->status_kendaraan_pasuruan,
            $r->monitoring_alert_pasuruan,
            $r->action_required_pasuruan,
            $r->nama_kapal_pasuruan,
            $fmt($r->etd_pasuruan),
            $fmt($r->eta_pasuruan),
            $fmt($r->atd_pasuruan),
            $fmt($r->ata_pasuruan),
            $fmt($r->act_pgi_date_pasuruan),
            $fmt($r->tanggal_tiba_pasuruan),
            $r->lama_perjalanan_pasuruan,
            $r->sla_tiba_pasuruan,
            $r->keterangan_waktu_tiba_pasuruan,
            $r->alert_pasuruan,
            $alertLive,
            $fmt($r->tanggal_bongkar_pasuruan),
            $r->overstay_days_pasuruan,
            $overstayLive,
            $r->sla_bongkar_pasuruan,
            $r->keterangan_waktu_bongkar_pasuruan,
            $slaBongkarLive,
            $r->reason_waktu_tiba_pasuruan,
            $r->reason_waktu_bongkar_pasuruan,
            $statusAkhir,
            $statusAlert,
            $r->remarks_pasuruan,
            $r->keterangan_monitoring_pasuruan,
            $r->act_urutan_bongkar_pasuruan,
            $fmt($r->created_at, 'd-m-Y H:i:s'),
            $fmt($r->updated_at, 'd-m-Y H:i:s'),
        ];
    }
}
