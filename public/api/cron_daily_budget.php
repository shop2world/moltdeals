<?php
/**
 * /api/cron_daily_budget.php — Reset daily budget counters
 * Should be called once daily via cron or manually.
 * URL: https://moltdeals.net/api/cron_daily_budget.php?key=SECRET
 */
$root = realpath(__DIR__ . "/../..");
$env = [];
foreach (file($root . "/.env") as $line) {
    $line = trim($line);
    if ($line && $line[0] !== "#" && strpos($line, "=") !== false) {
        list($k, $v) = explode("=", $line, 2);
        $env[trim($k)] = trim($v);
    }
}
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD']);
$pdo->exec("UPDATE campaigns SET budget_spent_today = 0 WHERE status IN ('active','paused')");

// Re-activate campaigns that were paused due to daily budget
$pdo->exec("UPDATE campaigns SET status = 'active' WHERE status = 'exhausted' AND budget_total IS NOT NULL AND budget_spent < budget_total");

echo json_encode(array("success" => true, "message" => "Daily budgets reset"));