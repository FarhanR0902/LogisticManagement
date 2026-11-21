<?php

namespace App\Exports;

use App\Models\LogistikPengiriman;
use App\Exports\Lite\Cell;
use App\Exports\Lite\Row;
use App\Exports\Lite\Color;
use App\Exports\Lite\Style;
use App\Exports\Lite\Options;
use App\Exports\Lite\Writer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Export Monitoring lengkap (setara PlannerSlicerExport), 3 sheet:
 *  - Ringkasan : KPI, tabel per dimensi, SLA per PIC, status akhir/qty/kuli (TANPA chart)
 *  - Data      : tabel tblLogistik
 *  - Dashboard : Top 20 Tujuan + tabel sumber grafik + semua chart + slicer
 * Satuan hitung = grup no_shipment + tujuan (baris pertama / id terkecil jadi wakil).
 */
class MonitoringSlicerExport
{
    private const SUF = ['', '_2', '_3', '_4', '_5'];
    private const FMT_DATE = 'dd-mm-yyyy';
    private const SHIPMENT_DIKECUALIKAN = ['45'];   // sama dengan controller

    // label => heading kolom Rk di sheet Data (urutan = kolom B.. di Ringkasan)
    public const METRICS = [
        'Total Grup'           => 'Rk Grup',
        'Sudah Tiba'           => 'Rk Sudah Tiba',
        'Belum Tiba'           => 'Rk Belum Tiba',
        'Pending Tiba'         => 'Rk Pending Tiba',
        'Mendekati (H-7)'      => 'Rk Mendekati',
        'On Track'             => 'Rk On Track',
        'Di Gudang Berikutnya' => 'Rk Gudang Berikutnya',
        'Sudah Bongkar'        => 'Rk Sudah Bongkar',
        'Pending Bongkar'      => 'Rk Pending Bongkar',
        'Data Lengkap'         => 'Rk Data Lengkap',
        'In Transit'           => 'Rk In Transit',
    ];

    /** heading dimensi yang dijadikan slicer (dipakai controller) */
    public const SLICER_HEADS = ['PIC', 'Area', 'Tujuan', 'Dist Channel', 'Ekspedisi', 'Bulan Keluar', 'Periode Shipment', 'Status'];

    protected array $f;
    private array $seenGrp = [];
    private ?array $cols = null;
    private int $nRows = 0;
    private array $keysCache = [];
    private $cr = null;
    private array $cv = [];

    /**
     * $filters: periode, pic_monitoring, dist_channel, area, bulan, tahun, tanggal_shipment,
     *           keluar_gudang_dari, keluar_gudang_sampai, keluar_gudang_tgl, tgl_dari, tgl_sampai
     * (daftar boleh array atau string "a,b,c"; '__kosong__' di periode = tanpa periode)
     */
    public function __construct(array $filters = [])
    {
        $this->f = $filters;
    }

    /** posisi slicer di sheet Dashboard: grid 2 kolom mulai kolom V, di kanan chart */
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

    // ============================================================
    // QUERY
    // ============================================================
    protected function toList($v): array
    {
        if (is_string($v)) $v = explode(',', $v);
        return array_values(array_filter(
            array_map(fn($x) => trim((string) $x), (array) $v),
            fn($x) => $x !== ''
        ));
    }

    /** tanggal keluar gudang terakhir dari semua gudang (1900-01-01 = kosong) */
    private function keluarSql(): string
    {
        $parts = array_map(
            fn($s) => "COALESCE(NULLIF(TRIM(tanggal_keluar_gudang{$s}),''),'1900-01-01')",
            self::SUF
        );
        return 'GREATEST(' . implode(',', $parts) . ')';
    }

    public function query()
    {
        $q = LogistikPengiriman::query();
        $f = $this->f;
        $keluar = $this->keluarSql();

        // shipment yang tidak ditampilkan di halaman Data Monitoring
        foreach (self::SHIPMENT_DIKECUALIKAN as $prefix) {
            $q->whereRaw("COALESCE(TRIM(no_shipment),'') NOT LIKE ?", [$prefix . '%']);
        }

        if ($v = $this->toList($f['pic_monitoring'] ?? [])) $q->whereIn('pic_monitoring', $v);
        if ($v = $this->toList($f['area'] ?? []))           $q->whereIn('area', $v);
        if ($v = $this->toList($f['dist_channel'] ?? []))   $q->whereIn('dist_channel', $v);

        if ($v = array_map('intval', $this->toList($f['bulan'] ?? []))) {
            $q->whereRaw("MONTH({$keluar}) IN (" . implode(',', array_fill(0, count($v), '?')) . ')', $v);
        }
        if ($v = array_map('intval', $this->toList($f['tahun'] ?? []))) {
            $q->whereRaw("YEAR({$keluar}) IN (" . implode(',', array_fill(0, count($v), '?')) . ')', $v);
        }

        if ($list = $this->toList($f['periode'] ?? [])) {
            $q->where(function ($w) use ($list) {
                $real = array_values(array_filter($list, fn($x) => strtolower($x) !== '__kosong__'));
                if ($real) $w->whereIn('periode_shipment', $real);
                if (count($real) !== count($list)) {
                    $w->orWhereRaw("COALESCE(NULLIF(TRIM(periode_shipment),''),'') = ''");
                }
            });
        }

        if (!empty($f['tanggal_shipment'])) {
            $d = $f['tanggal_shipment'];
            $q->where('tanggal_shipment', '>=', $d)
              ->where('tanggal_shipment', '<', date('Y-m-d', strtotime($d . ' +1 day')));
        }

        if (!empty($f['keluar_gudang_dari']))   $q->whereRaw("DATE({$keluar}) >= ?", [$f['keluar_gudang_dari']]);
        if (!empty($f['keluar_gudang_sampai'])) $q->whereRaw("DATE({$keluar}) <= ?", [$f['keluar_gudang_sampai']]);
        if (!empty($f['keluar_gudang_tgl']))    $q->whereDate('tanggal_keluar_gudang', $f['keluar_gudang_tgl']);
        if (!empty($f['tgl_dari']))             $q->whereRaw("DATE({$keluar}) >= ?", [$f['tgl_dari']]);
        if (!empty($f['tgl_sampai']))           $q->whereRaw("DATE({$keluar}) <= ?", [$f['tgl_sampai']]);

        return $q;
    }

