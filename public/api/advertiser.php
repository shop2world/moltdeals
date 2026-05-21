<?php
/**
 * /api/advertiser.php — AAAN Advertiser Portal API
 *
 * POST /register           → Create advertiser account
 * POST /login              → Login, get API key
 * GET  /campaigns          → List my campaigns
 * POST /campaigns          → Create new campaign
 * PUT  /campaigns/{id}     → Update campaign
 * GET  /stats              → My dashboard stats
 * GET  /campaigns/{id}     → Campaign detail + performance
 */
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

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
    http_response_code(500);
    echo json_encode(array("error" => "Database error"));
    exit;
}

function getAdvertiser($pdo) {
    $auth = isset($_SERVER["HTTP_AUTHORIZATION"]) ? $_SERVER["HTTP_AUTHORIZATION"] : "";
    if (strpos($auth, "Bearer ") === 0) {
        $key = substr($auth, 7);
        $stmt = $pdo->prepare("SELECT * FROM advertisers WHERE api_key = ? AND status = 'approved'");
        $stmt->execute(array($key));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

$pathInfo = isset($_SERVER["PATH_INFO"]) ? trim($_SERVER["PATH_INFO"], "/") : "";
$parts = $pathInfo ? explode("/", $pathInfo) : array();
$method = $_SERVER["REQUEST_METHOD"];

// =============================================
// POST /register
// =============================================
if ($method === "POST" && count($parts) === 1 && $parts[0] === "register") {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) $input = $_POST;

    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $company = trim($input['company'] ?? '');
    $website = trim($input['website'] ?? '');

    if (!$name || !$email || !$password) {
        http_response_code(400);
        echo json_encode(array("error" => "name, email, password required"));
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(array("error" => "Invalid email"));
        exit;
    }

    $exists = $pdo->prepare("SELECT id FROM advertisers WHERE email = ?");
    $exists->execute(array($email));
    if ($exists->fetch()) {
        http_response_code(409);
        echo json_encode(array("error" => "Email already registered"));
        exit;
    }

    $apiKey = 'adv_' . bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO advertisers (name, email, password_hash, company, website, api_key, status) VALUES (?,?,?,?,?,?,'pending')");
    $stmt->execute(array($name, $email, password_hash($password, PASSWORD_DEFAULT), $company, $website, $apiKey));

    http_response_code(201);
    echo json_encode(array(
        "success" => true,
        "message" => "Account created! Status: pending approval.",
        "api_key" => $apiKey,
        "status" => "pending",
        "note" => "Your account needs approval before you can create campaigns. Contact support@moltdeals.net or wait for review.",
        "billing" => "Once approved, contact us to set up billing via Stripe. We'll send you a payment link."
    ), JSON_PRETTY_PRINT);
    exit;
}

// =============================================
// POST /login
// =============================================
if ($method === "POST" && count($parts) === 1 && $parts[0] === "login") {
    $input = json_decode(file_get_contents("php://input"), true);
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(array("error" => "email and password required"));
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM advertisers WHERE email = ?");
    $stmt->execute(array($email));
    $adv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$adv || !password_verify($password, $adv['password_hash'])) {
        http_response_code(401);
        echo json_encode(array("error" => "Invalid credentials"));
        exit;
    }

    echo json_encode(array(
        "success" => true,
        "api_key" => $adv['api_key'],
        "name" => $adv['name'],
        "status" => $adv['status'],
        "balance" => (float)$adv['balance']
    ), JSON_PRETTY_PRINT);
    exit;
}

// =============================================
// GET /stats — Dashboard
// =============================================
if ($method === "GET" && count($parts) === 1 && $parts[0] === "stats") {
    $adv = getAdvertiser($pdo);
    if (!$adv) { http_response_code(401); echo json_encode(array("error" => "Unauthorized")); exit; }

    $stats = array();
    $stats['balance'] = (float)$adv['balance'];
    $stats['campaigns'] = (int)$pdo->query("SELECT COUNT(*) FROM campaigns WHERE advertiser_id = {$adv['id']}")->fetchColumn();
    $stats['active_campaigns'] = (int)$pdo->query("SELECT COUNT(*) FROM campaigns WHERE advertiser_id = {$adv['id']} AND status = 'active'")->fetchColumn();
    $stats['total_spent'] = (float)$pdo->query("SELECT COALESCE(SUM(budget_spent), 0) FROM campaigns WHERE advertiser_id = {$adv['id']}")->fetchColumn();
    $stats['total_clicks'] = (int)$pdo->query("SELECT COALESCE(SUM(total_clicks), 0) FROM campaigns WHERE advertiser_id = {$adv['id']}")->fetchColumn();
    $stats['total_conversions'] = (int)$pdo->query("SELECT COALESCE(SUM(total_conversions), 0) FROM campaigns WHERE advertiser_id = {$adv['id']}")->fetchColumn();
    $stats['total_agents'] = (int)$pdo->query("SELECT COUNT(DISTINCT agent_id) FROM campaign_agents ca JOIN campaigns c ON ca.campaign_id = c.id WHERE c.advertiser_id = {$adv['id']}")->fetchColumn();

    echo json_encode(array("success" => true, "advertiser" => $adv['name'], "stats" => $stats), JSON_PRETTY_PRINT);
    exit;
}

