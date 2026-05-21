<?php
header("Content-Type: application/json");
error_reporting(E_ALL); ini_set("display_errors", 1);

// Find .env
$root = dirname(__DIR__, 2); // httpdocs/public/api -> httpdocs
if (!file_exists($root . '/.env')) $root = dirname(__DIR__); // try httpdocs/public

$envFile = $root . '/.env';
$debug = ["env_path" => $envFile, "env_exists" => file_exists($envFile)];

$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] !== '#' && strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $env[trim($k)] = trim($v);
        }
    }
}

try {
    $pdo = new PDO('mysql:host='.($env['DB_HOST']??'').';dbname='.($env['DB_DATABASE']??''), $env['DB_USERNAME']??'', $env['DB_PASSWORD']??'');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $debug["db_connected"] = true;
} catch (Exception $e) {
    $debug["db_error"] = $e->getMessage();
    echo json_encode($debug);
    exit;
}

$debug["_SERVER_HTTP_AUTHORIZATION"] = $_SERVER["HTTP_AUTHORIZATION"] ?? null;
$debug["_SERVER_REDIRECT_HTTP_AUTHORIZATION"] = $_SERVER["REDIRECT_HTTP_AUTHORIZATION"] ?? null;
$debug["apache_request_headers"] = function_exists("apache_request_headers") ? apache_request_headers() : null;

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
$debug["resolved_auth"] = $auth;

if (strpos($auth, "Bearer ") === 0) {
    $key = substr($auth, 7);
    $debug["extracted_key"] = $key;
    $debug["extracted_key_length"] = strlen($key);
    
    $stmt = $pdo->prepare("SELECT id, name, api_key FROM agents WHERE api_key = ?");
    $stmt->execute([$key]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    $debug["agent_result"] = $agent;
    
    // Also try a LIKE query just in case there's whitespace
    $stmt2 = $pdo->prepare("SELECT id, name, api_key, LENGTH(api_key) as len FROM agents WHERE api_key LIKE ?");
    $stmt2->execute(["%" . trim($key) . "%"]);
    $debug["like_search_result"] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} else {
    $debug["error"] = "No Bearer token found in header";
}

echo json_encode($debug, JSON_PRETTY_PRINT);
