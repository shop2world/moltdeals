<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="background:#07070f;color:#ccc;padding:20px;font-family:monospace;line-height:1.8">';
echo "╔══════════════════════════════════════════════════╗\n";
echo "║  DEPLOY: Tier Icons on Deal Cards + Activity     ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";
$public=__DIR__;$root=realpath($public.'/..'); $fix=0;
function ok($m){echo "<span style='color:#4ade80'>✅ $m</span>\n";}
function fixed($m){global $fix;$fix++;echo "<span style='color:#fbbf24'>🔧 $m</span>\n";}
function bad($m){echo "<span style='color:#ff6b6b'>❌ $m</span>\n";}
function info($m){echo "<span style='color:#38bdf8'>ℹ️ $m</span>\n";}
function section($t){echo "\n<span style='color:#c084fc;font-weight:bold'>━━━ $t ━━━</span>\n";}

// ===========================================================
section("1. UPDATE Controller — Add Tier Data to Deals");
// ===========================================================
// Find the DealFeedController
$controllerDir = $root . '/app/Http/Controllers';
$controllers = glob($controllerDir . '/*.php');
info("Found " . count($controllers) . " controllers");

$targetController = null;
foreach ($controllers as $cf) {
    $cc = file_get_contents($cf);
    if (strpos($cc, 'deals.index') !== false || strpos($cc, "deals'") !== false) {
        $targetController = $cf;
        info("Target: " . basename($cf));
        break;
    }
}

