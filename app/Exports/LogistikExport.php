<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Data Logistik.
 * - Kolom & urutan sama dengan tabel di halaman data_logistik
 * - Ditambah kolom detail DO (Sales Document, Customer Reference, dst.)
 * - 1 baris Excel = 1 DO (tidak digabung per shipment + tujuan)
 *
 * Pemakaian di controller:
 *   return Excel::download(new LogistikExport($query), 'data_logistik.xlsx');
 *   ($query = Eloquent/Query Builder yang sudah difilter, JANGAN di-get())
 */
class LogistikExport implements
    FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithStyles
{
    protected $builder;
    private ?array $cols = null;
    private ?array $agg = null;

    public function __construct($builder)
    {
        $this->builder = $builder;
    }

    // ============================================================
    // QUERY
    // ============================================================
    public function query()
    {
        return (clone $this->builder)
            ->orderBy('tanggal_naik_logistik', 'DESC')
            ->orderBy('no_shipment')
            ->orderBy('tujuan')
            ->orderBy('id');
    }

    // ============================================================
    // DEFINISI KOLOM [heading, tipe, closure]
    // tipe: text | date | time | money | pct | pct4 | num
    // ============================================================
    private function columns(): array
    {
        if ($this->cols !== null) {
            return $this->cols;
        }

        $c = [];

        $c[] = ['Tanggal Naik Logistik', 'date',  fn($r) => $this->xl($r->tanggal_naik_logistik)];
        $c[] = ['Rencana Kirim',         'date',  fn($r) => $this->xl($r->rencana_kirim)];
        $c[] = ['Lead Time',             'text',  fn($r) => $r->transport_lead_time];
        $c[] = ['Planner',               'text',  fn($r) => $r->planner];
        $c[] = ['No Shipment',           'text',  fn($r) => (string) $r->no_shipment];
        $c[] = ['Update Posisi Mobil',   'text',  fn($r) => $this->statusPengiriman($r)];
        $c[] = ['Dist Channel',          'text',  fn($r) => $r->dist_channel];
        $c[] = ['Tujuan',                'text',  fn($r) => $r->tujuan];
        $c[] = ['Area',                  'text',  fn($r) => $r->area];
        $c[] = ['Ketersediaan Unit',     'text',  fn($r) => (empty($r->rencana_kirim) || empty($r->tanggal_dpt_unit)) ? 'BELUM DAPAT' : 'SUDAH DAPAT'];
        $c[] = ['Mobil',                 'text',  fn($r) => $r->mobil];
        $c[] = ['Delivery Quantity',     'num',   fn($r) => $this->num($r->total_do_qty_car)];
        $c[] = ['Nilai Muatan',          'money', fn($r) => $this->num($r->nilai_muatan)];
        $c[] = ['Biaya Kirim',           'money', fn($r) => $this->num($r->biaya_kirim)];
        $c[] = ['CR (%)',                'pct4',  fn($r) => $this->computeCR($r)];
        $c[] = ['Kubikasi',              'num',   fn($r) => $this->num($r->kubikasi)];
        $c[] = ['Tonase',                'num',   fn($r) => $this->num($r->tonase)];
        $c[] = ['Total Kubik',           'num',   fn($r) => $this->num($r->total_kubik)];
        $c[] = ['Total Tonase',          'num',   fn($r) => $this->num($r->total_tonase)];
        $c[] = ['Hasil Kubik (%)',       'pct',   fn($r) => $this->hasil($r->total_kubik, $r->kubikasi)];
        $c[] = ['Hasil Tonase (%)',      'pct',   fn($r) => $this->hasil($r->total_tonase, $r->tonase)];
        $c[] = ['Pengiriman Optimal Shipment',       'text', fn($r) => $this->optimalShipment($r)];
        $c[] = ['Reason Tidak Optimal Per Shipment', 'text', fn($r) => $r->reason_optimal];
        $c[] = ['Pengiriman Optimal Customer',       'text', fn($r) => $this->optimalCustomer($r)];
        $c[] = ['Reason Optimal Customer',           'text', fn($r) => $r->reason_optimal_customer ?: '-'];
        $c[] = ['Kategori Ekspedisi',    'text',  fn($r) => $r->kategori_ekspedisi ?: '-'];
        $c[] = ['Ekspedisi',             'text',  fn($r) => $r->ekpedisi];
        $c[] = ['Tanggal Dapat Unit',    'date',  fn($r) => $this->xl($r->tanggal_dpt_unit)];
        $c[] = ['Lama Waktu Pencarian',  'text',  fn($r) => $r->lama_waktu_pencarian ?? '-'];
        $c[] = ['SLA Dapat Mobil',       'text',  fn($r) => $r->sla_dapat_mobil ?: '-'];

        // ---- 3 gudang: KACS, Sentul, CCIE ----
        foreach (['' => 'KACS', '_2' => 'Sentul', '_3' => 'CCIE'] as $s => $n) {
            $c[] = ["Planning Loading {$n}", 'date', fn($r) => $this->xl($r->{'planning_loading' . $s})];
            $c[] = ["Tanggal Tiba {$n}",     'date', fn($r) => $this->xl($r->{'tanggal_tiba_gudang' . $s})];
            $c[] = ["Waktu Tiba {$n}",       'time', fn($r) => $this->jam($r->{'waktu_tiba_gudang' . $s})];
            $c[] = ["Tanggal Keluar {$n}",   'date', fn($r) => $this->xl($r->{'tanggal_keluar_gudang' . $s})];
            $c[] = ["Waktu Keluar {$n}",     'time', fn($r) => $this->jam($r->{'waktu_keluar_gudang' . $s})];
            $c[] = ["Reason Gudang {$n}",    'text', fn($r) => $r->{'reason_gudang' . $s} ?: '-'];
            $c[] = ["Lama Di {$n}",          'text', fn($r) => $this->durasiGudang($r->{'tanggal_tiba_gudang' . $s}, $r->{'tanggal_keluar_gudang' . $s})];
            $c[] = ["Status {$n}",           'text', fn($r) => $this->statusGudang($r->{'planning_loading' . $s}, $r->{'tanggal_tiba_gudang' . $s})];
            $c[] = ["SLA Loading {$n}",      'text', fn($r) => $this->slaLoading($r->{'planning_loading' . $s}, $r->{'tanggal_tiba_gudang' . $s})];
        }

        $c[] = ['PIC Monitoring',          'text',  fn($r) => $r->pic_monitoring];
        $c[] = ['Nama Kapal',              'text',  fn($r) => $r->nama_kapal];
        $c[] = ['ETD',                     'text',  fn($r) => $r->etd];
        $c[] = ['ETA',                     'text',  fn($r) => $r->eta];
        $c[] = ['Monitoring Alert',        'text',  fn($r) => $r->status_kendaraan ?: '-'];
        $c[] = ['Alert',                   'text',  fn($r) => $this->estimasiAlert($r)['alert']];
        $c[] = ['Urutan Bongkar',          'text',  fn($r) => $r->act_urutan_bongkar];
        $c[] = ['Actual Delivery Quantity','num',   fn($r) => $this->num($r->qty_monitoring)];
        $c[] = ['Biaya Kuli',              'money', fn($r) => $this->num($r->biaya_kuli)];
        $c[] = ['Total Biaya Kuli',        'money', fn($r) => $this->num($r->total_biaya_kuli)];
        $c[] = ['Selisih Quantity',        'num',   fn($r) => $this->num($r->selisih_qty)];
        $c[] = ['Reason Selisih Quantity', 'text',  fn($r) => $r->remarks_qty];
        $c[] = ['Act PGI Date',            'date',  fn($r) => $this->xl($r->act_pgi_date)];
        $c[] = ['ATD',                     'text',  fn($r) => $r->atd];
        $c[] = ['ATA',                     'text',  fn($r) => $r->ata];
        $c[] = ['Tanggal Estimasi',        'date',  fn($r) => $this->estimasiAlert($r)['estimasi']];
        $c[] = ['Tanggal Tiba',            'date',  fn($r) => $this->xl($r->tanggal_tiba)];
        $c[] = ['Waktu Tiba',              'time',  fn($r) => $this->jam($r->waktu_tiba)];
        $c[] = ['Lama Perjalanan (Hari)',  'num',   fn($r) => $this->lamaPerjalanan($r)];
        $c[] = ['SLA Tiba',                'text',  fn($r) => $r->sla_tiba ?: '-'];
        $c[] = ['Tanggal Bongkar',         'date',  fn($r) => $this->xl($r->tanggal_bongkar)];
        $c[] = ['Waktu Bongkar',           'time',  fn($r) => $this->jam($r->waktu_bongkar)];
        $c[] = ['Status Bongkar',          'text',  fn($r) => $this->statusBongkar($r)];
        $c[] = ['Overstay (Hari)',         'num',   fn($r) => $this->num($r->overstay_days)];
        $c[] = ['SLA Bongkar',             'text',  fn($r) => $r->sla_bongkar ?: '-'];
        $c[] = ['Reason Tiba',             'text',  fn($r) => $r->reason_tiba];
        $c[] = ['Reason Bongkar',          'text',  fn($r) => $r->reason_bongkar];
        $c[] = ['Status Akhir',            'text',  fn($r) => $this->statusAkhir($r)];
        $c[] = ['Status Alert',            'text',  fn($r) => $this->statusAlert($r)];
        $c[] = ['Remarks',                 'text',  fn($r) => $r->remarks];
        $c[] = ['Nama Driver',             'text',  fn($r) => $r->nama_driver];
        $c[] = ['No Pol',                  'text',  fn($r) => $r->no_pol];
        $c[] = ['Route',                   'text',  fn($r) => $r->route];
        $c[] = ['Shipping Point',          'text',  fn($r) => $r->route ? explode('-', trim($r->route))[0] : '-'];
        $c[] = ['Pulau',                   'text',  fn($r) => $r->pulau];
        $c[] = ['Via Kirim',               'text',  fn($r) => $r->via_kirim];
        $c[] = ['Estimasi Tiba Di Customer','date', fn($r) => $this->estimasiAdminXl($r)];
        $c[] = ['Ontime/Delay Admin',      'text',  fn($r) => $this->statusEstimasiAdmin($r)];
        $c[] = ['Create On',               'date',  fn($r) => $this->xl($r->create_on)];
        $c[] = ['No Delivery',             'text',  fn($r) => $r->no_delivery ?: '-'];

        // ---- detail per DO (di tabel hanya muncul saat baris di-expand) ----
        $c[] = ['Sales Document',          'text',  fn($r) => $r->sales_document ?: '-'];
        $c[] = ['Customer Reference',      'text',  fn($r) => $r->customer_reference ?: '-'];
        $c[] = ['Contract End Date',       'date',  fn($r) => $this->xl($r->contract_end_date)];
        $c[] = ['No DO',                   'text',  fn($r) => $r->no_do ?: '-'];
        $c[] = ['Kirim Kontribusi',        'money', fn($r) => $this->num($r->kirim_kontribusi)];

        return $this->cols = $c;
    }

    // ============================================================
    // CONTRACT MAATWEBSITE
    // ============================================================
    public function headings(): array
    {
        return array_map(fn($col) => $col[0], $this->columns());
    }

    public function map($r): array
    {
        return array_map(fn($col) => ($col[2])($r), $this->columns());
    }

    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->columns() as $i => [$head, $type]) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);

            switch ($type) {
                case 'date':  $formats[$letter] = 'dd-mm-yyyy'; break;
                case 'money': $formats[$letter] = '"Rp" #,##0'; break;
                case 'pct':   $formats[$letter] = '0.00"%"'; break;
                case 'pct4':  $formats[$letter] = '0.0000"%"'; break;
                case 'num':   $formats[$letter] = '#,##0.##'; break;
            }

            // kolom yang harus tetap teks (angka panjang / jam)
            if (in_array($head, ['No Shipment', 'No Pol', 'No Delivery', 'No DO', 'Sales Document', 'Customer Reference'], true)
                || str_starts_with($head, 'Waktu ')) {
                $formats[$letter] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => '03C03C'],
                ],
            ],
        ];
    }

    // ============================================================
    // HELPER DASAR
    // ============================================================
    private function xl($v)
    {
        if ($v === null || $v === '' || $v === false || $v === 'mm/dd/yyyy') return '';
        $ts = is_int($v) ? $v : strtotime((string) $v);
        if (!$ts || $ts <= 0) return '';
        return XlDate::PHPToExcel(new \DateTime(date('Y-m-d', $ts)));
    }

    private function jam($v): string
    {
        return $v ? substr((string) $v, 0, 5) : '';
    }

    private function num($v)
    {
        return ($v === null || $v === '' || !is_numeric($v)) ? '' : (float) $v;
    }

    private function ts($v): ?int
    {
        if (empty($v)) return null;
        $t = strtotime((string) $v);
        return $t ?: null;
    }

    private function dayStart($v): ?int
    {
        $t = $this->ts($v);
        return $t ? strtotime(date('Y-m-d', $t)) : null;
    }

    // ============================================================
    // CR (sama dengan tabel: kirim_kontribusi / nilai_muatan)
    // ============================================================
    private function computeCR($r)
    {
        $muatan = (float) $r->nilai_muatan;
        $biaya  = (float) ($r->kirim_kontribusi ?? $r->biaya_kirim);
        return $muatan > 0 ? round(($biaya / $muatan) * 100, 4) : 0;
    }

    // ============================================================
    // KUBIK / TONASE / OPTIMAL
    // ============================================================
    private function hasil($total, $kapasitas)
    {
        $kap = (float) ($kapasitas ?? 0);
        if ($kap <= 0) return '';
        return round(((float) ($total ?? 0) / $kap) * 100, 2);
    }

    private function optimalLabel($k, $t): string
    {
        if ($k === '' && $t === '') return '-';
        $ok = ($k !== '' && $k >= 85) || ($t !== '' && $t >= 85);
        return $ok ? 'Optimal' : 'Tidak Optimal';
    }

    /** per customer: hanya muatan baris itu */
    private function optimalCustomer($r): string
    {
        return $this->optimalLabel(
            $this->hasil($r->total_kubik, $r->kubikasi),
            $this->hasil($r->total_tonase, $r->tonase)
        );
    }

    /** per shipment: semua baris di no_shipment yang sama dijumlahkan */
    private function optimalShipment($r): string
    {
        $a = $this->aggregates()[trim((string) $r->no_shipment)] ?? null;
        if (!$a) return $this->optimalCustomer($r);

        return $this->optimalLabel(
            $this->hasil($a['sum_kubik'], $a['kubikasi']),
            $this->hasil($a['sum_tonase'], $a['tonase'])
        );
    }

    private function aggregates(): array
    {
        if ($this->agg !== null) return $this->agg;

        return $this->agg = DB::table('logistik_pengiriman')
            ->select(
                'no_shipment',
                DB::raw('SUM(COALESCE(total_kubik,0)) as sum_kubik'),
                DB::raw('SUM(COALESCE(total_tonase,0)) as sum_tonase'),
                DB::raw('MAX(kubikasi) as kubikasi'),
                DB::raw('MAX(tonase) as tonase')
            )
            ->whereNotNull('no_shipment')
            ->where('no_shipment', '!=', '')
            ->groupBy('no_shipment')
            ->get()
            ->keyBy('no_shipment')
            ->map(fn($g) => [
                'sum_kubik'  => (float) $g->sum_kubik,
                'sum_tonase' => (float) $g->sum_tonase,
                'kubikasi'   => (float) $g->kubikasi,
                'tonase'     => (float) $g->tonase,
            ])
            ->toArray();
    }

    // ============================================================
    // GUDANG (logika sama dengan tabel: planning vs tiba, per hari)
    // ============================================================
    private function durasiGudang($tiba, $keluar): string
    {
        $a = $this->ts($tiba);
        $b = $this->ts($keluar);
        if (!$a || !$b) return '-';

        $menit   = max(0, intdiv($b - $a, 60));
        $desimal = $menit / 1440;
        $hari    = floor($desimal);
        $jam     = round(($desimal - $hari) * 24);

        if ($jam == 24) { $jam = 0; $hari += 1; }

        if ($hari > 0 && $jam > 0) return "{$hari} Hari {$jam} Jam";
        if ($hari > 0) return "{$hari} Hari";
        if ($jam > 0) return "{$jam} Jam";
        return '0 Jam';
    }

    private function statusGudang($planning, $tiba): string
    {
        $a = $this->dayStart($planning);
        $b = $this->dayStart($tiba);
        if (!$a || !$b) return '-';
        return $b > $a ? 'Delay' : 'On Time';
    }

    private function slaLoading($planning, $tiba): string
    {
        $a = $this->dayStart($planning);
        $b = $this->dayStart($tiba);
        if (!$a || !$b) return '-';
        return $b > $a ? 'H+' . (int) round(($b - $a) / 86400) : 'Sesuai SLA';
    }

    // ============================================================
    // UPDATE POSISI MOBIL (sama dengan badgeStatusPengiriman controller)
    // ============================================================
    private function statusPengiriman($r): string
    {
        $gudang = collect([
            ['n' => 'KACS',   'p' => $r->planning_loading,   't' => $r->tanggal_tiba_gudang,   'k' => $r->tanggal_keluar_gudang],
            ['n' => 'SENTUL', 'p' => $r->planning_loading_2, 't' => $r->tanggal_tiba_gudang_2, 'k' => $r->tanggal_keluar_gudang_2],
            ['n' => 'CCIE',   'p' => $r->planning_loading_3, 't' => $r->tanggal_tiba_gudang_3, 'k' => $r->tanggal_keluar_gudang_3],
        ])
            ->filter(fn($g) => !empty($g['p']))
            ->sortBy(fn($g) => strtotime($g['p']))
            ->values();

        $sg = null;
        foreach ($gudang as $g) {
            if (empty($g['t'])) { $sg = 'PERJALANAN KE ' . $g['n']; break; }
            if (empty($g['k'])) { $sg = 'DI GUDANG ' . $g['n']; break; }
        }

        if (empty($r->tanggal_dpt_unit)) return 'MENCARI UNIT';
        if ($gudang->isEmpty() && empty($r->tanggal_tiba)) return 'PERJALANAN KE GUDANG';
        if ($sg) return $sg;
        if (empty($r->tanggal_tiba)) return 'PERJALANAN KE TUJUAN';
        return !empty($r->tanggal_bongkar) ? 'SUDAH SELESAI' : 'SUDAH TIBA TUJUAN';
    }

    private function gudangAktif($r): ?string
    {
        $gudang = collect([
            ['n' => 'KACS',   'p' => $r->planning_loading,   't' => $r->tanggal_tiba_gudang],
            ['n' => 'SENTUL', 'p' => $r->planning_loading_2, 't' => $r->tanggal_tiba_gudang_2],
            ['n' => 'CCIE',   'p' => $r->planning_loading_3, 't' => $r->tanggal_tiba_gudang_3],
        ])
            ->filter(fn($g) => !empty($g['p']))
            ->sortBy(fn($g) => strtotime($g['p']))
            ->values();

        foreach ($gudang as $g) {
            if (empty($g['t'])) return 'PERJALANAN KE ' . $g['n'];
        }
        return null;
    }

    // ============================================================
    // ESTIMASI TIBA + ALERT
    // ============================================================
    private function estimasiAlert($r): array
    {
        $aktif = $this->gudangAktif($r);
        if ($aktif) {
            return ['estimasi' => $aktif, 'alert' => '-'];
        }

        $est     = $this->ts($r->estimasi_tiba);
        $estShow = $est ? $this->xl($r->estimasi_tiba) : '-';

        if ($r->tanggal_tiba) return ['estimasi' => $estShow, 'alert' => 'TIBA'];
        if (!$est)            return ['estimasi' => $estShow, 'alert' => '-'];

        $today = strtotime(date('Y-m-d'));
        $sisa  = floor(($est - $today) / 86400);

        if ($sisa < 0)       $alert = 'Pending Tiba H+' . abs($sisa);
        elseif ($sisa <= 7)  $alert = 'H-' . $sisa;
        else                 $alert = 'ON TRACK';

        return ['estimasi' => $estShow, 'alert' => $alert];
    }

    private function lamaPerjalanan($r)
    {
        if (empty($r->tanggal_tiba)) return '-';

        $keluar = array_filter([
            $r->tanggal_keluar_gudang, $r->tanggal_keluar_gudang_2, $r->tanggal_keluar_gudang_3,
        ]);
        if (empty($keluar)) return '-';

        $k = max(array_map('strtotime', $keluar));
        return max(0, floor((strtotime($r->tanggal_tiba) - $k) / 86400));
    }

    private function statusBongkar($r): string
    {
        if ($r->tanggal_bongkar) return 'Sudah Bongkar';

        if ($r->tanggal_tiba) {
            $tiba  = strtotime(date('Y-m-d', strtotime($r->tanggal_tiba)));
            $today = strtotime(date('Y-m-d'));
            return 'Pending Bongkar H+' . max(0, floor(($today - $tiba) / 86400));
        }

        return '-';
    }

    // ============================================================
    // STATUS AKHIR & STATUS ALERT
    // ============================================================
    private function statusAkhir($r): string
    {
        $t = strtoupper(trim($r->sla_tiba ?? ''));
        $b = strtoupper(trim($r->sla_bongkar ?? ''));

        if (empty($r->tanggal_tiba)) return 'Dalam Perjalanan';
        if (empty($r->tanggal_bongkar)) return 'Sudah Tiba - Dalam Pembongkaran';
        if ($t === 'ON TIME' && $b === 'ON TIME') return 'Pengiriman On Time';
        return 'Pengiriman Delay';
    }

    private function statusAlert($r): string
    {
        $t = strtoupper(trim($r->sla_tiba ?? ''));
        $b = strtoupper(trim($r->sla_bongkar ?? ''));

        if ($t === 'ON TIME' && $b === 'ON TIME') return 'Delivered Ontime';
        if ($t === 'DELAY'   && $b === 'ON TIME') return 'Delay Perjalanan';
        if ($t === 'ON TIME' && $b === 'DELAY')   return 'Delay Pembongkaran';
        if ($t === 'DELAY'   && $b === 'DELAY')   return 'Delivered Delay';
        return 'Belum Selesai';
    }

    // ============================================================
    // ESTIMASI ADMIN (rencana kirim + lead time, +1 hari Jawa Barat)
    // ============================================================
    private function estimasiAdminTs($r): ?int
    {
        $rencana = $this->dayStart($r->rencana_kirim);
        if (!$rencana) return null;

        $hari = (int) ($r->transport_lead_time ?? 0);
        if (strtolower(trim($r->area ?? '')) === 'jawa barat') $hari += 1;

        return strtotime("+{$hari} days", $rencana);
    }

    private function estimasiAdminXl($r)
    {
        $ts = $this->estimasiAdminTs($r);
        return $ts ? XlDate::PHPToExcel(new \DateTime(date('Y-m-d', $ts))) : '';
    }

    private function statusEstimasiAdmin($r): string
    {
        $est = $this->estimasiAdminTs($r);
        if (!$est) return '-';

        if (!empty($r->tanggal_tiba)) {
            return $this->dayStart($r->tanggal_tiba) <= $est ? 'On Time' : 'Delay';
        }

        return strtotime(date('Y-m-d')) > $est ? 'Delay' : 'Belum Tiba';
    }
}