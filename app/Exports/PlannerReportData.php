<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;

class PlannerReportData extends PlannerExport implements WithEvents, WithTitle
{
    public const SUFFIXES = ['', '_2', '_3', '_4', '_5'];

    /**
     * Periode "10-2026" bisa dibaca Excel sebagai tanggal saat dipakai sebagai kriteria SUMIFS.
     * Kunci bantu (kolom Rk Periode + label di Ringkasan) diberi awalan supaya selalu teks.
     */
    private const PERIODE_PREFIX = 'Periode ';
    /** batas hari tanggal_naik_logistik -> tanggal_dpt_unit per planner (kunci HURUF BESAR) */
    private const NAIK_UNIT_SLA = ['ADIT' => 2, 'YERE' => 2, 'NOVA' => 0, 'DEVIN' => 0];
    private const NAIK_UNIT_DEFAULT = 2;   // planner lain di luar daftar
    private const OPTIMAL_MIN = 85;

    private ?array $ship = null;
    private ?array $extraCols = null;
    private ?array $tblCols = null;
    private ?array $grp = null;
    private ?int $total = null;
    private ?array $letters = null;
    private array $keysCache = [];

    public function title(): string
    {
        return 'Data';
    }

    public function hasColumn(string $c): bool
    {
        $this->tblCols ??= array_flip(Schema::getColumnListing('logistik_pengiriman'));
        return isset($this->tblCols[$c]);
    }

    // ---------- kolom ----------
    protected function columns(): array
    {
        return $this->extraCols ??= array_merge(parent::columns(), $this->extraInfoColumns(), $this->rankColumns());
    }

    private function extraInfoColumns(): array
    {
        $c = [];
        if ($this->hasColumn('periode_shipment')) {
            $c[] = ['Periode Shipment', 'text', fn($r) => $r->periode_shipment];
        }
        $c[] = ['Tanggal Shipment', 'date', fn($r) => $this->xl($r->tanggal_shipment)];
        $c[] = ['Bulan Shipment',   'text', fn($r) => $r->tanggal_shipment ? date('Y-m', strtotime($r->tanggal_shipment)) : '(kosong)'];
        return $c;
    }

    /** id baris wakil (MIN id per shipment+tujuan) => [nilai grup, biaya grup] */
    private function groupInfo(): array
    {
        if ($this->grp !== null) return $this->grp;
        $out = [];
        foreach ($this->groupRows()->select('lp.id', 'g.g_nilai', 'g.g_biaya')->get() as $x) {
            $out[(int) $x->id] = [(float) $x->g_nilai, (float) $x->g_biaya];
        }
        return $this->grp = $out;
    }
    public function batasNaikUnit(string $planner): int
    {
        return self::NAIK_UNIT_SLA[strtoupper(trim($planner))] ?? self::NAIK_UNIT_DEFAULT;
    }

    /** selisih hari (tanpa jam), null kalau salah satu kosong, minimal 0 */
    private function hariBeda($a, $b): ?int
    {
        if (trim((string) $a) === '' || trim((string) $b) === '') return null;
        $x = strtotime(date('Y-m-d', strtotime((string) $a)));
        $y = strtotime(date('Y-m-d', strtotime((string) $b)));
        if (!$x || !$y) return null;
        return max(0, (int) floor(($y - $x) / 86400));
    }

