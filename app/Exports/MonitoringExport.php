<?php

namespace App\Exports;

use App\Models\LogistikPengiriman;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonitoringExport implements
    FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithStyles
{
    private const FMT_DATE = 'dd-mm-yyyy';

    protected array $f;

    /**
     * $filters: pic_monitoring, area, bulan, tahun,
     *           keluar_gudang_tgl, tgl_dari, tgl_sampai
     */
    public function __construct(array $filters = [])
    {
        $this->f = $filters;
    }

    private function keluarSql(): string
    {
        return "GREATEST(
            COALESCE(tanggal_keluar_gudang,'1900-01-01'),
            COALESCE(tanggal_keluar_gudang_2,'1900-01-01'),
            COALESCE(tanggal_keluar_gudang_3,'1900-01-01')
        )";
    }

    public function query()
    {
        $q = LogistikPengiriman::query();
        $f = $this->f;
        $keluar = $this->keluarSql();

        if (!empty($f['pic_monitoring'])) {
            $q->where('pic_monitoring', $f['pic_monitoring']);
        }
        if (!empty($f['area'])) {
            $q->where('area', $f['area']);
        }
        if (!empty($f['bulan'])) {
            $q->whereRaw("MONTH({$keluar}) = ?", [(int) $f['bulan']]);
        }
        if (!empty($f['tahun'])) {
            $q->whereRaw("YEAR({$keluar}) = ?", [(int) $f['tahun']]);
        }
        if (!empty($f['keluar_gudang_tgl'])) {
            $q->whereDate('tanggal_keluar_gudang', $f['keluar_gudang_tgl']);
        }
        if (!empty($f['tgl_dari'])) {
            $q->whereRaw("DATE({$keluar}) >= ?", [$f['tgl_dari']]);
        }
        if (!empty($f['tgl_sampai'])) {
            $q->whereRaw("DATE({$keluar}) <= ?", [$f['tgl_sampai']]);
        }

        return $q->orderBy('no_shipment', 'ASC')
                 ->orderBy('act_urutan_bongkar', 'ASC')
                 ->orderBy('id', 'ASC');
    }

    // urutan HARUS sama dengan renderRowColumns() (0-33), jam tiba/bongkar dipisah
    public function headings(): array
    {
        return [
            'Tanggal Keluar Gudang', // A
            'Act PGI Date',          // B
            'Dist Channel',          // C
            'Area',                  // D
            'No Shipment',           // E
            'Tujuan',                // F
            'Ekspedisi',             // G
            'PIC',                   // H
            'Status',                // I
            'Alert',                 // J
            'Total DO Qty',          // K
            'Selisih Qty Do',        // L
            'Biaya Kuli',            // M
            'Total Biaya Kuli',      // N
            'Qty Actual Do',         // O
            'Reason Qty',            // P
            'Urutan Bongkar',        // Q
            'Estimasi Tiba',         // R
            'Tanggal Tiba',          // S
            'Jam Tiba',              // T
            'Lama Perjalanan',       // U
            'SLA Tiba',              // V
            'Tanggal Bongkar',       // W
            'Jam Bongkar',           // X
            'Status Bongkar',        // Y
            'Overstay',              // Z
            'SLA Bongkar',           // AA
            'Reason Tiba',           // AB
            'Reason Bongkar',        // AC
            'Remarks',               // AD
            'Nama Kapal',            // AE
            'ETD',                   // AF
            'ETA',                   // AG
            'ATD',                   // AH
            'ATA',                   // AI
            'Kelengkapan Data',      // AJ
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A'  => self::FMT_DATE,
            'B'  => self::FMT_DATE,
            'E'  => NumberFormat::FORMAT_TEXT,   // no shipment panjang, jangan jadi 4.2E+09
            'N'  => '"Rp" #,##0',
            'R'  => self::FMT_DATE,
            'S'  => self::FMT_DATE,
            'W'  => self::FMT_DATE,
            'AF' => self::FMT_DATE,
            'AG' => self::FMT_DATE,
            'AH' => self::FMT_DATE,
            'AI' => self::FMT_DATE,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function map($r): array
    {
        $info    = $this->getKeluarGudangInfo($r);
        $keluar  = $info['keluar'];
        $blocked = $info['blocked'];
        $blockedLabel = $info['blocked_status'] === 'sedang'
            ? 'Sedang di Gudang Berikutnya'
            : 'Menuju Gudang Berikutnya';

        $leadtime = (int) ($r->transport_lead_time ?? 0);
        $estimasi = !empty($r->estimasi_tiba)
            ? strtotime($r->estimasi_tiba)
            : ((!$blocked && $keluar) ? strtotime("+{$leadtime} days", $keluar) : null);

        $today = strtotime(date('Y-m-d'));
        $tiba  = !empty($r->tanggal_tiba) ? strtotime($r->tanggal_tiba) : null;

        // ----- Lama perjalanan -----
        $lama = ($tiba && $keluar) ? floor(($tiba - $keluar) / 86400) : '-';

        // ----- Alert (sama dengan render) -----
        $alert = '-';
        if ($tiba) {
            $alert = 'TIBA';
        } elseif (!$blocked && $estimasi) {
            $sisa = floor(($estimasi - $today) / 86400);
            if ($sisa < 0)      $alert = 'Pending Tiba H+' . abs($sisa);
            elseif ($sisa <= 7) $alert = 'H-' . $sisa;
            else                $alert = 'ON TRACK';
        }

        // ----- Status bongkar -----
        $statusBongkar = '-';
        if (!empty($r->tanggal_bongkar)) {
            $statusBongkar = 'Sudah Bongkar';
        } elseif ($tiba) {
            $hari = floor(($today - strtotime(date('Y-m-d', $tiba))) / 86400);
            $statusBongkar = 'Pending Bongkar H+' . max(0, $hari);
        }

        // ----- Kelengkapan data -----
        $missing = [];
        if (empty($r->tanggal_tiba))    $missing[] = 'Tgl Tiba';
        if (empty($r->waktu_tiba))      $missing[] = 'Jam Tiba';
        if (empty($r->tanggal_bongkar)) $missing[] = 'Tgl Bongkar';
        if (empty($r->waktu_bongkar))   $missing[] = 'Jam Bongkar';

        $isOverdue = ($estimasi && !$blocked) ? ($estimasi < $today) : false;

        if (count($missing) === 0) {
            $kelengkapan = 'Lengkap';
        } elseif (!empty($r->tanggal_tiba) && empty($r->tanggal_bongkar)) {
            $kelengkapan = 'Sampai Tujuan (belum bongkar)';
        } elseif (!$isOverdue) {
            $kelengkapan = '- (belum jatuh tempo)';
        } else {
            $kelengkapan = 'Belum lengkap: ' . implode(', ', $missing);
        }

        return [
            $blocked ? $blockedLabel : $this->xl($keluar),               // A
            $this->xl($r->act_pgi_date),                                 // B
            $r->dist_channel,                                            // C
            $r->area,                                                    // D
            (string) $r->no_shipment,                                    // E
            $r->tujuan,                                                  // F
            $r->ekpedisi,                                                // G
            $r->pic_monitoring,                                          // H
            $r->status_kendaraan,                                        // I
            $alert,                                                      // J
            $r->total_do_qty_car,                                        // K
            $r->selisih_qty,                                             // L
            $r->biaya_kuli,                                              // M
            $r->total_biaya_kuli ?? 0,                                   // N
            $r->qty_monitoring,                                          // O
            $r->remarks_qty,                                             // P
            $r->act_urutan_bongkar,                                      // Q
            $blocked ? $blockedLabel : $this->xl($estimasi),             // R
            $this->xl($r->tanggal_tiba),                                 // S
            $r->waktu_tiba ? substr($r->waktu_tiba, 0, 5) : '',          // T
            $lama,                                                       // U
            $r->sla_tiba ?? '-',                                         // V
            $this->xl($r->tanggal_bongkar),                              // W
            $r->waktu_bongkar ? substr($r->waktu_bongkar, 0, 5) : '',    // X
            $statusBongkar,                                              // Y
            $r->overstay_days ?? '-',                                    // Z
            $r->sla_bongkar ?? '-',                                      // AA
            $r->reason_tiba,                                             // AB
            $r->reason_bongkar,                                          // AC
            $r->remarks,                                                 // AD
            $r->nama_kapal,                                              // AE
            $this->xl($r->etd),                                          // AF
            $this->xl($r->eta),                                          // AG
            $this->xl($r->atd),                                          // AH
            $this->xl($r->ata),                                          // AI
            $kelengkapan,                                                // AJ
        ];
    }

    /**
     * Ubah timestamp / string tanggal jadi serial tanggal Excel
     * (ditampilkan dd-mm-yyyy lewat columnFormats). Kosong -> ''.
     */
    private function xl($v)
    {
        if ($v === null || $v === '' || $v === false) {
            return '';
        }

        $ts = is_int($v) ? $v : strtotime((string) $v);
        if (!$ts || $ts <= 0) {
            return '';
        }

        // buang jam, hanya tanggal
        return XlDate::PHPToExcel(new \DateTime(date('Y-m-d', $ts)));
    }

    // salinan logic controller::getKeluarGudangInfo
    private function getKeluarGudangInfo($r): array
    {
        $cycles = [
            ['planning' => $r->planning_loading,   'tiba' => $r->tanggal_tiba_gudang,   'keluar' => $r->tanggal_keluar_gudang],
            ['planning' => $r->planning_loading_2, 'tiba' => $r->tanggal_tiba_gudang_2, 'keluar' => $r->tanggal_keluar_gudang_2],
            ['planning' => $r->planning_loading_3, 'tiba' => $r->tanggal_tiba_gudang_3, 'keluar' => $r->tanggal_keluar_gudang_3],
        ];

        $blocked = false;
        $blockedStatus = null;
        $keluarTs = [];

        foreach ($cycles as $c) {
            $started = !empty($c['planning']) || !empty($c['tiba']);
            $selesai = !empty($c['keluar']);

            if ($started && !$selesai) {
                $blocked = true;
                $blockedStatus = !empty($c['tiba']) ? 'sedang' : 'menuju';
            }
            if ($selesai) {
                $keluarTs[] = strtotime($c['keluar']);
            }
        }

        return [
            'blocked'        => $blocked,
            'blocked_status' => $blockedStatus,
            'keluar'         => $keluarTs ? max($keluarTs) : null,
        ];
    }
}