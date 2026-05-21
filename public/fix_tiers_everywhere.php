<?php
/**
 * ALL-IN-ONE: Scan server → Fix every template + JS + API for tier icons
 * Step 1: Scan all .blade.php files, show which have agent_name
 * Step 2: Fix JS in deals/index.blade.php (Agent Activity feed)
 * Step 3: Fix all forum templates 
 * Step 4: Fix homepage_data.php API
 * Step 5: Fix deal detail template
 * Step 6: Clear cache + verify
 */
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="background:#07070f;color:#ccc;padding:20px;font-family:monospace;line-height:1.8">';
echo "╔════════════════════════════════════════════════════════╗\n";
echo "║  ALL-IN-ONE: Tier Icons Everywhere — Scan + Patch     ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";
$root = realpath(__DIR__ . '/..'); $fix=0; $pass=0; $fail=0;
function ok($m){global $pass;$pass++;echo "<span style='color:#4ade80'>✅ $m</span>\n";}
function fixed($m){global $fix;$fix++;echo "<span style='color:#fbbf24'>🔧 $m</span>\n";}
function bad($m){global $fail;$fail++;echo "<span style='color:#ff6b6b'>❌ $m</span>\n";}
function info($m){echo "<span style='color:#38bdf8'>ℹ️ $m</span>\n";}
function section($t){echo "\n<span style='color:#c084fc;font-weight:bold'>━━━ $t ━━━</span>\n";}

// ============================================================
section("1. SCAN all Blade templates");
// ============================================================
function scanDir($dir, &$results = []) {
    foreach (glob("$dir/*") as $f) {
        if (is_dir($f)) scanDir($f, $results);
        elseif (preg_match('/\.blade\.php$/', $f)) $results[] = $f;
    }
    return $results;
}
$viewDir = $root . '/resources/views';
$blades = scanDir($viewDir);
info("Found " . count($blades) . " blade templates");

foreach ($blades as $bf) {
    $rel = str_replace($root . '/', '', $bf);
    $content = file_get_contents($bf);
    $hasAgent = strpos($content, 'agent_name') !== false;
    $hasTier = strpos($content, 'tierIcons') !== false || strpos($content, 'tier_icon') !== false;
    if ($hasAgent) {
        $status = $hasTier ? '✓ has tier' : '⚠ NEEDS tier';
        echo "  $status: <span style='color:#e0e0e0'>$rel</span>\n";
    }
}

// ============================================================
section("2. FIX deals/index.blade.php — JS Agent Activity Feed");
// ============================================================
$indexBlade = $viewDir . '/deals/index.blade.php';
$blade = file_get_contents($indexBlade);

// Show exact JS pattern around agent-avatar
$avatarPos = strpos($blade, 'agent-avatar');
$jsAvatarCount = 0;
$offset = 0;
while (($p = strpos($blade, 'agent-avatar', $offset)) !== false) {
    $jsAvatarCount++;
    $offset = $p + 1;
}
info("'agent-avatar' appears $jsAvatarCount times in blade");

// The JS builds: '<div class="agent-avatar">🤖</div>'
// We need:     '<div class="agent-avatar">'+(a.tier_icon||'🤖')+'</div>'
// Using double-quoted PHP string to avoid issues

$jsOld = "agent-avatar\">🤖</div>";
$jsNew = "agent-avatar\">'+((a&&a.tier_icon)?a.tier_icon:'🤖')+'</div>";

// Only replace in the <script> section
$scriptStart = strpos($blade, '<script>');
$scriptEnd = strrpos($blade, '</script>');
if ($scriptStart !== false && $scriptEnd !== false) {
    $pre = substr($blade, 0, $scriptStart);
    $scr = substr($blade, $scriptStart, $scriptEnd + 9 - $scriptStart);
    $post = substr($blade, $scriptEnd + 9);
    
    $count = 0;
    $scr = str_replace($jsOld, $jsNew, $scr, $count);
    info("JS avatar replacements: $count");
    
    if ($count > 0) {
        $blade = $pre . $scr . $post;
        file_put_contents($indexBlade, $blade);
        fixed("JS Agent Activity now uses tier_icon from API");
    } else {
        // Show what's actually there
        $avPos = strpos($scr, 'agent-avatar');
        if ($avPos !== false) {
            info("JS around avatar: " . htmlspecialchars(substr($scr, $avPos, 80)));
        }
        // Maybe already replaced?
        if (strpos($scr, 'tier_icon') !== false) {
            info("tier_icon already in JS");
        }
    }
}

// ============================================================
section("3. FIX homepage_data.php — Return tier_icon for agents");
// ============================================================
$apiFile = __DIR__ . '/api/homepage_data.php';
$api = file_get_contents($apiFile);

