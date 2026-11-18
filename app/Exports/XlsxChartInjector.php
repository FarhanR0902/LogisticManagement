<?php

namespace App\Exports;

use ZipArchive;

/**
 * Menyuntikkan bar chart ke sheet hasil OpenSpout (OpenSpout tidak punya chart).
 * Jalankan SEBELUM XlsxSlicerInjector, yang akan menempelkan slicer ke drawing ini.
 */
class XlsxChartInjector
{
    private const NS_XDR = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';
    private const NS_A   = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_C   = 'http://schemas.openxmlformats.org/drawingml/2006/chart';
    private const NS_R   = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @param array $charts [
     *   ['title'=>'...', 'cat'=>'$A$9:$A$16',
     *    'series'=>[['name'=>'$F$8','val'=>'$F$9:$F$16','color'=>'0284C7'], ...],
     *    'anchor'=>[kolomAwal, barisAwal, kolomAkhir, barisAkhir]],  // indeks mulai 0
     * ]
     */
    public static function add(string $path, string $sheet, array $charts): void
    {
        if (!$charts) return;

        $tmp = $path . '.chart.tmp';
        try {
            if (!copy($path, $tmp)) throw new \RuntimeException('gagal membuat salinan sementara');
            self::build($tmp, $sheet, $charts);
            if (!rename($tmp, $path)) throw new \RuntimeException('gagal mengganti file asli');
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw $e;
        }
    }

    private static function nextRid(string $rels): int
    {
        preg_match_all('/\bId="rId(\d+)"/', $rels, $all);
        return empty($all[1]) ? 1 : max(array_map('intval', $all[1])) + 1;
    }

    private static function freeName(ZipArchive $z, string $fmt): int
    {
        $n = 1;
        while ($z->locateName(sprintf($fmt, $n)) !== false) $n++;
        return $n;
    }

