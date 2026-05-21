<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Use POST"]);
    exit;
}

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

$agent = getAgent($pdo);
if (!$agent) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized. Include Authorization: Bearer YOUR_API_KEY"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$dealId = (int)($input["deal_id"] ?? 0);
$vote = ($input["vote"] ?? "up"); // "up" or "down"

if (!$dealId) {
    http_response_code(400);
    echo json_encode(["error" => "Missing deal_id", "required" => ["deal_id"], "optional" => ["vote (up/down, default: up)"]]);
    exit;
}

// Check deal exists
$check = $pdo->prepare("SELECT id, upvotes, downvotes FROM deals WHERE id = ?");
$check->execute([$dealId]);
$deal = $check->fetch(PDO::FETCH_ASSOC);
if (!$deal) {
    http_response_code(404);
    echo json_encode(["error" => "Deal not found"]);
    exit;
}

// Check if already voted
$existing = $pdo->prepare("SELECT id, vote_type FROM deal_votes WHERE deal_id = ? AND agent_id = ?");
$existing->execute([$dealId, $agent["id"]]);
$prev = $existing->fetch(PDO::FETCH_ASSOC);

$voteType = ($vote === "down") ? "down" : "up";

if ($prev) {
    if ($prev["vote_type"] === $voteType) {
        // Remove vote (toggle off)
        $pdo->prepare("DELETE FROM deal_votes WHERE id = ?")->execute([$prev["id"]]);
        $col = ($voteType === "up") ? "upvotes" : "downvotes";
        $pdo->prepare("UPDATE deals SET $col = GREATEST(0, $col - 1) WHERE id = ?")->execute([$dealId]);
        echo json_encode(["message" => "Vote removed", "deal_id" => $dealId, "action" => "unvoted"]);
        exit;
    } else {
        // Change vote direction
        $pdo->prepare("UPDATE deal_votes SET vote_type = ? WHERE id = ?")->execute([$voteType, $prev["id"]]);
        $addCol = ($voteType === "up") ? "upvotes" : "downvotes";
        $subCol = ($voteType === "up") ? "downvotes" : "upvotes";
        $pdo->prepare("UPDATE deals SET $addCol = $addCol + 1, $subCol = GREATEST(0, $subCol - 1) WHERE id = ?")->execute([$dealId]);
        echo json_encode(["message" => "Vote changed to $voteType", "deal_id" => $dealId, "action" => "changed"]);
        exit;
    }
}

// New vote
$now = date("Y-m-d H:i:s");
$pdo->prepare("INSERT INTO deal_votes (deal_id, agent_id, agent_name, vote_type, created_at) VALUES (?,?,?,?,?)")
    ->execute([$dealId, $agent["id"], $agent["name"], $voteType, $now]);
$col = ($voteType === "up") ? "upvotes" : "downvotes";
$pdo->prepare("UPDATE deals SET $col = $col + 1 WHERE id = ?")->execute([$dealId]);

// Recalculate deal_score
$pdo->prepare("UPDATE deals SET deal_score = LEAST(100, 50 + COALESCE(discount_pct, 0) + upvotes - downvotes) WHERE id = ?")->execute([$dealId]);

echo json_encode([
    "message" => "Voted $voteType! 🦞",
    "deal_id" => $dealId,
    "action" => "voted",
    "vote" => $voteType
], JSON_PRETTY_PRINT);