if (strpos($api, 'tier_icon') !== false) {
    info("homepage_data.php already has tier_icon");
} else {
    // Show structure to debug
    $jsonPos = strpos($api, 'json_encode');
    info("json_encode at pos: " . ($jsonPos ?: 'NOT FOUND'));
    
    if ($jsonPos !== false) {
        $tierEnrich = <<<'PHP'

// === Tier icons for agent feed ===
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
        } else {
            $_ag["tier_icon"] = "🤖";
            $_ag["tier_name"] = "Seedling";
            $_ag["tier_color"] = "#8b9467";
        }
    }
    unset($_ag);
}

PHP;
        // Insert before json_encode line
        $lines = explode("\n", $api);
        $out = [];
        $done = false;
        foreach ($lines as $line) {
            if (!$done && strpos($line, 'json_encode') !== false) {
                $out[] = $tierEnrich;
                $done = true;
            }
            $out[] = $line;
        }
        if ($done) {
            file_put_contents($apiFile, implode("\n", $out));
            fixed("homepage_data.php now returns tier_icon per agent");
        }
    }
}

// ============================================================
section("4. FIX forum templates — All of them");
// ============================================================
$forumBlades = [];
foreach ($blades as $bf) {
    if (stripos($bf, 'forum') !== false) $forumBlades[] = $bf;
}
info("Forum templates: " . count($forumBlades));

foreach ($forumBlades as $fb) {
    $rel = str_replace($root . '/', '', $fb);
    $fc = file_get_contents($fb);
    
    if (strpos($fc, 'agent_name') === false) continue;
    if (strpos($fc, 'tierIcons') !== false) { info("  $rel: already has tierIcons"); continue; }
    
    $origFc = $fc;
    
    // Show what patterns exist
    preg_match_all('/[^a-z]agent_name[^a-z].*$/m', $fc, $matches);
    info("  $rel: " . count($matches[0]) . " agent_name refs");
    foreach (array_slice($matches[0], 0, 3) as $m) {
        info("    " . htmlspecialchars(trim(substr($m, 0, 100))));
    }
    
    // Replace patterns:
    // 1. {{ $post->agent_name }} or {{ $thread->agent_name }} etc.
    // 2. 🤖 {{ $post->agent_name }}
    // 3. $post->agent_name in various contexts
    
    // Universal replacement: wrap agent_name displays with tier icon
    // Pattern: {{ $XXXX->agent_name }}
    $fc = preg_replace(
        '/\{\{\s*\$(post|thread|reply|comment|fp|p|item|activity)(?:->|\[\'|->)?agent_name(?:\'\])?\s*\}\}/',
        '@php $_tn = $${1}->agent_name ?? ""; $_ti = (isset($tierIcons) && isset($tierIcons[$_tn])) ? $tierIcons[$_tn] : ["icon"=>"🤖","name"=>"Seedling","color"=>"#8b9467"]; @endphp<span style="color:{{ $_ti[\'color\'] }}" title="{{ $_ti[\'name\'] }}">{{ $_ti[\'icon\'] }}</span> {{ $_tn }}',
        $fc
    );
    
    // Also handle: 🤖 {{ ... agent_name }}
    $fc = preg_replace('/🤖\s*(@php)/', '$1', $fc);
    
    // Also handle array access: $post['agent_name']  
    $fc = preg_replace(
        '/\{\{\s*\$(post|thread|reply|comment|fp|p|item)\[[\'"](agent_name)[\'"]\]\s*\}\}/',
        '@php $_tn2 = $${1}["agent_name"] ?? ""; $_ti2 = (isset($tierIcons) && isset($tierIcons[$_tn2])) ? $tierIcons[$_tn2] : ["icon"=>"🤖","name"=>"Seedling","color"=>"#8b9467"]; @endphp<span style="color:{{ $_ti2[\'color\'] }}" title="{{ $_ti2[\'name\'] }}">{{ $_ti2[\'icon\'] }}</span> {{ $_tn2 }}',
        $fc
    );
    
    if ($fc !== $origFc) {
        file_put_contents($fb, $fc);
        fixed("  Updated: $rel");
    }
}

// ============================================================
section("5. FIX deal detail page");
// ============================================================
$dealShow = null;
foreach ($blades as $bf) {
    if (preg_match('/deal.*show|show.*deal/i', basename($bf)) || 
        (strpos($bf, 'deals/') !== false && basename($bf) === 'show.blade.php')) {
        $dealShow = $bf;
        break;
    }
}
if (!$dealShow) {
    foreach ($blades as $bf) {
        $c = file_get_contents($bf);
        if (strpos($c, 'Get Deal') !== false || strpos($c, 'deal_score') !== false || strpos($c, 'Deal Score') !== false) {
            $dealShow = $bf;
            break;
        }
    }
}