    private static function chartXml(array $c, string $sheet): string
    {
        $q   = "'" . str_replace("'", "''", $sheet) . "'!";
        $esc = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $ser = '';
        foreach ($c['series'] as $i => $s) {
            $ser .= '<c:ser><c:idx val="' . $i . '"/><c:order val="' . $i . '"/>'
                . '<c:tx><c:strRef><c:f>' . $esc($q . $s['name']) . '</c:f></c:strRef></c:tx>'
                . '<c:spPr><a:solidFill><a:srgbClr val="' . $s['color'] . '"/></a:solidFill></c:spPr>'
                . '<c:invertIfNegative val="0"/>'
                . '<c:cat><c:strRef><c:f>' . $esc($q . $c['cat']) . '</c:f></c:strRef></c:cat>'
                . '<c:val><c:numRef><c:f>' . $esc($q . $s['val']) . '</c:f></c:numRef></c:val>'
                . '</c:ser>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<c:chartSpace xmlns:c="' . self::NS_C . '" xmlns:a="' . self::NS_A . '" xmlns:r="' . self::NS_R . '">'
            . '<c:chart><c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:pPr><a:defRPr sz="1200" b="1"/></a:pPr>'
            . '<a:r><a:rPr lang="id-ID" sz="1200" b="1"/><a:t>' . $esc($c['title']) . '</a:t></a:r></a:p></c:rich></c:tx>'
            . '<c:overlay val="0"/></c:title><c:autoTitleDeleted val="0"/>'
            . '<c:plotArea><c:layout/><c:barChart><c:barDir val="col"/><c:grouping val="clustered"/><c:varyColors val="0"/>'
            . $ser
            . '<c:gapWidth val="80"/><c:axId val="111111"/><c:axId val="222222"/></c:barChart>'
            . '<c:catAx><c:axId val="111111"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/>'
            . '<c:axPos val="b"/><c:numFmt formatCode="General" sourceLinked="0"/><c:majorTickMark val="out"/><c:minorTickMark val="none"/>'
            . '<c:tickLblPos val="nextTo"/><c:crossAx val="222222"/><c:crosses val="autoZero"/><c:auto val="1"/>'
            . '<c:lblAlgn val="ctr"/><c:lblOffset val="100"/><c:noMultiLvlLbl val="0"/></c:catAx>'
            . '<c:valAx><c:axId val="222222"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/>'
            . '<c:axPos val="l"/><c:majorGridlines/><c:numFmt formatCode="#,##0" sourceLinked="0"/>'
            . '<c:majorTickMark val="out"/><c:minorTickMark val="none"/><c:tickLblPos val="nextTo"/>'
            . '<c:crossAx val="111111"/><c:crosses val="autoZero"/><c:crossBetween val="between"/></c:valAx>'
            . '</c:plotArea><c:legend><c:legendPos val="b"/><c:overlay val="0"/></c:legend>'
            . '<c:plotVisOnly val="1"/><c:dispBlanksAs val="gap"/></c:chart></c:chartSpace>';
    }

    private static function build(string $file, string $sheet, array $charts): void
    {
        $z = new ZipArchive();
        if ($z->open($file) !== true) throw new \RuntimeException('xlsx tidak bisa dibuka');

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

            $file2     = basename($t[1]);
            $sheetPath = 'xl/worksheets/' . $file2;
            $relPath   = 'xl/worksheets/_rels/' . $file2 . '.rels';
            $sheetXml  = $z->getFromName($sheetPath);
            if ($sheetXml === false) throw new \RuntimeException("file sheet {$sheet} tidak ada");
            if (strpos($sheetXml, '<drawing') !== false) throw new \RuntimeException('sheet sudah punya drawing');

            $sheetRel = $z->getFromName($relPath)
                ?: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';

            $d     = self::freeName($z, 'xl/drawings/drawing%d.xml');
            $c0    = self::freeName($z, 'xl/charts/chart%d.xml');
            $anch  = '';
            $dRels = '';
            $parts = [];
            $ctAdd = '';

            foreach ($charts as $i => $c) {
                $cn  = $c0 + $i;
                $rid = $i + 1;
                [$x1, $y1, $x2, $y2] = $c['anchor'];

                $parts["xl/charts/chart{$cn}.xml"] = self::chartXml($c, $sheet);
                $dRels .= '<Relationship Id="rId' . $rid . '" Type="' . self::NS_R . '/chart" Target="../charts/chart' . $cn . '.xml"/>';
                $ctAdd .= '<Override PartName="/xl/charts/chart' . $cn . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>';

                $anch .= '<xdr:twoCellAnchor><xdr:from><xdr:col>' . $x1 . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $y1
                    . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from><xdr:to><xdr:col>' . $x2 . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>'
                    . $y2 . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
                    . '<xdr:graphicFrame macro=""><xdr:nvGraphicFramePr><xdr:cNvPr id="' . (2 + $i) . '" name="Chart ' . ($i + 1) . '"/>'
                    . '<xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr><xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm>'
                    . '<a:graphic><a:graphicData uri="' . self::NS_C . '"><c:chart xmlns:c="' . self::NS_C . '" xmlns:r="' . self::NS_R
                    . '" r:id="rId' . $rid . '"/></a:graphicData></a:graphic></xdr:graphicFrame><xdr:clientData/></xdr:twoCellAnchor>';
            }

            $parts["xl/drawings/drawing{$d}.xml"] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<xdr:wsDr xmlns:xdr="' . self::NS_XDR . '" xmlns:a="' . self::NS_A . '">' . $anch . '</xdr:wsDr>';
            $parts["xl/drawings/_rels/drawing{$d}.xml.rels"] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $dRels . '</Relationships>';
            $ctAdd .= '<Override PartName="/xl/drawings/drawing' . $d . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';

            $sr  = self::nextRid($sheetRel);
            $tag = '<drawing xmlns:r="' . self::NS_R . '" r:id="rId' . $sr . '"/>';
            $cnt = 0;
            $new = preg_replace(
                '/<(legacyDrawing|legacyDrawingHF|drawingHF|picture|oleObjects|controls|webPublishItems|tableParts|extLst)\b/',
                $tag . '<$1', $sheetXml, 1, $cnt
            );
            $sheetXml = ($cnt === 1 && $new !== null) ? $new : str_replace('</worksheet>', $tag . '</worksheet>', $sheetXml);
            $parts[$sheetPath] = $sheetXml;

            $parts[$relPath] = str_replace('</Relationships>',
                '<Relationship Id="rId' . $sr . '" Type="' . self::NS_R . '/drawing" Target="../drawings/drawing' . $d . '.xml"/></Relationships>',
                $sheetRel);
            $parts['[Content_Types].xml'] = str_replace('</Types>', $ctAdd . '</Types>', $ct);

            $prev = libxml_use_internal_errors(true);
            foreach ($parts as $pname => $xml) {
                $dom = new \DOMDocument();
                if (!$dom->loadXML($xml)) {
                    $err = libxml_get_errors();
                    $msg = isset($err[0]) ? trim($err[0]->message) : 'tidak diketahui';
                    libxml_clear_errors();
                    libxml_use_internal_errors($prev);
                    throw new \RuntimeException("XML tidak valid di {$pname}: " . $msg);
                }
            }
            libxml_use_internal_errors($prev);

            foreach ($parts as $pname => $xml) $z->addFromString($pname, $xml);
            if (!$z->close()) throw new \RuntimeException('gagal menyimpan zip');
        } catch (\Throwable $e) {
            @$z->unchangeAll();
            @$z->close();
            throw $e;
        }
    }
}