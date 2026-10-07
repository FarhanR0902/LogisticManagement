<?php

namespace App\Exports;

use App\Models\LogistikPengiriman;
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

class LogistikExport implements
    FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting
{
    protected array $f = [];
    protected ?array $ids = null;
    private ?array $cols = null;
    private ?array $agg = null;

    /**
     * $arg: array filter (dist_channel, date, month, year, pic_monitoring, area)
     *       ATAU collection hasil query lama (diambil id-nya saja).
     */
    public function __construct($arg = null)
    {
        if (is_array($arg)) {
            $this->f = $arg;
        } elseif ($arg instanceof \Illuminate\Support\Collection) {
            $this->ids = $arg->pluck('id')->all();
        }
    }

    // ============================================================
    // QUERY (filter sama dengan dataLogistikAjax)
    // ============================================================
    public function query()
    {
        $q = LogistikPengiriman::query();
        $f = $this->f;

        if ($this->ids !== null) {
            $q->whereIn('id', $this->ids);
        }
        if (!empty($f['dist_channel'])) {
            $q->whereRaw('LOWER(TRIM(dist_channel)) = ?', [strtolower(trim($f['dist_channel']))]);
        }
        if (!empty($f['date']))           $q->whereDate('rencana_kirim', $f['date']);
        if (!empty($f['month']))          $q->whereMonth('tanggal_naik_logistik', (int) $f['month']);
        if (!empty($f['year']))           $q->whereYear('tanggal_naik_logistik', (int) $f['year']);
        if (!empty($f['pic_monitoring'])) $q->where('pic_monitoring', $f['pic_monitoring']);
        if (!empty($f['area']))           $q->where('area', $f['area']);

        return $q->orderBy('tanggal_naik_logistik', 'DESC')->orderBy('id', 'DESC');
    }

    // ============================================================
    // DEFINISI KOLOM [heading, tipe, closure]
    // tipe: text | date | time | money | pct | pct4 | num
    // urutan = urutan di dataLogistikAjax()
    // ============================================================
    private function columns(): array
    {
        if ($this->cols !== null) {
            return $this->cols;
        }

        $c = [];

        $c[] = ['Tanggal Naik Logistik', 'date', fn($r) => $this->xl($r->tanggal_naik_logistik)];
        $c[] = ['Rencana Kirim', 'date', fn($r) => $this->xl($r->rencana_kirim)];
        $c[] = ['Transport Lead Time', 'text', fn($r) => $r->transport_lead_time];
        $c[] = ['Nama Driver', 'text', fn($r) => $r->nama_driver];
        $c[] = ['No Pol', 'text', fn($r) => $r->no_pol];
        $c[] = ['Planner', 'text', fn($r) => $r->planner];
        $c[] = ['No Shipment', 'text', fn($r) => (string) $r->no_shipment];
        $c[] = ['Status Perjalanan', 'text', fn($r) => $this->statusPengiriman($r)];
        $c[] = ['Distribution Channel', 'text', fn($r) => $r->dist_channel];
        $c[] = ['Tujuan', 'text', fn($r) => $r->tujuan];
        $c[] = ['Area', 'text', fn($r) => $r->area];
        $c[] = ['Status Mobil', 'text', fn($r) => (empty($r->rencana_kirim) || empty($r->tanggal_dpt_unit)) ? 'BELUM DAPAT' : 'SUDAH DAPAT'];
        $c[] = ['Mobil', 'text', fn($r) => $r->mobil];
        $c[] = ['Total DO Qty Car', 'num', fn($r) => $this->num($r->total_do_qty_car)];
        $c[] = ['Nilai Muatan', 'money', fn($r) => $this->num($r->nilai_muatan)];
        $c[] = ['Biaya Kirim', 'money', fn($r) => $this->num($r->biaya_kirim)];
        $c[] = ['CR (%)', 'pct4', fn($r) => $this->computeCR($r)];
        $c[] = ['Kubikasi (%)', 'pct', fn($r) => $this->num($r->kubikasi)];
        $c[] = ['Tonase', 'num', fn($r) => $this->num($r->tonase)];
        $c[] = ['Total Kubik', 'num', fn($r) => $this->num($r->total_kubik)];
        $c[] = ['Total Tonase', 'num', fn($r) => $this->num($r->total_tonase)];
        $c[] = ['Hasil Kubik (%)', 'pct', fn($r) => $this->hasil($r->total_kubik, $r->kubikasi)];
        $c[] = ['Hasil Tonase (%)', 'pct', fn($r) => $this->hasil($r->total_tonase, $r->tonase)];
        $c[] = ['Pengiriman Optimal', 'text', fn($r) => $this->optimal($r)];
        $c[] = ['Reason Optimal', 'text', fn($r) => $r->reason_optimal];
        $c[] = ['Kategori Ekspedisi', 'text', fn($r) => $r->kategori_ekspedisi ?: '-'];
        $c[] = ['Ekspedisi', 'text', fn($r) => $r->ekpedisi];
        $c[] = ['Tanggal Dpt Unit', 'date', fn($r) => $this->xl($r->tanggal_dpt_unit)];
        $c[] = ['Lama Waktu Pencarian', 'text', fn($r) => $r->lama_waktu_pencarian ?? '-'];
        $c[] = ['SLA Dapat Mobil', 'text', fn($r) => $r->sla_dapat_mobil ?: '-'];

        // ---- 3 gudang ----
        foreach (['' => 'KACS', '_2' => 'Sentul', '_3' => 'CCIE'] as $s => $nama) {
            $c[] = ["Planning Loading {$nama}", 'date', fn($r) => $this->xl($r->{'planning_loading' . $s})];
            $c[] = ["Tanggal Tiba {$nama}", 'date', fn($r) => $this->xl($r->{'tanggal_tiba_gudang' . $s})];
            $c[] = ["Jam Tiba {$nama}", 'time', fn($r) => $this->jam($r->{'waktu_tiba_gudang' . $s})];
            $c[] = ["Tanggal Keluar {$nama}", 'date', fn($r) => $this->xl($r->{'tanggal_keluar_gudang' . $s})];
            $c[] = ["Jam Keluar {$nama}", 'time', fn($r) => $this->jam($r->{'waktu_keluar_gudang' . $s})];
            $c[] = ["Reason Gudang {$nama}", 'text', fn($r) => $r->{'reason_gudang' . $s} ?: '-'];
            $c[] = ["Durasi di {$nama}", 'text', fn($r) => $this->durasiGudang($r->{'tanggal_tiba_gudang' . $s}, $r->{'tanggal_keluar_gudang' . $s})];
            $c[] = ["Status {$nama}", 'text', fn($r) => $this->statusGudang($r->{'tanggal_tiba_gudang' . $s}, $r->{'tanggal_keluar_gudang' . $s})];
            $c[] = ["SLA Loading {$nama}", 'text', fn($r) => $this->slaLoading($r->{'tanggal_tiba_gudang' . $s}, $r->{'tanggal_keluar_gudang' . $s})];
        }

        $c[] = ['PIC Monitoring', 'text', fn($r) => $r->pic_monitoring];
        $c[] = ['Nama Kapal', 'text', fn($r) => $r->nama_kapal];
        $c[] = ['ETD', 'date', fn($r) => $this->xl($r->etd)];
        $c[] = ['ETA', 'date', fn($r) => $this->xl($r->eta)];
        $c[] = ['Status Kendaraan', 'text', fn($r) => $r->status_kendaraan ?: '-'];
        $c[] = ['Alert', 'text', fn($r) => $this->estimasiAlert($r)['alert']];
        $c[] = ['Act Urutan Bongkar', 'text', fn($r) => $r->act_urutan_bongkar];
        $c[] = ['Qty Monitoring', 'num', fn($r) => $this->num($r->qty_monitoring)];
        $c[] = ['Biaya Kuli', 'money', fn($r) => $this->num($r->biaya_kuli)];
        $c[] = ['Total Biaya Kuli', 'money', fn($r) => $this->num($r->total_biaya_kuli)];
        $c[] = ['Selisih Qty', 'num', fn($r) => $this->num($r->selisih_qty)];
        $c[] = ['Remarks Qty', 'text', fn($r) => $r->remarks_qty];
        $c[] = ['Act PGI Date', 'date', fn($r) => $this->xl($r->act_pgi_date)];
        $c[] = ['ATD', 'date', fn($r) => $this->xl($r->atd)];
        $c[] = ['ATA', 'date', fn($r) => $this->xl($r->ata)];
        $c[] = ['Estimasi Tiba', 'date', fn($r) => $this->estimasiAlert($r)['estimasi']];
        $c[] = ['Tanggal Tiba', 'date', fn($r) => $this->xl($r->tanggal_tiba)];
        $c[] = ['Jam Tiba', 'time', fn($r) => $this->jam($r->waktu_tiba)];
        $c[] = ['Lama Perjalanan (Hari)', 'num', fn($r) => $this->lamaPerjalanan($r)];
        $c[] = ['SLA Tiba', 'text', fn($r) => $r->sla_tiba ?: '-'];
        $c[] = ['Tanggal Bongkar', 'date', fn($r) => $this->xl($r->tanggal_bongkar)];
        $c[] = ['Jam Bongkar', 'time', fn($r) => $this->jam($r->waktu_bongkar)];
        $c[] = ['Status Bongkar', 'text', fn($r) => $this->statusBongkar($r)];
        $c[] = ['Overstay (Hari)', 'num', fn($r) => $this->num($r->overstay_days)];
        $c[] = ['SLA Bongkar', 'text', fn($r) => $r->sla_bongkar ?: '-'];
        $c[] = ['Reason Tiba', 'text', fn($r) => $r->reason_tiba];
        $c[] = ['Reason Bongkar', 'text', fn($r) => $r->reason_bongkar];
        $c[] = ['Status Pengiriman', 'text', fn($r) => $this->statusAkhir($r)];
        $c[] = ['Status Delivered', 'text', fn($r) => $this->statusAlert($r)];
        $c[] = ['Remarks', 'text', fn($r) => $r->remarks];
        $c[] = ['Route', 'text', fn($r) => $r->route];
        $c[] = ['Asal (Route)', 'text', fn($r) => $r->route ? explode('-', trim($r->route))[0] : '-'];
        $c[] = ['Pulau', 'text', fn($r) => $r->pulau];
        $c[] = ['Via Kirim', 'text', fn($r) => $r->via_kirim];
        $c[] = ['Estimasi Admin', 'date', fn($r) => $this->estimasiAdminXl($r)];
        $c[] = ['Status Estimasi Admin', 'text', fn($r) => $this->statusEstimasiAdmin($r)];
        $c[] = ['Create On', 'date', fn($r) => $this->xl($r->create_on)];

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

            if (in_array($head, ['No Shipment', 'No Pol', 'Jam Tiba', 'Jam Bongkar'], true)
                || str_starts_with($head, 'Jam ')) {
                $formats[$letter] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
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
    // CR (agregat per no_shipment, sama dengan controller)
    // ============================================================
    private function aggregates(): array
    {
        if ($this->agg !== null) return $this->agg;

        return $this->agg = DB::table('logistik_pengiriman')
            ->select(
                'no_shipment',
                DB::raw('SUM(nilai_muatan) as total_muatan'),
                DB::raw('MAX(biaya_kirim) as total_biaya')
            )
            ->whereNotNull('no_shipment')
            ->where('no_shipment', '!=', '')
            ->groupBy('no_shipment')
            ->get()
            ->keyBy('no_shipment')
            ->map(fn($g) => [
                'total_muatan' => (float) $g->total_muatan,
                'total_biaya'  => (float) $g->total_biaya,
            ])
            ->toArray();
    }

    private function computeCR($r)
    {
        $agg = $this->aggregates();
        $no  = trim((string) $r->no_shipment);

        if ($no === '' || !isset($agg[$no])) {
            $muatan = (float) $r->nilai_muatan;
            $biaya  = (float) $r->biaya_kirim;
            return $muatan > 0 ? round(($biaya / $muatan) * 100, 4) : 0;
        }

        $totalMuatan = $agg[$no]['total_muatan'];
        $totalBiaya  = $agg[$no]['total_biaya'];
        $nilaiBaris  = (float) $r->nilai_muatan;

        if ($totalMuatan <= 0 || $nilaiBaris <= 0) return 0;

        return round(($nilaiBaris / $totalMuatan) * (($totalBiaya / $totalMuatan) * 100), 4);
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

    private function optimal($r): string
    {
        $k = $this->hasil($r->total_kubik, $r->kubikasi);
        $t = $this->hasil($r->total_tonase, $r->tonase);

        if ($k === '' && $t === '') return '-';

        $ok = ($k !== '' && $k >= 85) || ($t !== '' && $t >= 85);
        return $ok ? 'Optimal' : 'Tidak Optimal';
    }

    // ============================================================
    // GUDANG
    // ============================================================
    private function durasiGudang($planning, $tiba): string
    {
        $a = $this->ts($planning);
        $b = $this->ts($tiba);
        if (!$a || !$b) return '-';

        $menit = max(0, intdiv($b - $a, 60));
        $desimal = $menit / 1440;
        $hari = floor($desimal);
        $jam  = round(($desimal - $hari) * 24);

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
    // STATUS PENGIRIMAN (perjalanan)
    // ============================================================
    private function statusPengiriman($r): string
    {
        $gudang = collect([
            ['nama' => 'KACS',   'planning' => $r->planning_loading,   'tiba' => $r->tanggal_tiba_gudang,   'keluar' => $r->tanggal_keluar_gudang],
            ['nama' => 'SENTUL', 'planning' => $r->planning_loading_2, 'tiba' => $r->tanggal_tiba_gudang_2, 'keluar' => $r->tanggal_keluar_gudang_2],
            ['nama' => 'CCIE',   'planning' => $r->planning_loading_3, 'tiba' => $r->tanggal_tiba_gudang_3, 'keluar' => $r->tanggal_keluar_gudang_3],
        ])
            ->filter(fn($g) => !empty($g['planning']))
            ->sortBy(fn($g) => strtotime($g['planning']))
            ->values();

        $statusGudang = null;
        foreach ($gudang as $g) {
            if (empty($g['tiba'])) { $statusGudang = 'PERJALANAN KE ' . $g['nama']; break; }
            if (empty($g['keluar'])) { $statusGudang = 'DI GUDANG ' . $g['nama']; break; }
        }

        if (empty($r->tanggal_dpt_unit)) return 'MENCARI UNIT';
        if ($gudang->count() === 0 && empty($r->tanggal_tiba)) return 'PERJALANAN KE GUDANG';
        if ($statusGudang) return $statusGudang;
        if (empty($r->tanggal_tiba)) return 'PERJALANAN KE TUJUAN';
        if (!empty($r->tanggal_bongkar)) return 'SUDAH SELESAI';
        return 'SUDAH TIBA TUJUAN';
    }

    private function gudangAktif($r): ?string
    {
        $gudang = collect([
            ['nama' => 'KACS',   'planning' => $r->planning_loading,   'tiba' => $r->tanggal_tiba_gudang],
            ['nama' => 'SENTUL', 'planning' => $r->planning_loading_2, 'tiba' => $r->tanggal_tiba_gudang_2],
            ['nama' => 'CCIE',   'planning' => $r->planning_loading_3, 'tiba' => $r->tanggal_tiba_gudang_3],
        ])
            ->filter(fn($g) => !empty($g['planning']))
            ->sortBy(fn($g) => strtotime($g['planning']))
            ->values();

        foreach ($gudang as $g) {
            if (empty($g['tiba'])) return 'PERJALANAN KE ' . $g['nama'];
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

        $est = $this->ts($r->estimasi_tiba);
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
    // STATUS AKHIR & ALERT DELIVERED
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