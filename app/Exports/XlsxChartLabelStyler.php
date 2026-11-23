<?php

namespace App\Exports;

use ZipArchive;

/**
 * Mempercantik chart hasil PhpSpreadsheet (gaya dashboard monitoring):
 *  - label angka = kotak berwarna series + border lebih gelap, teks putih/hitam otomatis
 *  - nilai 0 disembunyikan lewat format angka
 *  - garis grid gelap, sudut chart membulat
 * Dipanggil SETELAH file xlsx jadi (lihat XlsxSlicerInjector::inject). Gagal = file asli tidak berubah.
 */
class XlsxChartLabelStyler
{
    /** kolom Ringkasan berisi persen (CR): label pakai 0.0% */
    private const PCT_COLS = ['N', 'Y'];
    private const GRID     = '1F2937';

    public static function apply(string $path): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        try {
            $new = [];
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

                // buang dLbls tingkat plot (di luar <c:ser>) bawaan PhpSpreadsheet; yang dipakai dLbls per series
                $out = preg_replace('#(</c:ser>)\s*<c:dLbls>.*?</c:dLbls>#s', '$1', $out) ?? $out;

                // garis grid gelap
                $grid = '<c:majorGridlines><c:spPr><a:ln w="9525"><a:solidFill><a:srgbClr val="' . self::GRID
                    . '"/></a:solidFill></a:ln></c:spPr></c:majorGridlines>';
                $out = preg_replace('#<c:majorGridlines\s*/>#', $grid, $out) ?? $out;
                $out = preg_replace('#<c:majorGridlines>.*?</c:majorGridlines>#s', $grid, $out) ?? $out;

                // sudut membulat
                $out = preg_replace('#<c:roundedCorners val="0"\s*/>#', '<c:roundedCorners val="1"/>', $out) ?? $out;

                $prev = libxml_use_internal_errors(true);
                $ok   = (new \DOMDocument())->loadXML($out);
                libxml_clear_errors();
                libxml_use_internal_errors($prev);

                if ($ok) {
                    $new[$name] = $out;
                }
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