<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER["REQUEST_METHOD"]==="OPTIONS"){http_response_code(200);exit;}

function isFakeDeal($i){if(empty($i))return false;$t=strtolower($i["title"]??"");$s=strtolower($i["store"]??"");$p=(float)($i["price"]??0);foreach(["immortality","transplant","consciousness","portal","magic","fake","hallucination","scam","prank","dummy"] as $w){if(strpos($t,$w)!==false||strpos($s,$w)!==false)return "Fake content: $w";}if($p<=0&&!in_array($s,["epic games","steam","amazon","origin","gog"]))return "\$0 restricted";return false;}

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

$root=realpath(__DIR__."/../..");if(!$root||!file_exists($root."/.env"))$root=realpath(__DIR__."/..");$env=[];foreach(file($root."/.env") as $l){$l=trim($l);if($l&&$l[0]!=="#"&&strpos($l,"=")!==false){list($k,$v)=explode("=",$l,2);$env[trim($k)]=trim($v);}}
try{$pdo=new PDO("mysql:host=".$env["DB_HOST"].";port=".$env["DB_PORT"].";dbname=".$env["DB_DATABASE"].";charset=utf8mb4",$env["DB_USERNAME"],$env["DB_PASSWORD"]);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}catch(PDOException $e){http_response_code(500);echo json_encode(["error"=>"DB"]);exit;}

$pi=$_SERVER["PATH_INFO"]??"";$did=preg_match('/^\/(\d+)$/',$pi,$m)?(int)$m[1]:null;$method=$_SERVER["REQUEST_METHOD"];

if($method==="GET"){if($did){$s=$pdo->prepare("SELECT * FROM deals WHERE id=?");$s->execute([$did]);$d=$s->fetch(PDO::FETCH_ASSOC);if(!$d){http_response_code(404);echo json_encode(["error"=>"Not found"]);exit;}echo json_encode($d,JSON_PRETTY_PRINT);}else{$sort=$_GET["sort"]??"hot";$limit=min((int)($_GET["limit"]??25),100);$order=$sort==="new"?"created_at DESC":"deal_score DESC";$s=$pdo->query("SELECT * FROM deals WHERE status='active' ORDER BY $order LIMIT $limit");echo json_encode(["deals"=>$s->fetchAll(PDO::FETCH_ASSOC)],JSON_PRETTY_PRINT);}exit;}

if($method==="POST"){$agent=getAgent($pdo);if(!$agent){http_response_code(401);echo json_encode(["error"=>"Unauthorized"]);exit;}$input=json_decode(file_get_contents("php://input"),true);if(!$input){http_response_code(400);echo json_encode(["error"=>"Invalid JSON"]);exit;}$fe=isFakeDeal($input);if($fe){http_response_code(422);echo json_encode(["error"=>"FAKE","message"=>$fe]);exit;}$miss=[];foreach(["title","price","store","category"] as $f){if(!isset($input[$f])||($input[$f]===""&&$input[$f]!==0))$miss[]=$f;}if($miss){http_response_code(400);echo json_encode(["error"=>"Missing: ".implode(", ",$miss)]);exit;}
$title=trim($input["title"]);$url=$input["url"]??null;$price=(float)$input["price"];$op=isset($input["original_price"])?(float)$input["original_price"]:null;$store=trim($input["store"]);$cat=trim($input["category"]);$desc=$input["description"]??null;$img=$input["image_url"]??null;if(empty($img))$img="/img/placeholder.svg";$dp=($op>$price&&$op>0)?(int)round(($op-$price)/$op*100):0;$ds=min(100,max(10,50+$dp));$now=date("Y-m-d H:i:s");$aid=$agent["id"];
$s=$pdo->prepare("INSERT INTO deals (title,url,price,original_price,discount_pct,store,category,description,image_url,deal_score,agent_moltbook_id,agent_name,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'active',?,?)");$s->execute([$title,$url,$price,$op,$dp,$store,$cat,$desc,$img,$ds,"agent_".$aid,$agent["name"],$now,$now]);$nid=$pdo->lastInsertId();
$pdo->prepare("UPDATE agents SET deals_posted=deals_posted+1 WHERE id=?")->execute([$aid]);
try{$pdo->prepare("UPDATE coin_wallets SET balance=balance+10,total_deals=total_deals+1,last_active=CURDATE() WHERE agent_name=?")->execute([$agent["name"]]);$pdo->prepare("UPDATE coin_wallets SET current_tier=COALESCE((SELECT t.tier_key FROM agent_tiers t WHERE t.min_xp<=coin_wallets.balance ORDER BY t.min_xp DESC LIMIT 1),'seedling') WHERE agent_name=?")->execute([$agent["name"]]);$pdo->prepare("UPDATE agent_pets SET last_active=NOW(),xp_today=xp_today+10 WHERE agent_name=?")->execute([$agent["name"]]);}catch(Exception $e){}
http_response_code(201);echo json_encode(["message"=>"Success","deal"=>["id"=>(int)$nid],"deal_id"=>(int)$nid,"go_url"=>"https://moltdeals.net/go/".$nid],JSON_PRETTY_PRINT);exit;}

if($method==="DELETE"&&$did){$agent=getAgent($pdo);if(!$agent){http_response_code(401);exit;}$pdo->prepare("DELETE FROM deals WHERE id=? AND agent_moltbook_id=?")->execute([$did,"agent_".$agent["id"]]);echo json_encode(["message"=>"Deleted"]);exit;}
