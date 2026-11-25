<?php

namespace App\Exports;

use ZipArchive;

/**
 * Mempercantik chart hasil PhpSpreadsheet:
 *  - label angka = kotak berwarna series + border gelap, teks putih/hitam otomatis, nilai 0 disembunyikan
 *  - garis grid gelap, sudut membulat
 *  - chart tujuan (kategori Ringkasan!AD): rentang tetap diganti NAMA DINAMIS (OFFSET sepanjang
 *    Ringkasan!AM2) supaya jumlah batang ikut slicer, dan nama tujuan dimiringkan
 * Gagal = file asli tidak berubah.
 */
class XlsxChartLabelStyler
{
    private const PCT_COLS = ['N', 'AI'];
    private const GRID     = '1F2937';
    private const DYN_COLS = ['AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK'];

    public static function apply(string $path): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        try {
            $new      = [];
            $needName = false;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!preg_match('#^xl/charts/chart\d+\.xml$#', $name)) {
                    continue;
                }

                $xml = (string) $zip->getFromIndex($i);
                if (strpos($xml, '<c:ser>') === false) {
                    continue;
                }

                $out = preg_replace_callback('#<c:ser>.*?</c:ser>#s', fn($m) => self::styleSeries($m[0]), $xml);
                if ($out === null) {
                    continue;
                }

                $out = preg_replace('#(</c:ser>)\s*<c:dLbls>.*?</c:dLbls>#s', '$1', $out) ?? $out;

                $grid = '<c:majorGridlines><c:spPr><a:ln w="9525"><a:solidFill><a:srgbClr val="' . self::GRID
                    . '"/></a:solidFill></a:ln></c:spPr></c:majorGridlines>';
                $out = preg_replace('#<c:majorGridlines\s*/>#', $grid, $out) ?? $out;
                $out = preg_replace('#<c:majorGridlines>.*?</c:majorGridlines>#s', $grid, $out) ?? $out;
                $out = preg_replace('#<c:roundedCorners val="0"\s*/>#', '<c:roundedCorners val="1"/>', $out) ?? $out;

                // chart tujuan: rentang tetap -> nama dinamis, label nama miring
                $changed = false;
                $out     = self::dynamicRanges($out, $changed);
                if ($changed) {
                    $needName = true;
                    $out      = self::rotateCatLabels($out);
                }

                if (strpos($out, '<c:grouping val="stacked"/>') !== false) {
                    $out = self::stacked($out);
                }

                $prev = libxml_use_internal_errors(true);
                $ok   = (new \DOMDocument())->loadXML($out);
                libxml_clear_errors();
                libxml_use_internal_errors($prev);