    /**
     * Wakil per no_shipment (MIN id) => status optimal SHIPMENT.
     * Hasil kubik/tonase tiap tujuan dijumlah dulu (sama dengan optimalPerShipment di controller).
     * Nilai: 'OPTIMAL' | 'TIDAK OPTIMAL' | '-' (tidak ada kapasitas)
     */
    private function shipInfo(): array
    {
        if ($this->ship !== null) return $this->ship;

        $rows = (clone $this->query())->toBase()->reorder()
            ->selectRaw("COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)) AS sk,
                     COALESCE(tujuan,'') AS tj,
                     MIN(id) AS mid,
                     SUM(COALESCE(total_kubik,0))  AS s_kubik, MAX(kubikasi) AS k_kap,
                     SUM(COALESCE(total_tonase,0)) AS s_ton,   MAX(tonase)   AS t_kap")
            ->groupByRaw('sk, tj')
            ->get();

        $agg = [];
        foreach ($rows as $x) {
            $a = $agg[$x->sk] ?? ['mid' => PHP_INT_MAX, 'k' => null, 't' => null];
            $a['mid'] = min($a['mid'], (int) $x->mid);
            if ((float) $x->k_kap > 0) $a['k'] = ($a['k'] ?? 0) + round((float) $x->s_kubik / (float) $x->k_kap * 100, 2);
            if ((float) $x->t_kap > 0) $a['t'] = ($a['t'] ?? 0) + round((float) $x->s_ton   / (float) $x->t_kap * 100, 2);
            $agg[$x->sk] = $a;
        }

        $out = [];
        foreach ($agg as $a) {
            $out[$a['mid']] = ($a['k'] === null && $a['t'] === null)
                ? '-'
                : ((($a['k'] ?? 0) >= self::OPTIMAL_MIN || ($a['t'] ?? 0) >= self::OPTIMAL_MIN) ? 'OPTIMAL' : 'TIDAK OPTIMAL');
        }
        return $this->ship = $out;
    }

    public function groupRows()
    {
        $base = clone $this->query();
        $base = $base->toBase()->reorder();

        $agg = $base->select(DB::raw(
            'MIN(id) AS id, SUM(COALESCE(nilai_muatan,0)) AS g_nilai, MAX(COALESCE(biaya_kirim,0)) AS g_biaya'
        ))->groupByRaw("COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id', id)), COALESCE(tujuan,'')");

        return DB::table('logistik_pengiriman AS lp')->joinSub($agg, 'g', 'g.id', '=', 'lp.id');
    }

    public function totalRows(): int
    {
        return $this->total ??= (int) (clone $this->query())->toBase()->reorder()->count();
    }

    /** kunci bantu: kosong => "(kosong)", periode diberi awalan */
    private function keyValue(string $col, $v): string
    {
        $v = trim((string) $v);
        if ($v === '') return '(kosong)';
        return $col === 'periode_shipment' ? self::PERIODE_PREFIX . $v : $v;
    }

    public function distinctKeys(string $col): array
    {
        if (isset($this->keysCache[$col])) return $this->keysCache[$col];
        if (!$this->hasColumn($col)) return $this->keysCache[$col] = [];

        $raw = $this->groupRows()
            ->selectRaw("COALESCE(NULLIF(TRIM(lp.{$col}),''),'') AS k")
            ->distinct()->orderBy('k')->pluck('k')->all();

        $keys = array_values(array_unique(array_map(fn($v) => $this->keyValue($col, $v), $raw)));

        return $this->keysCache[$col] = $keys;
    }

    private function rankColumns(): array
    {
        $wk = fn($r) => isset($this->groupInfo()[(int) $r->id]);
        $fl = fn($r, $c) => trim((string) ($r->{$c} ?? '')) !== '';
        $sfx = array_values(array_filter(self::SUFFIXES, fn($s) => $this->hasColumn("tanggal_keluar_gudang{$s}")));

        // gudang: selesai = ada keluar & tidak ada siklus menggantung
        $status = function ($r) use ($sfx, $fl) {
            $any = false;
            $dangling = false;
            $anyFilled = false;
            foreach ($sfx as $s) {
                $k = $fl($r, "tanggal_keluar_gudang{$s}");
                $started = $fl($r, "planning_loading{$s}") || $fl($r, "tanggal_tiba_gudang{$s}");
                if ($k) $any = true;
                if ($started || $k) $anyFilled = true;
                if ($started && !$k) $dangling = true;
            }
            return ['selesai' => $any && !$dangling, 'belum' => !$anyFilled];
        };
        $flag = fn(callable $cond) => fn($r) => ($wk($r) && $cond($r)) ? 1 : 0;

        $gudangDelay = function ($r) use ($sfx) {
            foreach ($sfx as $s) {
                $i = $this->infoGudang($r, $s);
                if ($i && $i['delay']) return true;
            }
            return false;
        };
        $sw  = fn($r) => isset($this->shipInfo()[(int) $r->id]);          // baris wakil per no_shipment
        $sf  = fn(callable $cond) => fn($r) => ($sw($r) && $cond($r)) ? 1 : 0;
        $st  = fn($r) => $this->shipInfo()[(int) $r->id] ?? null;
        $cari = fn($r) => $this->hariBeda($r->rencana_kirim, $r->tanggal_dpt_unit);
        $naik = fn($r) => $this->hariBeda($r->tanggal_naik_logistik, $r->tanggal_dpt_unit);
        $naikDelay = fn($r) => ($h = $naik($r)) !== null && $h > $this->batasNaikUnit((string) $r->planner);

        $cols = [
            ['Rk Tampil',            'raw', fn($r) => ''],   // diisi rumus SUBTOTAL
            ['Rk Wakil Grup',        'raw', fn($r) => $wk($r) ? 1 : 0],
            ['Rk Nilai Grup',        'raw', fn($r) => $this->groupInfo()[(int) $r->id][0] ?? 0],
            ['Rk Biaya Grup',        'raw', fn($r) => $this->groupInfo()[(int) $r->id][1] ?? 0],
            ['Rk Armada Sudah',      'raw', $flag(fn($r) => $fl($r, 'rencana_kirim') && $fl($r, 'tanggal_dpt_unit'))],
            ['Rk Armada Belum',      'raw', $flag(fn($r) => !($fl($r, 'rencana_kirim') && $fl($r, 'tanggal_dpt_unit')))],
            ['Rk Armada Delay',      'raw', $flag(fn($r) => str_starts_with($this->slaDapatMobil($r), 'H+'))],
            ['Rk Gudang Selesai',    'raw', $flag(fn($r) => $status($r)['selesai'])],
            ['Rk Di Gudang',         'raw', $flag(fn($r) => !$status($r)['selesai'])],
            ['Rk Belum Input Gudang', 'raw', $flag(fn($r) => $status($r)['belum'])],
            ['Rk Gudang Delay',      'raw', $flag($gudangDelay)],
            ['Rk Optimal',           'raw', $flag(fn($r) => strtoupper(trim((string) $r->pengiriman_optimal)) === 'OPTIMAL')],
            ['Rk Tidak Optimal',     'raw', $flag(fn($r) => strtoupper(trim((string) $r->pengiriman_optimal)) === 'TIDAK OPTIMAL')],
            ['Rk In Transit',        'raw', $flag(fn($r) => $status($r)['selesai'] && !$fl($r, 'tanggal_tiba'))],
            ['Rk Data Lengkap',      'raw', $flag(fn($r) => $this->kelengkapan($r) === 'Lengkap')],
            ['Rk Ship Wakil',         'raw', fn($r) => $sw($r) ? 1 : 0],
            ['Rk Ship Optimal',       'raw', $sf(fn($r) => $st($r) === 'OPTIMAL')],
            ['Rk Ship Tidak Optimal', 'raw', $sf(fn($r) => $st($r) === 'TIDAK OPTIMAL')],

            // rencana_kirim -> tanggal_dpt_unit (lead time cari mobil)
            ['Rk Ship Cari Ada',      'raw', $sf(fn($r) => $cari($r) !== null)],
            ['Rk Ship Cari Hari',     'raw', fn($r) => $sw($r) ? ($cari($r) ?? 0) : 0],
            ['Rk Ship Cari OT',       'raw', $sf(fn($r) => $cari($r) !== null && !str_starts_with($this->slaDapatMobil($r), 'H+'))],
            ['Rk Ship Cari Delay',    'raw', $sf(fn($r) => $cari($r) !== null && str_starts_with($this->slaDapatMobil($r), 'H+'))],

            // tanggal_naik_logistik -> tanggal_dpt_unit (batas beda per planner)
            ['Rk Ship Naik Ada',      'raw', $sf(fn($r) => $naik($r) !== null)],
            ['Rk Ship Naik Hari',     'raw', fn($r) => $sw($r) ? ($naik($r) ?? 0) : 0],
            ['Rk Ship Naik OT',       'raw', $sf(fn($r) => $naik($r) !== null && !$naikDelay($r))],
            ['Rk Ship Naik Delay',    'raw', $sf(fn($r) => $naik($r) !== null && $naikDelay($r))],
        ];

        foreach (
            [
                ['Rk Planner', 'planner'],
                ['Rk Area', 'area'],
                ['Rk Dist Channel', 'dist_channel'],
                ['Rk Periode', 'periode_shipment'],
                ['Rk Pulau', 'pulau']
            ] as [$h, $c]
        ) {
            if ($this->hasColumn($c)) {
                $cols[] = [$h, 'raw', fn($r) => $this->keyValue($c, $r->{$c} ?? '')];
            }
        }
        return $cols;
    }

    /** heading unik (syarat Excel Table) */
    public function headings(): array
    {
        $seen = [];
        $out = [];
        foreach ($this->columns() as $col) {
            $h = (string) $col[0];
            $k = mb_strtolower($h);
            if (isset($seen[$k])) {
                $seen[$k]++;
                $h .= ' (' . $seen[$k] . ')';
            } else {
                $seen[$k] = 1;
            }
            $out[] = $h;
        }
        return $out;
    }

    /** huruf kolom berdasarkan heading (di-cache) */
    public function letter(string $head): string
    {
        if ($this->letters === null) {
            $this->letters = [];
            foreach ($this->headings() as $i => $h) {
                $this->letters[$h] = Coordinate::stringFromColumnIndex($i + 1);
            }
        }
        if (!isset($this->letters[$head])) {
            throw new \RuntimeException("Kolom '{$head}' tidak ada di sheet Data.");
        }
        return $this->letters[$head];
    }

    public function columnFormats(): array
    {
        $f = parent::columnFormats();
        $f[$this->letter('Rk Nilai Grup')] = '#,##0';
        $f[$this->letter('Rk Biaya Grup')] = '#,##0';
        return $f;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastRow = $sheet->getHighestRow();
            $lastCol = $sheet->getHighestColumn();
            $sheet->getTabColor()->setRGB('0284C7');

            $first = $this->letter('Rk Tampil');
            $sheet->getStyle("{$first}1:{$lastCol}1")->applyFromArray([
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '6B7280']],
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            ]);

            if ($lastRow >= 2) {
                $tc = $this->letter('Rk Tampil');
                $wk = $this->letter('Rk Wakil Grup');
                for ($r = 2; $r <= $lastRow; $r++) {
                    $sheet->setCellValueExplicit("{$tc}{$r}", "=SUBTOTAL(103,\${$wk}{$r})", DataType::TYPE_FORMULA);
                }
                $table = new Table("A1:{$lastCol}{$lastRow}", 'tblLogistik'); // nama dipakai injector
                $table->setShowHeaderRow(true);
                $table->setAllowFilter(true);
                $table->setStyle((new TableStyle(TableStyle::TABLE_STYLE_LIGHT1))->setShowRowStripes(true));
                $sheet->addTable($table);
            }
        }];
    }
}
