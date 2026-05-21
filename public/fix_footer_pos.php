<?php
/**
 * Debug + fix footer position
 * Upload to: httpdocs/public/fix_footer_pos.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="background:#0a0a14;color:#4caf50;padding:20px;font-family:monospace;line-height:1.6">';
echo "🔧 Fix Footer Position\n\n";

$root = realpath(__DIR__ . '/..');
$viewsDir = $root . '/resources/views';

// Show structure of blade files
foreach (['deals/index.blade.php', 'deals/list.blade.php'] as $v) {
    $path = $viewsDir . '/' . $v;
    if (!file_exists($path)) continue;
    
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    $total = count($lines);
    
    echo "📄 {$v} ({$total} lines)\n";
    
    // Find key positions
    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (strpos($trimmed, 'molt-footer') !== false ||
            strpos($trimmed, '<footer') !== false ||
            strpos($trimmed, '</footer>') !== false ||
            strpos($trimmed, '</body>') !== false ||
            strpos($trimmed, '</html>') !== false ||
            strpos($trimmed, '<body') !== false ||
            strpos($trimmed, '<head') !== false ||
            strpos($trimmed, '</head>') !== false ||
            strpos($trimmed, 'footer-wave') !== false ||
            strpos($trimmed, 'footer-inner') !== false ||
            strpos($trimmed, '@extends') !== false ||
            strpos($trimmed, '@section') !== false ||
            strpos($trimmed, '@endsection') !== false ||
            strpos($trimmed, '@yield') !== false ||
            strpos($trimmed, 'Share Button') !== false ||
            strpos($trimmed, 'OpenClaw') !== false ||
            strpos($trimmed, 'Powered by') !== false) {
            echo "   L" . ($i+1) . ": " . substr($trimmed, 0, 120) . "\n";
        }
    }
    
    // Show first 5 and last 30 lines
    echo "\n   --- FIRST 5 LINES ---\n";
    for ($i = 0; $i < min(5, $total); $i++) {
        echo "   L" . ($i+1) . ": " . substr(trim($lines[$i]), 0, 120) . "\n";
    }
    echo "\n   --- LAST 30 LINES ---\n";
    for ($i = max(0, $total - 30); $i < $total; $i++) {
        echo "   L" . ($i+1) . ": " . substr(trim($lines[$i]), 0, 120) . "\n";
    }
    echo "\n\n";
}

echo "🗑️ Script stays for next step.\n";
echo '</pre>';
