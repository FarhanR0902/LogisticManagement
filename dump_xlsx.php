<?php
$z = new ZipArchive;
if ($z->open(__DIR__ . '/storage/app/debug_slicer.xlsx') !== true) {
    die("debug_slicer.xlsx tidak ada / bukan zip\n");
}

$out = "== DAFTAR PART ==\n";
for ($i = 0; $i < $z->numFiles; $i++) {
    $out .= $z->getNameIndex($i) . ' (' . $z->statIndex($i)['size'] . " bytes)\n";
}

$files = [
    '[Content_Types].xml',
    'xl/workbook.xml',
    'xl/_rels/workbook.xml.rels',
    'xl/slicers/slicer1.xml',
    'xl/slicerCaches/slicerCache1.xml',
    'xl/drawings/drawing99.xml',
];

for ($i = 0; $i < $z->numFiles; $i++) {
    $n = $z->getNameIndex($i);
    if (preg_match('#^xl/worksheets/_rels/.+\.rels$#', $n)) $files[] = $n;
}

foreach ($files as $f) {
    $out .= "\n\n===== $f =====\n" . substr((string) $z->getFromName($f), 0, 6000);
}

for ($i = 0; $i < $z->numFiles; $i++) {
    $n = $z->getNameIndex($i);
    if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $n)) {
        $x = (string) $z->getFromName($n);
        $x = preg_replace('#<sheetData>.*</sheetData>#s', '<sheetData>...DIBUANG...</sheetData>', $x);
        $out .= "\n\n===== $n (tanpa sheetData) =====\n" . substr($x, 0, 6000);
    }
    if (preg_match('#^xl/tables/table\d+\.xml$#', $n)) {
        $out .= "\n\n===== $n =====\n" . substr((string) $z->getFromName($n), 0, 3000);
    }
}

file_put_contents(__DIR__ . '/storage/app/dump2.txt', $out);
echo "selesai: storage/app/dump2.txt (" . strlen($out) . " bytes)\n";