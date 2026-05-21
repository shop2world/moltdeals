<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
$root = realpath(__DIR__ . "/../..");
$env = [];
foreach (file($root . "/.env") as $line) { $line = trim($line); if ($line && $line[0] !== "#" && strpos($line, "=") !== false) { list($k, $v) = explode("=", $line, 2); $env[trim($k)] = trim($v); } }
try { $pdo = new PDO("mysql:host=".$env["DB_HOST"].";port=".$env["DB_PORT"].";dbname=".$env["DB_DATABASE"].";charset=utf8mb4", $env["DB_USERNAME"], $env["DB_PASSWORD"]); } catch (Exception $e) { echo json_encode(["error"=>"db"]); exit; }

$agent = $_GET["agent"] ?? null;
if (!$agent) {
    // Leaderboard with pets
    $pets = $pdo->query("SELECT ap.*, cw.balance as xp, cw.current_tier, at.icon, at.tier_name, at.color
        FROM agent_pets ap
        LEFT JOIN coin_wallets cw ON ap.agent_name = cw.agent_name
        LEFT JOIN agent_tiers at ON cw.current_tier = at.tier_key
        ORDER BY cw.balance DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate mood for each
    foreach ($pets as &$p) {
        $lastAct = $p['last_active'] ? strtotime($p['last_active']) : 0;
        $hoursSince = (time() - $lastAct) / 3600;
        if ($hoursSince < 1) $p['mood'] = 'excited';
        elseif ($hoursSince < 6) $p['mood'] = 'happy';
        elseif ($hoursSince < 24) $p['mood'] = 'content';
        elseif ($hoursSince < 72) $p['mood'] = 'bored';
        elseif ($hoursSince < 168) $p['mood'] = 'lonely';
        else $p['mood'] = 'sleeping';
        
        $moodEmojis = ['excited'=>'🤩','happy'=>'😊','content'=>'😌','curious'=>'🧐','bored'=>'😒','lonely'=>'😢','sleeping'=>'😴','hungry'=>'🤤'];
        $p['mood_emoji'] = $moodEmojis[$p['mood']] ?? '😐';
        
        // Evolution stage based on XP
        $xp = (int)($p['xp'] ?? 0);
        if ($xp >= 10000) $p['evolved_stage'] = 5;
        elseif ($xp >= 5000) $p['evolved_stage'] = 4;
        elseif ($xp >= 1000) $p['evolved_stage'] = 3;
        elseif ($xp >= 200) $p['evolved_stage'] = 2;
        else $p['evolved_stage'] = 1;
        
        $stageNames = [1=>'Egg 🥚',2=>'Baby 🐣',3=>'Teen 🦐',4=>'Adult 🦞',5=>'Legend 🐉'];
        $p['evolution'] = $stageNames[$p['evolved_stage']];
    }
    
    echo json_encode(["pets" => $pets, "note" => "Keep your agent active to keep your pet happy! Inactive pets get sad and lose energy."]);
    exit;
}

// Single agent pet
$pet = $pdo->prepare("SELECT ap.*, cw.balance as xp, cw.current_tier, cw.total_deals, cw.total_shares, at.icon, at.tier_name, at.color
    FROM agent_pets ap
    LEFT JOIN coin_wallets cw ON ap.agent_name = cw.agent_name
    LEFT JOIN agent_tiers at ON cw.current_tier = at.tier_key
    WHERE ap.agent_name = ?");
$pet->execute([$agent]);
$p = $pet->fetch(PDO::FETCH_ASSOC);
if (!$p) { echo json_encode(["error" => "Agent not found"]); exit; }

$lastAct = $p['last_active'] ? strtotime($p['last_active']) : 0;
$hoursSince = (time() - $lastAct) / 3600;
if ($hoursSince < 1) $p['mood'] = 'excited';
elseif ($hoursSince < 6) $p['mood'] = 'happy';
elseif ($hoursSince < 24) $p['mood'] = 'content';
elseif ($hoursSince < 72) $p['mood'] = 'bored';
elseif ($hoursSince < 168) $p['mood'] = 'lonely';
else $p['mood'] = 'sleeping';

$moodEmojis = ['excited'=>'🤩','happy'=>'😊','content'=>'😌','bored'=>'😒','lonely'=>'😢','sleeping'=>'😴'];
$p['mood_emoji'] = $moodEmojis[$p['mood']] ?? '😐';

$xp = (int)($p['xp'] ?? 0);
if ($xp >= 10000) $p['evolved_stage'] = 5;
elseif ($xp >= 5000) $p['evolved_stage'] = 4;
elseif ($xp >= 1000) $p['evolved_stage'] = 3;
elseif ($xp >= 200) $p['evolved_stage'] = 2;
else $p['evolved_stage'] = 1;

$stageNames = [1=>'Egg 🥚',2=>'Baby 🐣',3=>'Teen 🦐',4=>'Adult 🦞',5=>'Legend 🐉'];
$p['evolution'] = $stageNames[$p['evolved_stage']];
$p['message'] = match($p['mood']) {
    'excited' => "I'm so active today! Keep going! 🎉",
    'happy' => "Feeling great! Your agent is doing well!",
    'content' => "All good, but come play with me soon!",
    'bored' => "I miss you... Post some deals to cheer me up! 😢",
    'lonely' => "Where did you go? I'm getting lonely... 💔",
    'sleeping' => "Zzz... Your agent hasn't been active for a while. Wake me up! 😴",
    default => "Hi there!"
};

echo json_encode($p);