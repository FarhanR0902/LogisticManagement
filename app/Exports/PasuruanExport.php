<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
 * EXPORT FULL data logistik_pengiriman_pasuruan
 *  - BAGIAN 1: SEMUA kolom tabel (urutan sama dengan database).
 *              Kolom baru yang belum terdaftar di RAW otomatis ikut (sebagai teks).
 *  - BAGIAN 2: kolom hasil hitungan otomatis (status, SLA, alert, CR, optimal, dst.)
 *  - 1 baris Excel = 1 DO
 *
 * Pemakaian:
 *   return Excel::download(new PasuruanExportFull($query), 'data_pasuruan_full.xlsx');
 *   ($query = query builder yang sudah difilter, JANGAN di-get())
 */
class PasuruanExportFull implements
    FromQuery, WithHeadings, WithMapping, WithColumnFormatting, WithStyles
{
    private const TABLE = 'logistik_pengiriman_pasuruan';

    protected $builder;
    private ?array $cols = null;
    private int $rawCount = 0;
    private ?array $shipAgg = null;
    private ?array $custAgg = null;

    /** kolom level shipment: kalau DO ini kosong, ambil dari DO lain di shipment yang sama */
    private const SHIPMENT_FIELDS = [
        'nama_kapal_pasuruan', 'etd_pasuruan', 'eta_pasuruan',
        'atd_pasuruan', 'ata_pasuruan', 'act_pgi_date_pasuruan',
    ];

    /**
     * Semua kolom tabel, urut sesuai database.
     * tipe: text | ftext (paksa teks) | num | numt (angka kalau numerik, else teks)
     *       money | pct4 | dt (tanggal+jam) | date
     */
    private const RAW = [
        ['id', 'num'],
        ['tanggal_terima_po_pasuruan', 'dt'],
        ['rencana_kirim_pasuruan', 'dt'],
        ['transport_lead_time_pasuruan', 'num'],
        ['estimasi_tiba_pasuruan', 'date'],
        ['planner_pasuruan', 'text'],
        ['no_shipment_pasuruan', 'ftext'],
        ['tujuan_pasuruan', 'text'],
        ['route_pasuruan', 'text'],
        ['via_kirim_pasuruan', 'text'],
        ['dist_channel_pasuruan', 'text'],
        ['ekpedisi_pasuruan', 'text'],
        ['area_pasuruan', 'text'],
        ['pulau_pasuruan', 'text'],
        ['ketersediaan_unit_pasuruan', 'text'],
        ['mobil_pasuruan', 'text'],
        ['perubahan_mobil_pasuruan', 'text'],
        ['kategori_pengiriman_pasuruan', 'text'],
        ['nilai_muatan_pasuruan', 'money'],
        ['kubikasi_pasuruan', 'numt'],
        ['biaya_kirim_pasuruan', 'money'],
        ['cr_pasuruan', 'pct4'],
        ['kategori_ekspedisi_pasuruan', 'text'],
        ['ekspedisi_pasuruan', 'text'],
        ['total_do_pasuruan', 'num'],
        ['no_pol_pasuruan', 'ftext'],
        ['nama_driver_pasuruan', 'text'],
        ['tanggal_dpt_unit_pasuruan', 'dt'],
        ['planning_loading_pasuruan', 'dt'],
        ['tanggal_tiba_gudang_pasuruan', 'dt'],
        ['tanggal_keluar_gudang_pasuruan', 'dt'],
        ['lama_digudang_pasuruan', 'num'],
        ['sla_ketepatan_loading_pasuruan', 'text'],
        ['keterangan_loading_pasuruan', 'text'],
        ['keterangan_loading2_pasuruan', 'text'],
        ['lama_waktu_pencarian_pasuruan', 'num'],
        ['sla_dapat_mobil_pasuruan', 'text'],
        ['sla_ketibaan_gudang_muat_pasuruan', 'text'],
        ['keterangan_ketibaan_gudang_pasuruan', 'text'],
        ['pic_monitoring_pasuruan', 'text'],
        ['status_kendaraan_pasuruan', 'text'],
        ['monitoring_alert_pasuruan', 'text'],
        ['action_required_pasuruan', 'text'],
        ['tanggal_tiba_pasuruan', 'dt'],
        ['lama_perjalanan_pasuruan', 'num'],
        ['sla_tiba_pasuruan', 'text'],
        ['keterangan_waktu_tiba_pasuruan', 'text'],
        ['tanggal_bongkar_pasuruan', 'dt'],
        ['overstay_days_pasuruan', 'num'],
        ['sla_bongkar_pasuruan', 'text'],
        ['status_akhir_pasuruan', 'text'],
        ['keterangan_waktu_bongkar_pasuruan', 'text'],
        ['reason_waktu_tiba_pasuruan', 'text'],
        ['reason_waktu_bongkar_pasuruan', 'text'],
        ['remarks_pasuruan', 'text'],
        ['keterangan_monitoring_pasuruan', 'text'],
        ['created_at', 'dt'],
        ['updated_at', 'dt'],
        ['nama_kapal_pasuruan', 'text'],
        ['etd_pasuruan', 'date'],
        ['eta_pasuruan', 'date'],
        ['alert_pasuruan', 'text'],
        ['act_urutan_bongkar_pasuruan', 'num'],
        ['actual_delivery_quantity_pasuruan', 'num'],
        ['selisih_quantity_pasuruan', 'num'],
        ['reason_selisih_quantity_pasuruan', 'text'],
        ['act_pgi_date_pasuruan', 'date'],
        ['atd_pasuruan', 'dt'],
        ['ata_pasuruan', 'dt'],
        ['tanggal_estimasi_pasuruan', 'date'],
        ['transport_laut_pasuruan', 'text'],
        ['remarks_qty_pasuruan', 'text'],
        ['selisih_qty_pasuruan', 'num'],
        ['shipping_point_pasuruan', 'text'],
        ['qty_monitoring_pasuruan', 'num'],
        ['biaya_kuli_pasuruan', 'money'],
        ['total_biaya_kuli_pasuruan', 'money'],
        ['create_tgl_pasuruan', 'dt'],
        ['Div', 'text'],
        ['tonase_pasuruan', 'numt'],
        ['total_kubik_pasuruan', 'numt'],
        ['total_tonase_pasuruan', 'numt'],
        ['hasil_kubik_pasuruan', 'numt'],
        ['hasil_tonase_pasuruan', 'numt'],
        ['pengiriman_optimal_pasuruan', 'text'],
        ['reason_optimal_pasuruan', 'text'],
        ['reason_pengiriman_optimal_pasuruan', 'text'],
        ['pengiriman_optimal_customer_pasuruan', 'text'],
        ['reason_pengiriman_optimal_customer_pasuruan', 'text'],
        ['customer_reference_pasuruan', 'ftext'],
        ['sales_document_pasuruan', 'ftext'],
        ['contract_end_date_pasuruan', 'date'],
        ['no_do_pasuruan', 'ftext'],
        ['no_delivery_pasuruan', 'ftext'],
        ['kirim_kontribusi_pasuruan', 'money'],
    ];

    public function __construct($builder)
    {
        $this->builder = $builder;
    }

    public function query()
    {
        return (clone $this->builder)
            ->orderBy('tanggal_terima_po_pasuruan', 'DESC')
            ->orderBy('no_shipment_pasuruan')
            ->orderBy('tujuan_pasuruan')
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
        $raw = self::RAW;

        // kolom baru di database yang belum terdaftar di RAW -> ikut otomatis sebagai teks
        $known = array_column($raw, 0);
        foreach (array_diff(Schema::getColumnListing(self::TABLE), $known) as $extra) {
            $raw[] = [$extra, 'text'];
        }

        foreach ($raw as [$col, $type]) {
            $c[] = [$col, $type, fn($r) => $this->raw($r, $col, $type)];
        }
        $this->rawCount = count($raw);

        // ---------- BAGIAN 2: HASIL HITUNGAN OTOMATIS ----------
        $c[] = ['[Hitung] Update Posisi Mobil',   'text', fn($r) => $this->statusPosisiMobil($r)];
        $c[] = ['[Hitung] Ketersediaan Unit',     'text', fn($r) => empty($r->tanggal_dpt_unit_pasuruan) ? 'BELUM DAPAT' : 'SUDAH DAPAT'];
        $c[] = ['[Hitung] CR (%)',                'pct4', fn($r) => $this->computeCR($r)];
        $c[] = ['[Hitung] Hasil Kubik (%)',       'pct',  fn($r) => $this->hasilKubik($r)];
        $c[] = ['[Hitung] Hasil Tonase (%)',      'pct',  fn($r) => $this->hasilTonase($r)];
        $c[] = ['[Hitung] Pengiriman Optimal Shipment', 'text', fn($r) => $this->optimalShipment($r)];
        $c[] = ['[Hitung] Pengiriman Optimal Customer', 'text', fn($r) => $this->optimalCustomer($r)];
        $c[] = ['[Hitung] Lama Waktu Pencarian',  'text', fn($r) => $this->lamaPencarian($r)];
        $c[] = ['[Hitung] SLA Dapat Mobil',       'text', fn($r) => $this->slaDapatMobil($r)];
        $c[] = ['[Hitung] Durasi Gudang',         'text', fn($r) => $this->durasiGudang($r->planning_loading_pasuruan, $r->tanggal_tiba_gudang_pasuruan)];
        $c[] = ['[Hitung] Status Gudang',         'text', fn($r) => $this->statusGudang($r->planning_loading_pasuruan, $r->tanggal_tiba_gudang_pasuruan)];
        $c[] = ['[Hitung] SLA Loading',           'text', fn($r) => $this->slaLoading($r->planning_loading_pasuruan, $r->tanggal_tiba_gudang_pasuruan)];
        $c[] = ['[Hitung] Alert Estimasi Tiba',   'text', fn($r) => $this->alertEstimasi($r)];
        $c[] = ['[Hitung] Lama Perjalanan (Hari)','num',  fn($r) => $this->lamaPerjalanan($r)];
        $c[] = ['[Hitung] SLA Tiba',              'text', fn($r) => $this->slaTiba($r)];
        $c[] = ['[Hitung] Status Bongkar',        'text', fn($r) => $this->statusBongkar($r)];
        $c[] = ['[Hitung] Overstay (Hari)',       'num',  fn($r) => $this->overstay($r)];
        $c[] = ['[Hitung] SLA Bongkar',           'text', fn($r) => $this->slaBongkar($r)];
        $c[] = ['[Hitung] Status Akhir',          'text', fn($r) => $this->statusAkhir($r)];
        $c[] = ['[Hitung] Status Alert',          'text', fn($r) => $this->statusAlert($r)];
        $c[] = ['[Hitung] Selisih Qty (Total DO - Actual)', 'num', fn($r) => $this->selisihQty($r)];
        $c[] = ['[Hitung] Shipping Point (Route)','text', fn($r) => $r->route_pasuruan ? explode('-', trim($r->route_pasuruan))[0] : '-'];
        $c[] = ['[Hitung] Estimasi Tiba Di Customer', 'date', fn($r) => $this->estimasiAdminXl($r)];
        $c[] = ['[Hitung] Ontime/Delay Admin',    'text', fn($r) => $this->statusEstimasiAdmin($r)];

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
                case 'ftext': $formats[$letter] = NumberFormat::FORMAT_TEXT; break;
            }
        }

        return $formats;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');
        $sheet->getDefaultColumnDimension()->setWidth(20);

        $total = count($this->columns());

        // bagian 1 (kolom database) = hijau, bagian 2 (hitungan) = biru
        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex($this->rawCount) . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '03C03C']],
        ]);
        $sheet->getStyle(
            Coordinate::stringFromColumnIndex($this->rawCount + 1) . '1:' .
            Coordinate::stringFromColumnIndex($total) . '1'
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
            case 'num':
            case 'money':
            case 'pct4':  return $this->num($v);
            case 'numt':  return ($v === null || $v === '') ? '' : (is_numeric($v) ? (float) $v : (string) $v);
            default:      return $v === null ? '' : (string) $v;
        }
    }

    // ============================================================
    // AGREGAT: fallback level shipment + optimal per shipment / per customer
    // ============================================================
    private function n(string $c): string
    {
        return "COALESCE(CAST(NULLIF(TRIM({$c}),'') AS DECIMAL(18,4)),0)";
    }

    private function shipmentAgg(): array
    {
        if ($this->shipAgg !== null) return $this->shipAgg;

        $q = DB::table(self::TABLE)->select(
            'no_shipment_pasuruan',
            DB::raw('SUM(' . $this->n('total_kubik_pasuruan') . ') as sum_kubik'),
            DB::raw('SUM(' . $this->n('total_tonase_pasuruan') . ') as sum_tonase'),
            DB::raw('MAX(' . $this->n('kubikasi_pasuruan') . ') as kubikasi'),
            DB::raw('MAX(' . $this->n('tonase_pasuruan') . ') as tonase')
        );

        foreach (self::SHIPMENT_FIELDS as $f) {
            $q->addSelect(DB::raw("MAX(NULLIF(TRIM({$f}),'')) as f_{$f}"));
        }

        return $this->shipAgg = $q
            ->whereNotNull('no_shipment_pasuruan')
            ->where('no_shipment_pasuruan', '!=', '')
            ->groupBy('no_shipment_pasuruan')
            ->get()
            ->keyBy('no_shipment_pasuruan')
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

    /** agregat per shipment + tujuan (= "customer") */
    private function customerAgg(): array
    {
        if ($this->custAgg !== null) return $this->custAgg;

        return $this->custAgg = DB::table(self::TABLE)
            ->select(
                'no_shipment_pasuruan',
                DB::raw("COALESCE(tujuan_pasuruan,'') as tj"),
                DB::raw('SUM(' . $this->n('total_kubik_pasuruan') . ') as sum_kubik'),
                DB::raw('SUM(' . $this->n('total_tonase_pasuruan') . ') as sum_tonase'),
                DB::raw('MAX(' . $this->n('kubikasi_pasuruan') . ') as kubikasi'),
                DB::raw('MAX(' . $this->n('tonase_pasuruan') . ') as tonase')
            )
            ->whereNotNull('no_shipment_pasuruan')
            ->where('no_shipment_pasuruan', '!=', '')
            ->groupBy('no_shipment_pasuruan')
            ->groupByRaw("COALESCE(tujuan_pasuruan,'')")
            ->get()
            ->keyBy(fn($g) => $g->no_shipment_pasuruan . '|' . $g->tj)
            ->map(fn($g) => [
                'sum_kubik'  => (float) $g->sum_kubik,
                'sum_tonase' => (float) $g->sum_tonase,
                'kubikasi'   => (float) $g->kubikasi,
                'tonase'     => (float) $g->tonase,
            ])
            ->toArray();
    }

    /** nilai kolom baris ini; kalau kosong, ambil dari DO lain di shipment yang sama */
    private function shp($r, string $field)
    {
        $v = $r->{$field} ?? null;
        if ($v !== null && trim((string) $v) !== '') return $v;

        return $this->shipmentAgg()[trim((string) $r->no_shipment_pasuruan)]['f_' . $field] ?? $v;
    }

    /** agregat customer; kalau baris tanpa shipment, pakai nilai baris itu sendiri */
    private function custOf($r): array
    {
        $key = trim((string) $r->no_shipment_pasuruan) . '|' . ($r->tujuan_pasuruan ?? '');
        $a   = $this->customerAgg()[$key] ?? null;
        if ($a) return $a;

        return [
            'sum_kubik'  => (float) $r->total_kubik_pasuruan,
            'sum_tonase' => (float) $r->total_tonase_pasuruan,
            'kubikasi'   => (float) $r->kubikasi_pasuruan,
            'tonase'     => (float) $r->tonase_pasuruan,
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

    private function xlDt($v)
    {
        if ($v === null || $v === '' || $v === false || $v === 'mm/dd/yyyy') return '';
        $ts = is_int($v) ? $v : strtotime((string) $v);
        if (!$ts || $ts <= 0) return '';
        return XlDate::PHPToExcel(new \DateTime(date('Y-m-d H:i:s', $ts)));
    }

    private function num($v)
    {
        return ($v === null || $v === '' || !is_numeric($v)) ? '' : (float) $v;
    }

    private function ts($v): ?int
    {
        if (empty($v)) return null;
        $t = strtotime((string) $v);
        return ($t && $t > 0) ? $t : null;
    }

    private function dayStart($v): ?int
    {
        $t = $this->ts($v);
        return $t ? strtotime(date('Y-m-d', $t)) : null;
    }

    // ============================================================
    // CR / KUBIK / TONASE / OPTIMAL
    // ============================================================
    /** kontribusi biaya kirim / nilai muatan (sama dengan logika grup di dashboard) */
    private function computeCR($r)
    {
        $muatan = (float) $r->nilai_muatan_pasuruan;
        $kontr  = (float) $r->kirim_kontribusi_pasuruan;
        $biaya  = $kontr > 0 ? $kontr : (float) $r->biaya_kirim_pasuruan;
        return $muatan > 0 ? round(($biaya / $muatan) * 100, 4) : 0;
    }

    private function pct($total, $kap)
    {
        return $kap > 0 ? round(($total / $kap) * 100, 2) : '';
    }

    /** hasil per shipment + tujuan, sama dengan kolom hasil_kubik_pasuruan di database */
    private function hasilKubik($r)
    {
        $a = $this->custOf($r);
        return $this->pct($a['sum_kubik'], $a['kubikasi']);
    }

    private function hasilTonase($r)
    {
        $a = $this->custOf($r);
        return $this->pct($a['sum_tonase'], $a['tonase']);
    }

    private function optimalLabel($k, $t): string
    {
        if ($k === '' && $t === '') return '-';
        $ok = ($k !== '' && $k >= 85) || ($t !== '' && $t >= 85);
        return $ok ? 'Optimal' : 'Tidak Optimal';
    }

    private function optimalCustomer($r): string
    {
        $a = $this->custOf($r);
        return $this->optimalLabel(
            $this->pct($a['sum_kubik'], $a['kubikasi']),
            $this->pct($a['sum_tonase'], $a['tonase'])
        );
    }

    private function optimalShipment($r): string
    {
        $a = $this->shipmentAgg()[trim((string) $r->no_shipment_pasuruan)] ?? null;
        if (!$a) return $this->optimalCustomer($r);

        return $this->optimalLabel(
            $this->pct($a['sum_kubik'], $a['kubikasi']),
            $this->pct($a['sum_tonase'], $a['tonase'])
        );
    }

    // ============================================================
    // POSISI MOBIL
    // ============================================================
    private function statusPosisiMobil($r): string
    {
        if (empty($r->tanggal_dpt_unit_pasuruan)) return 'MENCARI UNIT';
        if (empty($r->tanggal_tiba_gudang_pasuruan)) return 'PERJALANAN KE GUDANG';
        if (empty($r->tanggal_keluar_gudang_pasuruan)) return 'DI GUDANG';
        if (empty($r->tanggal_tiba_pasuruan)) return 'PERJALANAN KE TUJUAN';
        if (empty($r->tanggal_bongkar_pasuruan)) return 'TIBA DI TUJUAN';
        return 'SUDAH SELESAI';
    }

    // ============================================================
    // SLA DAPAT MOBIL
    // ============================================================
    private function selisihDapatMobil($r): ?int
    {
        $a = $this->dayStart($r->rencana_kirim_pasuruan);
        $b = $this->dayStart($r->tanggal_dpt_unit_pasuruan);
        if (!$a || !$b) return null;
        return (int) floor(($b - $a) / 86400);
    }

    private function lamaPencarian($r): string
    {
        $s = $this->selisihDapatMobil($r);
        if ($s === null) return '-';
        return $s <= 0 ? 'H+0' : 'H+' . $s;
    }

    private function slaDapatMobil($r): string
    {
        $s = $this->selisihDapatMobil($r);
        if ($s === null) return '-';

        $area  = strtoupper(trim($r->area_pasuruan ?? ''));
        $batas = in_array($area, ['JABODEBEK', 'JABODETABEK'], true) ? 0 : ($area === 'JAWA_BARAT' ? 1 : 2);

        return $s > $batas ? 'H+' . ($s - $batas) : 'Sesuai SLA';
    }

    // ============================================================
    // GUDANG
    // ============================================================
    private function durasiGudang($planning, $tiba): string
    {
        $a = $this->ts($planning);
        $b = $this->ts($tiba);
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
    // ESTIMASI / ALERT / PERJALANAN
    // ============================================================
    private function alertEstimasi($r): string
    {
        if (!empty($r->tanggal_tiba_pasuruan)) return 'TIBA';

        $est = $this->dayStart($r->estimasi_tiba_pasuruan);
        if (!$est) return '-';

        $sisa = floor(($est - strtotime(date('Y-m-d'))) / 86400);

        if ($sisa < 0)  return 'OVERDUE';
        if ($sisa <= 7) return 'H-' . $sisa;
        return 'ON TRACK';
    }

    private function lamaPerjalanan($r)
    {
        $k = $this->dayStart($r->tanggal_keluar_gudang_pasuruan);
        $t = $this->dayStart($r->tanggal_tiba_pasuruan);
        if (!$k || !$t) return '-';
        return max(0, floor(($t - $k) / 86400));
    }

    private function slaTiba($r): string
    {
        $t = $this->dayStart($r->tanggal_tiba_pasuruan);
        $e = $this->dayStart($r->estimasi_tiba_pasuruan);
        if (!$t || !$e) return '-';
        return $t <= $e ? 'On Time' : 'Delay';
    }

    private function statusBongkar($r): string
    {
        if (!empty($r->tanggal_bongkar_pasuruan)) return 'Telah Bongkar';

        $t = $this->dayStart($r->tanggal_tiba_pasuruan);
        if ($t) {
            return 'Pending Bongkar H+' . max(0, floor((strtotime(date('Y-m-d')) - $t) / 86400));
        }

        return '-';
    }

    private function overstay($r)
    {
        $t = $this->dayStart($r->tanggal_tiba_pasuruan);
        $b = $this->dayStart($r->tanggal_bongkar_pasuruan);
        if (!$t || !$b) return '-';
        return max(0, floor(($b - $t) / 86400));
    }

    private function slaBongkar($r): string
    {
        $t = $this->dayStart($r->tanggal_tiba_pasuruan);
        $b = $this->dayStart($r->tanggal_bongkar_pasuruan);
        if (!$t || !$b) return '-';
        return floor(($b - $t) / 86400) <= 0 ? 'On Time' : 'Delay';
    }

    private function selisihQty($r)
    {
        $actualRaw = $r->actual_delivery_quantity_pasuruan;
        if ($actualRaw === null || $actualRaw === '' || (float) $actualRaw == 0) return '';
        return (float) $r->total_do_pasuruan - (float) $actualRaw;
    }

    // ============================================================
    // STATUS AKHIR & STATUS ALERT
    // ============================================================
    private function statusAkhir($r): string
    {
        $t = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
        $b = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));

        if (empty($r->tanggal_tiba_pasuruan)) return 'Dalam Perjalanan';
        if (empty($r->tanggal_bongkar_pasuruan)) return 'Sudah Tiba - Dalam Pembongkaran';
        if ($t === 'ON TIME' && $b === 'ON TIME') return 'Pengiriman On Time';
        return 'Pengiriman Delay';
    }

    private function statusAlert($r): string
    {
        $t = strtoupper(trim($r->sla_tiba_pasuruan ?? ''));
        $b = strtoupper(trim($r->sla_bongkar_pasuruan ?? ''));

        if ($t === 'ON TIME' && $b === 'ON TIME') return 'Delivered Ontime';
        if ($t === 'DELAY'   && $b === 'ON TIME') return 'Delay Perjalanan';
        if ($t === 'ON TIME' && $b === 'DELAY')   return 'Delay Pembongkaran';
        if ($t === 'DELAY'   && $b === 'DELAY')   return 'Delivered Delay';
        return 'Belum Selesai';
    }

    // ============================================================
    // ESTIMASI ADMIN (rencana kirim + lead time)
    // ============================================================
    private function estimasiAdminTs($r): ?int
    {
        $rencana = $this->dayStart($r->rencana_kirim_pasuruan);
        if (!$rencana || $r->transport_lead_time_pasuruan === null || $r->transport_lead_time_pasuruan === '') {
            return null;
        }

        return strtotime('+' . (int) $r->transport_lead_time_pasuruan . ' days', $rencana);
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

        $tiba = $this->dayStart($r->tanggal_tiba_pasuruan);
        if ($tiba) {
            return $tiba <= $est ? 'On Time' : 'Delay';
        }

        return strtotime(date('Y-m-d')) > $est ? 'Delay' : 'Belum Tiba';
    }
}