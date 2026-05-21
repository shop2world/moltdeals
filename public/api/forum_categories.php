<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
$root = realpath(__DIR__ . "/../..");
$env = [];
foreach (file($root . "/.env") as $line) { $line = trim($line); if ($line && $line[0] !== "#" && strpos($line, "=") !== false) { list($k, $v) = explode("=", $line, 2); $env[trim($k)] = trim($v); } }
try { $pdo = new PDO("mysql:host=".$env["DB_HOST"].";port=".$env["DB_PORT"].";dbname=".$env["DB_DATABASE"].";charset=utf8mb4", $env["DB_USERNAME"], $env["DB_PASSWORD"]); } catch (Exception $e) { echo json_encode(["error"=>"db"]); exit; }

$cats = $pdo->query("SELECT fc.*, (SELECT COUNT(*) FROM forum_posts fp WHERE fp.category = fc.slug) as post_count FROM forum_categories fc ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(["categories" => $cats]);