    public function base()
    {
        return $this->query()->toBase()->reorder();
    }

    // ============================================================
    // HELPER
    // ============================================================
    private function sk($r): string
    {
        $s = trim((string) $r->no_shipment);
        return $s !== '' ? $s : 'id' . $r->id;
    }

    /** kosong => "(kosong)"; periode diberi awalan supaya "10-2026" tidak dibaca Excel sebagai tanggal */
    private function key($v, bool $periode = false): string
    {
        $v = trim((string) $v);
        if ($v === '') return '(kosong)';
        return $periode ? 'Periode ' . $v : $v;
    }

    private function xl($v)
    {
        if ($v === null || $v === '' || $v === false) return '';
        $ts = is_int($v) ? $v : strtotime((string) $v);
        if (!$ts || $ts <= 0) return '';
        return strtotime(date('Y-m-d', $ts) . ' UTC') / 86400 + 25569;
    }

    private function jam($v): string
    {
        return $v ? substr((string) $v, 0, 5) : '';
    }

    private function slaAda($v): bool
    {
        $v = trim((string) $v);
        return $v !== '' && $v !== '-';
    }

    private function slaDelay($v): bool
    {
        $v = strtoupper(trim((string) $v));
        return str_starts_with($v, 'H+') || $v === 'DELAY';
    }

    /** salinan getKeluarGudangInfo (5 gudang) */
    private function keluarInfo($r): array
    {
        $blocked = false;
        $status = null;
        $ts = [];

        foreach (self::SUF as $s) {
            $planning = $r->{"planning_loading{$s}"} ?? null;
            $tiba     = $r->{"tanggal_tiba_gudang{$s}"} ?? null;
            $keluar   = $r->{"tanggal_keluar_gudang{$s}"} ?? null;

            $started = !empty($planning) || !empty($tiba);
            $selesai = !empty($keluar);

            if ($started && !$selesai) {
                $blocked = true;
                $status = !empty($tiba) ? 'sedang' : 'menuju';
            }
            if ($selesai) $ts[] = strtotime($keluar);
        }

        return [
            'blocked' => $blocked,
            'status'  => $status,
            'keluar'  => $ts ? max($ts) : null,
        ];
    }

    /** semua nilai turunan satu baris (alert, status bongkar, kelengkapan, dst), di-cache per baris */
    private function calc($r): array
    {
        if ($this->cr === $r) return $this->cv;

        $info    = $this->keluarInfo($r);
        $keluar  = $info['keluar'];
        $blocked = $info['blocked'];
        $label   = $info['status'] === 'sedang' ? 'Sedang di Gudang Berikutnya' : 'Menuju Gudang Berikutnya';

        $lead     = (int) ($r->transport_lead_time ?? 0);
        $estimasi = !empty($r->estimasi_tiba)
            ? strtotime($r->estimasi_tiba)
            : ((!$blocked && $keluar) ? strtotime("+{$lead} days", $keluar) : null);

        $today = strtotime(date('Y-m-d'));
        $tiba  = !empty($r->tanggal_tiba) ? strtotime($r->tanggal_tiba) : null;
        $lama  = ($tiba && $keluar) ? (int) floor(($tiba - $keluar) / 86400) : null;

        // alert + kategori
        $alert = '-';
        $cat   = 'none';
        if ($tiba) {
            $alert = 'TIBA';
            $cat = 'tiba';
        } elseif (!$blocked && $estimasi) {
            $sisa = (int) floor(($estimasi - $today) / 86400);
            if ($sisa < 0)      { $alert = 'Pending Tiba H+' . abs($sisa); $cat = 'pending'; }
            elseif ($sisa <= 7) { $alert = 'H-' . $sisa;                   $cat = 'mendekati'; }
            else                { $alert = 'ON TRACK';                     $cat = 'ontrack'; }
        } elseif ($blocked) {
            $cat = 'blocked';
        }

        // status bongkar
        $sb = '-';
        if (!empty($r->tanggal_bongkar)) {
            $sb = 'Sudah Bongkar';
        } elseif ($tiba) {
            $hari = (int) floor(($today - strtotime(date('Y-m-d', $tiba))) / 86400);
            $sb = 'Pending Bongkar H+' . max(0, $hari);
        }

        // kelengkapan
        $missing = [];
        if (empty($r->tanggal_tiba))    $missing[] = 'Tgl Tiba';
        if (empty($r->waktu_tiba))      $missing[] = 'Jam Tiba';
        if (empty($r->tanggal_bongkar)) $missing[] = 'Tgl Bongkar';
        if (empty($r->waktu_bongkar))   $missing[] = 'Jam Bongkar';

        $overdue = ($estimasi && !$blocked) ? ($estimasi < $today) : false;
        if (!$missing) {
            $kel = 'Lengkap';
        } elseif (!empty($r->tanggal_tiba) && empty($r->tanggal_bongkar)) {
            $kel = 'Sampai Tujuan (belum bongkar)';
        } elseif (!$overdue) {
            $kel = '- (belum jatuh tempo)';
        } else {
            $kel = 'Belum lengkap: ' . implode(', ', $missing);
        }

        $this->cr = $r;
        return $this->cv = [
            'keluar'   => $keluar,
            'estimasi' => $estimasi,
            'blocked'  => $blocked,
            'label'    => $label,
            'tiba'     => $tiba,
            'lama'     => $lama,
            'alert'    => $alert,
            'cat'      => $cat,
            'sb'       => $sb,
            'kel'      => $kel,
        ];
    }