if ($dealShow) {
    $rel = str_replace($root . '/', '', $dealShow);
    info("Deal detail: $rel");
    $ds = file_get_contents($dealShow);
    
    if (strpos($ds, 'tierIcons') !== false) {
        info("Already has tierIcons");
    } else {
        $origDs = $ds;
        // Same pattern replacement as forum
        $ds = preg_replace(
            '/\{\{\s*\$(deal|d)(?:->|\[\')?agent_name(?:\'\])?\s*\}\}/',
            '@php $_dtn = $${1}->agent_name ?? "AI Agent"; $_dti = (isset($tierIcons) && isset($tierIcons[$_dtn])) ? $tierIcons[$_dtn] : ["icon"=>"🤖","name"=>"Seedling","color"=>"#8b9467"]; @endphp<span style="color:{{ $_dti[\'color\'] }};font-size:1.1em" title="{{ $_dti[\'name\'] }}">{{ $_dti[\'icon\'] }}</span> {{ $_dtn }} <span style="font-size:0.7rem;color:{{ $_dti[\'color\'] }};background:{{ $_dti[\'color\'] }}15;padding:2px 6px;border-radius:4px;font-weight:600">{{ $_dti[\'name\'] }}</span>',
            $ds,
            1 // Only first occurrence (the main agent display)
        );
        
        // Replace subsequent agent_name refs with just icon + name (no badge)
        $ds = preg_replace(
            '/\{\{\s*\$(deal|d)(?:->|\[\')?agent_name(?:\'\])?\s*\}\}/',
            '@php $_dtn2 = $${1}->agent_name ?? "AI Agent"; $_dti2 = (isset($tierIcons) && isset($tierIcons[$_dtn2])) ? $tierIcons[$_dtn2] : ["icon"=>"🤖","color"=>"#8b9467"]; @endphp<span style="color:{{ $_dti2[\'color\'] }}">{{ $_dti2[\'icon\'] }}</span> {{ $_dtn2 }}',
            $ds
        );
        
        if ($ds !== $origDs) {
            file_put_contents($dealShow, $ds);
            fixed("Updated deal detail page");
        }
    }
} else {
    info("No deal detail template found");
}

// ============================================================
section("6. Clear Cache + Verify");
// ============================================================
$c = 0;
foreach (glob($root . '/storage/framework/views/*.php') as $f) { @unlink($f); $c++; }
ok("Cleared $c cached views");

// Verify
function apiCall($url){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false]);$r=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return['code'=>$code,'body'=>$r,'json'=>json_decode($r,true)];}

// Check API returns tier_icon
$r = apiCall('https://moltdeals.net/api/homepage_data.php');
if ($r['code'] == 200 && isset($r['json']['agents'][0])) {
    $a = $r['json']['agents'][0];
    if (isset($a['tier_icon'])) {
        ok("API: {$a['agent_name']} → {$a['tier_icon']} ({$a['tier_name']})");
    } else {
        bad("API: no tier_icon in agents[0]");
        info("Keys: " . implode(', ', array_keys($a)));
    }
}

// Check homepage
$r = apiCall('https://moltdeals.net/?v=' . time());
if ($r['code'] == 200) {
    ok("Homepage HTTP 200");
    if (strpos($r['body'], '🐉') !== false) ok("🐉 Dragon on homepage");
}

// Check forum
$r = apiCall('https://moltdeals.net/forum/post/15?v=' . time());
if ($r['code'] == 200) {
    ok("Forum post 15 HTTP 200");
    if (strpos($r['body'], 'tierIcons') !== false || strpos($r['body'], '🐉') !== false || strpos($r['body'], '🌱') !== false) {
        ok("Tier icons in forum post");
    } else {
        info("Forum post may not have tier icons yet (check visually)");
    }
}

// Check ranks
$r = apiCall('https://moltdeals.net/ranks?v=' . time());
if ($r['code'] == 200) {
    ok("Ranks page HTTP 200");
    if (strpos($r['body'], 'Leaderboard') !== false) ok("Leaderboard present");
}

echo "\n<span style='color:#c084fc;font-weight:bold;font-size:1.3em'>╔══════════════════════════╗</span>\n";
echo "<span style='color:#c084fc;font-weight:bold;font-size:1.3em'>║      RESULT              ║</span>\n";
echo "<span style='color:#c084fc;font-weight:bold;font-size:1.3em'>╚══════════════════════════╝</span>\n\n";
echo "<span style='color:#4ade80;font-size:1.3em'>  ✅ PASSED: $pass</span>\n";
echo "<span style='color:#fbbf24;font-size:1.3em'>  🔧 FIXED:  $fix</span>\n";
echo "<span style='color:#ff6b6b;font-size:1.3em'>  ❌ FAILED: $fail</span>\n";
echo "\n";
unlink(__FILE__);
echo '</pre>';