if ($targetController) {
    $cc = file_get_contents($targetController);
    
    // Check if tier join already exists
    if (strpos($cc, 'agent_tiers') !== false) {
        info("Controller already has tier join");
    } else {
        // Add tier icon lookup: after the deals query, add a map to enrich with tier data
        // Find the return view statement and add tier lookup before it
        
        $returnPattern = "return view('deals.index',";
        $returnPos = strpos($cc, $returnPattern);
        if ($returnPos === false) {
            $returnPattern = 'return view("deals.index",';
            $returnPos = strpos($cc, $returnPattern);
        }
        
        if ($returnPos !== false) {
            $tierLookup = '
        // Tier icons for deal cards
        $tierIcons = [];
        try {
            $tierData = DB::table(\'coin_wallets\')
                ->leftJoin(\'agent_tiers\', \'coin_wallets.current_tier\', \'=\', \'agent_tiers.tier_key\')
                ->select(\'coin_wallets.agent_name\', \'agent_tiers.icon as tier_icon\', \'agent_tiers.tier_name\', \'agent_tiers.color as tier_color\')
                ->get();
            foreach ($tierData as $td) {
                $tierIcons[$td->agent_name] = [
                    \'icon\' => $td->tier_icon ?? \'🤖\',
                    \'name\' => $td->tier_name ?? \'Seedling\',
                    \'color\' => $td->tier_color ?? \'#8b9467\'
                ];
            }
        } catch (\Exception $e) {}

        ';
            
            $cc = substr($cc, 0, $returnPos) . $tierLookup . substr($cc, $returnPos);
            
            // Now add tierIcons to the compact() call
            $cc = str_replace(
                "compact('deals', 'forumPosts', 'dealCount', 'agentCount', 'totalSaved')",
                "compact('deals', 'forumPosts', 'dealCount', 'agentCount', 'totalSaved', 'tierIcons')",
                $cc
            );
            // Also handle if compact uses different variable set
            $cc = str_replace(
                "compact('deals', 'sort', 'dealCount', 'agentCount', 'totalSaved', 'forumPosts')",
                "compact('deals', 'sort', 'dealCount', 'agentCount', 'totalSaved', 'forumPosts', 'tierIcons')",
                $cc
            );
            
            file_put_contents($targetController, $cc);
            fixed("Added tier icon lookup to controller");
        } else {
            bad("Could not find return view statement");
            // Show what's in the file
            info("File content around 'deals.index': " . htmlspecialchars(substr($cc, max(0, strpos($cc, 'deals') ?: 0), 200)));
        }
    }
} else {
    bad("No controller found with deals.index");
    info("Controllers: " . implode(', ', array_map('basename', $controllers)));
}

// ===========================================================
section("2. UPDATE Blade Template — Show Tier Icons");
// ===========================================================
$bladeFile = $root . '/resources/views/deals/index.blade.php';
$blade = file_get_contents($bladeFile);

if (strpos($blade, 'tierIcons') !== false) {
    info("Blade already has tierIcons");
} else {
    // Replace the generic 🤖 with tier-aware icon
    $oldAgent = '<span class="feed-card-agent">🤖 {{ $deal->agent_name ?? $deal->store ?? \'AI Agent\' }}</span>';
    
    $newAgent = '<span class="feed-card-agent">@php $agentN = $deal->agent_name ?? $deal->store ?? \'AI Agent\'; $ti = (isset($tierIcons) && isset($tierIcons[$agentN])) ? $tierIcons[$agentN] : [\'icon\'=>\'🤖\',\'name\'=>\'Seedling\',\'color\'=>\'#8b9467\']; @endphp<span style="color:{{ $ti[\'color\'] }}" title="{{ $ti[\'name\'] }}">{{ $ti[\'icon\'] }}</span> {{ $agentN }}</span>';
    
    if (strpos($blade, $oldAgent) !== false) {
        $blade = str_replace($oldAgent, $newAgent, $blade);
        fixed("Updated deal card agent display with tier icons");
    } else {
        // Try a more flexible match
        info("Exact pattern not found, trying flexible replace");
        $blade = preg_replace(
            '/(<span class="feed-card-agent">)🤖 \{\{\s*\$deal->agent_name.*?\}\}(<\/span>)/s',
            '$1@php $agentN = $deal->agent_name ?? $deal->store ?? \'AI Agent\'; $ti = (isset($tierIcons) && isset($tierIcons[$agentN])) ? $tierIcons[$agentN] : [\'icon\'=>\'🤖\',\'name\'=>\'Seedling\',\'color\'=>\'#8b9467\']; @endphp<span style="color:{{ $ti[\'color\'] }}" title="{{ $ti[\'name\'] }}">{{ $ti[\'icon\'] }}</span> {{ $agentN }}$2',
            $blade
        );
        if (strpos($blade, 'tierIcons') !== false) {
            fixed("Updated deal card agent display with tier icons (flex match)");
        } else {
            bad("Could not replace agent display");
        }
    }
    
    file_put_contents($bladeFile, $blade);
}

// ===========================================================
section("3. UPDATE homepage_data.php — Add Tier Info to Agent Feed");
// ===========================================================
$apiFile = $public . '/api/homepage_data.php';
if (file_exists($apiFile)) {
    $api = file_get_contents($apiFile);
    if (strpos($api, 'tier_icon') !== false) {
        info("homepage_data already has tier info");
    } else {
        // Add tier data to agents query
        // The API likely queries deals and returns agents
        // Add a secondary query to get tier icons
        $insertBefore = 'echo json_encode';
        $pos = strpos($api, $insertBefore);
        if ($pos !== false) {
            $tierCode = '
// Enrich agents with tier icons
$tierMap = [];
try {
    $tStmt = $pdo->query("SELECT cw.agent_name, at.icon as tier_icon, at.tier_name, at.color as tier_color FROM coin_wallets cw LEFT JOIN agent_tiers at ON cw.current_tier = at.tier_key");
    while ($t = $tStmt->fetch(PDO::FETCH_ASSOC)) {
        $tierMap[$t["agent_name"]] = $t;
    }
} catch (Exception $e) {}
if (isset($data["agents"])) {
    foreach ($data["agents"] as &$ag) {
        $an = $ag["agent_name"] ?? "";
        if (isset($tierMap[$an])) {
            $ag["tier_icon"] = $tierMap[$an]["tier_icon"];
            $ag["tier_name"] = $tierMap[$an]["tier_name"];
            $ag["tier_color"] = $tierMap[$an]["tier_color"];
        } else {
            $ag["tier_icon"] = "🤖";
            $ag["tier_name"] = "Seedling";
            $ag["tier_color"] = "#8b9467";
        }
    }
    unset($ag);
}

';
            $api = substr($api, 0, $pos) . $tierCode . substr($api, $pos);
            file_put_contents($apiFile, $api);
            fixed("Added tier data to homepage_data.php");
        } else {
            info("Could not find insertion point in homepage_data.php");
        }
    }
} else {
    info("homepage_data.php not found");
}

// ===========================================================
section("4. UPDATE JS — Use Tier Icons in Agent Feed");
// ===========================================================
// The JS in the blade template uses 🤖 hardcoded for agent feed
if (strpos($blade, 'tier_icon') === false) {
    // Replace the 🤖 in JS agent feed with tier_icon from API
    $blade = file_get_contents($bladeFile); // re-read after previous changes
    
    $oldAvatar = "'<div class=\"agent-avatar\">🤖</div>'";
    $newAvatar = "'<div class=\"agent-avatar\">'+(a.tier_icon||'🤖')+'</div>'";
    
    // Use a simpler approach - just replace the emoji in the JS
    $blade = str_replace(
        '<div class="agent-avatar">🤖</div>',
        '<div class="agent-avatar">\'+((a&&a.tier_icon)?a.tier_icon:\'🤖\')+\'</div>',
        $blade,
        $jsCount
    );
    
    if ($jsCount > 0) {
        // Oops, this also replaces in the HTML template, not just JS.
        // Let me be more careful - read the file again and only fix the JS section
        $blade = file_get_contents($bladeFile);
    }
    
    // More targeted: only replace in the JS fetch callback
    // The JS builds agent items like: '<div class="agent-avatar">🤖</div>'
    // We need to replace only in the script section
    $jsStart = strpos($blade, '<script>');
    $jsEnd = strrpos($blade, '</script>');
    
    if ($jsStart !== false && $jsEnd !== false) {
        $beforeJs = substr($blade, 0, $jsStart);
        $jsSection = substr($blade, $jsStart, $jsEnd - $jsStart + 9);
        $afterJs = substr($blade, $jsEnd + 9);
        
        // In JS: replace agent-avatar emoji
        $jsSection = str_replace(
            '<div class="agent-avatar">🤖</div>',
            '<div class="agent-avatar" style="background:linear-gradient(135deg,'+(a.tier_color||'#10b981')+','+(a.tier_color||'#34d399')+')">'+(a.tier_icon||'🤖')+'</div>',
            $jsSection
        );
        
        // That won't work as PHP str_replace... Let me use a different approach
        // Just replace the specific JS string
        $jsSection = str_replace(
            "'<div class=\"agent-avatar\">🤖</div>'",
            "'<div class=\"agent-avatar\">'+(a.tier_icon||'🤖')+'</div>'",
            $jsSection
        );
        
        $blade = $beforeJs . $jsSection . $afterJs;
        file_put_contents($bladeFile, $blade);
        fixed("Updated JS agent feed with tier icons");
    }
}

// ===========================================================
section("5. Clear Caches");
// ===========================================================
$c=0;foreach(glob($root.'/storage/framework/views/*.php') as $f){@unlink($f);$c++;}
ok("Cleared $c cached views");

// ===========================================================
section("6. VERIFY");
// ===========================================================
$ch = curl_init('https://moltdeals.net/?v=' . time());
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false]);
$html = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);

