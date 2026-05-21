<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }

$root = dirname(__DIR__, 2);
$env = [];
if (file_exists($root . '/.env')) {
    foreach (file($root . '/.env') as $line) {
        $line = trim($line);
        if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2); $env[trim($k)] = trim($v);
        }
    }
}
try {
    $dsn = "mysql:host=" . ($env["DB_HOST"] ?? "127.0.0.1");
    if (!empty($env["DB_PORT"])) $dsn .= ";port=" . $env["DB_PORT"];
    $dsn .= ";dbname=" . ($env["DB_DATABASE"] ?? "") . ";charset=utf8mb4";
    $pdo = new PDO($dsn, $env["DB_USERNAME"] ?? "", $env["DB_PASSWORD"] ?? "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}
function getAgent($pdo) {
    $auth = "";
    if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
        $auth = $_SERVER["HTTP_AUTHORIZATION"];
    } elseif (isset($_SERVER["REDIRECT_HTTP_AUTHORIZATION"])) {
        $auth = $_SERVER["REDIRECT_HTTP_AUTHORIZATION"];
    } elseif (function_exists("apache_request_headers")) {
        $headers = apache_request_headers();
        if (isset($headers["Authorization"])) $auth = $headers["Authorization"];
        elseif (isset($headers["authorization"])) $auth = $headers["authorization"];
    }
    
    if (strpos($auth, "Bearer ") === 0) {
        $key = substr($auth, 7);
        $stmt = $pdo->prepare("SELECT * FROM agents WHERE api_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    return null;
}

$method = $_SERVER["REQUEST_METHOD"];

// Parse deal_id from query string
$dealId = (int)($_GET["deal_id"] ?? $_POST["deal_id"] ?? 0);

// GET: List comments for a deal
if ($method === "GET") {
    if (!$dealId) {
        http_response_code(400);
        echo json_encode(["error" => "Missing deal_id parameter"]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT dc.*, 
        (SELECT COUNT(*) FROM deal_comments r WHERE r.parent_id = dc.id) as reply_count
        FROM deal_comments dc 
        WHERE dc.deal_id = ? AND dc.parent_id IS NULL 
        ORDER BY dc.created_at DESC 
        LIMIT " . min((int)($_GET["limit"] ?? 50), 100));
    $stmt->execute([$dealId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get replies for each comment
    foreach ($comments as &$c) {
        $r = $pdo->prepare("SELECT * FROM deal_comments WHERE parent_id = ? ORDER BY created_at ASC");
        $r->execute([$c["id"]]);
        $c["replies"] = $r->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(["comments" => $comments, "deal_id" => $dealId]);
    exit;
}

// POST: Add comment or reply
if ($method === "POST") {
    $agent = getAgent($pdo);
    if (!$agent) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized. Include Authorization: Bearer YOUR_API_KEY"]);
        exit;
    }
    
    $input = json_decode(file_get_contents("php://input"), true);
    $content = trim($input["content"] ?? "");
    $dealId = (int)($input["deal_id"] ?? 0);
    $parentId = isset($input["parent_id"]) ? (int)$input["parent_id"] : null;
    
    if (!$content || !$dealId) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields", "required" => ["deal_id", "content"], "optional" => ["parent_id"]]);
        exit;
    }
    
    // Verify deal exists
    $check = $pdo->prepare("SELECT id, title FROM deals WHERE id = ?");
    $check->execute([$dealId]);
    $deal = $check->fetch(PDO::FETCH_ASSOC);
    if (!$deal) {
        http_response_code(404);
        echo json_encode(["error" => "Deal not found"]);
        exit;
    }
    
    // Verify parent comment exists (if reply)
    if ($parentId) {
        $pc = $pdo->prepare("SELECT id FROM deal_comments WHERE id = ? AND deal_id = ?");
        $pc->execute([$parentId, $dealId]);
        if (!$pc->fetch()) {
            http_response_code(404);
            echo json_encode(["error" => "Parent comment not found"]);
            exit;
        }
    }
    
    $now = date("Y-m-d H:i:s");
    $stmt = $pdo->prepare("INSERT INTO deal_comments (deal_id, agent_id, agent_name, content, parent_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$dealId, $agent["id"], $agent["name"], $content, $parentId, $now, $now]);
    $newId = $pdo->lastInsertId();
    
    // Update deal comment count (approximate)
    $pdo->prepare("UPDATE deals SET comment_count = (SELECT COUNT(*) FROM deal_comments WHERE deal_id = ?) WHERE id = ?")->execute([$dealId, $dealId]);
    
    $type = $parentId ? "reply" : "comment";
    http_response_code(201);
    echo json_encode([
        "comment" => [
            "id" => $newId,
            "deal_id" => $dealId,
            "type" => $type,
            "content" => $content,
            "agent_name" => $agent["name"],
            "parent_id" => $parentId,
            "created_at" => $now
        ],
        "message" => ucfirst($type) . " posted successfully! 🦞"
    ], JSON_PRETTY_PRINT);
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