                if ($ok) {
                    $new[$name] = $out;
                }
            }

            if ($needName) {
                $wb = self::addNames((string) $zip->getFromName('xl/workbook.xml'));

                $prev = libxml_use_internal_errors(true);
                $ok   = (new \DOMDocument())->loadXML($wb);
                libxml_clear_errors();
                libxml_use_internal_errors($prev);
                if (!$ok) {
                    throw new \RuntimeException('workbook.xml tidak valid setelah menambah nama dinamis');
                }
                $new['xl/workbook.xml'] = $wb;
            }

            foreach ($new as $name => $xml) {
                $zip->addFromString($name, $xml);
            }

            return $zip->close();
        } catch (\Throwable $e) {
            @$zip->unchangeAll();
            @$zip->close();
            report($e);
            return false;
        }
    }

    /** rentang tetap Ringkasan!$AD$4:$AD$23 -> nama dinamis (panjang = Ringkasan!AM2); cache dibuang */
    private static function dynamicRanges(string $xml, &$changed = null): string
    {
        $changed = false;
        $cols    = implode('|', self::DYN_COLS);
        $q       = "(?:'|&apos;|&#0*39;|&quot;)?";   // &#039; (dipakai PhpSpreadsheet) ikut terbaca

        $out = preg_replace_callback(
            '~<c:f>\s*' . $q . 'Ringkasan' . $q . '!\$?(' . $cols . ')\$?4:\$?\1\$?\d+\s*</c:f>~',
            function ($m) use (&$changed) {
                $changed = true;
                return '<c:f>[0]!tj_' . $m[1] . '</c:f>';
            },
            $xml
        ) ?? $xml;

        return preg_replace('~(<c:f>\[0\]!tj_[A-Z]+</c:f>)<c:(str|num)Cache>.*?</c:\2Cache>~s', '$1', $out) ?? $out;
    }

    private static function addNames(string $wb): string
    {
        $names = '';
        foreach (self::DYN_COLS as $c) {
            $names .= '<definedName name="tj_' . $c . '">OFFSET(Ringkasan!$' . $c . '$4,0,0,MAX(1,Ringkasan!$AM$2),1)</definedName>';
        }

        if (strpos($wb, '</definedNames>') !== false) {
            return str_replace('</definedNames>', $names . '</definedNames>', $wb);
        }
        if (preg_match('/<definedNames\s*\/>/', $wb)) {
            return preg_replace_callback('/<definedNames\s*\/>/', fn() => '<definedNames>' . $names . '</definedNames>', $wb, 1);
        }
        return str_replace('</sheets>', '</sheets><definedNames>' . $names . '</definedNames>', $wb);
    }

    /** label kategori (nama tujuan) di sumbu bawah: miring 45 derajat, semua tampil */
    private static function rotateCatLabels(string $xml): string
    {
        return preg_replace_callback('#<c:catAx>.*?</c:catAx>#s', function ($m) {
            $x = $m[0];

            $tx = '<c:txPr><a:bodyPr rot="-2700000" vert="horz"/><a:lstStyle/><a:p><a:pPr>'
                . '<a:defRPr sz="800"/></a:pPr><a:endParaRPr lang="en-US"/></a:p></c:txPr>';

            $x = preg_replace('#<c:txPr>.*?</c:txPr>#s', '', $x);
            $x = preg_replace('#<c:crossAx#', $tx . '<c:crossAx', $x, 1);

            if (strpos($x, 'tickLblSkip') === false) {
                $x = strpos($x, '<c:noMultiLvlLbl') !== false
                    ? preg_replace('#<c:noMultiLvlLbl#', '<c:tickLblSkip val="1"/><c:noMultiLvlLbl', $x, 1)
                    : str_replace('</c:catAx>', '<c:tickLblSkip val="1"/></c:catAx>', $x);
            }
            return $x;
        }, $xml) ?? $xml;
    }

    private static function stacked(string $xml): string
    {
        $xml = str_replace('<c:dLblPos val="outEnd"/>', '<c:dLblPos val="ctr"/>', $xml);
        $xml = str_replace('sz="900"', 'sz="700"', $xml);

        if (strpos($xml, '<c:overlap') === false) {
            $xml = preg_replace('#(<c:gapWidth val="\d+"/>)#', '$1<c:overlap val="100"/>', $xml, 1, $n);
            if (!$n) {
                $xml = preg_replace('#<c:axId#', '<c:overlap val="100"/><c:axId', $xml, 1);
            }
        }
        return preg_replace('#<c:gapWidth val="\d+"/>#', '<c:gapWidth val="10"/>', $xml, 1) ?? $xml;
    }

    private static function styleSeries(string $ser): string
    {
        $color = '2563EB';
        if (preg_match('#<c:spPr>.*?<a:srgbClr val="([0-9A-Fa-f]{6})"#s', $ser, $m)) {
            $color = strtoupper($m[1]);
        }

        $isPct = preg_match('#<c:val>.*?<c:f>(.*?)</c:f>#s', $ser, $m)
            && preg_match('#\$(' . implode('|', self::PCT_COLS) . ')\$\d+#', $m[1]);
        $fmt = $isPct ? '0.0%;;;' : '#,##0;;;';

        [$r, $g, $b] = [hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2))];
        $text   = (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '000000' : 'FFFFFF';
        $border = sprintf('%02X%02X%02X', (int) ($r * 0.65), (int) ($g * 0.65), (int) ($b * 0.65));

        $dl = '<c:dLbls>'
            . '<c:numFmt formatCode="' . $fmt . '" sourceLinked="0"/>'
            . '<c:spPr><a:solidFill><a:srgbClr val="' . $color . '"/></a:solidFill>'
            . '<a:ln w="19050"><a:solidFill><a:srgbClr val="' . $border . '"/></a:solidFill></a:ln></c:spPr>'
            . '<c:txPr><a:bodyPr/><a:lstStyle/><a:p><a:pPr><a:defRPr sz="900" b="1">'
            . '<a:solidFill><a:srgbClr val="' . $text . '"/></a:solidFill></a:defRPr></a:pPr>'
            . '<a:endParaRPr lang="en-US"/></a:p></c:txPr>'
            . '<c:dLblPos val="outEnd"/>'
            . '<c:showLegendKey val="0"/><c:showVal val="1"/><c:showCatName val="0"/>'
            . '<c:showSerName val="0"/><c:showPercent val="0"/><c:showBubbleSize val="0"/>'
            . '</c:dLbls>';

        $ser = preg_replace('#<c:dLbls>.*?</c:dLbls>#s', '', $ser);

        $count = 0;
        $out = preg_replace('#<c:cat>#', $dl . '<c:cat>', $ser, 1, $count);
        if ($count !== 1) {
            $out = preg_replace('#<c:val>#', $dl . '<c:val>', $ser, 1, $count);
        }

        return $count === 1 ? $out : $ser;
    }
}