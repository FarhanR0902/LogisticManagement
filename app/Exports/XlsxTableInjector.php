<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use ZipArchive;

/**
 * Menyuntikkan Excel Table (ListObject) ke sheet hasil export .xlsx (OpenSpout).
 * Dipanggil SEBELUM XlsxSlicerInjector, karena slicer mencari tabel lewat displayName.
 * Bekerja di salinan sementara; file asli hanya diganti kalau semua langkah lolos.
 */
class XlsxTableInjector
{
    public static function add(string $path, string $sheet, string $name, array $heads, int $lastRow): void
    {
        if ($lastRow < 2 || empty($heads)) {
            return;
        }

        $tmp = $path . '.table.tmp';

        try {
            if (!copy($path, $tmp)) {
                throw new \RuntimeException('gagal membuat salinan sementara');
            }

            self::build($tmp, $sheet, $name, $heads, $lastRow);

            if (!rename($tmp, $path)) {
                throw new \RuntimeException('gagal mengganti file asli');
            }
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw $e;
        }
    }

    private static function build(string $file, string $sheet, string $name, array $heads, int $lastRow): void
    {
        $seen = [];
        foreach ($heads as $i => $h) {
            $h = trim((string) $h);
            $k = mb_strtolower($h);
            if ($h === '' || isset($seen[$k])) {
                throw new \RuntimeException("header kolom kosong/duplikat: '{$h}' (kolom " . ($i + 1) . ')');
            }
            $seen[$k]  = true;
            $heads[$i] = $h;
        }
        $heads = array_values($heads);

        $z = new ZipArchive();
        if ($z->open($file) !== true) {
            throw new \RuntimeException('xlsx tidak bisa dibuka');
        }

        try {
            $wb   = $z->getFromName('xl/workbook.xml');
            $rels = $z->getFromName('xl/_rels/workbook.xml.rels');
            $ct   = $z->getFromName('[Content_Types].xml');
            if ($wb === false || $rels === false || $ct === false) {
                throw new \RuntimeException('struktur xlsx tidak dikenali');
            }

            if (!preg_match('/<sheet\b[^>]*\bname="' . preg_quote($sheet, '/') . '"[^>]*>/', $wb, $m)
                || !preg_match('/\br:id="([^"]+)"/', $m[0], $rid)
                || !preg_match('/<Relationship\b[^>]*\bId="' . preg_quote($rid[1], '/') . '"[^>]*>/', $rels, $rm)
                || !preg_match('/\bTarget="([^"]+)"/', $rm[0], $t)) {
                throw new \RuntimeException("sheet {$sheet} tidak ditemukan");
            }

            $fileName  = basename($t[1]);
            $sheetPath = 'xl/worksheets/' . $fileName;
            $relPath   = 'xl/worksheets/_rels/' . $fileName . '.rels';

            $sheetXml = $z->getFromName($sheetPath);
            if ($sheetXml === false) {
                throw new \RuntimeException("file sheet {$sheet} tidak ada");
            }
            if (strpos($sheetXml, '<tableParts') !== false) {
                throw new \RuntimeException('sheet sudah punya tabel');
            }

            $sheetRel = $z->getFromName($relPath)
                ?: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';

            $n = 1;
            while ($z->locateName("xl/tables/table{$n}.xml") !== false) {
                $n++;
            }

            $esc     = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $lastCol = Coordinate::stringFromColumnIndex(count($heads));
            $ref     = "A1:{$lastCol}{$lastRow}";

            $cols = '';
            foreach ($heads as $i => $h) {
                $cols .= '<tableColumn id="' . ($i + 1) . '" name="' . $esc($h) . '"/>';
            }

            $table = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="' . $n
                . '" name="' . $esc($name) . '" displayName="' . $esc($name)
                . '" ref="' . $ref . '" totalsRowShown="0">'
                . '<autoFilter ref="' . $ref . '"/>'
                . '<tableColumns count="' . count($heads) . '">' . $cols . '</tableColumns>'
                . '<tableStyleInfo name="TableStyleLight1" showFirstColumn="0" showLastColumn="0" '
                . 'showRowStripes="1" showColumnStripes="0"/></table>';

            // autoFilter level sheet bentrok dengan autoFilter tabel
            $sheetXml = preg_replace('#<autoFilter\b[^>]*/>#', '', $sheetXml);
            $sheetXml = preg_replace('#<autoFilter\b[^>]*>.*?</autoFilter>#s', '', $sheetXml);
            $wb = preg_replace('#<definedName\b[^>]*name="_xlnm\._FilterDatabase"[^>]*>.*?</definedName>#s', '', $wb);
            $wb = preg_replace('#<definedNames>\s*</definedNames>#', '', $wb);

            // tableParts harus sebelum extLst
            $tp = '<tableParts count="1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<tablePart r:id="rIdTbl' . $n . '"/></tableParts>';

            $sheetXml = strpos($sheetXml, '<extLst') !== false
                ? preg_replace('/<extLst\b/', $tp . '<extLst', $sheetXml, 1)
                : str_replace('</worksheet>', $tp . '</worksheet>', $sheetXml);

            $sheetRel = str_replace('</Relationships>',
                '<Relationship Id="rIdTbl' . $n . '" '
                . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" '
                . 'Target="../tables/table' . $n . '.xml"/></Relationships>',
                $sheetRel);

            $ct = str_replace('</Types>',
                '<Override PartName="/xl/tables/table' . $n . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml"/></Types>',
                $ct);

            // hitung ulang rumus saat dibuka; urutan di workbook: sheets → definedNames → calcPr
            if (preg_match('/<calcPr\b[^>]*\/>/', $wb)) {
                if (strpos($wb, 'fullCalcOnLoad') === false) {
                    $wb = preg_replace('/<calcPr\b/', '<calcPr fullCalcOnLoad="1"', $wb, 1);
                }
            } else {
                $calc = '<calcPr calcId="191029" fullCalcOnLoad="1"/>';
                $wb = strpos($wb, '</definedNames>') !== false
                    ? str_replace('</definedNames>', '</definedNames>' . $calc, $wb)
                    : str_replace('</sheets>', '</sheets>' . $calc, $wb);
            }

            $prev  = libxml_use_internal_errors(true);
            $check = [
                "table{$n}.xml"       => $table,
                $sheetPath            => $sheetXml,
                'workbook.xml'        => $wb,
                $relPath              => $sheetRel,
                '[Content_Types].xml' => $ct,
            ];
            foreach ($check as $label => $xml) {
                $dom = new \DOMDocument();
                if (!$dom->loadXML($xml)) {
                    $err = libxml_get_errors();
                    $msg = isset($err[0]) ? trim($err[0]->message) : 'tidak diketahui';
                    libxml_clear_errors();
                    libxml_use_internal_errors($prev);
                    throw new \RuntimeException("XML tidak valid di {$label}: " . $msg);
                }
            }
            libxml_use_internal_errors($prev);

            $z->addFromString("xl/tables/table{$n}.xml", $table);
            $z->addFromString($sheetPath, $sheetXml);
            $z->addFromString($relPath, $sheetRel);
            $z->addFromString('[Content_Types].xml', $ct);
            $z->addFromString('xl/workbook.xml', $wb);

            if (!$z->close()) {
                throw new \RuntimeException('gagal menyimpan zip');
            }
        } catch (\Throwable $e) {
            @$z->unchangeAll();
            @$z->close();
            throw $e;
        }
    }
}