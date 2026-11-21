<?php
namespace App\Exports\Lite;

use ZipArchive;

class Writer
{
    private string $path = '';
    /** @var Sheet[] */
    private array $sheets = [];
    private ?Sheet $cur = null;
    private array $xfs = [];          // sig => [Style, id]
    private static array $colCache = [];

    public function __construct(private Options $options = new Options()) {}

    public function openToFile(string $path): void
    {
        $this->path = $path;
        $this->sheets = [];
        $this->xfs = [];
        $this->newSheet('Sheet1');
    }

    public function getCurrentSheet(): Sheet
    {
        return $this->cur;
    }

    public function addNewSheetAndMakeItCurrent(): Sheet
    {
        return $this->newSheet('Sheet' . (count($this->sheets) + 1));
    }

    private function newSheet(string $name): Sheet
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xsh');
        $s = new Sheet($name, $tmp);
        $s->fh = fopen($tmp, 'wb');

        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
           . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        if ($this->options->cols) {
            $x .= '<cols>';
            foreach ($this->options->cols as [$f, $t, $w]) {
                $x .= '<col min="' . $f . '" max="' . $t . '" width="' . $w . '" customWidth="1"/>';
            }
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        fwrite($s->fh, $x);

        $this->sheets[] = $s;
        return $this->cur = $s;
    }

    private static function col(int $n): string
    {
        if (isset(self::$colCache[$n])) return self::$colCache[$n];
        $s = '';
        for ($i = $n; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return self::$colCache[$n] = $s;
    }

    private static function esc(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function xf(?Style $s): int
    {
        if ($s === null) return 0;
        $sig = $s->sig();
        if ($sig === '0|||') return 0;
        if (!isset($this->xfs[$sig])) {
            $this->xfs[$sig] = [$s, count($this->xfs) + 1];
        }
        return $this->xfs[$sig][1];
    }

    public function addRow(Row $row): void
    {
        $sh = $this->cur;
        $n = ++$sh->rowNum;

        if (!$row->cells) {
            fwrite($sh->fh, '<row r="' . $n . '"/>');
            return;
        }

        $out = '<row r="' . $n . '">';
        $i = 0;
        foreach ($row->cells as $c) {
            $i++;
            $v = $c->value;
            $xf = $this->xf($c->style);
            $sA = $xf ? ' s="' . $xf . '"' : '';
            $ref = self::col($i) . $n;

            if ($v === null || $v === '') {
                if ($xf) $out .= '<c r="' . $ref . '"' . $sA . '/>';
            } elseif (is_bool($v)) {
                $out .= '<c r="' . $ref . '"' . $sA . ' t="b"><v>' . (int) $v . '</v></c>';
            } elseif (is_int($v) || is_float($v)) {
                if (is_float($v) && (is_nan($v) || is_infinite($v))) {
                    if ($xf) $out .= '<c r="' . $ref . '"' . $sA . '/>';
                } else {
                    $out .= '<c r="' . $ref . '"' . $sA . '><v>' . $v . '</v></c>';
                }
            } else {
                $v = (string) $v;
                if ($v[0] === '=' && strlen($v) > 1) {
                    $out .= '<c r="' . $ref . '"' . $sA . '><f>' . self::esc(substr($v, 1)) . '</f></c>';
                } else {
                    if (strlen($v) > 32000) $v = mb_substr($v, 0, 32000);
                    $out .= '<c r="' . $ref . '"' . $sA . ' t="inlineStr"><is><t xml:space="preserve">'
                         . self::esc($v) . '</t></is></c>';
                }
            }
        }
        fwrite($sh->fh, $out . '</row>');
    }

    public function close(): void
    {
        foreach ($this->sheets as $s) {
            fwrite($s->fh, '</sheetData></worksheet>');
            fclose($s->fh);
        }

        if (is_file($this->path)) @unlink($this->path);
        $zip = new ZipArchive();
        if ($zip->open($this->path, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Tidak bisa membuat file xlsx: ' . $this->path);
        }

        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->sheets as $i => $s) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $zip->addFromString('[Content_Types].xml', $ct . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rel = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->sheets as $i => $s) {
            $k = $i + 1;
            $wb .= '<sheet name="' . htmlspecialchars($s->name, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" sheetId="' . $k . '" r:id="rId' . $k . '"/>';
            $rel .= '<Relationship Id="rId' . $k . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $k . '.xml"/>';
        }
        $sid = count($this->sheets) + 1;
        $rel .= '<Relationship Id="rId' . $sid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $zip->addFromString('xl/workbook.xml', $wb . '</sheets><calcPr fullCalcOnLoad="1"/></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rel);
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($this->sheets as $i => $s) {
            $zip->addFile($s->tmp, 'xl/worksheets/sheet' . ($i + 1) . '.xml');
        }
        $zip->close();

        foreach ($this->sheets as $s) @unlink($s->tmp);
    }

    private function stylesXml(): string
    {
        $builtin = ['@' => 49, '#,##0' => 3, '#,##0.00' => 4, '0.00' => 2, '0' => 1, 'General' => 0];
        $numFmts = [];
        $fonts = ['<font><sz val="11"/><name val="Calibri"/></font>'];
        $fontIdx = ['|' => 0];
        $fills = ['<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>'];
        $fillIdx = [];
        $xf = ['<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'];

        foreach ($this->xfs as [$st, $id]) {
            $fk = (int) $st->bold . '|' . $st->color;
            if (!isset($fontIdx[$fk])) {
                $f = '<font>' . ($st->bold ? '<b/>' : '') . '<sz val="11"/>'
                   . ($st->color ? '<color rgb="FF' . $st->color . '"/>' : '')
                   . '<name val="Calibri"/></font>';
                $fontIdx[$fk] = count($fonts);
                $fonts[] = $f;
            }
            $fillId = 0;
            if ($st->bg) {
                if (!isset($fillIdx[$st->bg])) {
                    $fillIdx[$st->bg] = count($fills);
                    $fills[] = '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $st->bg . '"/><bgColor indexed="64"/></patternFill></fill>';
                }
                $fillId = $fillIdx[$st->bg];
            }
            $nf = 0;
            if ($st->format !== null && $st->format !== '') {
                if (isset($builtin[$st->format])) {
                    $nf = $builtin[$st->format];
                } else {
                    if (!isset($numFmts[$st->format])) $numFmts[$st->format] = 164 + count($numFmts);
                    $nf = $numFmts[$st->format];
                }
            }
            $xf[$id] = '<xf numFmtId="' . $nf . '" fontId="' . $fontIdx[$fk] . '" fillId="' . $fillId . '" borderId="0" xfId="0"'
                . ($nf ? ' applyNumberFormat="1"' : '') . ' applyFont="1"' . ($fillId ? ' applyFill="1"' : '') . '/>';
        }
        ksort($xf);

        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($numFmts) {
            $x .= '<numFmts count="' . count($numFmts) . '">';
            foreach ($numFmts as $code => $id) {
                $x .= '<numFmt numFmtId="' . $id . '" formatCode="' . htmlspecialchars($code, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/>';
            }
            $x .= '</numFmts>';
        }
        $x .= '<fonts count="' . count($fonts) . '">' . implode('', $fonts) . '</fonts>'
            . '<fills count="' . count($fills) . '">' . implode('', $fills) . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($xf) . '">' . implode('', $xf) . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
        return $x;
    }
}