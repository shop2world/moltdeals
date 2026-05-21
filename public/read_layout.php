<?php
// Read and display the nav section of layout file
header('Content-Type: text/plain; charset=utf-8');
$root = realpath(__DIR__ . '/..');
$f = $root . '/resources/views/layouts/moltdeals.blade.php';
$lines = file($f);
echo "=== LAYOUT FILE: " . count($lines) . " lines, " . filesize($f) . " bytes ===\n\n";
echo "=== ALL LINES WITH href ===\n";
foreach ($lines as $i => $line) {
    if (stripos($line, 'href') !== false) {
        echo ($i+1) . ": " . trim($line) . "\n";
    }
}
echo "\n=== LINES WITH 'ranks' or 'partners' or 'forum' ===\n";
foreach ($lines as $i => $line) {
    if (stripos($line, 'ranks') !== false || stripos($line, 'partners') !== false || stripos($line, 'forum') !== false) {
        echo ($i+1) . ": " . rtrim($line) . "\n";
    }
}
echo "\n=== content/yield section ===\n";
foreach ($lines as $i => $line) {
    if (stripos($line, 'yield') !== false || stripos($line, 'content') !== false || stripos($line, 'container') !== false || stripos($line, 'main') !== false || stripos($line, 'wrapper') !== false) {
        echo ($i+1) . ": " . rtrim($line) . "\n";
    }
}
echo "\n=== NAV AREA (lines 1-80) ===\n";
for ($i = 0; $i < min(80, count($lines)); $i++) {
    echo ($i+1) . ": " . $lines[$i];
}