    // ============================================================
    // KOLOM: [heading, format|null, closure($r)]
    // Kolom tampilan HARUS di depan, kolom bantu "Rk ..." di belakang
    // (MonitoringFastExport mengandalkan urutan ini).
    // ============================================================
    public function columns(): array
    {
        if ($this->cols !== null) return $this->cols;

        $c = fn($r) => $this->calc($r);

        // wakil grup no_shipment + tujuan = baris pertama (id terkecil)
        $gw = function ($r) {
            $k = $this->sk($r) . '|' . ($r->tujuan ?? '');
            $this->seenGrp[$k] ??= $r->id;
            return $this->seenGrp[$k] === $r->id;
        };
        $f = fn(callable $cond) => fn($r) => ($gw($r) && $cond($r)) ? 1 : 0;

        $num  = fn($col) => fn($r) => is_numeric($r->{$col} ?? null) ? (float) $r->{$col} : '';
        $num0 = fn($col) => fn($r) => (float) ($r->{$col} ?? 0);

        return $this->cols = [
            // ---------- kolom tampilan (urutan sama dengan halaman) ----------
            ['Tanggal Keluar Gudang', self::FMT_DATE, fn($r) => $c($r)['blocked'] ? $c($r)['label'] : $this->xl($c($r)['keluar'])],
            ['Act PGI Date',      self::FMT_DATE, fn($r) => $this->xl($r->act_pgi_date)],
            ['Dist Channel',      null, fn($r) => $this->key($r->dist_channel)],
            ['Area',              null, fn($r) => $this->key($r->area)],
            ['No Shipment',       '@',  fn($r) => (string) $r->no_shipment],
            ['Tujuan',            null, fn($r) => $this->key($r->tujuan)],
            ['Ekspedisi',         null, fn($r) => $this->key($r->ekpedisi)],
            ['PIC',               null, fn($r) => $this->key($r->pic_monitoring)],
            ['Status',            null, fn($r) => $this->key($r->status_kendaraan)],
            ['Alert',             null, fn($r) => $c($r)['alert']],
            ['Total DO Qty',      '#,##0', $num('total_do_qty_car')],
            ['Selisih Qty Do',    '#,##0', $num('selisih_qty')],
            ['Biaya Kuli',        '"Rp" #,##0', $num('biaya_kuli')],
            ['Total Biaya Kuli',  '"Rp" #,##0', $num('total_biaya_kuli')],
            ['Qty Actual Do',     '#,##0', $num('qty_monitoring')],
            ['Reason Qty',        null, fn($r) => $r->remarks_qty],
            ['Urutan Bongkar',    null, fn($r) => $r->act_urutan_bongkar],
            ['Estimasi Tiba',     self::FMT_DATE, fn($r) => $c($r)['blocked'] ? $c($r)['label'] : $this->xl($c($r)['estimasi'])],
            ['Tanggal Tiba',      self::FMT_DATE, fn($r) => $this->xl($r->tanggal_tiba)],
            ['Jam Tiba',          null, fn($r) => $this->jam($r->waktu_tiba)],
            ['Lama Perjalanan',   null, fn($r) => $c($r)['lama'] ?? '-'],
            ['SLA Tiba',          null, fn($r) => $r->sla_tiba ?? '-'],
            ['Tanggal Bongkar',   self::FMT_DATE, fn($r) => $this->xl($r->tanggal_bongkar)],
            ['Jam Bongkar',       null, fn($r) => $this->jam($r->waktu_bongkar)],
            ['Status Bongkar',    null, fn($r) => $c($r)['sb']],
            ['Overstay',          null, fn($r) => $r->overstay_days ?? '-'],
            ['SLA Bongkar',       null, fn($r) => $r->sla_bongkar ?? '-'],
            ['Reason Tiba',       null, fn($r) => $r->reason_tiba],
            ['Reason Bongkar',    null, fn($r) => $r->reason_bongkar],
            ['Remarks',           null, fn($r) => $r->remarks],
            ['Nama Kapal',        null, fn($r) => $r->nama_kapal],
            ['ETD',               self::FMT_DATE, fn($r) => $this->xl($r->etd)],
            ['ETA',               self::FMT_DATE, fn($r) => $this->xl($r->eta)],
            ['ATD',               self::FMT_DATE, fn($r) => $this->xl($r->atd)],
            ['ATA',               self::FMT_DATE, fn($r) => $this->xl($r->ata)],
            ['Kelengkapan Data',  null, fn($r) => $c($r)['kel']],

            // ---------- kolom tambahan ----------
            ['Periode Shipment',  null, fn($r) => $this->key($r->periode_shipment, true)],
            ['Tanggal Shipment',  self::FMT_DATE, fn($r) => $this->xl($r->tanggal_shipment)],
            ['Bulan Keluar',      null, fn($r) => $c($r)['keluar'] ? date('Y-m', $c($r)['keluar']) : '(kosong)'],
            ['Sales Document',    '@',  fn($r) => (string) ($r->sales_document ?? '')],
            ['Customer Reference', '@', fn($r) => (string) ($r->customer_reference ?? '')],
            ['No DO',             '@',  fn($r) => (string) ($r->no_do ?? '')],
            ['Contract End Date', self::FMT_DATE, fn($r) => $this->xl($r->contract_end_date)],
            ['Qty Total DO Car',  '#,##0', $num('qty_total_do_car')],
            ['Nilai Muatan',      '"Rp" #,##0', $num('nilai_muatan')],
            ['Kirim Kontribusi',  '"Rp" #,##0', $num('kirim_kontribusi')],
            ['Total Kubik',       '#,##0.00', $num('total_kubik')],
            ['Total Tonase',      '#,##0.00', $num('total_tonase')],

            // ---------- kolom bantu (Rk) ----------
            ['Rk Tampil',  null, fn($r) => ''],   // diganti rumus SUBTOTAL
            ['Rk Grup',    null, fn($r) => $gw($r) ? 1 : 0],
            ['Rk Sudah Tiba',        null, $f(fn($r) => $c($r)['tiba'] !== null)],
            ['Rk Belum Tiba',        null, $f(fn($r) => $c($r)['tiba'] === null)],
            ['Rk Pending Tiba',      null, $f(fn($r) => $c($r)['cat'] === 'pending')],
            ['Rk Mendekati',         null, $f(fn($r) => $c($r)['cat'] === 'mendekati')],
            ['Rk On Track',          null, $f(fn($r) => $c($r)['cat'] === 'ontrack')],
            ['Rk Gudang Berikutnya', null, $f(fn($r) => $c($r)['cat'] === 'blocked')],
            ['Rk Sudah Bongkar',     null, $f(fn($r) => !empty($r->tanggal_bongkar))],
            ['Rk Pending Bongkar',   null, $f(fn($r) => str_starts_with($c($r)['sb'], 'Pending Bongkar'))],
            ['Rk Data Lengkap',      null, $f(fn($r) => $c($r)['kel'] === 'Lengkap')],
            ['Rk In Transit',        null, $f(fn($r) => $c($r)['keluar'] !== null && !$c($r)['blocked'] && $c($r)['tiba'] === null)],
            ['Rk Lama Ada',          null, $f(fn($r) => $c($r)['lama'] !== null)],
            ['Rk Lama Hari',         null, fn($r) => $gw($r) ? ($c($r)['lama'] ?? 0) : 0],
            ['Rk SlaTiba Ada',       null, $f(fn($r) => $this->slaAda($r->sla_tiba))],
            ['Rk SlaTiba Delay',     null, $f(fn($r) => $this->slaDelay($r->sla_tiba))],
            ['Rk SlaBongkar Ada',    null, $f(fn($r) => $this->slaAda($r->sla_bongkar))],
            ['Rk SlaBongkar Delay',  null, $f(fn($r) => $this->slaDelay($r->sla_bongkar))],

            // status akhir (per grup)
            ['Rk Status OT',            null, $f(fn($r) => $r->status_akhir === 'On Time Total')],
            ['Rk Status Delay Perj',    null, $f(fn($r) => $r->status_akhir === 'Delay Perjalanan')],
            ['Rk Status Delay Bongkar', null, $f(fn($r) => $r->status_akhir === 'Delay Pembongkaran')],
            ['Rk Status Delay Total',   null, $f(fn($r) => $r->status_akhir === 'Delay Total')],

            // angka per DO (sengaja TIDAK di-gate wakil grup: jumlah semua DO = jumlah grup)
            ['Rk Total DO Qty', null, $num0('total_do_qty_car')],
            ['Rk Selisih Qty',  null, $num0('selisih_qty')],
            ['Rk Qty Actual',   null, $num0('qty_monitoring')],
            ['Rk Total Kuli',   null, $num0('total_biaya_kuli')],
        ];
    }