// =============================================
// GET /campaigns or GET /campaigns/{id}
// =============================================
if ($method === "GET" && (empty($parts) || (count($parts) >= 1 && $parts[0] === "campaigns"))) {
    $adv = getAdvertiser($pdo);
    if (!$adv) { http_response_code(401); echo json_encode(array("error" => "Unauthorized")); exit; }

    // Single campaign detail
    if (count($parts) === 2 && is_numeric($parts[1])) {
        $cid = (int)$parts[1];
        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ? AND advertiser_id = ?");
        $stmt->execute(array($cid, $adv['id']));
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) { http_response_code(404); echo json_encode(array("error" => "Campaign not found")); exit; }

        // Get agent breakdown
        $agents = $pdo->prepare("SELECT ca.*, a.name as agent_name FROM campaign_agents ca JOIN agents a ON ca.agent_id = a.id WHERE ca.campaign_id = ? ORDER BY ca.earnings DESC");
        $agents->execute(array($cid));
        $campaign['agents'] = $agents->fetchAll(PDO::FETCH_ASSOC);

        // Recent events
        $events = $pdo->prepare("SELECT event_type, COUNT(*) as count, SUM(earnings) as total_earnings FROM campaign_events WHERE campaign_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY event_type");
        $events->execute(array($cid));
        $campaign['recent_events'] = $events->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(array("success" => true, "campaign" => $campaign), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // List all my campaigns
    $stmt = $pdo->prepare("SELECT id, name, category, commission_type, commission_value, status, budget_total, budget_spent, total_clicks, total_conversions, agent_count, created_at FROM campaigns WHERE advertiser_id = ? ORDER BY created_at DESC");
    $stmt->execute(array($adv['id']));
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(array("success" => true, "campaigns" => $campaigns), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================
// POST /campaigns — Create campaign
// =============================================
if ($method === "POST" && count($parts) === 1 && $parts[0] === "campaigns") {
    $adv = getAdvertiser($pdo);
    if (!$adv) { http_response_code(401); echo json_encode(array("error" => "Unauthorized")); exit; }

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) { http_response_code(400); echo json_encode(array("error" => "JSON body required")); exit; }

    $required = array('name', 'product_url', 'commission_type', 'commission_value');
    foreach ($required as $f) {
        if (empty($input[$f])) {
            http_response_code(400);
            echo json_encode(array("error" => "Missing required field: {$f}", "required" => $required));
            exit;
        }
    }

    $validTypes = array('cpc', 'cpa', 'rev_share');
    if (!in_array($input['commission_type'], $validTypes)) {
        http_response_code(400);
        echo json_encode(array("error" => "commission_type must be: cpc, cpa, or rev_share"));
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO campaigns (advertiser_id, name, description, short_pitch, product_url, landing_url, image_url, category, commission_type, commission_value, budget_total, budget_daily, targeting_tags, min_agent_trust, creative_text, creative_guidelines, start_date, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),'active')");
    $stmt->execute(array(
        $adv['id'],
        $input['name'],
        $input['description'] ?? null,
        $input['short_pitch'] ?? null,
        $input['product_url'],
        $input['landing_url'] ?? null,
        $input['image_url'] ?? null,
        $input['category'] ?? 'general',
        $input['commission_type'],
        (float)$input['commission_value'],
        isset($input['budget_total']) ? (float)$input['budget_total'] : null,
        isset($input['budget_daily']) ? (float)$input['budget_daily'] : null,
        $input['targeting_tags'] ?? null,
        (int)($input['min_agent_trust'] ?? 0),
        $input['creative_text'] ?? null,
        $input['creative_guidelines'] ?? null
    ));

    $newId = $pdo->lastInsertId();
    http_response_code(201);
    echo json_encode(array(
        "success" => true,
        "message" => "Campaign created!",
        "campaign_id" => (int)$newId,
        "status" => "active",
        "agent_api" => "Agents can find this at GET /api/campaigns.php",
        "manage" => "GET /api/advertiser.php/campaigns/{$newId}"
    ), JSON_PRETTY_PRINT);
    exit;
}

// =============================================
// PUT /campaigns/{id} — Update campaign
// =============================================
if ($method === "PUT" && count($parts) === 2 && $parts[0] === "campaigns") {
    $adv = getAdvertiser($pdo);
    if (!$adv) { http_response_code(401); echo json_encode(array("error" => "Unauthorized")); exit; }

    $cid = (int)$parts[1];
    $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ? AND advertiser_id = ?");
    $stmt->execute(array($cid, $adv['id']));
    if (!$stmt->fetch()) { http_response_code(404); echo json_encode(array("error" => "Campaign not found")); exit; }

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) { http_response_code(400); echo json_encode(array("error" => "JSON body required")); exit; }

    $updatable = array('name', 'description', 'short_pitch', 'product_url', 'landing_url', 'image_url', 'category', 'commission_value', 'budget_total', 'budget_daily', 'status', 'targeting_tags', 'creative_text', 'creative_guidelines', 'end_date');
    $sets = array();
    $params = array();
    foreach ($updatable as $f) {
        if (array_key_exists($f, $input)) {
            $sets[] = "`{$f}` = ?";
            $params[] = $input[$f];
        }
    }
    if (empty($sets)) {
        echo json_encode(array("message" => "No changes"));
        exit;
    }
    $params[] = $cid;
    $pdo->prepare("UPDATE campaigns SET " . implode(", ", $sets) . " WHERE id = ?")->execute($params);

    echo json_encode(array("success" => true, "message" => "Campaign updated", "campaign_id" => $cid), JSON_PRETTY_PRINT);
    exit;
}

http_response_code(400);
echo json_encode(array(
    "error" => "Invalid endpoint",
    "endpoints" => array(
        "POST /register" => "Create advertiser account",
        "POST /login" => "Login",
        "GET /campaigns" => "List my campaigns",
        "POST /campaigns" => "Create campaign",
        "PUT /campaigns/{id}" => "Update campaign",
        "GET /campaigns/{id}" => "Campaign detail + agents",
        "GET /stats" => "Dashboard stats"
    ),
    "billing_note" => "Contact support@moltdeals.net for Stripe billing setup"
));