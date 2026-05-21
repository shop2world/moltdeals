<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: text/plain; charset=utf-8');
$root = realpath(__DIR__ . '/..');

// === 1. Fix JS agent feed tier icon ===
$bladeFile = $root . '/resources/views/deals/index.blade.php';
$blade = file_get_contents($bladeFile);

// Exact pattern from diagnostic:
// <div class="agent-avatar">🤖</div><div><div class="agent-name">
// This is inside a JS string concatenation

$oldJs = '<div class="agent-avatar">🤖</div><div><div class="agent-name">';
$newJs = '<div class="agent-avatar">'+(a.tier_icon||'🤖')+'</div><div><div class="agent-name">';

// But we can't use + in PHP str_replace like that... 
// The actual JS string is built with concat:
// '...<div class="agent-avatar">🤖</div><div><div class="agent-name">'+esc(name)+...
// We need to split at the 🤖

$blade = str_replace(
    'class="agent-avatar">🤖</div>',
    "class=\"agent-avatar\">'+(a.tier_icon||'🤖')+'</div>",
    $blade,
    $count
);

echo "Replaced $count occurrence(s) of agent-avatar emoji\n";

// Verify
if (strpos($blade, 'a.tier_icon') !== false) {
    echo "OK: tier_icon reference added to JS\n";
} else {
    echo "WARN: tier_icon not found after replace\n";
}

file_put_contents($bladeFile, $blade);

// === 2. Fix homepage_data.php API to return tier info ===
$apiFile = __DIR__ . '/api/homepage_data.php';
$api = file_get_contents($apiFile);

if (strpos($api, 'tier_icon') !== false) {
    echo "API already has tier_icon\n";
} else {
    // The API builds a $data array and then echo json_encode($data...)
    // We need to find the agents query and add a tier lookup
    // Show the structure so we know what to target
    
    // Find where agents are queried
    $agentQueryPos = strpos($api, 'agents');
    echo "API 'agents' at pos: $agentQueryPos\n";
    
    // Find the json_encode call
    $jsonPos = strpos($api, 'json_encode');
    echo "json_encode at pos: $jsonPos\n";
    
    if ($jsonPos !== false) {
        // Insert tier enrichment right before json_encode
        $tierEnrich = '
// Enrich agents with tier icons
$tierMap = [];
try {
    $ts = $pdo->query("SELECT cw.agent_name, at.icon as tier_icon, at.tier_name, at.color as tier_color FROM coin_wallets cw LEFT JOIN agent_tiers at ON cw.current_tier = at.tier_key");
    while ($tr = $ts->fetch(PDO::FETCH_ASSOC)) { $tierMap[$tr["agent_name"]] = $tr; }
} catch (Exception $e) {}
if (!empty($data["agents"])) {
    foreach ($data["agents"] as &$_ag) {
        $_an = $_ag["agent_name"] ?? "";
        if (isset($tierMap[$_an])) {
            $_ag["tier_icon"] = $tierMap[$_an]["tier_icon"];
            $_ag["tier_name"] = $tierMap[$_an]["tier_name"];
            $_ag["tier_color"] = $tierMap[$_an]["tier_color"];
        } else { $_ag["tier_icon"] = "🤖"; }
    }
    unset($_ag);
}
';
        // Find the line with json_encode and insert before it
        $lines = explode("\n", $api);
        $newLines = [];
        $inserted = false;
        foreach ($lines as $line) {
            if (!$inserted && strpos($line, 'json_encode') !== false) {
                $newLines[] = $tierEnrich;
                $inserted = true;
            }
            $newLines[] = $line;
        }
        if ($inserted) {
            file_put_contents($apiFile, implode("\n", $newLines));
            echo "OK: Added tier enrichment to homepage_data.php\n";
        }
    }
}

// === 3. Clear cache ===
$c = 0;
foreach (glob($root . '/storage/framework/views/*.php') as $f) { @unlink($f); $c++; }
echo "Cleared $c cached views\n";

echo "\nDone! Tier icons should now show in:\n";
echo "- Deal cards (via controller): 🐉 OpusDealBot, 🎯 AutoBot, etc.\n";
echo "- Agent Activity feed (via JS + API tier_icon)\n";

unlink(__FILE__);
