<?php

namespace App\Exports;

use ZipArchive;

/**
 * Menyuntikkan slicer Excel Table ke file .xlsx hasil PhpSpreadsheet.
 * Slicer diletakkan di $sheet (mis. 'Ringkasan'), mengontrol tabel tblLogistik (sheet Data).
 * Bekerja di salinan sementara; file asli hanya diganti kalau semua langkah lolos.
 */
class XlsxSlicerInjector
{
    private const NS_X14 = 'http://schemas.microsoft.com/office/spreadsheetml/2009/9/main';
    private const NS_X15 = 'http://schemas.microsoft.com/office/spreadsheetml/2010/11/main';
    private const NS_R   = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_MC  = 'http://schemas.openxmlformats.org/markup-compatibility/2006';
    private const NS_X   = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_XDR = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';
    private const NS_A   = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const TABLE_NAME = 'tblLogistik';

    /**
     * @param string $path    file xlsx hasil export
     * @param string $sheet   sheet tempat slicer diletakkan (mis. 'Ringkasan')
     * @param array  $wanted  heading kolom tabel yang dijadikan slicer
     * @param array  $slots   posisi tiap slicer, lihat LogistikReportSummary::slicerSlots()
     */
    public static function inject(string $path, string $sheet, array $wanted, array $slots = []): bool
    {
        $tmp = $path . '.slicer.tmp';

        try {
            if (!copy($path, $tmp)) {
                throw new \RuntimeException('gagal membuat salinan sementara');
            }

            self::build($tmp, $sheet, $wanted, $slots);

            $z = new ZipArchive();
            if ($z->open($tmp) !== true) {
                throw new \RuntimeException('hasil injeksi tidak bisa dibuka sebagai zip');
            }
            $z->close();

            if (!rename($tmp, $path)) {
                throw new \RuntimeException('gagal mengganti file asli');
            }

            return true;
        } catch (\Throwable $e) {
            @unlink($tmp);
            report($e);
            return false;
        }
    }

    /** id tabel + daftar kolom (nama & id) dibaca dari xl/tables/*.xml */
    private static function readTable(ZipArchive $zip, string $displayName): array
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (!preg_match('#^xl/tables/table\d+\.xml$#', $n)) {
                continue;
            }

            $dom = new \DOMDocument();
            if (!@$dom->loadXML((string) $zip->getFromIndex($i))) {
                continue;
            }
            if ($dom->documentElement->getAttribute('displayName') !== $displayName) {
                continue;
            }

            $cols = [];
            foreach ($dom->getElementsByTagName('tableColumn') as $c) {
                $cols[mb_strtolower($c->getAttribute('name'))] = [
                    'head' => $c->getAttribute('name'),
                    'id'   => (int) $c->getAttribute('id'),
                ];
            }

