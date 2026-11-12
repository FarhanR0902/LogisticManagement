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

    private ?array $extraCols = null;
    private ?array $tblCols = null;
    private ?array $grp = null;
    private ?int $total = null;
    private ?array $letters = null;
    private array $keysCache = [];

    public function title(): string { return 'Data'; }

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
            $any = false; $dangling = false; $anyFilled = false;
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
            ['Rk Belum Input Gudang','raw', $flag(fn($r) => $status($r)['belum'])],
            ['Rk Gudang Delay',      'raw', $flag($gudangDelay)],
            ['Rk Optimal',           'raw', $flag(fn($r) => strtoupper(trim((string) $r->pengiriman_optimal)) === 'OPTIMAL')],
            ['Rk Tidak Optimal',     'raw', $flag(fn($r) => strtoupper(trim((string) $r->pengiriman_optimal)) === 'TIDAK OPTIMAL')],
            ['Rk In Transit',        'raw', $flag(fn($r) => $status($r)['selesai'] && !$fl($r, 'tanggal_tiba'))],
            ['Rk Data Lengkap',      'raw', $flag(fn($r) => $this->kelengkapan($r) === 'Lengkap')],
        ];

        foreach ([['Rk Planner', 'planner'], ['Rk Area', 'area'], ['Rk Dist Channel', 'dist_channel'],
                  ['Rk Periode', 'periode_shipment'], ['Rk Pulau', 'pulau']] as [$h, $c]) {
            if ($this->hasColumn($c)) {
                $cols[] = [$h, 'raw', fn($r) => $this->keyValue($c, $r->{$c} ?? '')];
            }
        }
        return $cols;
    }

    /** heading unik (syarat Excel Table) */
    public function headings(): array
    {
        $seen = []; $out = [];
        foreach ($this->columns() as $col) {
            $h = (string) $col[0]; $k = mb_strtolower($h);
            if (isset($seen[$k])) { $seen[$k]++; $h .= ' (' . $seen[$k] . ')'; } else { $seen[$k] = 1; }
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