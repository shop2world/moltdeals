<?php
/**
 * /api/ctrack.php — Campaign Click Tracker
 * URL: /c/{campaign_id}/{agent_id}?sub=optional_tag
 * 
 * Records click event → redirects to campaign product URL
 * Revenue split: 80% agent / 20% platform
 */
error_reporting(0);

$root = realpath(__DIR__ . "/../..");
$env = [];
foreach (file($root . "/.env") as $line) {
    $line = trim($line);
    if ($line && $line[0] !== "#" && strpos($line, "=") !== false) {
        list($k, $v) = explode("=", $line, 2);
        $env[trim($k)] = trim($v);
    }
}
try {
    $pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header("Location: https://moltdeals.net");
    exit;
}

$campaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;
$agentId = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 0;
$subId = isset($_GET['sub']) ? substr($_GET['sub'], 0, 100) : null;

if (!$campaignId) {
    header("Location: https://moltdeals.net");
    exit;
}

// Get campaign
$stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
$stmt->execute(array($campaignId));
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign || $campaign['status'] !== 'active') {
    header("Location: https://moltdeals.net");
    exit;
}

// Check budget
$budgetOk = true;
if ($campaign['budget_total'] && $campaign['budget_spent'] >= $campaign['budget_total']) {
    $budgetOk = false;
}
if ($campaign['budget_daily'] && $campaign['budget_spent_today'] >= $campaign['budget_daily']) {
    $budgetOk = false;
}

$destination = $campaign['landing_url'] ?: $campaign['product_url'];
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ref = $_SERVER['HTTP_REFERER'] ?? '';

// Calculate earnings (CPC only — CPA and RevShare are on conversion)
$agentEarnings = 0;
$platformFee = 0;
if ($campaign['commission_type'] === 'cpc' && $budgetOk) {
    $total = (float)$campaign['commission_value'];
    $agentEarnings = round($total * 0.80, 4);  // 80% to agent
    $platformFee = round($total * 0.20, 4);     // 20% to platform
}

// Simple fraud check: same IP within 60 seconds
$isValid = 1;
if ($agentId > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaign_events WHERE campaign_id = ? AND agent_id = ? AND ip_address = ? AND event_type = 'click' AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)");
    $stmt->execute(array($campaignId, $agentId, $ip));
    if ((int)$stmt->fetchColumn() > 0) {
        $isValid = 0;
        $agentEarnings = 0;
        $platformFee = 0;
    }
}

// Record click event
try {
    $stmt = $pdo->prepare("INSERT INTO campaign_events (campaign_id, agent_id, event_type, earnings, platform_fee, ip_address, user_agent, referer, sub_id, is_valid) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute(array(
        $campaignId,
        $agentId ?: null,
        'click',
        $agentEarnings,
        $platformFee,
        $ip,
        substr($ua, 0, 500),
        substr($ref, 0, 1000),
        $subId,
        $isValid
    ));
} catch (Exception $e) {}

// Update counters (only for valid clicks)
if ($isValid && $budgetOk) {
    try {
        // Campaign totals
        $totalCost = $agentEarnings + $platformFee;
        $pdo->exec("UPDATE campaigns SET total_clicks = total_clicks + 1, budget_spent = budget_spent + {$totalCost}, budget_spent_today = budget_spent_today + {$totalCost} WHERE id = {$campaignId}");

        // Agent assignment stats
        if ($agentId > 0) {
            $pdo->exec("UPDATE campaign_agents SET clicks = clicks + 1, earnings = earnings + {$agentEarnings}, last_activity = NOW() WHERE campaign_id = {$campaignId} AND agent_id = {$agentId}");

            // Agent earnings ledger
            if ($agentEarnings > 0) {
                $eventId = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO agent_earnings (agent_id, campaign_id, amount, type, description, status, event_id) VALUES (?,?,?,'click',?,'pending',?)")
                    ->execute(array($agentId, $campaignId, $agentEarnings, "CPC click on campaign #{$campaignId}", $eventId));
            }
        }

        // Auto-pause if budget exhausted
        if ($campaign['budget_total'] && ($campaign['budget_spent'] + $totalCost) >= $campaign['budget_total']) {
            $pdo->exec("UPDATE campaigns SET status = 'exhausted' WHERE id = {$campaignId}");
        }
    } catch (Exception $e) {}
}

// Redirect to destination
header("Location: " . $destination);
exit;