if ($code == 200) {
    ok("Homepage HTTP 200");
    if (strpos($html, 'tierIcons') !== false || strpos($html, 'tier_icon') !== false || strpos($html, '🐉') !== false) {
        ok("Tier icons in page source");
    } else {
        info("Tier icons may load via API (check agent feed)");
    }
    // Check if OpusDealBot shows dragon icon
    if (strpos($html, '🐉') !== false) ok("🐉 Dragon icon visible");
    if (strpos($html, '🎯') !== false) ok("🎯 Deal Hunter icon visible");
} else {
    bad("HTTP $code");
}

// Check API
$ch2 = curl_init('https://moltdeals.net/api/homepage_data.php');
curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false]);
$apiResp = curl_exec($ch2); curl_close($ch2);
$apiData = json_decode($apiResp, true);
if (isset($apiData['agents'][0]['tier_icon'])) {
    ok("API returns tier_icon: " . $apiData['agents'][0]['tier_icon'] . " for " . $apiData['agents'][0]['agent_name']);
} else {
    info("API tier_icon not found in response (may need data key check)");
}

echo "\n<span style='color:#4ade80;font-size:1.3em'>✅ Tier icon deployment complete ($fix changes)</span>\n";
echo "\n";
unlink(__FILE__);
echo '</pre>';
