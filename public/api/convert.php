<?php
/**
 * /api/convert.php — Conversion Postback
 * 
 * Called by advertiser when a conversion (purchase) happens.
 * 
 * GET /api/convert.php?campaign_id=42&agent_id=5&order_id=XYZ&amount=99.99&token=SECRET
 * POST /api/convert.php (JSON body with same fields)
 * 
 * Revenue split: 80% agent / 20% platform
 */
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

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
    echo json_encode(array("error" => "DB error"));
    exit;
}

// Accept both GET and POST
$input = $_GET;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $body = json_decode(file_get_contents("php://input"), true);
    if ($body) $input = array_merge($input, $body);
}

$campaignId = (int)($input['campaign_id'] ?? 0);
$agentId = (int)($input['agent_id'] ?? 0);
$orderId = $input['order_id'] ?? '';
$amount = (float)($input['amount'] ?? 0);
$token = $input['token'] ?? '';

if (!$campaignId) {
    echo json_encode(array("error" => "campaign_id required"));
    exit;
}

// Get campaign
$stmt = $pdo->prepare("SELECT c.*, a.api_key as adv_key FROM campaigns c JOIN advertisers a ON c.advertiser_id = a.id WHERE c.id = ?");
$stmt->execute(array($campaignId));
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    echo json_encode(array("error" => "Campaign not found"));
    exit;
}

// Auth: either advertiser API key or a pre-shared token
$auth = isset($_SERVER["HTTP_AUTHORIZATION"]) ? $_SERVER["HTTP_AUTHORIZATION"] : "";
$advKey = (strpos($auth, "Bearer ") === 0) ? substr($auth, 7) : '';
if ($advKey !== $campaign['adv_key'] && $token !== substr(md5($campaign['adv_key'] . $campaignId), 0, 16)) {
    http_response_code(401);
    echo json_encode(array("error" => "Unauthorized. Use advertiser API key or valid token."));
    exit;
}

// Duplicate check
if ($orderId) {
    $dup = $pdo->prepare("SELECT id FROM campaign_events WHERE campaign_id = ? AND order_id = ? AND event_type = 'conversion'");
    $dup->execute(array($campaignId, $orderId));
    if ($dup->fetch()) {
        echo json_encode(array("error" => "Duplicate conversion", "order_id" => $orderId));
        exit;
    }
}

// Calculate commission
$commissionTotal = 0;
if ($campaign['commission_type'] === 'cpa') {
    $commissionTotal = (float)$campaign['commission_value'];
} elseif ($campaign['commission_type'] === 'rev_share') {
    $commissionTotal = round($amount * ((float)$campaign['commission_value'] / 100), 2);
}

$agentEarnings = round($commissionTotal * 0.80, 4);
$platformFee = round($commissionTotal * 0.20, 4);

// Record conversion
$pdo->prepare("INSERT INTO campaign_events (campaign_id, agent_id, event_type, earnings, platform_fee, revenue, order_id, ip_address, is_valid) VALUES (?,?,?,?,?,?,?,?,1)")
    ->execute(array($campaignId, $agentId ?: null, 'conversion', $agentEarnings, $platformFee, $amount, $orderId, $_SERVER['REMOTE_ADDR'] ?? ''));

$eventId = $pdo->lastInsertId();

// Update counters
$totalCost = $agentEarnings + $platformFee;
$pdo->exec("UPDATE campaigns SET total_conversions = total_conversions + 1, budget_spent = budget_spent + {$totalCost} WHERE id = {$campaignId}");

if ($agentId > 0) {
    $pdo->exec("UPDATE campaign_agents SET conversions = conversions + 1, earnings = earnings + {$agentEarnings}, last_activity = NOW() WHERE campaign_id = {$campaignId} AND agent_id = {$agentId}");

    // Agent earnings ledger
    $pdo->prepare("INSERT INTO agent_earnings (agent_id, campaign_id, amount, type, description, status, event_id) VALUES (?,?,?,'conversion',?,'pending',?)")
        ->execute(array($agentId, $campaignId, $agentEarnings, "Conversion on campaign #{$campaignId}" . ($orderId ? " (order: {$orderId})" : ""), $eventId));
}

echo json_encode(array(
    "success" => true,
    "conversion" => array(
        "campaign_id" => $campaignId,
        "agent_id" => $agentId,
        "order_id" => $orderId,
        "sale_amount" => $amount,
        "commission" => $commissionTotal,
        "agent_earnings" => $agentEarnings,
        "platform_fee" => $platformFee
    )
), JSON_PRETTY_PRINT);