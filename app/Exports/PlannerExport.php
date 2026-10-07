<?php

namespace App\Exports;

use App\Models\LogistikPengiriman;
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

class PlannerExport implements
    FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithStyles
{
    protected array $f;
    private ?array $cols = null;

    /**
     * $filters: planner, area, create_tgl, bulan, tahun
     * (bulan/tahun mengacu ke tanggal_naik_logistik, sama seperti applyFilter)
     */
    public function __construct(array $filters = [])
    {
        $this->f = $filters;
    }

    // ============================================================
    // QUERY (urutan sama dengan dataAjax)
    // ============================================================
    public function query()
    {
        $q = LogistikPengiriman::query();
        $f = $this->f;

        if (!empty($f['planner']))    $q->where('planner', $f['planner']);
        if (!empty($f['area']))       $q->where('area', $f['area']);
        if (!empty($f['create_tgl'])) $q->whereDate('create_tgl', $f['create_tgl']);
        if (!empty($f['bulan']))      $q->whereMonth('tanggal_naik_logistik', (int) $f['bulan']);
        if (!empty($f['tahun']))      $q->whereYear('tanggal_naik_logistik', (int) $f['tahun']);

        return $q->orderByRaw($this->selesaiGudangSql() . ' ASC')
                 ->orderByRaw('CAST(no_shipment AS UNSIGNED) ASC')
                 ->orderBy('id', 'ASC');
    }

    private function selesaiGudangSql(): string
    {
        $f = fn($c) => "NULLIF(TRIM({$c}), '') IS NOT NULL";
        $e = fn($c) => "NULLIF(TRIM({$c}), '') IS NULL";

        $cycles = [
            ['planning_loading',   'tanggal_tiba_gudang',   'tanggal_keluar_gudang'],
            ['planning_loading_2', 'tanggal_tiba_gudang_2', 'tanggal_keluar_gudang_2'],
            ['planning_loading_3', 'tanggal_tiba_gudang_3', 'tanggal_keluar_gudang_3'],
        ];

        $menggantung = [];
        foreach ($cycles as [$p, $t, $k]) {
            $menggantung[] = '((' . $f($p) . ' OR ' . $f($t) . ') AND ' . $e($k) . ')';
        }

        $adaKeluar = '(' . implode(' OR ', [
            $f('tanggal_keluar_gudang'),
            $f('tanggal_keluar_gudang_2'),
            $f('tanggal_keluar_gudang_3'),
        ]) . ')';

        return "CASE WHEN {$adaKeluar} AND NOT (" . implode(' OR ', $menggantung) . ") THEN 1 ELSE 0 END";
    }

    // ============================================================
    // DEFINISI KOLOM: [heading, tipe, closure($r)]
    // tipe: text | date | time | money | pct | pct4 | num
    // ============================================================
    private function columns(): array
    {
        if ($this->cols !== null) {
            return $this->cols;
        }

        $c = [];

        $c[] = ['Tanggal Import', 'text', fn($r) => $r->create_tgl ? date('d/m/Y H:i', strtotime($r->create_tgl)) : '-'];
        $c[] = ['Nama Planner', 'text', fn($r) => $r->planner];
        $c[] = ['No Shipment', 'text', fn($r) => (string) $r->no_shipment];

        $c[] = ['Tanggal Terima Dari Admin', 'date', fn($r) => $this->xl($r->tanggal_naik_logistik)];
        $c[] = ['Rencana Kirim', 'date', fn($r) => $this->xl($r->rencana_kirim)];
        $c[] = ['Tanggal Dapat Unit', 'date', fn($r) => $this->xl($r->tanggal_dpt_unit)];

        // ---- 3 gudang: input ----
        $gudang = [
            ''   => 'KACS',
            '_2' => 'Sentul',
            '_3' => 'CCIE',
        ];

        foreach ($gudang as $s => $nama) {
            $c[] = ["Planning Loading {$nama}", 'date', fn($r) => $this->xl($r->{'planning_loading' . ($s === '' ? '' : $s)})];
            $c[] = ["Tanggal Tiba {$nama}", 'date', fn($r) => $this->xl($r->{'tanggal_tiba_gudang' . $s})];
            $c[] = ["Jam Tiba {$nama}", 'time', fn($r) => $this->jam($r->{'waktu_tiba_gudang' . $s})];
            $c[] = ["Tanggal Keluar {$nama}", 'date', fn($r) => $this->xl($r->{'tanggal_keluar_gudang' . $s})];
            $c[] = ["Jam Keluar {$nama}", 'time', fn($r) => $this->jam($r->{'waktu_keluar_gudang' . $s})];
            $c[] = ["Reason Gudang {$nama}", 'text', fn($r) => $r->{'reason_gudang' . $s}];
        }

        // ---- info pengiriman (input) ----
        $c[] = ['Tujuan', 'text', fn($r) => $r->tujuan];
        $c[] = ['Route', 'text', fn($r) => $r->route];
        $c[] = ['Pulau', 'text', fn($r) => $r->pulau];
        $c[] = ['Area', 'text', fn($r) => $r->area];
        $c[] = ['Via Kirim', 'text', fn($r) => $r->via_kirim];
        $c[] = ['Dist Channel', 'text', fn($r) => $r->dist_channel];
        $c[] = ['Kategori Ekspedisi', 'text', fn($r) => $r->kategori_ekspedisi];
        $c[] = ['Ekspedisi', 'text', fn($r) => $r->ekpedisi];
        $c[] = ['Lead Time', 'text', fn($r) => $r->transport_lead_time];
        $c[] = ['Nama Driver', 'text', fn($r) => $r->nama_driver];
        $c[] = ['No Pol', 'text', fn($r) => $r->no_pol];
        $c[] = ['Mobil', 'text', fn($r) => $r->mobil];
        $c[] = ['Total Qty', 'num', fn($r) => $this->num($r->total_do_qty_car)];
        $c[] = ['Nilai Muatan', 'money', fn($r) => $this->num($r->nilai_muatan)];
        $c[] = ['Biaya Kirim', 'money', fn($r) => $this->num($r->biaya_kirim)];
        $c[] = ['CR (%)', 'pct4', fn($r) => $this->num($r->cr)];

        // ---- kapasitas & optimal ----
        $c[] = ['Kubikasi (%)', 'pct', fn($r) => $this->num($r->kubikasi)];
        $c[] = ['Tonase (%)', 'pct', fn($r) => $this->num($r->tonase)];
        $c[] = ['Total Kubik', 'num', fn($r) => $this->num($r->total_kubik)];
        $c[] = ['Total Tonase', 'num', fn($r) => $this->num($r->total_tonase)];
        $c[] = ['Hasil Kubik (%)', 'pct', fn($r) => $this->num($r->hasil_kubik)];
        $c[] = ['Hasil Tonase (%)', 'pct', fn($r) => $this->num($r->hasil_tonase)];
        $c[] = ['Pengiriman Optimal', 'text', fn($r) => $r->pengiriman_optimal === 'OPTIMAL'
            ? 'Optimal'
            : ($r->pengiriman_optimal ? 'Tidak Optimal' : '-')];
        $c[] = ['Reason Optimal', 'text', fn($r) => $r->reason_optimal];

        // ---- armada ----
        $c[] = ['Status Mobil', 'text', fn($r) => !empty($r->tanggal_dpt_unit) ? 'SUDAH DAPAT' : 'BELUM DAPAT'];
        $c[] = ['Lama Waktu Pencarian', 'text', fn($r) => $r->lama_waktu_pencarian];
        $c[] = ['SLA Dapat Mobil', 'text', fn($r) => $this->slaDapatMobil($r)];

        // ---- hasil rumus per gudang ----
        foreach ($gudang as $s => $nama) {
            $c[] = ["Lama Di {$nama}", 'text', fn($r) => ($i = $this->infoGudang($r, $s)) ? $i['text'] : '-'];
            $c[] = ["Status {$nama}", 'text', fn($r) => ($i = $this->infoGudang($r, $s)) ? ($i['delay'] ? 'Delay' : 'On Time') : '-'];
            $c[] = ["SLA Loading {$nama}", 'text', fn($r) => ($i = $this->infoGudang($r, $s))
                ? ($i['delay'] ? 'H+' . $i['hari'] : 'Sesuai SLA')
                : '-'];
        }

        $c[] = ['Shipping Point', 'text', fn($r) => $r->route ? explode('-', trim($r->route))[0] : '-'];
        $c[] = ['Kelengkapan Data', 'text', fn($r) => $this->kelengkapan($r)];

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
                case 'time':  $formats[$letter] = NumberFormat::FORMAT_TEXT; break;
            }

            if ($head === 'No Shipment' || $head === 'No Pol') {
                $formats[$letter] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('D2'); // header + 3 kolom pertama tetap terlihat
        return [1 => ['font' => ['bold' => true]]];
    }

    // ============================================================
    // HELPER
    // ============================================================
    private function xl($v)
    {
        if ($v === null || $v === '' || $v === false) return '';
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

    private function cleanTime($value): ?string
    {
        $value = trim((string) $value);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value) ? $value : null;
    }

    // salinan logic PlannerController::hitungGudang (delay jika > 24 jam)
    private function hitungGudang($tglTiba, $wTiba, $tglKeluar, $wKeluar): ?array
    {
        if (empty($tglTiba) || empty($tglKeluar)) return null;

        $awal  = strtotime(date('Y-m-d', strtotime($tglTiba))   . ' ' . ($this->cleanTime($wTiba)   ?: '00:00:00'));
        $akhir = strtotime(date('Y-m-d', strtotime($tglKeluar)) . ' ' . ($this->cleanTime($wKeluar) ?: '00:00:00'));

        $menit = max(0, intdiv($akhir - $awal, 60));
        $hari  = intdiv($menit, 1440);
        $jam   = intdiv($menit % 1440, 60);
        $mnt   = $menit % 60;

        $text = trim(($hari ? "{$hari} Hari " : '') . ($jam ? "{$jam} Jam " : '') . "{$mnt} Menit");

        return ['text' => $text, 'delay' => $menit > 1440, 'hari' => $hari];
    }

    private function infoGudang($r, string $s): ?array
    {
        return $this->hitungGudang(
            $r->{'tanggal_tiba_gudang' . $s},   $r->{'waktu_tiba_gudang' . $s},
            $r->{'tanggal_keluar_gudang' . $s}, $r->{'waktu_keluar_gudang' . $s}
        );
    }

    // salinan logic SLA Dapat Mobil di renderRowColumns
    private function slaDapatMobil($r): string
    {
        if (!$r->rencana_kirim || !$r->tanggal_dpt_unit) return '-';

        $area    = strtoupper(trim($r->area ?? ''));
        $rencana = strtotime(date('Y-m-d', strtotime($r->rencana_kirim)));
        $dptUnit = strtotime(date('Y-m-d', strtotime($r->tanggal_dpt_unit)));
        $selisih = floor(($dptUnit - $rencana) / 86400);

        if (in_array($area, ['JABODEBEK', 'JABODETABEK', 'BANTEN'])) {
            $batas = 0;
        } elseif (in_array($area, ['JAWA_BARAT', 'JAWA BARAT'])) {
            $batas = 1;
        } else {
            $batas = 2;
        }

        return $selisih > $batas ? 'H+' . ($selisih - $batas) : 'Sesuai SLA';
    }

    // 5 field wajib, sama dengan render
    private function kelengkapan($r): string
    {
        $required = [
            'mobil' => 'Mobil', 'ekpedisi' => 'Ekspedisi', 'route' => 'Route',
            'nama_driver' => 'Nama Driver', 'no_pol' => 'No Pol',
        ];

        $missing = [];
        foreach ($required as $col => $label) {
            if (trim((string) ($r->$col ?? '')) === '') $missing[] = $label;
        }

        return $missing ? 'Belum lengkap: ' . implode(', ', $missing) : 'Lengkap';
    }
}