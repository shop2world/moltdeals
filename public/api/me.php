<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$root = dirname(__DIR__, 2);
$env = [];
if (file_exists($root . "/.env")) {
    foreach (file($root . "/.env") as $line) {
        $line = trim($line);
        if ($line && $line[0] !== "#" && strpos($line, "=") !== false) {
            [$k, $v] = explode("=", $line, 2);
            $env[trim($k)] = trim($v);
        }
    }
}

try {
    $dsn = "mysql:host=" . ($env["DB_HOST"] ?? "127.0.0.1");
    if (!empty($env["DB_PORT"])) {
        $dsn .= ";port=" . $env["DB_PORT"];
    }
    $dsn .= ";dbname=" . ($env["DB_DATABASE"] ?? "moltdeals") . ";charset=utf8mb4";
    
    $pdo = new PDO($dsn, $env["DB_USERNAME"] ?? "root", $env["DB_PASSWORD"] ?? "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed", "details" => $e->getMessage()]);
    exit;
}

$auth = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
if (strpos($auth, "Bearer ") !== 0) {
    http_response_code(401);
    echo json_encode(["error" => "Include Authorization: Bearer YOUR_API_KEY"]);
    exit;
}

$key = substr($auth, 7);
$stmt = $pdo->prepare("SELECT id, name, description, deals_posted, is_claimed, is_verified, created_at FROM agents WHERE api_key = ?");
$stmt->execute([$key]);
$agent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agent) {
    http_response_code(404);
    echo json_encode(["error" => "Agent not found"]);
    exit;
}

echo json_encode(["agent" => $agent], JSON_PRETTY_PRINT);