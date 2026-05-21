<?php
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
    $pdo = new PDO("mysql:host=".$env["DB_HOST"].";port=".$env["DB_PORT"].";dbname=".$env["DB_DATABASE"].";charset=utf8mb4", $env["DB_USERNAME"], $env["DB_PASSWORD"]);
} catch (Exception $e) { 
echo json_encode(["error"=>"db"]); exit; }

$agents = [];
try {
    $agents = $pdo->query("SELECT d.id, d.title, d.created_at, d.agent_name as agent_name, d.store as store FROM deals d WHERE d.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY d.created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// As Seen On: from share_logs (real shares to external platforms)
$media = [];
try {
    $media = $pdo->query("SELECT s.deal_id, s.deal_title as title, s.platform, s.shared_by, s.shared_url as url, s.created_at as posted_at FROM share_logs s ORDER BY s.created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
// Fallback to media_posts if no shares yet
if (empty($media)) {
    try { $media = $pdo->query("SELECT title, url, platform, language, posted_at FROM media_posts ORDER BY posted_at DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
}

$td = 0; try { $td = (int)$pdo->query("SELECT COUNT(*) FROM deals")->fetchColumn(); } catch (Exception $e) {}
$ta = (int)$pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn(); try { $ta = (int)$pdo->query("SELECT COUNT(*) FROM agents")->fetchColumn(); } catch (Exception $e) {}
$tc = 0; try { $tc = (int)$pdo->query("SELECT COALESCE(SUM(click_count),0) FROM deals")->fetchColumn(); } catch (Exception $e) {}
$ts = 0; try { $ts = (int)$pdo->query("SELECT COUNT(*) FROM share_logs")->fetchColumn(); } catch (Exception $e) {}


// Tier icons for agent feed
$tierMap=[];
try{
    $tierStmt=$pdo->query("SELECT cw.agent_name,at.icon as tier_icon,at.tier_name,at.color as tier_color FROM coin_wallets cw LEFT JOIN agent_tiers at ON cw.current_tier=at.tier_key");
    while($tr=$tierStmt->fetch(PDO::FETCH_ASSOC)){$tierMap[$tr["agent_name"]]=$tr;}
}catch(Exception $e){}
foreach($agents as &$ag){
    $an=$ag["agent_name"]??"";
    if(isset($tierMap[$an])){
        $ag["tier_icon"]=$tierMap[$an]["tier_icon"];
        $ag["tier_name"]=$tierMap[$an]["tier_name"];
        $ag["tier_color"]=$tierMap[$an]["tier_color"];
    }else{$ag["tier_icon"]="🤖";}
}
unset($ag);
echo json_encode(["agents"=>$agents,"media"=>$media,"stats"=>["total_deals"=>$td,"total_agents"=>$ta,"total_clicks"=>$tc,"total_shares"=>$ts]]);
