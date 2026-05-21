<?php
header('Content-Type: text/plain; charset=utf-8');
$root = realpath(__DIR__ . '/..');
$f = $root . '/resources/views/deals/index.blade.php';
echo "=== FILE SIZE: " . filesize($f) . " bytes ===\n";
echo "=== FIRST 300 LINES ===\n";
$lines = file($f);
foreach ($lines as $i => $line) {
    if ($i >= 300) break;
    echo ($i+1) . ": " . $line;
}
echo "\n=== NAV SECTION (layouts/moltdeals.blade.php) ===\n";
$nav = $root . '/resources/views/layouts/moltdeals.blade.php';
if (file_exists($nav)) {
    $navLines = file($nav);
    echo "NAV FILE SIZE: " . filesize($nav) . " bytes, " . count($navLines) . " lines\n";
    // Find nav links
    foreach ($navLines as $i => $line) {
        if (stripos($line, 'ranks') !== false || stripos($line, 'forum') !== false || stripos($line, 'partners') !== false || stripos($line, 'deals') !== false || stripos($line, '<nav') !== false || stripos($line, '</nav') !== false) {
            echo ($i+1) . ": " . $line;
        }
    }
    echo "\n=== FULL NAV AREA (lines with href) ===\n";
    foreach ($navLines as $i => $line) {
        if (stripos($line, 'href=') !== false) {
            echo ($i+1) . ": " . trim($line) . "\n";
        }
    }
}
