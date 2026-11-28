<?php

namespace App\Exports;

use App\Exports\Concerns\UrutanKolomWeb;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * EXPORT FULL data logistik_pengiriman (tanpa composer, tanpa index baru)
 *
 * Urutan & label kolom mengikuti tabel web (lihat UrutanKolomWeb):
 *   baseColumns() = definisi kolom apa adanya (nama DB + [Hitung] ...)
 *   columns()     = baseColumns() yang sudah diurutkan/diberi label seperti tabel web
 *
 * Jalur CEPAT (dipakai controller):
 *   - writeXlsx($path)  : XLSX ditulis langsung (XML streaming + ZipArchive), tanpa PhpSpreadsheet
 *   - rows()            : generator keyset (WHERE id > last ORDER BY id LIMIT n), tanpa OFFSET / filesort
 *   - CSV               : controller memakai headings() + rows() + map()
 *
 * Jalur LAMA (FromQuery/WithMapping dst.) tetap ada supaya kode lain tidak putus.
 *
 * Agregat per shipment (optimal, CR shipment, fallback kapal/etd/eta/...) dihitung 1 query
 * dari subquery data yang sudah terfilter (bukan scan seluruh tabel).
 */
class LogistikExportFull implements
    FromQuery, WithHeadings, WithMapping, WithColumnFormatting, WithStyles, WithCustomChunkSize
{
    use UrutanKolomWeb;

    protected $builder;
    private ?array $cols = null;
    private ?array $agg = null;
    private ?array $crShip = null;
    private ?array $tblAll = null;
    private ?array $rawColsCache = null;

    public ?string $tabel = null;     // null = logistik_pengiriman
    private ?array $aggScope = null;

    public bool $csv = false;         // true: tanggal jadi teks d-m-Y (untuk CSV)
    public bool $skipCalc = false;    // true: hanya kolom database (tanpa kolom [Hitung]) -> jauh lebih cepat

    // cache konversi (jumlah nilai unik kecil, dipanggil jutaan kali)
    private array $cD = [];   // tanggal -> serial/teks
    private array $cT = [];   // tanggal+jam -> serial/teks
    private array $cS = [];   // string -> timestamp
    private array $cY = [];   // timestamp -> awal hari
    private int $tzOff = 0;

    /** kolom level shipment: kalau DO ini kosong, ambil dari DO lain di shipment yang sama */
    private const SHIPMENT_FIELDS = ['nama_kapal', 'etd', 'eta', 'atd', 'ata', 'act_pgi_date'];

    /**
     * Semua kolom tabel, urut sesuai database.
     * tipe: text | ftext (paksa teks) | num | numt (angka kalau numerik, else teks)
     *       money | dt (tanggal+jam) | date | time
     */
    private const RAW = [
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
        ['kota', 'text'],
        ['tanggal_shipment', 'date'],
        ['periode_shipment', 'ftext'],

        ['planning_loading_4', 'dt'], ['tanggal_tiba_gudang_4', 'dt'], ['tanggal_keluar_gudang_4', 'dt'],
        ['waktu_tiba_gudang_4', 'time'], ['waktu_keluar_gudang_4', 'time'],
        ['reason_gudang_4', 'text'], ['lama_digudang_4', 'text'], ['status_gudang_4', 'text'], ['sla_loading_4', 'text'],

        ['planning_loading_5', 'dt'], ['tanggal_tiba_gudang_5', 'dt'], ['tanggal_keluar_gudang_5', 'dt'],
        ['waktu_tiba_gudang_5', 'time'], ['waktu_keluar_gudang_5', 'time'],
        ['reason_gudang_5', 'text'], ['lama_digudang_5', 'text'], ['status_gudang_5', 'text'], ['sla_loading_5', 'text'],
    ];

    public function __construct($builder)
    {
        $this->builder = $builder;
        $this->tzOff   = (int) date('Z');
    }

    // ============================================================
    // TABEL / KOLOM
    // ============================================================
    protected function tblHas(string $c): bool
    {
        $this->tblAll ??= array_flip(Schema::getColumnListing($this->tabel ?: 'logistik_pengiriman'));
        return isset($this->tblAll[$c]);
    }

    /** RAW yang benar-benar ada di tabel (yang belum ada dilewati, tidak error) */
    protected function rawCols(): array
    {
        return $this->rawColsCache ??= array_values(array_filter(self::RAW, fn($x) => $this->tblHas($x[0])));
    }

    public function rawCount(): int
    {
        return count($this->rawCols());
    }

    /** dipakai subclass (LogistikReportData): tabel kerja dengan alias */
    protected function source(string $alias)
    {
        return DB::table(($this->tabel ?: 'logistik_pengiriman') . " AS {$alias}");
    }

    private function g($r, string $k)
    {
        return $r->{$k} ?? null;
    }

    public function scopeAggregates(?array $noShipments): void
    {
        $this->aggScope = $noShipments;
        $this->agg = null;
        $this->crShip = null;
    }

    public function chunkSize(): int
    {
        return 5000;
    }

    // ============================================================
    // PEMBACA BARIS (keyset, tanpa OFFSET, tanpa sort ulang)
    // ============================================================
    public function rows(int $size = 3000): \Generator
    {
        $last = 0;
        while (true) {
            $batch = (clone $this->builder)
                ->where('id', '>', $last)
                ->orderBy('id')
                ->limit($size)
                ->get();

            if ($batch->isEmpty()) {
                return;
            }

            foreach ($batch as $r) {
                yield $r;
            }

            if ($batch->count() < $size) {
                return;
            }
            $last = $batch->last()->id;
            unset($batch);
        }
    }

    /** dipakai jalur lama (Maatwebsite) */
    public function query()
    {
        return (clone $this->builder)->orderBy('id');
    }

    // ============================================================
    // DEFINISI KOLOM [heading, tipe, closure]
    // ============================================================

    /**
     * Kolom yang dipakai export: baseColumns() diurutkan & diberi label seperti tabel web.
     * Kolom yang tidak masuk urutan web tetap ikut, ditaruh setelahnya.
     */
    protected function columns(): array
    {
        return $this->cols ??= $this->urutWeb($this->baseColumns());
    }

    /**
     * Kolom apa adanya: nama DB (urut RAW) lalu kolom "[Hitung] ...".
     * Subclass yang perlu menyaring berdasarkan nama DB memakai method ini.
     */
    protected function baseColumns(): array
    {
        $c = [];

        // ---------- BAGIAN 1: KOLOM DATABASE ----------
        foreach ($this->rawCols() as [$col, $type]) {
            $c[] = [$col, $type, $this->rawGetter($col, $type, in_array($col, self::SHIPMENT_FIELDS, true))];
        }

        if ($this->skipCalc) {
            return $c;
        }

        // ---------- BAGIAN 2: HASIL HITUNGAN OTOMATIS ----------
        $c[] = ['[Hitung] Update Posisi Mobil',    'text', fn($r) => $this->statusPengiriman($r)];
        $c[] = ['[Hitung] Ketersediaan Unit',      'text', fn($r) => (empty($r->rencana_kirim) || empty($r->tanggal_dpt_unit)) ? 'BELUM DAPAT' : 'SUDAH DAPAT'];
        $c[] = ['[Hitung] CR (%)',                 'pct4', fn($r) => $this->computeCR($r)];
        $c[] = ['[Hitung] CR Shipment (%)',        'pct4', fn($r) => $this->crShipment()[trim((string) $r->no_shipment)] ?? 0];
        $c[] = ['[Hitung] Hasil Kubik (%)',        'pct',  fn($r) => $this->hasil($r->total_kubik ?? null, $r->kubikasi ?? null)];
        $c[] = ['[Hitung] Hasil Tonase (%)',       'pct',  fn($r) => $this->hasil($r->total_tonase ?? null, $r->tonase ?? null)];
        $c[] = ['[Hitung] Pengiriman Optimal Shipment', 'text', fn($r) => $this->optimalShipment($r)];
        $c[] = ['[Hitung] Pengiriman Optimal Customer', 'text', fn($r) => $this->optimalCustomer($r)];

        foreach (['' => 'KACS', '_2' => 'Sentul', '_3' => 'CCIE', '_4' => 'P5', '_5' => 'FIFATEX'] as $s => $n) {
            $c[] = ["[Hitung] Lama Di {$n}",     'text', fn($r) => $this->durasiGudang($this->g($r, 'tanggal_tiba_gudang' . $s), $this->g($r, 'tanggal_keluar_gudang' . $s))];
            $c[] = ["[Hitung] Status {$n}",      'text', fn($r) => $this->statusGudang($this->g($r, 'planning_loading' . $s), $this->g($r, 'tanggal_tiba_gudang' . $s))];
            $c[] = ["[Hitung] SLA Loading {$n}", 'text', fn($r) => $this->slaLoading($this->g($r, 'planning_loading' . $s), $this->g($r, 'tanggal_tiba_gudang' . $s))];
        }

        $c[] = ['[Hitung] Alert',                   'text', fn($r) => $this->estimasiAlert($r)['alert']];
        $c[] = ['[Hitung] Tanggal Estimasi',        'date', fn($r) => $this->estimasiAlert($r)['estimasi']];
        $c[] = ['[Hitung] Lama Perjalanan (Hari)',  'num',  fn($r) => $this->lamaPerjalanan($r)];
        $c[] = ['[Hitung] Status Bongkar',          'text', fn($r) => $this->statusBongkar($r)];
        $c[] = ['[Hitung] Status Akhir',            'text', fn($r) => $this->statusAkhir($r)];
        $c[] = ['[Hitung] Status Alert',            'text', fn($r) => $this->statusAlert($r)];
        $c[] = ['[Hitung] Shipping Point (Route)',  'text', fn($r) => !empty($r->route) ? explode('-', trim($r->route))[0] : '-'];
        $c[] = ['[Hitung] Estimasi Tiba Di Customer', 'date', fn($r) => $this->estimasiAdminXl($r)];
        $c[] = ['[Hitung] Ontime/Delay Admin',      'text', fn($r) => $this->statusEstimasiAdmin($r)];

        return $c;
    }

    /** getter per kolom, dispesialisasi per tipe (tanpa switch / in_array per sel) */
    private function rawGetter(string $col, string $type, bool $shp): \Closure
    {
        $get = $shp
            ? fn($r) => $this->shp($r, $col)
            : fn($r) => $r->{$col} ?? null;

        return match ($type) {
            'date'          => fn($r) => $this->xl($get($r)),
            'dt'            => fn($r) => $this->xlDt($get($r)),
            'time'          => fn($r) => $this->jam($get($r)),
            'num', 'money'  => fn($r) => $this->num($get($r)),
            'numt'          => function ($r) use ($get) {
                $v = $get($r);
                return ($v === null || $v === '') ? '' : (is_numeric($v) ? (float) $v : (string) $v);
            },
            default         => function ($r) use ($get) {
                $v = $get($r);
                return $v === null ? '' : (string) $v;
            },
        };
    }

    public function colTypes(): array
    {
        return array_map(fn($c) => $c[1], $this->columns());
    }

    // ============================================================
    // CONTRACT MAATWEBSITE (jalur lama)
    // ============================================================
    public function headings(): array
    {
        return array_map(fn($col) => $col[0], $this->columns());
    }

    public function map($r): array
    {
        $out = [];
        foreach ($this->columns() as $col) {
            $out[] = ($col[2])($r);
        }
        return $out;
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

        // urutan kolom sekarang mengikuti web (DB & [Hitung] bercampur), jadi satu warna header
        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($this->columns())) . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '03C03C']],
        ]);

        return [];
    }

    // ============================================================
    // XLSX STREAMING (tanpa PhpSpreadsheet, tanpa composer)
    // ============================================================
    public function writeXlsx(string $path): void
    {
        $this->csv = false;
        $cols  = $this->columns();
        $fns   = array_map(fn($c) => $c[2], $cols);

        // indeks style (lihat styles.xml di bawah)
        $styleOf = ['date' => 1, 'dt' => 2, 'money' => 3, 'pct' => 4, 'pct4' => 5, 'num' => 6, 'ftext' => 7, 'time' => 7];

        $empty = $numO = $strO = [];
        foreach ($cols as $i => [, $type]) {
            $s  = $styleOf[$type] ?? 0;
            $sa = $s ? ' s="' . $s . '"' : '';
            $empty[$i] = "<c{$sa}/>";
            $numO[$i]  = "<c{$sa}><v>";
            $strO[$i]  = "<c{$sa} t=\"inlineStr\"><is><t xml:space=\"preserve\">";
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $fh  = fopen($tmp, 'wb');

        fwrite($fh, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<cols><col min="1" max="' . count($cols) . '" width="20" customWidth="1"/></cols>'
            . '<sheetData>');

        // header (satu warna hijau = style 8)
        $h = '<row>';
        foreach ($cols as [$name]) {
            $h .= '<c s="8" t="inlineStr"><is><t>' . $this->esc($name) . '</t></is></c>';
        }
        fwrite($fh, $h . '</row>');

        // isi
        $buf = '';
        $n   = 0;
        foreach ($this->rows() as $r) {
            $row = '<row>';
            foreach ($fns as $i => $fn) {
                $v = $fn($r);
                if ($v === '' || $v === null) {
                    $row .= $empty[$i];
                } elseif (is_int($v) || is_float($v)) {
                    $row .= $numO[$i] . $v . '</v></c>';
                } else {
                    $row .= $strO[$i] . $this->esc((string) $v) . '</t></is></c>';
                }
            }
            $buf .= $row . '</row>';

            if (++$n % 500 === 0) {
                // buang karakter kontrol ilegal XML 1x per 500 baris (bukan per sel)
                fwrite($fh, preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $buf));
                $buf = '';
            }
        }
        if ($buf !== '') {
            fwrite($fh, preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $buf));
        }

        fwrite($fh, '</sheetData></worksheet>');
        fclose($fh);

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Gagal membuat file XLSX: ' . $path);
        }
        $zip->addFromString('[Content_Types].xml', $this->xmlContentTypes());
        $zip->addFromString('_rels/.rels', $this->xmlRootRels());
        $zip->addFromString('xl/workbook.xml', $this->xmlWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xmlWorkbookRels());
        $zip->addFromString('xl/styles.xml', $this->xmlStyles());
        $zip->addFile($tmp, 'xl/worksheets/sheet1.xml');
        if (method_exists($zip, 'setCompressionName')) {
            // level 1 = jauh lebih cepat, ukuran file naik sedikit
            $zip->setCompressionName('xl/worksheets/sheet1.xml', \ZipArchive::CM_DEFLATE, 1);
        }
        $zip->close();
        @unlink($tmp);
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function xmlContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function xmlRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function xmlWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function xmlWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xmlStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="6">'
            . '<numFmt numFmtId="164" formatCode="dd\-mm\-yyyy"/>'
            . '<numFmt numFmtId="165" formatCode="dd\-mm\-yyyy\ hh:mm"/>'
            . '<numFmt numFmtId="166" formatCode="&quot;Rp&quot;\ #,##0"/>'
            . '<numFmt numFmtId="167" formatCode="0.00&quot;%&quot;"/>'
            . '<numFmt numFmtId="168" formatCode="0.0000&quot;%&quot;"/>'
            . '<numFmt numFmtId="169" formatCode="#,##0.##"/>'
            . '</numFmts>'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF03C03C"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="10">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="167" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="168" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="169" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    // ============================================================
    // AGREGAT PER SHIPMENT (1 query, dari data yang sudah terfilter)
    //  - dipakai: optimal shipment, CR shipment, fallback kapal/etd/eta/atd/ata/pgi
    // ============================================================
    private function loadAgg(): void
    {
        $sel = ['no_shipment', 'tujuan'];
        foreach (['nilai_muatan', 'kirim_kontribusi', 'biaya_kirim', 'total_kubik', 'total_tonase', 'kubikasi', 'tonase'] as $c) {
            if ($this->tblHas($c)) {
                $sel[] = $c;
            }
        }
        foreach (self::SHIPMENT_FIELDS as $f) {
            if ($this->tblHas($f)) {
                $sel[] = $f;
            }
        }
        $has = fn(string $c) => in_array($c, $sel, true);

        $sub = (clone $this->builder)->reorder()->select($sel);

        $expr = [
            'no_shipment',
            "COALESCE(tujuan,'') AS tj",
            $has('nilai_muatan')     ? 'SUM(COALESCE(nilai_muatan,0)) AS n'           : '0 AS n',
            $has('kirim_kontribusi') ? 'SUM(kirim_kontribusi) AS k'                    : 'NULL AS k',
            $has('biaya_kirim')      ? 'MAX(COALESCE(biaya_kirim,0)) AS b'             : '0 AS b',
            $has('total_kubik')      ? 'SUM(COALESCE(total_kubik,0)) AS sk'            : '0 AS sk',
            $has('total_tonase')     ? 'SUM(COALESCE(total_tonase,0)) AS st'           : '0 AS st',
            $has('kubikasi')         ? 'MAX(kubikasi) AS kb'                           : '0 AS kb',
            $has('tonase')           ? 'MAX(tonase) AS tn'                             : '0 AS tn',
        ];
        foreach (self::SHIPMENT_FIELDS as $f) {
            $expr[] = $has($f) ? "MAX(NULLIF(TRIM(`{$f}`),'')) AS f_{$f}" : "NULL AS f_{$f}";
        }

        $q = DB::query()->fromSub($sub, 't')
            ->selectRaw(implode(',', $expr))
            ->whereNotNull('no_shipment')
            ->where('no_shipment', '!=', '');

        if ($this->aggScope !== null) {
            $q->whereIn('no_shipment', $this->aggScope);
        }

        $agg = [];
        $tot = [];

        foreach ($q->groupByRaw("no_shipment, COALESCE(tujuan,'')")->cursor() as $x) {
            $k = trim((string) $x->no_shipment);

            // CR shipment: per (shipment,tujuan) pakai kontribusi kalau ada, selain itu biaya kirim; lalu dijumlah per shipment
            $tot[$k]['n'] = ($tot[$k]['n'] ?? 0) + (float) $x->n;
            $tot[$k]['b'] = ($tot[$k]['b'] ?? 0) + (float) ($x->k ?? $x->b);

            if (!isset($agg[$k])) {
                $agg[$k] = ['sum_kubik' => 0.0, 'sum_tonase' => 0.0, 'kubikasi' => 0.0, 'tonase' => 0.0];
                foreach (self::SHIPMENT_FIELDS as $f) {
                    $agg[$k]['f_' . $f] = null;
                }
            }
            $a = &$agg[$k];
            $a['sum_kubik']  += (float) $x->sk;
            $a['sum_tonase'] += (float) $x->st;
            $a['kubikasi']    = max($a['kubikasi'], (float) $x->kb);
            $a['tonase']      = max($a['tonase'], (float) $x->tn);
            foreach (self::SHIPMENT_FIELDS as $f) {
                $v = $x->{'f_' . $f};
                if ($v !== null && ($a['f_' . $f] === null || strcmp((string) $v, (string) $a['f_' . $f]) > 0)) {
                    $a['f_' . $f] = $v;
                }
            }
            unset($a);
        }

        $cr = [];
        foreach ($tot as $k => $v) {
            $cr[$k] = $v['n'] > 0 ? round($v['b'] / $v['n'] * 100, 4) : 0;
        }

        $this->agg    = $agg;
        $this->crShip = $cr;
    }

    private function aggregates(): array
    {
        if ($this->agg === null) {
            $this->loadAgg();
        }
        return $this->agg;
    }

    private function crShipment(): array
    {
        if ($this->crShip === null) {
            $this->loadAgg();
        }
        return $this->crShip;
    }

    /** nilai kolom baris ini; kalau kosong, ambil dari DO lain di shipment yang sama */
    private function shp($r, string $field)
    {
        $v = $r->{$field} ?? null;
        if ($v !== null && trim((string) $v) !== '') {
            return $v;
        }
        return $this->aggregates()[trim((string) $r->no_shipment)]['f_' . $field] ?? $v;
    }

    // ============================================================
    // HELPER DASAR (dengan cache)
    // ============================================================
    private function serialDate(int $ts)
    {
        return $this->csv
            ? date('d-m-Y', $ts)
            : intdiv($ts + $this->tzOff, 86400) + 25569;
    }

    /** tanggal saja -> serial Excel (atau teks d-m-Y kalau CSV) */
    protected function xl($v)
    {
        if ($v === null || $v === '' || $v === false || $v === 'mm/dd/yyyy') {
            return '';
        }
        $k = (string) $v;
        if (isset($this->cD[$k])) {
            return $this->cD[$k];
        }
        if (count($this->cD) > 200000) {
            $this->cD = [];
        }
        $ts = is_int($v) ? $v : strtotime($k);
        return $this->cD[$k] = (!$ts || $ts <= 0) ? '' : $this->serialDate($ts);
    }

    /** tanggal + jam -> serial Excel (atau teks kalau CSV) */
    private function xlDt($v)
    {
        if ($v === null || $v === '' || $v === false || $v === 'mm/dd/yyyy') {
            return '';
        }
        $k = (string) $v;
        if (isset($this->cT[$k])) {
            return $this->cT[$k];
        }
        if (count($this->cT) > 200000) {
            $this->cT = [];
        }
        $ts = is_int($v) ? $v : strtotime($k);
        if (!$ts || $ts <= 0) {
            return $this->cT[$k] = '';
        }
        return $this->cT[$k] = $this->csv
            ? date('d-m-Y H:i', $ts)
            : round(($ts + $this->tzOff) / 86400 + 25569, 8);
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
        if (empty($v)) {
            return null;
        }
        $k = (string) $v;
        if (!isset($this->cS[$k])) {
            if (count($this->cS) > 200000) {
                $this->cS = [];
            }
            $this->cS[$k] = strtotime($k) ?: 0;
        }
        return $this->cS[$k] ?: null;
    }

    private function dayStart($v): ?int
    {
        $t = $this->ts($v);
        if (!$t) {
            return null;
        }
        if (!isset($this->cY[$t])) {
            if (count($this->cY) > 200000) {
                $this->cY = [];
            }
            $this->cY[$t] = strtotime(date('Y-m-d', $t));
        }
        return $this->cY[$t];
    }

    // ============================================================
    // CR / KUBIK / TONASE / OPTIMAL
    // ============================================================
    private function computeCR($r)
    {
        $muatan = (float) ($r->nilai_muatan ?? 0);
        $biaya  = (float) ($r->kirim_kontribusi ?? $r->biaya_kirim ?? 0);
        return $muatan > 0 ? round(($biaya / $muatan) * 100, 4) : 0;
    }

    private function hasil($total, $kapasitas)
    {
        $kap = (float) ($kapasitas ?? 0);
        if ($kap <= 0) {
            return '';
        }
        return round(((float) ($total ?? 0) / $kap) * 100, 2);
    }

    private function optimalLabel($k, $t): string
    {
        if ($k === '' && $t === '') {
            return '-';
        }
        $ok = ($k !== '' && $k >= 85) || ($t !== '' && $t >= 85);
        return $ok ? 'Optimal' : 'Tidak Optimal';
    }

    private function optimalCustomer($r): string
    {
        return $this->optimalLabel(
            $this->hasil($r->total_kubik ?? null, $r->kubikasi ?? null),
            $this->hasil($r->total_tonase ?? null, $r->tonase ?? null)
        );
    }

    private function optimalShipment($r): string
    {
        $a = $this->aggregates()[trim((string) $r->no_shipment)] ?? null;
        if (!$a) {
            return $this->optimalCustomer($r);
        }

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
        if (!$a || !$b) {
            return '-';
        }

        $menit   = max(0, intdiv($b - $a, 60));
        $desimal = $menit / 1440;
        $hari    = floor($desimal);
        $jam     = round(($desimal - $hari) * 24);

        if ($jam == 24) {
            $jam = 0;
            $hari += 1;
        }

        if ($hari > 0 && $jam > 0) return "{$hari} Hari {$jam} Jam";
        if ($hari > 0) return "{$hari} Hari";
        if ($jam > 0) return "{$jam} Jam";
        return '0 Jam';
    }

    private function statusGudang($planning, $tiba): string
    {
        $a = $this->dayStart($planning);
        $b = $this->dayStart($tiba);
        if (!$a || !$b) {
            return '-';
        }
        return $b > $a ? 'Delay' : 'On Time';
    }

    private function slaLoading($planning, $tiba): string
    {
        $a = $this->dayStart($planning);
        $b = $this->dayStart($tiba);
        if (!$a || !$b) {
            return '-';
        }
        return $b > $a ? 'H+' . (int) round(($b - $a) / 86400) : 'Sesuai SLA';
    }

    // ============================================================
    // UPDATE POSISI MOBIL
    // ============================================================
    private function statusPengiriman($r): string
    {
        if (empty($r->tanggal_dpt_unit)) {
            return 'MENCARI UNIT';
        }

        $g = [];
        foreach ([['KACS', ''], ['SENTUL', '_2'], ['CCIE', '_3'], ['P5', '_4'], ['FIFATEX', '_5']] as [$n, $s]) {
            $p = $this->g($r, 'planning_loading' . $s);
            if (!empty($p)) {
                $g[] = [
                    'p' => $this->ts($p), 'n' => $n,
                    't' => $this->g($r, 'tanggal_tiba_gudang' . $s),
                    'k' => $this->g($r, 'tanggal_keluar_gudang' . $s),
                ];
            }
        }
        if (count($g) > 1) {
            usort($g, fn($a, $b) => $a['p'] <=> $b['p']);
        }

        if (!$g && empty($r->tanggal_tiba)) {
            return 'PERJALANAN KE GUDANG';
        }

        foreach ($g as $x) {
            if (empty($x['t'])) return 'PERJALANAN KE ' . $x['n'];
            if (empty($x['k'])) return 'DI GUDANG ' . $x['n'];
        }

        if (empty($r->tanggal_tiba)) {
            return 'PERJALANAN KE TUJUAN';
        }
        return !empty($r->tanggal_bongkar) ? 'SUDAH SELESAI' : 'SUDAH TIBA TUJUAN';
    }

    private function gudangAktif($r): ?string
    {
        $list = [];
        foreach (['KACS' => '', 'SENTUL' => '_2', 'CCIE' => '_3', 'P5' => '_4', 'FIFATEX' => '_5'] as $n => $s) {
            $p = $this->g($r, 'planning_loading' . $s);
            if (!empty($p)) {
                $list[] = ['n' => $n, 'p' => $this->ts($p), 't' => $this->g($r, 'tanggal_tiba_gudang' . $s)];
            }
        }
        if (count($list) > 1) {
            usort($list, fn($a, $b) => $a['p'] <=> $b['p']);
        }

        foreach ($list as $x) {
            if (empty($x['t'])) {
                return 'PERJALANAN KE ' . $x['n'];
            }
        }
        return null;
    }

    private function lamaPerjalanan($r)
    {
        if (empty($r->tanggal_tiba)) {
            return '-';
        }

        $max = 0;
        foreach (['', '_2', '_3', '_4', '_5'] as $s) {
            $v = $this->g($r, 'tanggal_keluar_gudang' . $s);
            if (!empty($v)) {
                $t = $this->ts($v);
                if ($t && $t > $max) {
                    $max = $t;
                }
            }
        }
        if ($max === 0) {
            return '-';
        }

        return max(0, floor(($this->ts($r->tanggal_tiba) - $max) / 86400));
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

        $est     = $this->ts($r->estimasi_tiba ?? null);
        $estShow = $est ? $this->xl($r->estimasi_tiba) : '-';

        if (!empty($r->tanggal_tiba)) return ['estimasi' => $estShow, 'alert' => 'TIBA'];
        if (!$est)                    return ['estimasi' => $estShow, 'alert' => '-'];

        $today = strtotime(date('Y-m-d'));
        $sisa  = floor(($est - $today) / 86400);

        if ($sisa < 0)      $alert = 'Pending Tiba H+' . abs($sisa);
        elseif ($sisa <= 7) $alert = 'H-' . $sisa;
        else                $alert = 'ON TRACK';

        return ['estimasi' => $estShow, 'alert' => $alert];
    }

    private function statusBongkar($r): string
    {
        if (!empty($r->tanggal_bongkar)) {
            return 'Sudah Bongkar';
        }

        if (!empty($r->tanggal_tiba)) {
            $tiba  = $this->dayStart($r->tanggal_tiba);
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
        $rencana = $this->dayStart($r->rencana_kirim ?? null);
        if (!$rencana) {
            return null;
        }

        $hari = (int) ($r->transport_lead_time ?? 0);
        if (strtolower(trim($r->area ?? '')) === 'jawa barat') {
            $hari += 1;
        }

        return strtotime("+{$hari} days", $rencana);
    }

    private function estimasiAdminXl($r)
    {
        $ts = $this->estimasiAdminTs($r);
        return $ts ? $this->serialDate($ts) : '';
    }

    private function statusEstimasiAdmin($r): string
    {
        $est = $this->estimasiAdminTs($r);
        if (!$est) {
            return '-';
        }

        if (!empty($r->tanggal_tiba)) {
            return $this->dayStart($r->tanggal_tiba) <= $est ? 'On Time' : 'Delay';
        }

        return strtotime(date('Y-m-d')) > $est ? 'Delay' : 'Belum Tiba';
    }
}