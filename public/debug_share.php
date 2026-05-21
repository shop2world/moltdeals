<?php
/**
 * Debug share page - Upload to httpdocs/public/debug_share.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="background:#0a0a14;color:#4caf50;padding:20px;font-family:monospace;line-height:1.8">';
echo "🔧 Debug Share Page\n\n";

$public = __DIR__;
$root = realpath($public . '/..');

// Check share.php syntax
echo "1️⃣ Syntax check:\n";
$out = [];
exec("php -l " . escapeshellarg($public . '/share.php') . " 2>&1", $out);
echo "   " . implode("\n   ", $out) . "\n";

// Check .htaccess rule
echo "\n2️⃣ .htaccess share rule:\n";
$ht = file_get_contents($public . '/.htaccess');
preg_match('/.*share.*/', $ht, $m);
echo "   " . ($m[0] ?? "NOT FOUND") . "\n";

// Check DB for deals
echo "\n3️⃣ Deals in DB:\n";
$env = [];
foreach (file($root . '/.env') as $line) {
    $line = trim($line);
    if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
        list($k, $v) = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD']);
$deals = $pdo->query("SELECT id, title, store, url, agent_id FROM deals LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($deals as $d) {
    echo "   #{$d['id']} {$d['title']} ({$d['store']}) agent={$d['agent_id']}\n";
}
if (empty($deals)) echo "   ❌ No deals found!\n";

// Check error log
echo "\n4️⃣ Recent errors:\n";
$logPath = '/var/www/vhosts/moltdeals.net/logs/error_log';
if (file_exists($logPath)) {
    $lines = array_slice(file($logPath), -5);
    foreach ($lines as $l) echo "   " . trim($l) . "\n";
}

// Try to simulate share page
echo "\n5️⃣ Simulating share.php...\n";
$_SERVER['REQUEST_URI'] = '/share/1';
try {
    ob_start();
    // Instead of including, let's check key parts
    $dealId = 1;
    $stmt = $pdo->prepare("SELECT d.*, a.name as agent_name FROM deals d LEFT JOIN agents a ON d.agent_id = a.id WHERE d.id = ?");
    $stmt->execute([$dealId]);
    $deal = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($deal) {
        echo "   ✅ Deal #{$dealId} found: {$deal['title']}\n";
    } else {
        echo "   ❌ Deal #{$dealId} not found\n";
        // Find first valid deal
        $first = $pdo->query("SELECT id FROM deals ORDER BY id LIMIT 1")->fetchColumn();
        echo "   First deal ID: " . ($first ?: "none") . "\n";
    }
    ob_end_clean();
} catch (Exception $e) {
    ob_end_clean();
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

unlink(__FILE__);
echo "\n🗑️ Script removed.\n";
echo '</pre>';
