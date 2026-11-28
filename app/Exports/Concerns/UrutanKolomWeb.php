<?php

namespace App\Exports\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Menyusun ulang kolom sheet Data laporan agar sama dengan urutan & label tabel web.
 *
 * Tiap kolom parent (LogistikExportFull) dicocokkan lewat heading-nya (dinormalisasi: huruf kecil,
 * tanpa spasi/simbol) dengan daftar alias. Alias pertama yang ketemu menang, jadi
 * kolom "[Hitung] ..." diprioritaskan, nama kolom DB jadi cadangan (mis. saat mode ringan).
 * Kolom parent yang tidak masuk urutan web tetap ikut, ditaruh setelah blok web.
 */
trait UrutanKolomWeb
{
    /** label web => heading asli parent (supaya letter('area') dst. tetap jalan) */
    protected array $aliasOf = [];

    private function webOrder(): array
    {
        $o = [
            ['Tanggal Naik Logistik', ['tanggal_naik_logistik']],
            ['Rencana Kirim', ['rencana_kirim']],
            ['Lead Time', ['transport_lead_time']],
            ['Planner', ['planner']],
            ['No Shipment', ['no_shipment']],
            ['Update Posisi Mobil', ['[Hitung] Update Posisi Mobil', 'status_pengiriman']],
            ['Dist Channel', ['dist_channel']],
            ['Tujuan', ['tujuan']],
            ['Area', ['area']],
            ['Kota', ['kota']],
            ['Ketersediaan Unit', ['[Hitung] Ketersediaan Unit', 'ketersediaan_unit']],
            ['Mobil', ['mobil']],
            ['Delivery Quantity', ['total_do_qty_car']],
            ['Nilai Muatan', ['nilai_muatan']],
            ['Biaya Kirim', ['biaya_kirim']],
            ['CR', ['[Hitung] CR (%)', 'cr']],
            ['CR Shipment', ['[Hitung] CR Shipment (%)']],
            ['Kubikasi', ['kubikasi']],
            ['Tonase', ['tonase']],
            ['Total Kubik', ['total_kubik']],
            ['Total Tonase', ['total_tonase']],
            ['Hasil Kubik', ['[Hitung] Hasil Kubik (%)', 'hasil_kubik']],
            ['Hasil Tonase', ['[Hitung] Hasil Tonase (%)', 'hasil_tonase']],
            ['Pengiriman Optimal Shipment', ['[Hitung] Pengiriman Optimal Shipment', 'pengiriman_optimal']],
            ['Reason Tidak Optimal Per Shipment', ['reason_optimal']],
            ['Pengiriman Optimal Customer', ['[Hitung] Pengiriman Optimal Customer', 'pengiriman_optimal_customer']],
            ['Kategori Ekspedisi', ['kategori_ekspedisi']],
            ['Ekspedisi', ['ekpedisi']],
            ['Tanggal Dapat Unit', ['tanggal_dpt_unit']],
            ['Lama Waktu Pencarian', ['lama_waktu_pencarian']],
            ['SLA Dapat Mobil', ['sla_dapat_mobil']],
        ];

        // [label web, suffix kolom DB, nama di [Hitung], urutan: true = Status dulu baru SLA (KACS)]
        $gudang = [
            ['KACS', '', 'KACS', true],
            ['Sentul', '_2', 'Sentul', false],
            ['CCIE', '_3', 'CCIE', false],
            ['P5', '_4', 'P5', false],
            ['Vivatex', '_5', 'FIFATEX', false],
        ];

        foreach ($gudang as [$nm, $s, $h, $statusDulu]) {
            $o[] = ["Planning Loading {$nm}", ["planning_loading{$s}"]];
            $o[] = ["Tanggal Tiba {$nm}", ["tanggal_tiba_gudang{$s}"]];
            $o[] = ["Waktu Tiba {$nm}", ["waktu_tiba_gudang{$s}"]];
            $o[] = ["Tanggal Keluar {$nm}", ["tanggal_keluar_gudang{$s}"]];
            $o[] = ["Waktu Keluar {$nm}", ["waktu_keluar_gudang{$s}"]];
            $o[] = ["Reason Gudang {$nm}", ["reason_gudang{$s}"]];
            $o[] = ["Lama Di {$nm}", ["[Hitung] Lama Di {$h}", "lama_digudang{$s}"]];

            $status = ["Status {$nm}", ["[Hitung] Status {$h}", "status_gudang{$s}"]];
            $sla    = ["SLA Loading {$nm}", ["[Hitung] SLA Loading {$h}", "sla_loading{$s}"]];

            $o[] = $statusDulu ? $status : $sla;
            $o[] = $statusDulu ? $sla : $status;
        }

        return array_merge($o, [
            ['PIC Monitoring', ['pic_monitoring']],
            ['Nama Kapal', ['nama_kapal']],
            ['ETD', ['etd']],
            ['ETA', ['eta']],
            ['Monitoring Alert', ['status_kendaraan', 'monitoring_alert']],
            ['Alert', ['[Hitung] Alert']],
            ['Urutan Bongkar', ['act_urutan_bongkar']],
            ['Actual Delivery Quantity', ['qty_monitoring']],
            ['Biaya Kuli', ['biaya_kuli']],
            ['Total Biaya Kuli', ['total_biaya_kuli']],
            ['Selisih Quantity', ['selisih_qty']],
            ['Reason Selisih Quantity', ['remarks_qty']],
            ['Act PGI Date', ['act_pgi_date']],
            ['ATD', ['atd']],
            ['ATA', ['ata']],
            ['Tanggal Estimasi', ['[Hitung] Tanggal Estimasi', 'estimasi_tiba']],
            ['Tanggal Tiba', ['tanggal_tiba']],
            ['Waktu Tiba', ['waktu_tiba']],
            ['Lama Perjalanan', ['[Hitung] Lama Perjalanan (Hari)', 'lama_perjalanan']],
            ['SLA Tiba', ['sla_tiba']],
            ['Tanggal Bongkar', ['tanggal_bongkar']],
            ['Waktu Bongkar', ['waktu_bongkar']],
            ['Status Bongkar', ['[Hitung] Status Bongkar']],
            ['Overstay', ['overstay_days']],
            ['SLA Bongkar', ['sla_bongkar']],
            ['Reason Tiba', ['reason_tiba']],
            ['Reason Bongkar', ['reason_bongkar']],
            ['Status Akhir', ['[Hitung] Status Akhir', 'status_akhir']],
            ['Status Alert', ['[Hitung] Status Alert']],
            ['Remarks', ['remarks']],
            ['Nama Driver', ['nama_driver']],
            ['No Pol', ['no_pol']],
            ['Route', ['route']],
            ['Shipping Point', ['[Hitung] Shipping Point (Route)', 'shipping_point']],
            ['Pulau', ['pulau']],
            ['Via Kirim', ['via_kirim']],
            ['Estimasi Tiba Di Customer', ['[Hitung] Estimasi Tiba Di Customer']],
            ['Ontime/Delay Admin', ['[Hitung] Ontime/Delay Admin']],
            ['Create On', ['create_on']],
            ['No Delivery', ['no_delivery']],
        ]);
    }

    /** @param array $base kolom parent: [[heading, tipe, closure], ...] */
    protected function urutWeb(array $base): array
    {
        $norm = fn($s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $s));

        $pool = [];
        foreach ($base as $i => $c) {
            $pool[$norm($c[0])][] = $i;
        }

        $used = [];
        $out  = [];
        $miss = [];

        foreach ($this->webOrder() as [$label, $aliases]) {
            $hit = null;
            foreach ($aliases as $a) {
                foreach ($pool[$norm($a)] ?? [] as $i) {
                    if (!isset($used[$i])) {
                        $hit = $i;
                        break 2;
                    }
                }
            }

            if ($hit === null) {
                $miss[] = $label;
                continue;
            }

            $used[$hit]            = true;
            $c                     = $base[$hit];
            $this->aliasOf[$label] = $c[0];
            $c[0]                  = $label;
            $out[]                 = $c;
        }

        // kolom parent yang tidak ada di urutan web tetap dipertahankan (setelah blok web)
        foreach ($base as $i => $c) {
            if (!isset($used[$i])) {
                $out[] = $c;
            }
        }

        if ($miss) {
            Log::warning('Export laporan: kolom web tidak ditemukan di parent: ' . implode(', ', $miss));
        }

        return $out;
    }
}