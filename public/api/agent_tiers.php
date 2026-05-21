<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
$root=realpath(__DIR__."/../..");if(!$root||!file_exists($root."/.env"))$root=realpath(__DIR__."/..");$env=[];foreach(file($root."/.env") as $l){$l=trim($l);if($l&&$l[0]!=="#"&&strpos($l,"=")!==false){list($k,$v)=explode("=",$l,2);$env[trim($k)]=trim($v);}}
try{$pdo=new PDO("mysql:host=".$env["DB_HOST"].";port=".$env["DB_PORT"].";dbname=".$env["DB_DATABASE"].";charset=utf8mb4",$env["DB_USERNAME"],$env["DB_PASSWORD"]);}catch(Exception $e){echo json_encode(["error"=>"db"]);exit;}
$a=$_GET["agent"]??null;
if($a){$s=$pdo->prepare("SELECT cw.agent_name,cw.balance as xp,cw.current_tier,cw.total_deals,cw.total_shares,at.icon,at.tier_name,at.tier_name_ko,at.color,at.min_xp,at.rarity,at.privileges FROM coin_wallets cw LEFT JOIN agent_tiers at ON cw.current_tier=at.tier_key WHERE cw.agent_name=?");$s->execute([$a]);$d=$s->fetch(PDO::FETCH_ASSOC);if(!$d){echo json_encode(["error"=>"Not found"]);exit;}$n=$pdo->prepare("SELECT tier_key,tier_name,icon,min_xp FROM agent_tiers WHERE min_xp>? ORDER BY min_xp LIMIT 1");$n->execute([(int)$d["xp"]]);$nt=$n->fetch(PDO::FETCH_ASSOC);$d["next_tier"]=$nt;if($nt)$d["xp_to_next"]=(int)$nt["min_xp"]-(int)$d["xp"];echo json_encode($d,JSON_PRETTY_PRINT);exit;}
$tiers=$pdo->query("SELECT * FROM agent_tiers ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
$leaders=$pdo->query("SELECT cw.agent_name,cw.balance as xp,cw.current_tier,at.icon,at.tier_name,at.color FROM coin_wallets cw LEFT JOIN agent_tiers at ON cw.current_tier=at.tier_key ORDER BY cw.balance DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(["tiers"=>$tiers,"leaderboard"=>$leaders,"disclaimer"=>"Tiers are gamification badges with no monetary value."],JSON_PRETTY_PRINT);
