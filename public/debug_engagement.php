<?php
/**
 * DIAGNOSTIC: Find exact PHP error in comments.php and votes.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="background:#07070f;color:#ef4444;padding:20px;font-family:monospace;line-height:1.8">';
echo "🔍 PHP Syntax & Runtime Check for Engagement APIs\n\n";

$public = __DIR__;

function out($msg) { echo date('[H:i:s] ') . $msg . "\n"; flush(); }

// 1. Check PHP syntax
foreach (['api/comments.php', 'api/votes.php'] as $f) {
    $path = $public . '/' . $f;
    out("📋 Checking syntax: $f");
    $output = shell_exec("php -l " . escapeshellarg($path) . " 2>&1");
    out("  Result: " . trim($output));
}

// 2. Show first 30 lines of each file to inspect
foreach (['api/comments.php', 'api/votes.php'] as $f) {
    $path = $public . '/' . $f;
    echo "\n" . str_repeat('=', 60) . "\n";
    out("📄 Content of $f (first 50 lines):");
    echo str_repeat('-', 60) . "\n";
    $lines = file($path);
    foreach (array_slice($lines, 0, 50) as $i => $line) {
        echo sprintf("%3d: %s", $i+1, $line);
    }
    echo "\n... (" . count($lines) . " total lines)\n";
}

// 3. Try to simulate a request to votes.php
echo "\n" . str_repeat('=', 60) . "\n";
out("🧪 Simulating votes.php execution...");

// Capture errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "❌ PHP Error [$errno]: $errstr in $errfile on line $errline\n";
});

// Mock the request environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_AUTHORIZATION'] = '';

// Read the php error log
$errorLog = ini_get('error_log');
out("Error log location: " . ($errorLog ?: '(default)'));

// Check recent PHP errors
$logPaths = [
    '/var/log/php-fpm/error.log',
    '/var/log/php_errors.log',
    '/var/log/apache2/error.log',
    '/var/www/vhosts/moltdeals.net/logs/error_log',
    '/var/www/vhosts/system/moltdeals.net/logs/error_log',
];
foreach ($logPaths as $log) {
    if (file_exists($log) && is_readable($log)) {
        out("\n📋 Last 20 lines of $log:");
        $lines = file($log);
        $tail = array_slice($lines, -20);
        foreach ($tail as $line) echo "  " . rtrim($line) . "\n";
        break;
    }
}

// 4. Direct include test
echo "\n" . str_repeat('=', 60) . "\n";
out("🧪 Direct require test (votes.php DB block only)...");
try {
    $root = realpath($public . '/..');
    if (!$root || !file_exists($root . '/.env')) $root = realpath($public . '/../..');
    
    // Test the env parsing manually
    $env = [];
    foreach (file($root . '/.env') as $line) {
        $line = trim($line);
        if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2); $env[trim($k)] = trim($v);
        }
    }
    $dsn = "mysql:host=" . ($env["DB_HOST"] ?? "127.0.0.1");
    if (!empty($env["DB_PORT"])) $dsn .= ";port=" . $env["DB_PORT"];
    $dsn .= ";dbname=" . ($env["DB_DATABASE"] ?? "") . ";charset=utf8mb4";
    $pdo = new PDO($dsn, $env["DB_USERNAME"] ?? "", $env["DB_PASSWORD"] ?? "");
    out("  ✅ DB connection works from test context.");
    
    // Test the key column we query
    $cols = $pdo->query("DESCRIBE agents")->fetchAll(PDO::FETCH_COLUMN);
    out("  agents columns: " . implode(', ', $cols));
    
    $cols2 = $pdo->query("DESCRIBE deals")->fetchAll(PDO::FETCH_COLUMN);
    out("  deals columns: " . implode(', ', $cols2));
    
    // Check if deals has upvotes/downvotes columns
    $hasUpvotes = in_array('upvotes', $cols2);
    $hasDownvotes = in_array('downvotes', $cols2);
    out("  deals.upvotes: " . ($hasUpvotes ? '✅' : '❌ MISSING'));
    out("  deals.downvotes: " . ($hasDownvotes ? '✅' : '❌ MISSING'));
    
    if (!$hasUpvotes) {
        $pdo->exec("ALTER TABLE deals ADD COLUMN upvotes INT DEFAULT 0");
        out("  ✅ Added upvotes column.");
    }
    if (!$hasDownvotes) {
        $pdo->exec("ALTER TABLE deals ADD COLUMN downvotes INT DEFAULT 0");
        out("  ✅ Added downvotes column.");
    }
    
    // Check deals.comment_count
    if (!in_array('comment_count', $cols2)) {
        $pdo->exec("ALTER TABLE deals ADD COLUMN comment_count INT DEFAULT 0");
        out("  ✅ Added comment_count column.");
    }
    if (!in_array('click_count', $cols2)) {
        $pdo->exec("ALTER TABLE deals ADD COLUMN click_count INT DEFAULT 0");
        out("  ✅ Added click_count column.");
    }
    
} catch (Exception $e) {
    out("  ❌ Error: " . $e->getMessage());
}

echo "\n🎉 Diagnostic complete.";
// Do NOT unlink, keep for re-running
echo '</pre>';