            return ['id' => (int) $dom->documentElement->getAttribute('id'), 'cols' => $cols];
        }

        throw new \RuntimeException("table {$displayName} tidak ditemukan (sheet Data kosong?)");
    }

    private static function relTarget(string $rels, string $id): ?string
    {
        if (preg_match('/<Relationship\b[^>]*\bId="' . preg_quote($id, '/') . '"[^>]*>/', $rels, $m)
            && preg_match('/\bTarget="([^"]+)"/', $m[0], $t)) {
            return $t[1];
        }
        return null;
    }

    private static function nextRid(string $rels): int
    {
        preg_match_all('/\bId="rId(\d+)"/', $rels, $all);
        return empty($all[1]) ? 1 : max(array_map('intval', $all[1])) + 1;
    }

    private static function anchor(int $n, string $name, array $s): string
    {
        [$c1, $o1, $c2, $o2, $r1, $r2] = $s;

        $from = "<xdr:from><xdr:col>{$c1}</xdr:col><xdr:colOff>{$o1}</xdr:colOff><xdr:row>{$r1}</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>";
        $to   = "<xdr:to><xdr:col>{$c2}</xdr:col><xdr:colOff>{$o2}</xdr:colOff><xdr:row>{$r2}</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>";

        return
            '<mc:AlternateContent xmlns:mc="' . self::NS_MC . '" xmlns:xdr="' . self::NS_XDR . '" xmlns:a="' . self::NS_A . '">'
            . '<mc:Choice xmlns:sle15="http://schemas.microsoft.com/office/drawing/2012/slicer" Requires="sle15">'
            . '<xdr:twoCellAnchor editAs="oneCell">' . $from . $to
            . '<xdr:graphicFrame macro=""><xdr:nvGraphicFramePr>'
            . '<xdr:cNvPr id="' . (1000 + $n) . '" name="' . $name . '"/><xdr:cNvGraphicFramePr/>'
            . '</xdr:nvGraphicFramePr><xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm>'
            . '<a:graphic><a:graphicData uri="http://schemas.microsoft.com/office/drawing/2010/slicer">'
            . '<sle:slicer xmlns:sle="http://schemas.microsoft.com/office/drawing/2010/slicer" name="' . $name . '"/>'
            . '</a:graphicData></a:graphic></xdr:graphicFrame><xdr:clientData/></xdr:twoCellAnchor>'
            . '</mc:Choice>'
            . '<mc:Fallback><xdr:twoCellAnchor>' . $from . $to
            . '<xdr:sp macro="" textlink=""><xdr:nvSpPr><xdr:cNvPr id="0" name=""/>'
            . '<xdr:cNvSpPr><a:spLocks noTextEdit="1"/></xdr:cNvSpPr></xdr:nvSpPr>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="1828800" cy="2381250"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:solidFill><a:prstClr val="white"/></a:solidFill>'
            . '<a:ln w="1"><a:solidFill><a:prstClr val="green"/></a:solidFill></a:ln></xdr:spPr>'
            . '<xdr:txBody><a:bodyPr vertOverflow="clip" horzOverflow="clip"/><a:lstStyle/>'
            . '<a:p><a:r><a:rPr lang="en-US" sz="1100"/><a:t>Slicer hanya tersedia di Excel 2013 ke atas.</a:t></a:r></a:p>'
            . '</xdr:txBody></xdr:sp><xdr:clientData/></xdr:twoCellAnchor></mc:Fallback>'
            . '</mc:AlternateContent>';
    }

    private static function build(string $file, string $sheet, array $wanted, array $slots): void
    {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new \RuntimeException('xlsx tidak bisa dibuka');
        }

        try {
            $wbXml  = $zip->getFromName('xl/workbook.xml');
            $wbRels = $zip->getFromName('xl/_rels/workbook.xml.rels');
            $ct     = $zip->getFromName('[Content_Types].xml');
            if (!$wbXml || !$wbRels || !$ct) {
                throw new \RuntimeException('struktur xlsx tidak dikenali');
            }

            // ---- sheet tempat slicer diletakkan
            if (!preg_match('/<sheet\b[^>]*\bname="' . preg_quote($sheet, '/') . '"[^>]*>/', $wbXml, $m)
                || !preg_match('/\br:id="([^"]+)"/', $m[0], $rid)
                || ($target = self::relTarget($wbRels, $rid[1])) === null) {
                throw new \RuntimeException("sheet {$sheet} tidak ditemukan");
            }
            $sheetFile = basename($target);
            $sheetPath = 'xl/worksheets/' . $sheetFile;
            $relPath   = 'xl/worksheets/_rels/' . $sheetFile . '.rels';

            $sheetXml = $zip->getFromName($sheetPath);
            if (!$sheetXml) {
                throw new \RuntimeException("file sheet {$sheet} tidak ada");
            }
            $sheetRel = $zip->getFromName($relPath)
                ?: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';

            // ---- tabel & kolom slicer (dibaca dari file)
            $tbl     = self::readTable($zip, self::TABLE_NAME);
            $tableId = $tbl['id'];

            $cols = [];
            foreach (array_unique($wanted) as $w) {
                $k = mb_strtolower((string) $w);
                if (isset($tbl['cols'][$k])) {
                    $cols[] = $tbl['cols'][$k];
                } else {
                    logger()->warning("Slicer: kolom '{$w}' tidak ada di tabel, dilewati");
                }
            }
            if (empty($cols)) {
                throw new \RuntimeException('tidak ada kolom slicer yang cocok dengan heading tabel');
            }

            $parts = [];

            $slicerXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<slicers xmlns="' . self::NS_X14 . '" xmlns:mc="' . self::NS_MC . '" mc:Ignorable="x" '
                . 'xmlns:x="' . self::NS_X . '">';
            $anchors = $definedNames = $cacheRefs = $extraWbRels = $ctAdd = '';
            $usedNames = [];
            $wbRid     = self::nextRid($wbRels);

            foreach ($cols as $i => $c) {
                $n = $i + 1;

                $base = 'Slicer_' . preg_replace('/[^A-Za-z0-9_]/', '_', $c['head']);
                $name = $base;
                for ($k = 2; isset($usedNames[$name]); $k++) {
                    $name = $base . '_' . $k;
                }
                $usedNames[$name] = true;

                $cap = htmlspecialchars($c['head'], ENT_XML1 | ENT_QUOTES, 'UTF-8');

                $slicerXml .= '<slicer name="' . $name . '" cache="' . $name . '" caption="' . $cap
                    . '" rowHeight="241300" style="SlicerStyleLight1"/>';

                $parts["xl/slicerCaches/slicerCache{$n}.xml"] =
                    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<slicerCacheDefinition xmlns="' . self::NS_X14 . '" '
                    . 'xmlns:mc="' . self::NS_MC . '" mc:Ignorable="x" '
                    . 'xmlns:x="' . self::NS_X . '" '
                    . 'name="' . $name . '" sourceName="' . $cap . '">'
                    . '<extLst><x:ext uri="{2F2917AC-EB37-4324-AD4E-5DD8C200BD13}" xmlns:x15="' . self::NS_X15 . '">'
                    . '<x15:tableSlicerCache tableId="' . $tableId . '" column="' . $c['id'] . '"/>'
                    . '</x:ext></extLst></slicerCacheDefinition>';

                $definedNames .= '<definedName name="' . $name . '">#N/A</definedName>';
                $extraWbRels  .= '<Relationship Id="rId' . ($wbRid + $i) . '" '
                    . 'Type="http://schemas.microsoft.com/office/2007/relationships/slicerCache" '
                    . 'Target="slicerCaches/slicerCache' . $n . '.xml"/>';
                $cacheRefs    .= '<x14:slicerCache r:id="rId' . ($wbRid + $i) . '"/>';
                $ctAdd        .= '<Override PartName="/xl/slicerCaches/slicerCache' . $n . '.xml" '
                    . 'ContentType="application/vnd.ms-excel.slicerCache+xml"/>';

                // posisi: dari slot (rapi); kalau slot habis, tumpuk di kanan sheet
                $slot = $slots[$i] ?? [20, 0, 23, 0, $i * 13, $i * 13 + 12];
                $anchors .= self::anchor($n, $name, $slot);
            }
            $slicerXml .= '</slicers>';
            $parts['xl/slicers/slicer1.xml'] = $slicerXml;

            // ---- drawing: tempel ke drawing yang sudah ada (chart), atau buat baru
            $sr = self::nextRid($sheetRel);

            if (preg_match('/<drawing\b[^>]*\br:id="([^"]+)"/', $sheetXml, $dm)) {
                $dt = self::relTarget($sheetRel, $dm[1]);
                if ($dt === null) {
                    throw new \RuntimeException('relasi <drawing> sheet tidak ditemukan');
                }
                $drawPath = 'xl/drawings/' . basename($dt);
                $drawXml  = $zip->getFromName($drawPath);
                $pos      = $drawXml === false ? false : strrpos($drawXml, '</xdr:wsDr>');
                if ($pos === false) {
                    throw new \RuntimeException("drawing {$drawPath} tidak dikenali");
                }
                $parts[$drawPath] = substr_replace($drawXml, $anchors, $pos, 0);
                $slicerRid = $sr;
                $newRels   = '';
            } else {
                $parts['xl/drawings/drawing99.xml'] =
                    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<xdr:wsDr xmlns:xdr="' . self::NS_XDR . '" xmlns:a="' . self::NS_A . '">' . $anchors . '</xdr:wsDr>';

                $count    = 0;
                $sheetXml = preg_replace(
                    '/<(legacyDrawing|legacyDrawingHF|drawingHF|picture|oleObjects|controls|webPublishItems|tableParts)\b/',
                    '<drawing xmlns:r="' . self::NS_R . '" r:id="rId' . $sr . '"/><$1',
                    $sheetXml, 1, $count
                );
                if ($count !== 1 || $sheetXml === null) {
                    $sheetXml = str_replace('</worksheet>',
                        '<drawing xmlns:r="' . self::NS_R . '" r:id="rId' . $sr . '"/></worksheet>', $sheetXml);
                }
                $newRels = '<Relationship Id="rId' . $sr . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing99.xml"/>';
                $slicerRid = $sr + 1;

                if (strpos($ct, '/xl/drawings/drawing99.xml') === false) {
                    $ctAdd .= '<Override PartName="/xl/drawings/drawing99.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
                }
            }

            // ---- rels sheet
            $parts[$relPath] = str_replace('</Relationships>',
                $newRels
                . '<Relationship Id="rId' . $slicerRid . '" Type="http://schemas.microsoft.com/office/2007/relationships/slicer" Target="../slicers/slicer1.xml"/>'
                . '</Relationships>', $sheetRel);

            // ---- sheet xml: slicerList (versi TABLE)
            $sheetExt = '<ext uri="{3A4CF648-6AED-40f4-86FF-DC5316D8AED3}" xmlns:x14="' . self::NS_X14 . '" xmlns:r="' . self::NS_R . '">'
                . '<x14:slicerList><x14:slicer r:id="rId' . $slicerRid . '"/></x14:slicerList></ext>';

            $sheetXml = strpos($sheetXml, '</extLst>') !== false
                ? str_replace('</extLst>', $sheetExt . '</extLst>', $sheetXml)
                : str_replace('</worksheet>', '<extLst>' . $sheetExt . '</extLst></worksheet>', $sheetXml);
            $parts[$sheetPath] = $sheetXml;

            // ---- workbook.xml: defined names + slicerCaches
            if (strpos($wbXml, '</definedNames>') !== false) {
                $wbXml = str_replace('</definedNames>', $definedNames . '</definedNames>', $wbXml);
            } elseif (preg_match('/<definedNames\s*\/>/', $wbXml)) {
                $wbXml = preg_replace_callback(
                    '/<definedNames\s*\/>/',
                    fn() => '<definedNames>' . $definedNames . '</definedNames>',
                    $wbXml, 1
                );
            } else {
                $wbXml = str_replace('</sheets>', '</sheets><definedNames>' . $definedNames . '</definedNames>', $wbXml);
            }

            $wbExt = '<ext uri="{46BE6895-7355-4a93-B00E-2C351335B9C9}" xmlns:x15="' . self::NS_X15 . '" xmlns:r="' . self::NS_R . '">'
                . '<x15:slicerCaches xmlns:x14="' . self::NS_X14 . '">' . $cacheRefs . '</x15:slicerCaches></ext>';

            $wbXml = strpos($wbXml, '</extLst>') !== false
                ? str_replace('</extLst>', $wbExt . '</extLst>', $wbXml)
                : str_replace('</workbook>', '<extLst>' . $wbExt . '</extLst></workbook>', $wbXml);
           // paksa Excel menghitung ulang semua rumus saat file dibuka
if (preg_match('/<calcPr\b[^>]*\/>/', $wbXml)) {
    $wbXml = preg_replace(
        '/<calcPr\b[^>]*\/>/',
        '<calcPr calcId="191029" calcMode="auto" fullCalcOnLoad="1"/>',
        $wbXml, 1
    );
}
                $parts['xl/workbook.xml']            = $wbXml;
            $parts['xl/_rels/workbook.xml.rels'] = str_replace('</Relationships>', $extraWbRels . '</Relationships>', $wbRels);

            // ---- content types
            $ctAdd .= '<Override PartName="/xl/slicers/slicer1.xml" ContentType="application/vnd.ms-excel.slicer+xml"/>';
            $parts['[Content_Types].xml'] = str_replace('</Types>', $ctAdd . '</Types>', $ct);

            // ---- styles.xml: default slicer style
            $st = $zip->getFromName('xl/styles.xml');
            if ($st === false) {
                throw new \RuntimeException('xl/styles.xml tidak ditemukan');
            }
            if (strpos($st, 'slicerStyles') === false) {
                $stExt = '<ext uri="{EB79DEF2-80B8-43e5-95BD-54CBDDF9020C}" xmlns:x14="' . self::NS_X14 . '">'
                    . '<x14:slicerStyles defaultSlicerStyle="SlicerStyleLight1"/></ext>';
                $parts['xl/styles.xml'] = strpos($st, '</extLst>') !== false
                    ? str_replace('</extLst>', $stExt . '</extLst>', $st)
                    : str_replace('</styleSheet>', '<extLst>' . $stExt . '</extLst></styleSheet>', $st);
            }

            // ---- validasi semua XML sebelum menulis
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

            foreach ($parts as $pname => $xml) {
                $zip->addFromString($pname, $xml);
            }

            if (!$zip->close()) {
                throw new \RuntimeException('gagal menyimpan zip');
            }
        } catch (\Throwable $e) {
            @$zip->unchangeAll();
            @$zip->close();
            throw $e;
        }
    }
}