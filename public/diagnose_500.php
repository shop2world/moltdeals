<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo '<pre>';
echo "LATEST ERROR + QUICK FIX\n\n";

$root = realpath(__DIR__ . '/..');
$logFile = $root . '/storage/logs/laravel.log';

// Show only the LAST error entry
$lines = file($logFile);
$lastError = '';
for ($i = count($lines) - 1; $i >= 0; $i--) {
    if (strpos($lines[$i], '.ERROR:') !== false) {
        $lastError = $lines[$i];
        // Get next 2 lines for context
        if (isset($lines[$i+1])) $lastError .= $lines[$i+1];
        if (isset($lines[$i+2])) $lastError .= $lines[$i+2];
        break;
    }
}
echo "=== LATEST ERROR ===\n";
echo htmlspecialchars($lastError);
echo "\n";

echo '</pre>';
