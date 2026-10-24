<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * EXPORT FULL data logistik_pengiriman
 *  - BAGIAN 1: SEMUA kolom tabel (119 kolom, urutan sama dengan database)
 *  - BAGIAN 2: kolom hasil hitungan otomatis (status, SLA, alert, CR, dst.)
 *  - 1 baris Excel = 1 DO
 *
 * Pemakaian:
 *   return Excel::download(new LogistikExportFull($query), 'data_logistik_full.xlsx');
 *   ($query = query builder yang sudah difilter, JANGAN di-get())
 */
class LogistikExportFull implements
    FromQuery, WithHeadings, WithMapping, WithColumnFormatting, WithStyles
{
    protected $builder;
    private ?array $cols = null;
    private ?array $agg = null;

    /** kolom level shipment: kalau DO ini kosong, ambil dari DO lain di shipment yang sama */
    private const SHIPMENT_FIELDS = ['nama_kapal', 'etd', 'eta', 'atd', 'ata', 'act_pgi_date'];

    /**
     * Semua kolom tabel, urut sesuai database.
     * tipe: text | ftext (paksa teks) | num | numt (angka kalau numerik, else teks)
     *       money | dt (tanggal+jam) | date | time
     */
    private const RAW = [
        ['id', 'num'],
        ['no', 'text'],
        ['tanggal_naik_logistik', 'dt'],
        ['rencana_kirim', 'dt'],
        ['transport_lead_time', 'text'],
        ['estimasi_admin', 'num'],
        ['planner', 'text'],
        ['no_shipment', 'ftext'],
        ['tujuan', 'text'],
        ['area', 'text'],
        ['ketersediaan_unit', 'text'],
        ['mobil', 'text'],
        ['perubahan_mobil', 'text'],
        ['nilai_muatan', 'money'],
        ['biaya_kirim', 'money'],
        ['biaya_kuli', 'money'],
        ['total_biaya_kuli', 'money'],
        ['cr', 'text'],
        ['kategori_ekspedisi', 'text'],
        ['ekpedisi', 'text'],
        ['nama_kapal', 'text'],
        ['etd', 'date'],
        ['eta', 'date'],
        ['atd', 'date'],
        ['ata', 'date'],
        ['nama_driver', 'text'],
        ['no_pol', 'ftext'],
        ['status_pengiriman', 'text'],
        ['tanggal_dpt_unit', 'dt'],
        ['planning_loading', 'dt'],
        ['tanggal_tiba_gudang', 'dt'],
        ['tanggal_keluar_gudang', 'dt'],
        ['lama_digudang', 'text'],
        ['status_gudang', 'text'],
        ['sla_loading', 'text'],
        ['keterangan', 'text'],
        ['lama_waktu_pencarian', 'text'],
        ['sla_dapat_mobil', 'text'],
        ['pic_monitoring', 'text'],
        ['status_kendaraan', 'text'],
        ['monitoring_alert', 'text'],
        ['action_required', 'text'],
        ['act_urutan_bongkar', 'text'],
        ['tanggal_tiba', 'date'],
        ['waktu_tiba', 'time'],
        ['lama_perjalanan', 'text'],
        ['sla_tiba', 'text'],
        ['tanggal_bongkar', 'date'],
        ['waktu_bongkar', 'time'],
        ['overstay_days', 'text'],
        ['sla_bongkar', 'text'],
        ['reason_tiba', 'text'],
        ['reason_bongkar', 'text'],
        ['status_akhir', 'text'],
        ['created_at', 'dt'],
        ['updated_at', 'dt'],
        ['tanggal_tiba_estimasi', 'date'],
        ['remarks', 'text'],
        ['dist_channel', 'text'],
        ['transportasi', 'text'],
        ['transport_laut', 'text'],
        ['tanggal_tiba_gudang_2', 'dt'],
        ['planning_loading_2', 'dt'],
        ['tanggal_keluar_gudang_2', 'dt'],
        ['lama_digudang_2', 'text'],
        ['status_gudang_2', 'text'],
        ['sla_loading_2', 'text'],
        ['tanggal_tiba_gudang_3', 'dt'],
        ['planning_loading_3', 'dt'],
        ['tanggal_keluar_gudang_3', 'dt'],
        ['lama_digudang_3', 'text'],
        ['status_gudang_3', 'text'],
        ['sla_loading_3', 'text'],
        ['cust_grp_5_desc', 'text'],
        ['cust_grp_3_desc', 'text'],
        ['ship_no', 'ftext'],
        ['cust_desc', 'text'],
        ['addt_text_4', 'text'],
        ['service_agent', 'text'],
        ['urutan_bongkar', 'num'],
        ['act_pgi_date', 'date'],
        ['created_by', 'text'],
        ['total_do_qty_car', 'num'],
        ['route', 'text'],
        ['shipping_point', 'text'],
        ['kubikasi', 'numt'],
        ['pulau', 'text'],
        ['via_kirim', 'text'],
        ['estimasi_tiba', 'dt'],
        ['create_tgl', 'dt'],
        ['qty_monitoring', 'num'],
        ['remarks_qty', 'text'],
        ['selisih_qty', 'num'],
        ['Div', 'text'],
        ['tonase', 'numt'],
        ['total_kubik', 'numt'],
        ['total_tonase', 'numt'],
        ['hasil_kubik', 'numt'],
        ['hasil_tonase', 'numt'],
        ['pengiriman_optimal', 'text'],
        ['pengiriman_optimal_customer', 'text'],
        ['reason_optimal_customer', 'text'],
        ['reason_optimal', 'text'],
        ['reason_gudang', 'text'],
        ['reason_gudang_2', 'text'],
        ['reason_gudang_3', 'text'],
        ['waktu_tiba_gudang', 'time'],
        ['waktu_keluar_gudang', 'time'],
        ['waktu_tiba_gudang_2', 'time'],
        ['waktu_keluar_gudang_2', 'time'],
        ['waktu_tiba_gudang_3', 'time'],
        ['waktu_keluar_gudang_3', 'time'],
        ['create_on', 'date'],
        ['no_delivery', 'ftext'],
        ['customer_reference', 'ftext'],
        ['sales_document', 'ftext'],
        ['contract_end_date', 'date'],
        ['no_do', 'ftext'],
        ['kirim_kontribusi', 'money'],
    ];

    public function __construct($builder)
    {
        $this->builder = $builder;
    }

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
    // ============================================================
    private function columns(): array
    {
        if ($this->cols !== null) {
            return $this->cols;
        }

        $c = [];

        // ---------- BAGIAN 1: SEMUA KOLOM DATABASE ----------
        foreach (self::RAW as [$col, $type]) {
            $c[] = [$col, $type, fn($r) => $this->raw($r, $col, $type)];
        }

        // ---------- BAGIAN 2: HASIL HITUNGAN OTOMATIS ----------
        $c[] = ['[Hitung] Update Posisi Mobil',    'text', fn($r) => $this->statusPengiriman($r)];
        $c[] = ['[Hitung] Ketersediaan Unit',      'text', fn($r) => (empty($r->rencana_kirim) || empty($r->tanggal_dpt_unit)) ? 'BELUM DAPAT' : 'SUDAH DAPAT'];
        $c[] = ['[Hitung] CR (%)',                 'pct4', fn($r) => $this->computeCR($r)];
        $c[] = ['[Hitung] Hasil Kubik (%)',        'pct',  fn($r) => $this->hasil($r->total_kubik, $r->kubikasi)];
        $c[] = ['[Hitung] Hasil Tonase (%)',       'pct',  fn($r) => $this->hasil($r->total_tonase, $r->tonase)];
        $c[] = ['[Hitung] Pengiriman Optimal Shipment', 'text', fn($r) => $this->optimalShipment($r)];
        $c[] = ['[Hitung] Pengiriman Optimal Customer', 'text', fn($r) => $this->optimalCustomer($r)];

        foreach (['' => 'KACS', '_2' => 'Sentul', '_3' => 'CCIE'] as $s => $n) {
            $c[] = ["[Hitung] Lama Di {$n}",     'text', fn($r) => $this->durasiGudang($r->{'tanggal_tiba_gudang' . $s}, $r->{'tanggal_keluar_gudang' . $s})];
            $c[] = ["[Hitung] Status {$n}",      'text', fn($r) => $this->statusGudang($r->{'planning_loading' . $s}, $r->{'tanggal_tiba_gudang' . $s})];
            $c[] = ["[Hitung] SLA Loading {$n}", 'text', fn($r) => $this->slaLoading($r->{'planning_loading' . $s}, $r->{'tanggal_tiba_gudang' . $s})];
        }

        $c[] = ['[Hitung] Alert',                   'text', fn($r) => $this->estimasiAlert($r)['alert']];
        $c[] = ['[Hitung] Tanggal Estimasi',        'date', fn($r) => $this->estimasiAlert($r)['estimasi']];
        $c[] = ['[Hitung] Lama Perjalanan (Hari)',  'num',  fn($r) => $this->lamaPerjalanan($r)];
        $c[] = ['[Hitung] Status Bongkar',          'text', fn($r) => $this->statusBongkar($r)];
        $c[] = ['[Hitung] Status Akhir',            'text', fn($r) => $this->statusAkhir($r)];
        $c[] = ['[Hitung] Status Alert',            'text', fn($r) => $this->statusAlert($r)];
        $c[] = ['[Hitung] Shipping Point (Route)',  'text', fn($r) => $r->route ? explode('-', trim($r->route))[0] : '-'];
        $c[] = ['[Hitung] Estimasi Tiba Di Customer', 'date', fn($r) => $this->estimasiAdminXl($r)];
        $c[] = ['[Hitung] Ontime/Delay Admin',      'text', fn($r) => $this->statusEstimasiAdmin($r)];

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
                case 'dt':    $formats[$letter] = 'dd-mm-yyyy hh:mm'; break;
                case 'money': $formats[$letter] = '"Rp" #,##0'; break;
                case 'pct':   $formats[$letter] = '0.00"%"'; break;
                case 'pct4':  $formats[$letter] = '0.0000"%"'; break;
                case 'num':   $formats[$letter] = '#,##0.##'; break;
                case 'ftext':
                case 'time':  $formats[$letter] = NumberFormat::FORMAT_TEXT; break;
            }
        }

        return $formats;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');
        $sheet->getDefaultColumnDimension()->setWidth(20);

        $rawCount   = count(self::RAW);
        $totalCount = count($this->columns());

        // header bagian 1 (kolom database) = hijau, bagian 2 (hitungan) = biru
        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex($rawCount) . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '03C03C']],
        ]);
        $sheet->getStyle(
            Coordinate::stringFromColumnIndex($rawCount + 1) . '1:' .
            Coordinate::stringFromColumnIndex($totalCount) . '1'
        )->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2563EB']],
        ]);

        return [];
    }

    // ============================================================
    // NILAI KOLOM DATABASE
    // ============================================================
    private function raw($r, string $col, string $type)
    {
        $v = in_array($col, self::SHIPMENT_FIELDS, true)
            ? $this->shp($r, $col)
            : ($r->{$col} ?? null);

        switch ($type) {
            case 'date':  return $this->xl($v);
            case 'dt':    return $this->xlDt($v);
            case 'time':  return $this->jam($v);
            case 'num':
            case 'money': return $this->num($v);
            case 'numt':  return ($v === null || $v === '') ? '' : (is_numeric($v) ? (float) $v : (string) $v);
            default:      return $v === null ? '' : (string) $v;
        }
    }

    // ============================================================
    // FALLBACK KOLOM LEVEL SHIPMENT + AGREGAT
    // ============================================================
    private function aggregates(): array
    {
        if ($this->agg !== null) return $this->agg;

        $q = DB::table('logistik_pengiriman')
            ->select(
                'no_shipment',
                DB::raw('SUM(COALESCE(total_kubik,0)) as sum_kubik'),
                DB::raw('SUM(COALESCE(total_tonase,0)) as sum_tonase'),
                DB::raw('MAX(kubikasi) as kubikasi'),
                DB::raw('MAX(tonase) as tonase')
            );

        foreach (self::SHIPMENT_FIELDS as $f) {
            $q->addSelect(DB::raw("MAX(NULLIF(TRIM({$f}),'')) as f_{$f}"));
        }

        return $this->agg = $q
            ->whereNotNull('no_shipment')
            ->where('no_shipment', '!=', '')
            ->groupBy('no_shipment')
            ->get()
            ->keyBy('no_shipment')
            ->map(function ($g) {
                $a = [
                    'sum_kubik'  => (float) $g->sum_kubik,
                    'sum_tonase' => (float) $g->sum_tonase,
                    'kubikasi'   => (float) $g->kubikasi,
                    'tonase'     => (float) $g->tonase,
                ];
                foreach (self::SHIPMENT_FIELDS as $f) {
                    $a['f_' . $f] = $g->{'f_' . $f};
                }
                return $a;
            })
            ->toArray();
    }

    /** nilai kolom baris ini; kalau kosong, ambil dari DO lain di shipment yang sama */
    private function shp($r, string $field)
    {
        $v = $r->{$field} ?? null;
        if ($v !== null && trim((string) $v) !== '') return $v;

        return $this->aggregates()[trim((string) $r->no_shipment)]['f_' . $field] ?? $v;
    }

    // ============================================================
    // HELPER DASAR
    // ============================================================
    /** tanggal saja -> serial Excel */
    private function xl($v)
    {
        if ($v === null || $v === '' || $v === false || $v === 'mm/dd/yyyy') return '';
        $ts = is_int($v) ? $v : strtotime((string) $v);
        if (!$ts || $ts <= 0) return '';
        return XlDate::PHPToExcel(new \DateTime(date('Y-m-d', $ts)));
    }

    /** tanggal + jam -> serial Excel */
    private function xlDt($v)
    {
        if ($v === null || $v === '' || $v === false || $v === 'mm/dd/yyyy') return '';
        $ts = is_int($v) ? $v : strtotime((string) $v);
        if (!$ts || $ts <= 0) return '';
        return XlDate::PHPToExcel(new \DateTime(date('Y-m-d H:i:s', $ts)));
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
    // CR / KUBIK / TONASE / OPTIMAL
    // ============================================================
    private function computeCR($r)
    {
        $muatan = (float) $r->nilai_muatan;
        $biaya  = (float) ($r->kirim_kontribusi ?? $r->biaya_kirim);
        return $muatan > 0 ? round(($biaya / $muatan) * 100, 4) : 0;
    }

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

    private function optimalCustomer($r): string
    {
        return $this->optimalLabel(
            $this->hasil($r->total_kubik, $r->kubikasi),
            $this->hasil($r->total_tonase, $r->tonase)
        );
    }

    private function optimalShipment($r): string
    {
        $a = $this->aggregates()[trim((string) $r->no_shipment)] ?? null;
        if (!$a) return $this->optimalCustomer($r);

        return $this->optimalLabel(
            $this->hasil($a['sum_kubik'], $a['kubikasi']),
            $this->hasil($a['sum_tonase'], $a['tonase'])
        );
    }

    // ============================================================
    // GUDANG
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
    // UPDATE POSISI MOBIL
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