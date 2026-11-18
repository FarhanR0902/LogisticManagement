<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PlannerSlicerExport extends PlannerExport
{
    private const NAIK_SLA = ['ADIT' => 2, 'YERE' => 2, 'NOVA' => 0, 'DEVIN' => 0];
    private const NAIK_DEFAULT = 2;

    private const METRICS = [
        'Total Grup' => 'Rk Grup',
        'Sudah Armada' => 'Rk Armada Sudah',
        'Belum Armada' => 'Rk Armada Belum',
        'Armada Delay' => 'Rk Armada Delay',
        'Gudang Selesai' => 'Rk Gudang Selesai',
        'Di Gudang' => 'Rk Di Gudang',
        'Belum Input Gudang' => 'Rk Belum Input',
        'Gudang Delay' => 'Rk Gudang Delay',
        'In Transit' => 'Rk In Transit',
    ];

    private array $gagg = [];
    private array $shipOpt = [];
    private array $seen = [];
    private ?array $cols2 = null;
    private int $nRows = 0;

    /**
     * Posisi slicer di sheet Ringkasan: grid 2 kolom, mulai kolom V (indeks 21),
     * di sebelah kanan chart (O..T). Format slot: [kolomAwal, offset, kolomAkhir, offset, barisAwal, barisAkhir].
     */
    public static function slicerSlots(int $count): array
    {
        $startCol = 21;
        $w = 2;
        $h = 13;
        $perRow = 2;
        $slots = [];
        for ($i = 0; $i < $count; $i++) {
            $c = $startCol + ($i % $perRow) * ($w + 1);
            $r = intdiv($i, $perRow) * ($h + 1);
            $slots[] = [$c, 0, $c + $w, 0, $r, $r + $h];
        }
        return $slots;
    }

    private function base()
    {
        return $this->query()->toBase()->reorder();
    }

    private function sk($r): string
    {
        $s = trim((string) $r->no_shipment);
        return $s !== '' ? $s : 'id' . $r->id;
    }

    private function hari($a, $b): ?int
    {
        if (trim((string) $a) === '' || trim((string) $b) === '') return null;
        $x = strtotime(date('Y-m-d', strtotime((string) $a)));
        $y = strtotime(date('Y-m-d', strtotime((string) $b)));
        return ($x && $y) ? max(0, (int) floor(($y - $x) / 86400)) : null;
    }

    private function key($v, bool $periode = false): string
    {
        $v = trim((string) $v);
        if ($v === '') return '(kosong)';
        return $periode ? 'Periode ' . $v : $v;
    }

    /** total per grup + status optimal per shipment (1 query agregat) */
    private function prepare(): void
    {
        $k = [];
        $t = [];
        $rows = $this->base()->selectRaw("COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id',id)) AS sk,
                COALESCE(tujuan,'') AS tj,
                SUM(COALESCE(nilai_muatan,0)) AS nilai, MAX(COALESCE(biaya_kirim,0)) AS biaya,
                SUM(COALESCE(total_kubik,0)) AS kb, MAX(kubikasi) AS kk,
                SUM(COALESCE(total_tonase,0)) AS tn, MAX(tonase) AS tk")
            ->groupByRaw('sk, tj')->get();

        foreach ($rows as $g) {
            $this->gagg[$g->sk . '|' . $g->tj] = [(float) $g->nilai, (float) $g->biaya];
            if ((float) $g->kk > 0) $k[$g->sk] = ($k[$g->sk] ?? 0) + round($g->kb / $g->kk * 100, 2);
            if ((float) $g->tk > 0) $t[$g->sk] = ($t[$g->sk] ?? 0) + round($g->tn / $g->tk * 100, 2);
        }
        foreach (array_unique(array_merge(array_keys($k), array_keys($t))) as $s) {
            $this->shipOpt[$s] = (($k[$s] ?? 0) >= 85 || ($t[$s] ?? 0) >= 85) ? 'OPTIMAL' : 'TIDAK OPTIMAL';
        }
        $this->nRows = (int) $this->base()->where('is_rep', 1)->count();
    }

    protected function columns(): array
    {
        if ($this->cols2 !== null) return $this->cols2;

        $fl     = fn($r, $c) => trim((string) ($r->{$c} ?? '')) !== '';
        $f      = fn(callable $c) => fn($r) => $c($r) ? 1 : 0;
        $shipRep = function ($r) {
            $s = $this->sk($r);
            $this->seen[$s] ??= $r->id;
            return $this->seen[$s] === $r->id;
        };
        $sf     = fn(callable $c) => fn($r) => ($shipRep($r) && $c($r)) ? 1 : 0;
        $cari   = fn($r) => $this->hari($r->rencana_kirim, $r->tanggal_dpt_unit);
        $naik   = fn($r) => $this->hari($r->tanggal_naik_logistik, $r->tanggal_dpt_unit);
        $batas  = fn($r) => self::NAIK_SLA[strtoupper(trim((string) $r->planner))] ?? self::NAIK_DEFAULT;
        $opt    = fn($r) => $this->shipOpt[$this->sk($r)] ?? null;
        $gDelay = function ($r) {
            foreach (array_keys(self::GUDANG) as $s) {
                $i = $this->infoGudang($r, $s);
                if ($i && $i['delay']) return true;
            }
            return false;
        };
        // ada minimal 1 gudang yang punya tiba + keluar (durasi bisa dihitung)
        $keluarAda = function ($r) {
            foreach (array_keys(self::GUDANG) as $s) {
                if ($this->infoGudang($r, $s)) return true;
            }
            return false;
        };
        // tiba gudang terakhir vs planning loading gudang itu: true = delay, false = on time, null = tidak ada data
        $tibaDelay = function ($r) {
            $best = null;
            $plan = '';
            foreach (array_keys(self::GUDANG) as $s) {
                $t = trim((string) ($r->{"tanggal_tiba_gudang{$s}"} ?? ''));
                if ($t === '') continue;
                if ($best === null || strtotime($t) >= strtotime($best)) {
                    $best = $t;
                    $plan = trim((string) ($r->{"planning_loading{$s}"} ?? ''));
                }
            }
            if ($best === null || $plan === '') return null;
            return date('Y-m-d', strtotime($best)) > date('Y-m-d', strtotime($plan));
        };
        $any = function ($r) use ($fl) {   // [ada keluar, menggantung, ada isi]
            $keluar = $dang = $isi = false;
            foreach (array_keys(self::GUDANG) as $s) {
                $k = $fl($r, "tanggal_keluar_gudang{$s}");
                $st = $fl($r, "planning_loading{$s}") || $fl($r, "tanggal_tiba_gudang{$s}");
                $keluar = $keluar || $k;
                $isi = $isi || $k || $st;
                $dang = $dang || ($st && !$k);
            }
            return [$keluar, $dang, $isi];
        };
        $grp = fn($r) => $this->gagg[$this->sk($r) . '|' . ($r->tujuan ?? '')] ?? [0, 0];

        return $this->cols2 = [
            ['Nama Planner',     'text', fn($r) => $this->key($r->planner)],
            ['No Shipment',      'text', fn($r) => (string) $r->no_shipment],
            ['Tujuan',           'text', fn($r) => $r->tujuan],
            ['Periode Shipment', 'text', fn($r) => $this->key($r->periode_shipment, true)],
            ['Tanggal Shipment', 'date', fn($r) => $this->xl($r->tanggal_shipment)],
            ['Area',             'text', fn($r) => $this->key($r->area)],
            ['Dist Channel',     'text', fn($r) => $this->key($r->dist_channel)],
            ['Pulau',            'text', fn($r) => $this->key($r->pulau)],
            ['Ekspedisi',        'text', fn($r) => $this->key($r->ekpedisi)],
            ['Mobil',            'text', fn($r) => $this->key($r->mobil)],
            ['Tanggal Terima Dari Admin', 'date', fn($r) => $this->xl($r->tanggal_naik_logistik)],
            ['Rencana Kirim',    'date', fn($r) => $this->xl($r->rencana_kirim)],
            ['Tanggal Dapat Unit', 'date', fn($r) => $this->xl($r->tanggal_dpt_unit)],
            ['Pengiriman Optimal Shipment', 'text', fn($r) => ($o = $opt($r)) === 'OPTIMAL' ? 'Optimal' : ($o ? 'Tidak Optimal' : '-')],
            ['Rk Nilai Grup',    'money', fn($r) => $grp($r)[0]],
            ['Rk Biaya Grup',    'money', fn($r) => $grp($r)[1]],
            ['Rk Tampil',        'raw',  fn($r) => ''],            // diganti rumus SUBTOTAL
            ['Rk Grup',          'raw',  fn($r) => 1],
            ['Rk Armada Sudah',  'raw',  $f(fn($r) => $fl($r, 'rencana_kirim') && $fl($r, 'tanggal_dpt_unit'))],
            ['Rk Armada Belum',  'raw',  $f(fn($r) => !($fl($r, 'rencana_kirim') && $fl($r, 'tanggal_dpt_unit')))],
            ['Rk Armada Delay',  'raw',  $f(fn($r) => str_starts_with($this->slaDapatMobil($r), 'H+'))],
            ['Rk Gudang Selesai', 'raw', $f(fn($r) => $any($r)[0] && !$any($r)[1])],
            ['Rk Di Gudang',     'raw',  $f(fn($r) => !($any($r)[0] && !$any($r)[1]))],
            ['Rk Belum Input',   'raw',  $f(fn($r) => !$any($r)[2])],
            ['Rk Gudang Delay',  'raw',  $f($gDelay)],
            ['Rk In Transit',    'raw',  $f(fn($r) => $any($r)[0] && !$any($r)[1] && !$fl($r, 'tanggal_tiba'))],
            ['Rk Ship Wakil',    'raw',  fn($r) => $shipRep($r) ? 1 : 0],
            ['Rk Ship Optimal',  'raw',  $sf(fn($r) => $opt($r) === 'OPTIMAL')],
            ['Rk Ship Tidak Optimal', 'raw', $sf(fn($r) => $opt($r) === 'TIDAK OPTIMAL')],
            ['Rk Ship Cari Ada',   'raw', $sf(fn($r) => $cari($r) !== null)],
            ['Rk Ship Cari Hari',  'raw', fn($r) => $shipRep($r) ? ($cari($r) ?? 0) : 0],
            ['Rk Ship Cari Delay', 'raw', $sf(fn($r) => $cari($r) !== null && str_starts_with($this->slaDapatMobil($r), 'H+'))],
            ['Rk Ship Naik Ada',   'raw', $sf(fn($r) => $naik($r) !== null)],
            ['Rk Ship Naik Hari',  'raw', fn($r) => $shipRep($r) ? ($naik($r) ?? 0) : 0],
            ['Rk Ship Naik Delay', 'raw', $sf(fn($r) => $naik($r) !== null && $naik($r) > $batas($r))],

            // ketibaan ke gudang (tiba terakhir vs planning loading)
            ['Rk Tiba Ada',      'raw', $f(fn($r) => $tibaDelay($r) !== null)],
            ['Rk Tiba Ontime',   'raw', $f(fn($r) => $tibaDelay($r) === false)],
            ['Rk Tiba Delay',    'raw', $f(fn($r) => $tibaDelay($r) === true)],
            // keluar gudang (durasi di gudang <= 24 jam = on time)
            ['Rk Keluar Ada',    'raw', $f($keluarAda)],
            ['Rk Keluar Ontime', 'raw', $f(fn($r) => $keluarAda($r) && !$gDelay($r))],
        ];
    }

    private function L(string $head): string
    {
        return Coordinate::stringFromColumnIndex(array_search($head, $this->headings(), true) + 1);
    }

    private function rng(string $head): string
    {
        $c = $this->L($head);
        return "Data!\${$c}\$2:\${$c}\$" . ($this->nRows + 1);
    }

    private function sumifs(string $flag, ?string $keyHead = null, ?string $keyCell = null): string
    {
        $s = "SUMIFS({$this->rng($flag)},{$this->rng('Rk Tampil')},1";
        if ($keyHead) $s .= ",{$this->rng($keyHead)},{$keyCell}";
        return $s . ')';
    }

    /** @return array [jumlah baris data, heading]; file xlsx berisi tabel "tblLogistik" + chart di Ringkasan */
    public function build(string $path): array
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $this->prepare();
        $heads = $this->headings();

        $o = new Options();
        $o->setColumnWidthForRange(18, 1, count($heads));
        $w = new Writer($o);
        $w->openToFile($path);

        $hs = (new Style())->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('0284C7');
        $hd = (new Style())->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('1F2937');
        $row = fn(array $v, ?Style $s = null) => new Row(array_map(fn($x) => Cell::fromValue($x, $s), $v));

        // style angka untuk sel di Ringkasan
        $sMoney = (new Style())->withFormat('#,##0');
        $sPct2  = (new Style())->withFormat('0.00"%"');
        $sDec1  = (new Style())->withFormat('0.0');
        $rowS = fn(array $v, array $st) => new Row(array_map(
            fn($x, $i) => Cell::fromValue($x, $st[$i] ?? null),
            $v,
            array_keys($v)
        ));

        // ================= RINGKASAN =================
        $w->getCurrentSheet()->setName('Ringkasan');
        $w->addRow($row(['DASHBOARD PLANNER']));
        $w->addRow($row(['Angka ikut berubah saat slicer / filter tabel dipakai (dihitung per shipment + tujuan).']));
        $w->addRow($row([]));

        $labels = array_keys(self::METRICS);
        $w->addRow($row(array_merge($labels, ['Nilai Muatan', 'Biaya Kirim', 'CR (%)']), $hs));
        $kpi = array_map(fn($fl) => '=' . $this->sumifs($fl), array_values(self::METRICS));
        $nC = count($kpi);
        $nL = Coordinate::stringFromColumnIndex($nC + 1);
        $bL = Coordinate::stringFromColumnIndex($nC + 2);
        $kpi[] = '=' . $this->sumifs('Rk Nilai Grup');
        $kpi[] = '=' . $this->sumifs('Rk Biaya Grup');
        $kpi[] = "=IF({$nL}5>0,{$bL}5/{$nL}5*100,0)";
        $w->addRow($rowS($kpi, [$nC => $sMoney, $nC + 1 => $sMoney, $nC + 2 => $sPct2]));
        $w->addRow($row([]));
        $r = 7;

        $dims = [
            'Nama Planner' => 'PER PLANNER',
            'Area' => 'PER AREA',
            'Dist Channel' => 'PER DIST CHANNEL',
            'Periode Shipment' => 'PER PERIODE SHIPMENT'
        ];

        $keys = [];
        foreach (array_keys($dims) as $h) {
            $col = ['Nama Planner' => 'planner', 'Area' => 'area', 'Dist Channel' => 'dist_channel', 'Periode Shipment' => 'periode_shipment'][$h];
            $keys[$h] = $this->base()->where('is_rep', 1)
                ->selectRaw("COALESCE(NULLIF(TRIM({$col}),''),'') AS k")->distinct()->orderBy('k')->pluck('k')
                ->map(fn($v) => $this->key($v, $h === 'Periode Shipment'))->unique()->values()->all();
        }

        // style baris grand total
        $gT = fn() => (new Style())->withFontBold(true)->withBackgroundColor('E5E7EB');
        $totStyles = [];
        for ($i = 0; $i <= $nC + 4; $i++) $totStyles[$i] = $gT();
        $totStyles[$nC + 2] = $gT()->withFormat('#,##0');
        $totStyles[$nC + 3] = $gT()->withFormat('#,##0');
        $totStyles[$nC + 4] = $gT()->withFormat('0.00"%"');

        $nn = Coordinate::stringFromColumnIndex($nC + 2);   // kolom Nilai Muatan
        $bb = Coordinate::stringFromColumnIndex($nC + 3);   // kolom Biaya Kirim

        foreach ($dims as $head => $judul) {
            $w->addRow($row([$judul]));
            $r++;
            $w->addRow($row(array_merge([''], $labels, ['Nilai Muatan', 'Biaya Kirim', 'CR (%)']), $hd));
            $r++;

            foreach ($keys[$head] as $k) {
                $cells = [$k];
                foreach (self::METRICS as $fl) $cells[] = '=' . $this->sumifs($fl, $head, "\$A{$r}");
                $cells[] = '=' . $this->sumifs('Rk Nilai Grup', $head, "\$A{$r}");
                $cells[] = '=' . $this->sumifs('Rk Biaya Grup', $head, "\$A{$r}");
                $cells[] = "=IF({$nn}{$r}>0,{$bb}{$r}/{$nn}{$r}*100,0)";
                $w->addRow($rowS($cells, [$nC + 2 => $sMoney, $nC + 3 => $sMoney, $nC + 4 => $sPct2]));
                $r++;
            }

            // GRAND TOTAL kategori ini
            $cells = ['GRAND TOTAL'];
            foreach (self::METRICS as $fl) $cells[] = '=' . $this->sumifs($fl);
            $cells[] = '=' . $this->sumifs('Rk Nilai Grup');
            $cells[] = '=' . $this->sumifs('Rk Biaya Grup');
            $cells[] = "=IF({$nn}{$r}>0,{$bb}{$r}/{$nn}{$r}*100,0)";
            $w->addRow($rowS($cells, $totStyles));
            $r++;

            $w->addRow($row([]));
            $w->addRow($row([]));
            $r += 2;
        }

        // optimal per shipment + SLA armada per planner
        $w->addRow($row(['OPTIMAL PER SHIPMENT & SLA ARMADA PER PLANNER']));
        $r++;
        $w->addRow($row([
            'Planner',
            'Batas Naik→Unit',
            'Total Shipment',
            'Optimal',
            'Tidak Optimal',
            '% Optimal',
            'Cari Mobil Tercatat',
            'Rata² Hari Cari',
            'Cari Mobil Delay',
            'Naik→Unit Tercatat',
            'Rata² Hari Naik→Unit',
            'Naik→Unit Delay',
            '% On Time Naik→Unit'
        ], $hd));
        $r++;
        $slaHdr   = $r - 1;   // baris header tabel optimal (untuk chart)
        $slaFirst = $r;       // baris data planner pertama
        foreach (array_merge($keys['Nama Planner'], [null]) as $k) {
            $s = fn($fl) => $this->sumifs($fl, $k === null ? null : 'Nama Planner', $k === null ? null : "\$A{$r}");
            $bt = $k === null ? '-' : (self::NAIK_SLA[strtoupper($k)] ?? self::NAIK_DEFAULT);
            $w->addRow($rowS([
                $k ?? 'TOTAL',
                $bt,
                '=' . $s('Rk Ship Wakil'),
                '=' . $s('Rk Ship Optimal'),
                '=' . $s('Rk Ship Tidak Optimal'),
                "=IF((D{$r}+E{$r})>0,D{$r}/(D{$r}+E{$r})*100,0)",
                '=' . $s('Rk Ship Cari Ada'),
                "=IF(G{$r}>0," . $s('Rk Ship Cari Hari') . "/G{$r},0)",
                '=' . $s('Rk Ship Cari Delay'),
                '=' . $s('Rk Ship Naik Ada'),
                "=IF(J{$r}>0," . $s('Rk Ship Naik Hari') . "/J{$r},0)",
                '=' . $s('Rk Ship Naik Delay'),
                "=IF(J{$r}>0,(J{$r}-L{$r})/J{$r}*100,0)",
            ], [5 => $sPct2, 7 => $sDec1, 10 => $sDec1, 12 => $sPct2]));
            $r++;
        }
        $w->addRow($row([]));
        $w->addRow($row([]));
        $r += 2;

        // ketibaan & keluar gudang per planner
        $w->addRow($row(['KETIBAAN & KELUAR GUDANG PER PLANNER']));
        $r++;
        $w->addRow($row([
            'Planner',
            'Tiba Tercatat',
            'Tiba On Time',
            'Tiba Delay',
            'Keluar Tercatat',
            'Keluar On Time',
            'Keluar Delay'
        ], $hd));
        $r++;
        $tgHdr   = $r - 1;
        $tgFirst = $r;
        foreach (array_merge($keys['Nama Planner'], [null]) as $k) {
            $s = fn($fl) => $this->sumifs($fl, $k === null ? null : 'Nama Planner', $k === null ? null : "\$A{$r}");
            $w->addRow($row([
                $k ?? 'TOTAL',
                '=' . $s('Rk Tiba Ada'),
                '=' . $s('Rk Tiba Ontime'),
                '=' . $s('Rk Tiba Delay'),
                '=' . $s('Rk Keluar Ada'),
                '=' . $s('Rk Keluar Ontime'),
                '=' . $s('Rk Gudang Delay'),
            ]));
            $r++;
        }

        // ================= DATA =================
        $w->addNewSheetAndMakeItCurrent();
        $w->getCurrentSheet()->setName('Data');
        $w->addRow($row($heads, $hs));

        $styles = [];
        foreach ($this->columnFormats() as $letter => $fmt) {
            $styles[Coordinate::columnIndexFromString($letter) - 1] = (new Style())->withFormat($fmt);
        }
        $iTampil = array_search('Rk Tampil', $heads, true);
        $cGrup   = $this->L('Rk Grup');
        $n = 1;

        $this->base()->where('is_rep', 1)->chunkById(5000, function ($rows) use ($w, $styles, $iTampil, $cGrup, &$n) {
            foreach ($rows as $r) {
                $n++;
                $cells = [];
                foreach ($this->map($r) as $i => $v) {
                    if ($i === $iTampil) $v = "=SUBTOTAL(103,\${$cGrup}{$n})";
                    $cells[] = Cell::fromValue($v, $styles[$i] ?? null);
                }
                $w->addRow(new Row($cells));
            }
        }, 'id');

        $w->close();

        // 1) tabel tblLogistik di sheet Data
        XlsxTableInjector::add($path, 'Data', 'tblLogistik', $heads, $n);

        // 2) chart di sheet Ringkasan
        $nP = count($keys['Nama Planner']);
        if ($nP > 0) {
            $f  = 9;
            $l  = $f + $nP - 1;               // blok PER PLANNER
            $of = $slaFirst;
            $ol = $slaFirst + $nP - 1;        // blok optimal
            $tf = $tgFirst;
            $tl = $tgFirst + $nP - 1;         // blok tiba/keluar
            try {
                XlsxChartInjector::add($path, 'Ringkasan', [
                    [
                        'title'  => 'Gudang Selesai vs Di Gudang per Planner',
                        'cat'    => "\$A\${$f}:\$A\${$l}",
                        'series' => [
                            ['name' => '$F$8', 'val' => "\$F\${$f}:\$F\${$l}", 'color' => '0284C7'],
                            ['name' => '$G$8', 'val' => "\$G\${$f}:\$G\${$l}", 'color' => 'F59E0B'],
                        ],
                        'anchor' => [14, 6, 20, 24],     // O7 : T25
                    ],
                    [
                        'title'  => 'Sudah vs Belum Armada per Planner',
                        'cat'    => "\$A\${$f}:\$A\${$l}",
                        'series' => [
                            ['name' => '$C$8', 'val' => "\$C\${$f}:\$C\${$l}", 'color' => '16A34A'],
                            ['name' => '$D$8', 'val' => "\$D\${$f}:\$D\${$l}", 'color' => 'DC2626'],
                        ],
                        'anchor' => [14, 26, 20, 44],    // O27 : T45
                    ],
                    [
                        'title'  => 'Pengiriman Optimal vs Tidak Optimal per Planner',
                        'cat'    => "\$A\${$of}:\$A\${$ol}",
                        'series' => [
                            ['name' => "\$D\${$slaHdr}", 'val' => "\$D\${$of}:\$D\${$ol}", 'color' => '16A34A'],
                            ['name' => "\$E\${$slaHdr}", 'val' => "\$E\${$of}:\$E\${$ol}", 'color' => 'F59E0B'],
                        ],
                        'anchor' => [14, 46, 20, 64],    // O47 : T65
                    ],
                    [
                        'title'  => 'Ketibaan ke Gudang: On Time vs Delay per Planner',
                        'cat'    => "\$A\${$tf}:\$A\${$tl}",
                        'series' => [
                            ['name' => "\$C\${$tgHdr}", 'val' => "\$C\${$tf}:\$C\${$tl}", 'color' => '16A34A'],
                            ['name' => "\$D\${$tgHdr}", 'val' => "\$D\${$tf}:\$D\${$tl}", 'color' => 'DC2626'],
                        ],
                        'anchor' => [14, 66, 20, 84],    // O67 : T85
                    ],
                    [
                        'title'  => 'Keluar Gudang: On Time vs Delay per Planner',
                        'cat'    => "\$A\${$tf}:\$A\${$tl}",
                        'series' => [
                            ['name' => "\$F\${$tgHdr}", 'val' => "\$F\${$tf}:\$F\${$tl}", 'color' => '16A34A'],
                            ['name' => "\$G\${$tgHdr}", 'val' => "\$G\${$tf}:\$G\${$tl}", 'color' => 'DC2626'],
                        ],
                        'anchor' => [14, 86, 20, 104],   // O87 : T105
                    ],
                ]);
            } catch (\Throwable $e) {
                report($e);   // chart gagal, export tetap jalan
            }
        }

        return [$n - 1, $heads];
    }
}