    public function headings(): array
    {
        return array_map(fn($col) => $col[0], $this->columns());
    }

    public function map($r): array
    {
        return array_map(fn($col) => ($col[2])($r), $this->columns());
    }

    private function L(string $head): string
    {
        $i = array_search($head, $this->headings(), true);
        if ($i === false) {
            throw new \RuntimeException("Kolom '{$head}' tidak ada di sheet Data.");
        }
        return Coordinate::stringFromColumnIndex($i + 1);
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

    // ---------- API untuk MonitoringReportData / FastExport / controller ----------
    /** heading kolom Data => [judul tabel Ringkasan, kolom DB / ekspresi SQL] */
    private function dims(): array
    {
        return [
            // PIC HARUS pertama
            'PIC'              => ['PER PIC',              'pic_monitoring'],
            'Area'             => ['PER AREA',             'area'],
            'Dist Channel'     => ['PER DIST CHANNEL',     'dist_channel'],
            'Ekspedisi'        => ['PER EKSPEDISI',        'ekpedisi'],
            'Bulan Keluar'     => ['PER BULAN KELUAR',     "DATE_FORMAT({$this->keluarSql()}, '%Y-%m')"],
            'Periode Shipment' => ['PER PERIODE SHIPMENT', 'periode_shipment'],
            'Status'           => ['PER STATUS',           'status_kendaraan'],
            'Tujuan'           => ['KANDIDAT TUJUAN (TOP 300, sumber Top 20 Dashboard)', 'tujuan'],
        ];
    }

    /** heading kolom Data => judul tabel */
    public function dimensions(): array
    {
        return array_map(fn($d) => $d[0], $this->dims());
    }

    /** nilai unik tiap dimensi (sama persis dengan isi kolom di sheet Data) */
    public function distinctKeys(string $head): array
    {
        if (isset($this->keysCache[$head])) return $this->keysCache[$head];

        $expr = $this->dims()[$head][1] ?? null;
        if ($expr === null) return $this->keysCache[$head] = [];

        // Tujuan: hanya 300 tujuan dengan grup (shipment + tujuan) terbanyak
        if ($head === 'Tujuan') {
            return $this->keysCache[$head] = $this->base()
                ->selectRaw("COALESCE(NULLIF(TRIM(tujuan),''),'') AS k,
                    COUNT(DISTINCT COALESCE(NULLIF(TRIM(no_shipment),''), CONCAT('id',id))) AS jml")
                ->groupByRaw('k')->orderByDesc('jml')->orderBy('k')->limit(300)->pluck('k')
                ->map(fn($v) => trim((string) $v) === '' ? '(kosong)' : trim((string) $v))
                ->unique()->values()->all();
        }

        return $this->keysCache[$head] = $this->base()
            ->selectRaw("COALESCE(NULLIF(TRIM({$expr}),''),'') AS k")->distinct()->orderBy('k')->pluck('k')
            ->map(function ($v) use ($head) {
                $v = trim((string) $v);
                if ($v === '' || ($head === 'Bulan Keluar' && $v === '1900-01')) return '(kosong)';
                return $head === 'Periode Shipment' ? 'Periode ' . $v : $v;
            })->unique()->values()->all();
    }

    public function totalRows(): int
    {
        return $this->nRows ?: ($this->nRows = (int) $this->base()->count());
    }

    public function letter(string $head): string
    {
        return $this->L($head);
    }

    /** huruf kolom => format angka/tanggal */
    public function columnFormats(): array
    {
        $out = [];
        foreach ($this->columns() as $i => [, $fmt]) {
            if ($fmt !== null) $out[Coordinate::stringFromColumnIndex($i + 1)] = $fmt;
        }
        return $out;
    }

    // ============================================================
    // BUILD
    // ============================================================
    /** @return array [jumlah baris data, heading] */
    public function build(string $path): array
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $this->nRows = (int) $this->base()->count();
        $heads = $this->headings();

        $o = new Options();
        $o->setColumnWidthForRange(18, 1, count($heads));
        $w = new Writer($o);
        $w->openToFile($path);

        $hs = (new Style())->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('0284C7');
        $hd = (new Style())->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('1F2937');
        $row = fn(array $v, ?Style $s = null) => new Row(array_map(fn($x) => Cell::fromValue($x, $s), $v));

        $sPct2 = (new Style())->withFormat('0.00"%"');
        $sDec1 = (new Style())->withFormat('0.0');
        $sInt  = (new Style())->withFormat('#,##0');
        $sRp   = (new Style())->withFormat('"Rp" #,##0');
        $sHide = (new Style())->withFormat('#,##0;-#,##0;;');   // angka 0 dikosongkan
        $rowS  = fn(array $v, array $st) => new Row(array_map(
            fn($x, $i) => Cell::fromValue($x, $st[$i] ?? null),
            $v,
            array_keys($v)
        ));
        $gT = (new Style())->withFontBold(true)->withBackgroundColor('E5E7EB');

        // ================= SHEET 1: RINGKASAN (tabel saja, tanpa chart) =================
        $w->getCurrentSheet()->setName('Ringkasan');
        $w->addRow($row(['RINGKASAN MONITORING']));
        $w->addRow($row(['Angka ikut berubah saat slicer / filter tabel dipakai (dihitung per shipment + tujuan). Grafik & slicer ada di sheet Dashboard.']));
        $w->addRow($row([]));

        $labels = array_keys(self::METRICS);
        $w->addRow($row($labels, $hs));
        $w->addRow($row(array_map(fn($fl) => '=' . $this->sumifs($fl), array_values(self::METRICS))));
        $w->addRow($row([]));
        $r = 7;

        $dims = $this->dims();
        $keys = [];
        foreach (array_keys($dims) as $head) $keys[$head] = $this->distinctKeys($head);

        $nM = count(self::METRICS);
        $blockFirst = [];
        $totStyles = array_fill(0, $nM + 1, $gT);

        foreach ($dims as $head => [$judul]) {
            if (!$keys[$head]) continue;

            $isTujuan = $head === 'Tujuan';

            $w->addRow($row([$judul]));
            $r++;
            // blok Tujuan punya kolom tambahan "Kunci" (kolom M) untuk peringkat Top 20 di Dashboard
            $w->addRow($row(array_merge([''], $labels, $isTujuan ? ['Kunci'] : []), $hd));
            $r++;
            $blockFirst[$head] = $r;

            foreach ($keys[$head] as $k) {
                $cells = [$k];
                foreach (self::METRICS as $fl) $cells[] = '=' . $this->sumifs($fl, $head, "\$A{$r}");
                if ($isTujuan) {
                    // Total Grup + tie-breaker kecil supaya nilai unik (LARGE/MATCH tidak bentrok)
                    $cells[] = "=B{$r}+(100000-ROW())/10000000";
                    $w->addRow($rowS($cells, array_fill(1, $nM, $sHide)));
                } else {
                    $w->addRow($row($cells));
                }
                $r++;
            }

            $cells = ['GRAND TOTAL'];
            foreach (self::METRICS as $fl) $cells[] = '=' . $this->sumifs($fl);
            $w->addRow($rowS($cells, $totStyles));
            $r++;

            $w->addRow($row([]));
            $w->addRow($row([]));
            $r += 2;
        }

        // ---------- lead time & SLA per PIC ----------
        $w->addRow($row(['LEAD TIME & SLA PER PIC']));
        $r++;
        $w->addRow($row([
            'PIC',
            'Lama Perjalanan Tercatat',
            'Rata² Lama Perjalanan (hari)',
            'SLA Tiba Tercatat',
            'SLA Tiba Delay',
            '% SLA Tiba OK',
            'SLA Bongkar Tercatat',
            'SLA Bongkar Delay',
            '% SLA Bongkar OK',
        ], $hd));
        $r++;
        $slaHdr   = $r - 1;
        $slaFirst = $r;
        foreach (array_merge($keys['PIC'], [null]) as $k) {
            $s = fn($fl) => $this->sumifs($fl, $k === null ? null : 'PIC', $k === null ? null : "\$A{$r}");
            $w->addRow($rowS([
                $k ?? 'TOTAL',
                '=' . $s('Rk Lama Ada'),
                "=IF(B{$r}>0," . $s('Rk Lama Hari') . "/B{$r},0)",
                '=' . $s('Rk SlaTiba Ada'),
                '=' . $s('Rk SlaTiba Delay'),
                "=IF(D{$r}>0,(D{$r}-E{$r})/D{$r}*100,0)",
                '=' . $s('Rk SlaBongkar Ada'),
                '=' . $s('Rk SlaBongkar Delay'),
                "=IF(G{$r}>0,(G{$r}-H{$r})/G{$r}*100,0)",
            ], [2 => $sDec1, 5 => $sPct2, 8 => $sPct2]));
            $r++;
        }
        $w->addRow($row([]));
        $w->addRow($row([]));
        $r += 2;

        // ---------- status akhir, qty & biaya kuli per PIC ----------
        $w->addRow($row(['STATUS AKHIR, QTY & BIAYA KULI PER PIC']));
        $r++;
        $w->addRow($row([
            'PIC',
            'On Time Total',
            'Delay Perjalanan',
            'Delay Pembongkaran',
            'Delay Total',
            'Total DO Qty',
            'Selisih Qty',
            'Qty Actual',
            'Total Biaya Kuli',
        ], $hd));
        $r++;
        $stHdr   = $r - 1;
        $stFirst = $r;
        foreach (array_merge($keys['PIC'], [null]) as $k) {
            $s = fn($fl) => $this->sumifs($fl, $k === null ? null : 'PIC', $k === null ? null : "\$A{$r}");
            $w->addRow($rowS([
                $k ?? 'TOTAL',
                '=' . $s('Rk Status OT'),
                '=' . $s('Rk Status Delay Perj'),
                '=' . $s('Rk Status Delay Bongkar'),
                '=' . $s('Rk Status Delay Total'),
                '=' . $s('Rk Total DO Qty'),
                '=' . $s('Rk Selisih Qty'),
                '=' . $s('Rk Qty Actual'),
                '=' . $s('Rk Total Kuli'),
            ], [5 => $sInt, 6 => $sInt, 7 => $sInt, 8 => $sRp]));
            $r++;
        }

        // ================= SHEET 2: DATA =================
        $w->addNewSheetAndMakeItCurrent();
        $w->getCurrentSheet()->setName('Data');
        $w->addRow($row($heads, $hs));

        $styles = [];
        foreach ($this->columns() as $i => [, $fmt]) {
            if ($fmt !== null) $styles[$i] = (new Style())->withFormat($fmt);
        }
        $iTampil = array_search('Rk Tampil', $heads, true);
        $cGrup   = $this->L('Rk Grup');
        $n = 1;

        // semua baris, urut id; wakil grup ditentukan di columns() ($gw)
        $this->base()->chunkById(5000, function ($rows) use ($w, $styles, $iTampil, $cGrup, &$n) {
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

        // ================= SHEET 3: DASHBOARD (chart + slicer) =================
        // Chart memakai referensi lokal sheet ini, jadi data sumbernya disalin
        // ke Dashboard lewat rumus (=Ringkasan!...). Tetap ikut slicer.
        $nP = count($keys['PIC'] ?? []);

        $mc  = fn(string $label) => Coordinate::stringFromColumnIndex(array_search($label, $labels, true) + 2);
        $rng = fn(string $col, int $a, int $cnt) => "\${$col}\${$a}:\${$col}\$" . ($a + $cnt - 1);
        $ser = fn(string $col, int $hdr, int $a, int $cnt, string $color) => [
            'name' => "\${$col}\${$hdr}", 'val' => $rng($col, $a, $cnt), 'color' => $color,
        ];

        $w->addNewSheetAndMakeItCurrent();
        $w->getCurrentSheet()->setName('Dashboard');
        $w->addRow($row(['DASHBOARD MONITORING']));
        $w->addRow($row(['Pakai slicer di kanan atas. Tabel di sheet ini adalah sumber grafik (otomatis, jangan diubah).']));
        $w->addRow($row([]));
        $w->addRow($row(['Top 20 Tujuan', 'Total Grup', 'Sudah Tiba', 'Belum Tiba', 'Kunci'], $hd));
        $dr = 4;   // nomor baris terakhir yang sudah ditulis di Dashboard

        // Top 20 Tujuan (A5:E24)
        $topN      = 20;
        $dashFirst = 5;
        $hasTop    = !empty($blockFirst['Tujuan']);
        if ($hasTop) {
            $a   = $blockFirst['Tujuan'];
            $b   = $a + count($keys['Tujuan']) - 1;
            $key = "Ringkasan!\$M\${$a}:\$M\${$b}";
            for ($i = 1; $i <= $topN; $i++) {
                $rr   = $dashFirst + $i - 1;
                $pick = fn($col) => "=IF(\$E{$rr}<1,\"\",INDEX(Ringkasan!\${$col}\${$a}:\${$col}\${$b},MATCH(\$E{$rr},{$key},0)))";
                $w->addRow($row([
                    $pick('A'), $pick('B'), $pick('C'), $pick('D'),
                    "=LARGE({$key},{$i})",
                ]));
            }
            $dr += $topN;
        }
        $w->addRow($row([]));
        $dr++;

        // tabel cermin: salin blok Ringkasan ke Dashboard. Return [baris header, baris data pertama, jumlah baris]
        $mirror = function (string $title, int $hdr, int $first, int $cnt, int $ncols, array $st = []) use ($w, $row, $rowS, $hd, &$dr): array {
            $L = fn(int $i) => Coordinate::stringFromColumnIndex($i);

            $w->addRow($row([$title]));
            $dr++;

            $h = [''];
            for ($c = 2; $c <= $ncols; $c++) $h[] = '=Ringkasan!' . $L($c) . $hdr;
            $w->addRow($row($h, $hd));
            $dr++;
            $hRow = $dr;

            for ($i = 0; $i < $cnt; $i++) {
                $cells = [];
                for ($c = 1; $c <= $ncols; $c++) $cells[] = '=Ringkasan!' . $L($c) . ($first + $i);
                $w->addRow($st ? $rowS($cells, $st) : $row($cells));
                $dr++;
            }

            $w->addRow($row([]));
            $dr++;

            return [$hRow, $hRow + 1, $cnt];
        };

        $mir = [];
        if ($nP > 0 && isset($blockFirst['PIC'])) {
            $mir['PIC'] = $mirror('PER PIC', $blockFirst['PIC'] - 1, $blockFirst['PIC'], $nP, $nM + 1);
            $mir['SLA'] = $mirror('LEAD TIME & SLA PER PIC', $slaHdr, $slaFirst, $nP, 9, [2 => $sDec1, 5 => $sPct2, 8 => $sPct2]);
            $mir['ST']  = $mirror('STATUS AKHIR, QTY & BIAYA KULI PER PIC', $stHdr, $stFirst, $nP, 9, [5 => $sInt, 6 => $sInt, 7 => $sInt, 8 => $sRp]);
        }
        foreach (['Area', 'Bulan Keluar'] as $dim) {
            $nD = count($keys[$dim] ?? []);
            if ($nD > 0 && isset($blockFirst[$dim])) {
                $mir[$dim] = $mirror('PER ' . strtoupper($dim), $blockFirst[$dim] - 1, $blockFirst[$dim], $nD, $nM + 1);
            }
        }

        $w->close();

        // ================= INJECT: tabel, lalu chart =================
        // 1) tabel tblLogistik di sheet Data (nama dipakai XlsxSlicerInjector)
        XlsxTableInjector::add($path, 'Data', 'tblLogistik', $heads, $n);

        // 2) semua chart di sheet Dashboard
        $charts = [];

        if ($hasTop) {
            $charts[] = [
                'title'  => 'Top 20 Tujuan: Sudah Tiba vs Belum Tiba',
                'cat'    => '$A$5:$A$24',
                'series' => [
                    ['name' => '$C$4', 'val' => '$C$5:$C$24', 'color' => '0284C7'],
                    ['name' => '$D$4', 'val' => '$D$5:$D$24', 'color' => 'F59E0B'],
                ],
                'anchor' => [6, 3, 14, 23],
            ];
        }

        if (isset($mir['PIC'], $mir['SLA'], $mir['ST'])) {
            [$ph, $pf] = $mir['PIC'];
            [$sh, $sf] = $mir['SLA'];
            [$th, $tf] = $mir['ST'];
            $catPic = $rng('A', $pf, $nP);

            $charts[] = ['title' => 'Sudah Tiba vs Belum Tiba per PIC', 'cat' => $catPic, 'series' => [
                $ser($mc('Sudah Tiba'), $ph, $pf, $nP, '16A34A'),
                $ser($mc('Belum Tiba'), $ph, $pf, $nP, 'F59E0B'),
            ]];
            $charts[] = ['title' => 'Sudah Bongkar vs Pending Bongkar per PIC', 'cat' => $catPic, 'series' => [
                $ser($mc('Sudah Bongkar'), $ph, $pf, $nP, '0284C7'),
                $ser($mc('Pending Bongkar'), $ph, $pf, $nP, 'DC2626'),
            ]];
            $charts[] = ['title' => 'SLA Tiba: Tercatat vs Delay per PIC', 'cat' => $rng('A', $sf, $nP), 'series' => [
                $ser('D', $sh, $sf, $nP, '16A34A'),
                $ser('E', $sh, $sf, $nP, 'DC2626'),
            ]];
            $charts[] = ['title' => 'SLA Bongkar: Tercatat vs Delay per PIC', 'cat' => $rng('A', $sf, $nP), 'series' => [
                $ser('G', $sh, $sf, $nP, '0284C7'),
                $ser('H', $sh, $sf, $nP, 'DC2626'),
            ]];
            $charts[] = ['title' => 'Alert Belum Tiba per PIC', 'cat' => $catPic, 'series' => [
                $ser($mc('Pending Tiba'), $ph, $pf, $nP, 'DC2626'),
                $ser($mc('Mendekati (H-7)'), $ph, $pf, $nP, 'F59E0B'),
                $ser($mc('On Track'), $ph, $pf, $nP, '16A34A'),
                $ser($mc('Di Gudang Berikutnya'), $ph, $pf, $nP, '6B7280'),
            ]];
            $charts[] = ['title' => 'In Transit per PIC', 'cat' => $catPic, 'series' => [
                $ser($mc('In Transit'), $ph, $pf, $nP, '7C3AED'),
            ]];
            $charts[] = ['title' => 'Status Akhir per PIC', 'cat' => $rng('A', $tf, $nP), 'series' => [
                $ser('B', $th, $tf, $nP, '16A34A'),
                $ser('C', $th, $tf, $nP, 'F59E0B'),
                $ser('D', $th, $tf, $nP, 'EA580C'),
                $ser('E', $th, $tf, $nP, 'DC2626'),
            ]];
        }

        foreach ([
            ['Area',         'Sudah Tiba vs Belum Tiba per Area',              'Sudah Tiba', 'Belum Tiba'],
            ['Area',         'In Transit & Pending Bongkar per Area',          'In Transit', 'Pending Bongkar'],
            ['Bulan Keluar', 'Total Grup vs Sudah Bongkar per Bulan Keluar',   'Total Grup', 'Sudah Bongkar'],
        ] as [$dim, $judul, $m1, $m2]) {
            if (!isset($mir[$dim])) continue;
            [$dh, $df, $nD] = $mir[$dim];
            $charts[] = ['title' => $judul, 'cat' => $rng('A', $df, $nD), 'series' => [
                $ser($mc($m1), $dh, $df, $nD, '0284C7'),
                $ser($mc($m2), $dh, $df, $nD, 'F59E0B'),
            ]];
        }

        // chart selain Top 20: kolom O..U, ditumpuk ke bawah mulai baris 26 (20 baris per chart).
        // Slicer ada di kolom V ke kanan, jadi tidak bertabrakan.
        $slot = 0;
        foreach ($charts as &$ch) {
            if (isset($ch['anchor'])) continue;
            $ch['anchor'] = [14, 25 + 20 * $slot, 20, 43 + 20 * $slot];
            $slot++;
        }
        unset($ch);

        if ($charts) {
            try {
                XlsxChartInjector::add($path, 'Dashboard', $charts);
            } catch (\Throwable $e) {
                report($e);   // chart gagal, export tetap jalan
            }
        }

        return [$n - 1, $heads];
    }
}