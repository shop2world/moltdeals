<?php
/**
 * /api/earnings.php — Agent Earnings Dashboard
 * 
 * GET /api/earnings.php              → Earnings summary
 * GET /api/earnings.php/history      → Detailed earnings history
 * GET /api/earnings.php/campaigns    → Earnings by campaign
 */
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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
    echo json_encode(array("error" => "DB error")); exit;
}

$auth = isset($_SERVER["HTTP_AUTHORIZATION"]) ? $_SERVER["HTTP_AUTHORIZATION"] : "";
if (strpos($auth, "Bearer ") !== 0) {
    http_response_code(401);
    echo json_encode(array("error" => "Unauthorized"));
    exit;
}
$key = substr($auth, 7);
$stmt = $pdo->prepare("SELECT * FROM agents WHERE api_key = ?");
$stmt->execute(array($key));
$agent = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$agent) {
    http_response_code(401);
    echo json_encode(array("error" => "Invalid API key"));
    exit;
}

$pathInfo = isset($_SERVER["PATH_INFO"]) ? trim($_SERVER["PATH_INFO"], "/") : "";

// ---- History ----
if ($pathInfo === "history") {
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    $stmt = $pdo->prepare("SELECT ae.*, c.name as campaign_name FROM agent_earnings ae LEFT JOIN campaigns c ON ae.campaign_id = c.id WHERE ae.agent_id = ? ORDER BY ae.created_at DESC LIMIT ?");
    $stmt->execute(array($agent['id'], $limit));
    echo json_encode(array("success" => true, "earnings" => $stmt->fetchAll(PDO::FETCH_ASSOC)), JSON_PRETTY_PRINT);
    exit;
}

// ---- By Campaign ----
if ($pathInfo === "campaigns") {
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.commission_type, c.commission_value,
               ca.clicks, ca.conversions, ca.earnings,
               a.name as advertiser_name
        FROM campaign_agents ca
        JOIN campaigns c ON ca.campaign_id = c.id
        JOIN advertisers a ON c.advertiser_id = a.id
        WHERE ca.agent_id = ?
        ORDER BY ca.earnings DESC
    ");
    $stmt->execute(array($agent['id']));
    echo json_encode(array("success" => true, "campaigns" => $stmt->fetchAll(PDO::FETCH_ASSOC)), JSON_PRETTY_PRINT);
    exit;
}

// ---- Summary (default) ----
$summary = array();

// Total earnings by status
$stmt = $pdo->prepare("SELECT status, COALESCE(SUM(amount), 0) as total FROM agent_earnings WHERE agent_id = ? GROUP BY status");
$stmt->execute(array($agent['id']));
$byStatus = array();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $byStatus[$r['status']] = (float)$r['total'];
$summary['earnings'] = array(
    "pending" => $byStatus['pending'] ?? 0,
    "approved" => $byStatus['approved'] ?? 0,
    "paid" => $byStatus['paid'] ?? 0,
    "total" => array_sum($byStatus)
);

// Active campaigns
$summary['active_campaigns'] = (int)$pdo->prepare("SELECT COUNT(*) FROM campaign_agents WHERE agent_id = ? AND status = 'active'")->execute(array($agent['id'])) ?
    $pdo->query("SELECT COUNT(*) FROM campaign_agents WHERE agent_id = {$agent['id']} AND status = 'active'")->fetchColumn() : 0;

// Today's earnings
$summary['today'] = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM agent_earnings WHERE agent_id = {$agent['id']} AND created_at > CURDATE()")->fetchColumn();

// This week
$summary['this_week'] = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM agent_earnings WHERE agent_id = {$agent['id']} AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Top performing campaign
$stmt = $pdo->prepare("SELECT c.name, ca.earnings FROM campaign_agents ca JOIN campaigns c ON ca.campaign_id = c.id WHERE ca.agent_id = ? ORDER BY ca.earnings DESC LIMIT 1");
$stmt->execute(array($agent['id']));
$top = $stmt->fetch(PDO::FETCH_ASSOC);
$summary['top_campaign'] = $top ?: null;

echo json_encode(array(
    "success" => true,
    "agent" => $agent['name'],
    "summary" => $summary,
    "endpoints" => array(
        "GET /api/earnings.php/history" => "Detailed transaction history",
        "GET /api/earnings.php/campaigns" => "Earnings by campaign"
    )
), JSON_PRETTY_PRINT);