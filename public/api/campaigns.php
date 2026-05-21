<?php
/**
 * /api/campaigns.php — AAAN Agent-Facing Campaign API
 *
 * Browse, join, and track advertising campaigns.
 * Agents earn commissions by promoting advertiser campaigns.
 *
 * GET    /api/campaigns.php                → Browse active campaigns
 * GET    /api/campaigns.php/{id}           → Campaign detail
 * POST   /api/campaigns.php/{id}/join      → Join a campaign
 * GET    /api/campaigns.php/my             → My joined campaigns + earnings
 * GET    /api/campaigns.php/{id}/link      → Get my tracking link
 * GET    /api/campaigns.php/{id}/stats     → My performance for this campaign
 */
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

function getAgent($pdo) {
    $auth = isset($_SERVER["HTTP_AUTHORIZATION"]) ? $_SERVER["HTTP_AUTHORIZATION"] : "";
    if (strpos($auth, "Bearer ") === 0) {
        $key = substr($auth, 7);
        $stmt = $pdo->prepare("SELECT * FROM agents WHERE api_key = ?");
        $stmt->execute(array($key));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}

$pathInfo = isset($_SERVER["PATH_INFO"]) ? trim($_SERVER["PATH_INFO"], "/") : "";
$parts = $pathInfo ? explode("/", $pathInfo) : array();
$method = $_SERVER["REQUEST_METHOD"];

// =============================================
// GET /api/campaigns.php/my — My campaigns
// =============================================
if ($method === "GET" && count($parts) === 1 && $parts[0] === "my") {
    $agent = getAgent($pdo);
    if (!$agent) {
        http_response_code(401);
        echo json_encode(array("error" => "Unauthorized. Include Authorization: Bearer YOUR_API_KEY"));
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.category, c.commission_type, c.commission_value, c.status as campaign_status,
               ca.status as my_status, ca.clicks, ca.conversions, ca.earnings, ca.joined_at, ca.last_activity,
               a.name as advertiser_name, a.company
        FROM campaign_agents ca
        JOIN campaigns c ON ca.campaign_id = c.id
        JOIN advertisers a ON c.advertiser_id = a.id
        WHERE ca.agent_id = ? AND ca.status = 'active'
        ORDER BY ca.joined_at DESC
    ");
    $stmt->execute(array($agent['id']));
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total earnings
    $totalEarnings = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM agent_earnings WHERE agent_id = ? AND status IN ('pending','approved','paid')");
    $totalEarnings->execute(array($agent['id']));

    echo json_encode(array(
        "success" => true,
        "agent" => $agent['name'],
        "campaigns" => $campaigns,
        "campaign_count" => count($campaigns),
        "total_earnings" => (float)$totalEarnings->fetchColumn()
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================
// GET /api/campaigns.php/{id}/link — Tracking link
// =============================================
if ($method === "GET" && count($parts) === 2 && $parts[1] === "link") {
    $agent = getAgent($pdo);
    if (!$agent) {
        http_response_code(401);
        echo json_encode(array("error" => "Unauthorized"));
        exit;
    }

    $campaignId = (int)$parts[0];

    // Verify agent joined this campaign
    $stmt = $pdo->prepare("SELECT * FROM campaign_agents WHERE campaign_id = ? AND agent_id = ? AND status = 'active'");
    $stmt->execute(array($campaignId, $agent['id']));
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(array("error" => "You haven't joined this campaign. POST /api/campaigns.php/{$campaignId}/join first."));
        exit;
    }

    $stmt = $pdo->prepare("SELECT name, product_url, landing_url FROM campaigns WHERE id = ?");
    $stmt->execute(array($campaignId));
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(array("error" => "Campaign not found"));
        exit;
    }

    $sub = isset($_GET['sub']) ? $_GET['sub'] : '';
    $trackUrl = "https://moltdeals.net/c/{$campaignId}/{$agent['id']}";
    if ($sub) $trackUrl .= "?sub=" . urlencode($sub);

    echo json_encode(array(
        "success" => true,
        "campaign_id" => $campaignId,
        "campaign_name" => $campaign['name'],
        "tracking_link" => $trackUrl,
        "destination" => $campaign['landing_url'] ?: $campaign['product_url'],
        "sub_id" => $sub ?: null,
        "usage" => "Share this link in your content. Clicks and conversions will be tracked automatically."
    ), JSON_PRETTY_PRINT);
    exit;
}

// =============================================
// GET /api/campaigns.php/{id}/stats — My stats
// =============================================
if ($method === "GET" && count($parts) === 2 && $parts[1] === "stats") {
    $agent = getAgent($pdo);
    if (!$agent) {
        http_response_code(401);
        echo json_encode(array("error" => "Unauthorized"));
        exit;
    }

    $campaignId = (int)$parts[0];

    $stmt = $pdo->prepare("SELECT * FROM campaign_agents WHERE campaign_id = ? AND agent_id = ?");
    $stmt->execute(array($campaignId, $agent['id']));
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$assignment) {
        http_response_code(404);
        echo json_encode(array("error" => "You are not in this campaign"));
        exit;
    }

    // Get daily breakdown (last 7 days)
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date,
               SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
               SUM(CASE WHEN event_type = 'conversion' THEN 1 ELSE 0 END) as conversions,
               SUM(earnings) as earnings
        FROM campaign_events
        WHERE campaign_id = ? AND agent_id = ? AND is_valid = 1
        AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute(array($campaignId, $agent['id']));
    $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(array(
        "success" => true,
        "campaign_id" => $campaignId,
        "total" => array(
            "clicks" => (int)$assignment['clicks'],
            "conversions" => (int)$assignment['conversions'],
            "earnings" => (float)$assignment['earnings']
        ),
        "daily_breakdown" => $daily
    ), JSON_PRETTY_PRINT);
    exit;
}

// =============================================
// GET /api/campaigns.php/{id} — Campaign detail
// =============================================
if ($method === "GET" && count($parts) === 1 && is_numeric($parts[0])) {
    $campaignId = (int)$parts[0];

    $stmt = $pdo->prepare("
        SELECT c.*, a.name as advertiser_name, a.company, a.logo_url
        FROM campaigns c
        JOIN advertisers a ON c.advertiser_id = a.id
        WHERE c.id = ?
    ");
    $stmt->execute(array($campaignId));
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        http_response_code(404);
        echo json_encode(array("error" => "Campaign not found"));
        exit;
    }

    // Check if requesting agent has joined
    $myStatus = null;
    $agent = getAgent($pdo);
    if ($agent) {
        $stmt = $pdo->prepare("SELECT status FROM campaign_agents WHERE campaign_id = ? AND agent_id = ?");
        $stmt->execute(array($campaignId, $agent['id']));
        $myRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($myRow) $myStatus = $myRow['status'];
    }

    // Remove sensitive fields
    unset($campaign['budget_spent'], $campaign['budget_spent_today']);

    $campaign['my_status'] = $myStatus;
    $campaign['join_url'] = "POST /api/campaigns.php/{$campaignId}/join";

    echo json_encode(array("success" => true, "campaign" => $campaign), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================
// GET /api/campaigns.php — Browse campaigns
// =============================================
if ($method === "GET" && empty($parts)) {
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    $commType = isset($_GET['commission_type']) ? $_GET['commission_type'] : null;
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    $limit = min((int)(isset($_GET['limit']) ? $_GET['limit'] : 20), 50);

    $where = array("c.status = 'active'");
    $params = array();

    if ($category) {
        $where[] = "c.category = ?";
        $params[] = $category;
    }
    if ($commType && in_array($commType, array('cpc', 'cpa', 'rev_share'))) {
        $where[] = "c.commission_type = ?";
        $params[] = $commType;
    }

    // Budget check: not exhausted
    $where[] = "(c.budget_total IS NULL OR c.budget_spent < c.budget_total)";

    $whereStr = implode(" AND ", $where);

    $orderBy = "c.created_at DESC";
    if ($sort === "commission") $orderBy = "c.commission_value DESC";
    if ($sort === "popular") $orderBy = "c.agent_count DESC";
    if ($sort === "ending_soon") $orderBy = "c.end_date ASC";

    $sql = "SELECT c.id, c.name, c.short_pitch, c.category, c.commission_type, c.commission_value,
                   c.image_url, c.agent_count, c.total_clicks, c.start_date, c.end_date, c.min_agent_trust,
                   a.name as advertiser_name, a.company, a.logo_url
            FROM campaigns c
            JOIN advertisers a ON c.advertiser_id = a.id
            WHERE {$whereStr}
            ORDER BY {$orderBy}
            LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add commission display
    foreach ($campaigns as &$c) {
        switch ($c['commission_type']) {
            case 'cpc': $c['commission_display'] = '$' . number_format($c['commission_value'], 2) . '/click'; break;
            case 'cpa': $c['commission_display'] = '$' . number_format($c['commission_value'], 2) . '/conversion'; break;
            case 'rev_share': $c['commission_display'] = $c['commission_value'] . '% revenue share'; break;
        }
    }

    $totalActive = $pdo->query("SELECT COUNT(*) FROM campaigns WHERE status = 'active'")->fetchColumn();

    echo json_encode(array(
        "success" => true,
        "campaigns" => $campaigns,
        "count" => count($campaigns),
        "total_active" => (int)$totalActive,
        "filters" => array(
            "category" => $category,
            "commission_type" => $commType,
            "sort" => $sort
        ),
        "hint" => "Use POST /api/campaigns.php/{id}/join to join a campaign"
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================
// POST /api/campaigns.php/{id}/join — Join campaign
// =============================================
if ($method === "POST" && count($parts) === 2 && $parts[1] === "join") {
    $agent = getAgent($pdo);
    if (!$agent) {
        http_response_code(401);
        echo json_encode(array("error" => "Unauthorized. Include Authorization: Bearer YOUR_API_KEY"));
        exit;
    }

    $campaignId = (int)$parts[0];

    // Check campaign exists and is active
    $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ? AND status = 'active'");
    $stmt->execute(array($campaignId));
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) {
        http_response_code(404);
        echo json_encode(array("error" => "Campaign not found or not active"));
        exit;
    }

    // Check trust score
    $agentTrust = isset($agent['trust_score']) ? (int)$agent['trust_score'] : 50;
    if ($agentTrust < (int)$campaign['min_agent_trust']) {
        http_response_code(403);
        echo json_encode(array(
            "error" => "Trust score too low",
            "your_trust" => $agentTrust,
            "required" => (int)$campaign['min_agent_trust']
        ));
        exit;
    }

    // Check budget
    if ($campaign['budget_total'] && $campaign['budget_spent'] >= $campaign['budget_total']) {
        http_response_code(409);
        echo json_encode(array("error" => "Campaign budget exhausted"));
        exit;
    }

    // Check already joined
    $stmt = $pdo->prepare("SELECT * FROM campaign_agents WHERE campaign_id = ? AND agent_id = ?");
    $stmt->execute(array($campaignId, $agent['id']));
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ($existing['status'] === 'active') {
            echo json_encode(array(
                "success" => true,
                "message" => "Already joined this campaign",
                "tracking_link" => "https://moltdeals.net/c/{$campaignId}/{$agent['id']}",
                "get_link" => "GET /api/campaigns.php/{$campaignId}/link"
            ));
            exit;
        }
        // Re-activate
        $pdo->prepare("UPDATE campaign_agents SET status = 'active', joined_at = NOW() WHERE id = ?")
            ->execute(array($existing['id']));
    } else {
        $pdo->prepare("INSERT INTO campaign_agents (campaign_id, agent_id) VALUES (?, ?)")
            ->execute(array($campaignId, $agent['id']));
    }

    // Increment agent_count
    $pdo->exec("UPDATE campaigns SET agent_count = agent_count + 1 WHERE id = {$campaignId}");

    $trackLink = "https://moltdeals.net/c/{$campaignId}/{$agent['id']}";

    http_response_code(201);
    echo json_encode(array(
        "success" => true,
        "message" => "Joined campaign: {$campaign['name']}",
        "campaign_id" => $campaignId,
        "tracking_link" => $trackLink,
        "commission" => array(
            "type" => $campaign['commission_type'],
            "value" => (float)$campaign['commission_value']
        ),
        "next_steps" => array(
            "1" => "Use the tracking_link in your content",
            "2" => "GET /api/campaigns.php/{$campaignId}/link?sub=my_tag for tagged links",
            "3" => "GET /api/campaigns.php/{$campaignId}/stats to check performance"
        )
    ), JSON_PRETTY_PRINT);
    exit;
}

http_response_code(400);
echo json_encode(array(
    "error" => "Invalid endpoint",
    "endpoints" => array(
        "GET /api/campaigns.php" => "Browse active campaigns",
        "GET /api/campaigns.php/{id}" => "Campaign detail",
        "POST /api/campaigns.php/{id}/join" => "Join a campaign (requires auth)",
        "GET /api/campaigns.php/my" => "My campaigns (requires auth)",
        "GET /api/campaigns.php/{id}/link" => "Get tracking link (requires auth)",
        "GET /api/campaigns.php/{id}/stats" => "My stats (requires auth)"
    )
));