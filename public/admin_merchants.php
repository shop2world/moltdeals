<?php
session_start();
error_reporting(E_ALL);
$public = __DIR__;
$root = realpath($public . '/..');
if (!$root || !file_exists($root . '/.env')) $root = realpath($public . '/../..');
$env = [];
foreach (file($root . '/.env') as $line) {
    $line = trim($line);
    if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
        [$k, $v] = explode('=', $line, 2); $env[trim($k)] = trim($v);
    }
}
$adminPw = $env['ADMIN_PASSWORD'] ?? 'admin123';
if (isset($_POST['pw']) && $_POST['pw'] === $adminPw) $_SESSION['merch_ok'] = true;
if (!($_SESSION['merch_ok'] ?? false)) {
    echo '<!DOCTYPE html><html><head><meta charset=utf-8><title>Login</title>
    <style>body{background:#0f0f1a;color:#eee;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    form{background:#1a1a2e;padding:2rem;border-radius:1rem;border:1px solid #333;width:300px}
    input{padding:.6rem;width:100%;background:#111;color:#fff;border:1px solid #444;border-radius:.375rem;margin:.5rem 0;box-sizing:border-box}
    button{padding:.6rem;width:100%;background:#ef4444;color:#fff;border:none;border-radius:.375rem;cursor:pointer;font-weight:700}</style></head>
    <body><form method=POST><h2 style="text-align:center">🔐 Merchant Admin</h2>
    <input type=password name=pw placeholder="Password" autofocus><button>Login</button></form></body></html>';
    exit;
}

$configDir = $public . '/config';
@mkdir($configDir, 0755, true);
$configFile = $configDir . '/merchant_map.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
if (!$config) $config = [];
$defaults = $config['_defaults'] ?? ['amazon' => '', 'ebay' => ''];
$merchants = array_filter($config, fn($k) => $k !== '_defaults', ARRAY_FILTER_USE_KEY);
$msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_defaults') {
        $defaults['amazon'] = trim($_POST['amazon'] ?? '');
        $defaults['ebay'] = trim($_POST['ebay'] ?? '');
        $config['_defaults'] = $defaults;
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $msg = '✅ Platform defaults saved!';
    }
    
    if ($action === 'add_merchant') {
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        $domain = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $domain);
        $domain = rtrim($domain, '/');
        $name = trim($_POST['display_name'] ?? $domain);
        $network = trim($_POST['network'] ?? 'cj');
        $prefix = trim($_POST['prefix'] ?? '');
        
        if ($domain && $prefix) {
            // Ensure prefix ends with proper separator for URL append
            // Auto-detect: if prefix contains ?url= or &murl= or &url=, it's ready
            // Otherwise append ?url= or &url=
            $config[$domain] = [
                'name' => $name,
                'network' => $network,
                'prefix' => $prefix,
                'active' => true,
                'added' => date('Y-m-d H:i:s')
            ];
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $msg = "✅ Added merchant: $name ($domain)";
            $merchants = array_filter($config, fn($k) => $k !== '_defaults', ARRAY_FILTER_USE_KEY);
        } else {
            $msg = '❌ Domain and prefix are required.';
        }
    }
    
    if ($action === 'delete_merchant') {
        $domain = $_POST['domain'] ?? '';
        if (isset($config[$domain])) {
            unset($config[$domain]);
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $msg = "🗑️ Removed: $domain";
            $merchants = array_filter($config, fn($k) => $k !== '_defaults', ARRAY_FILTER_USE_KEY);
        }
    }
    
    if ($action === 'toggle_merchant') {
        $domain = $_POST['domain'] ?? '';
        if (isset($config[$domain])) {
            $config[$domain]['active'] = !($config[$domain]['active'] ?? true);
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $msg = ($config[$domain]['active'] ? '✅ Enabled' : '⏸️ Disabled') . ": $domain";
            $merchants = array_filter($config, fn($k) => $k !== '_defaults', ARRAY_FILTER_USE_KEY);
        }
    }
    
    if ($action === 'test_link') {
        $domain = $_POST['domain'] ?? '';
        $testUrl = trim($_POST['test_url'] ?? '');
        if (isset($config[$domain]) && $testUrl) {
            $prefix = $config[$domain]['prefix'];
            $result = $prefix . urlencode($testUrl);
            $msg = "🔗 Test result for $domain:<br><code style='color:#10b981;word-break:break-all'>$result</code>";
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MoltDeals — Merchant Manager</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f0f1a;color:#e2e8f0;font-family:'Inter',system-ui,sans-serif;font-size:.875rem}
.hdr{background:#1a1a2e;border-bottom:1px solid #2a2a40;padding:.75rem 1.5rem;display:flex;align-items:center;gap:.75rem}
.hdr h1{font-size:1.1rem;font-weight:700}
.badge{background:#f59e0b;color:#000;border-radius:99px;padding:.1rem .5rem;font-size:.65rem;font-weight:700}
.cnt{max-width:900px;margin:1.5rem auto;padding:0 1rem}
.card{background:#1a1a2e;border:1px solid #2a2a40;border-radius:.75rem;padding:1.25rem;margin-bottom:1.25rem}
.card h2{font-size:.9rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.msg{border-radius:.5rem;padding:.6rem .8rem;margin-bottom:1rem;font-size:.85rem;background:#1e3a2e;border:1px solid #10b981;color:#6ee7b7}
.msg-err{background:#3a1e1e;border-color:#ef4444;color:#fca5a5}

/* Form */
.row{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem}
.row label{font-size:.75rem;color:#64748b;margin-bottom:.15rem;display:block}
.row .col{flex:1;min-width:150px}
input,select{width:100%;padding:.45rem .6rem;background:#0f0f1a;border:1px solid #334155;color:#e2e8f0;border-radius:.375rem;font-size:.8rem}
input:focus{border-color:#3b82f6;outline:none}
.btn{padding:.4rem .8rem;border:none;border-radius:.375rem;cursor:pointer;font-size:.8rem;font-weight:600}
.btn-green{background:#10b981;color:#fff}
.btn-blue{background:#3b82f6;color:#fff}
.btn-red{background:#dc2626;color:#fff;font-size:.7rem}
.btn-gray{background:#334155;color:#e2e8f0;font-size:.7rem}
.btn-yellow{background:#d97706;color:#fff;font-size:.7rem}

/* Table */
table{width:100%;border-collapse:collapse}
th{text-align:left;padding:.4rem .5rem;color:#64748b;background:#15152a;font-size:.7rem;text-transform:uppercase}
td{padding:.4rem .5rem;border-top:1px solid #1e293b;font-size:.8rem}
tr:hover td{background:#1e1e38}
.net{font-size:.65rem;font-weight:700;border-radius:99px;padding:.1rem .45rem}
.net-cj{background:#1e3a5f;color:#93c5fd}
.net-rakuten{background:#3a1e3a;color:#d8b4fe}
.net-impact{background:#1e3a2e;color:#86efac}
.net-awin{background:#3a2e1e;color:#fcd34d}
.net-other{background:#333;color:#aaa}
.prefix-display{font-family:monospace;font-size:.7rem;color:#94a3b8;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.active-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:4px}
.active-on{background:#10b981}
.active-off{background:#ef4444}

/* Info box */
.info{background:#111827;border:1px solid #1e3a5f;border-radius:.5rem;padding:.75rem;margin-bottom:1rem;font-size:.8rem;color:#93c5fd;line-height:1.6}
.info code{background:#0f172a;padding:.1rem .3rem;border-radius:3px;font-size:.75rem;color:#fcd34d}
</style>
</head>
<body>
<div class="hdr">
<span style="font-size:1.3rem">🦞</span>
<h1>Merchant Manager</h1>
<span class="badge">ADMIN</span>
<a href="/" style="margin-left:auto;color:#60a5fa;font-size:.8rem">← Site</a>
<a href="/admin_moderation.php" style="color:#60a5fa;font-size:.8rem">Moderation</a>
</div>
<div class="cnt">

<?php if ($msg) echo '<div class="msg' . (strpos($msg,'❌')!==false?' msg-err':'') . '">' . $msg . '</div>'; ?>

<!-- PLATFORM DEFAULTS -->
<div class="card">
<h2>☀️ Platform Default Tags (Amazon & eBay)</h2>
<form method="POST">
<input type="hidden" name="action" value="save_defaults">
<div class="row">
<div class="col"><label>Amazon Associate Tag</label><input name="amazon" value="<?= htmlspecialchars($defaults['amazon'] ?? '') ?>" placeholder="e.g. moltdeals-20"></div>
<div class="col"><label>eBay Campaign ID</label><input name="ebay" value="<?= htmlspecialchars($defaults['ebay'] ?? '') ?>" placeholder="e.g. 5339143374"></div>
<div class="col" style="flex:0;align-self:end"><button class="btn btn-green">💾 Save</button></div>
</div>
</form>
</div>

<!-- ADD MERCHANT -->
<div class="card">
<h2>➕ Add Merchant</h2>
<div class="info">
<strong>How it works:</strong> Paste only the <code>prefix</code> part of your deep link.<br>
When a user clicks "Get Deal", the system builds: <code>prefix</code> + <code>urlencode(product_url)</code><br><br>
<strong>Examples:</strong><br>
• CJ (Macy's): <code>https://www.dpbolvw.net/click-1816105-15578706?url=</code><br>
• Rakuten (Benefit): <code>https://click.linksynergy.com/link?id=vtJ18U6LStE&offerid=972257.24765409240881&type=2&murl=</code><br>
• CJ (Woot): <code>https://www.kqzyfj.com/click-1816105-17098058?sid=shop2ai?url=</code>
</div>
<form method="POST">
<input type="hidden" name="action" value="add_merchant">
<div class="row">
<div class="col"><label>Domain (no www)</label><input name="domain" placeholder="e.g. macys.com" required></div>
<div class="col"><label>Display Name</label><input name="display_name" placeholder="e.g. Macy's"></div>
<div class="col" style="max-width:130px"><label>Network</label>
<select name="network">
<option value="cj">CJ</option>
<option value="rakuten">Rakuten</option>
<option value="impact">Impact</option>
<option value="awin">Awin</option>
<option value="other">Other</option>
</select></div>
</div>
<div class="row">
<div class="col" style="flex:4"><label>Deep Link Prefix (everything before the product URL)</label><input name="prefix" placeholder="https://www.dpbolvw.net/click-1816105-15578706?url=" required style="font-family:monospace;font-size:.75rem"></div>
<div class="col" style="flex:0;align-self:end"><button class="btn btn-blue">➕ Add</button></div>
</div>
</form>
</div>

<!-- MERCHANT LIST -->
<div class="card">
<h2>📋 Registered Merchants (<?= count($merchants) ?>)</h2>
<?php if (empty($merchants)): ?>
<p style="color:#475569;text-align:center;padding:1rem">No merchants yet. Add one above.</p>
<?php else: ?>

<!-- Search & Filter Bar -->
<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center">
<input id="merchantSearch" type="text" placeholder="🔍 Search by domain, name, or category..." style="flex:1;min-width:200px;padding:.5rem .8rem;font-size:.85rem" oninput="filterMerchants()">
<select id="statusFilter" style="padding:.5rem;font-size:.8rem;min-width:100px" onchange="filterMerchants()">
<option value="all">All</option>
<option value="on">🟢 ON</option>
<option value="off">🔴 OFF</option>
</select>
<span id="matchCount" style="color:#64748b;font-size:.75rem"></span>
</div>

<table id="merchantTable">
<thead><tr><th>Domain</th><th>Name</th><th>Network</th><th>Prefix</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($merchants as $domain => $m): ?>
<?php $isActive = $m['active'] ?? true; ?>
<tr class="merchant-row" data-domain="<?= htmlspecialchars(strtolower($domain)) ?>" data-name="<?= htmlspecialchars(strtolower($m['name'] ?? $domain)) ?>" data-category="<?= htmlspecialchars(strtolower($m['category'] ?? '')) ?>" data-status="<?= $isActive ? 'on' : 'off' ?>">
<td><strong><?= htmlspecialchars($domain) ?></strong>
<?php if (!empty($m['category'])): ?><br><span style="font-size:.65rem;color:#64748b"><?= htmlspecialchars($m['category']) ?></span><?php endif; ?>
</td>
<td><?= htmlspecialchars($m['name'] ?? $domain) ?></td>
<td><span class="net net-<?= $m['network'] ?? 'other' ?>"><?= strtoupper($m['network'] ?? 'other') ?></span></td>
<td><div class="prefix-display" title="<?= htmlspecialchars($m['prefix'] ?? $m['deep_link_tpl'] ?? '') ?>"><?= htmlspecialchars($m['prefix'] ?? $m['deep_link_tpl'] ?? '-') ?></div></td>
<td>
<span class="active-dot <?= $isActive ? 'active-on' : 'active-off' ?>"></span>
<?= $isActive ? 'ON' : 'OFF' ?>
</td>
<td style="white-space:nowrap">
<form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_merchant"><input type="hidden" name="domain" value="<?= htmlspecialchars($domain) ?>"><button class="btn <?= $isActive ? 'btn-yellow' : 'btn-green' ?>" title="<?= $isActive ? 'Pause this merchant' : 'Activate this merchant' ?>"><?= $isActive ? '⏸️ Pause' : '▶️ Activate' ?></button></form>
<form method="POST" style="display:inline" onsubmit="return confirm('Delete <?= htmlspecialchars($domain) ?>?')"><input type="hidden" name="action" value="delete_merchant"><input type="hidden" name="domain" value="<?= htmlspecialchars($domain) ?>"><button class="btn btn-red">🗑️</button></form>
</td>
</tr>
<!-- Test row -->
<tr class="test-row" data-for="<?= htmlspecialchars(strtolower($domain)) ?>" style="background:#111827">
<td colspan="6">
<div style="display:flex;gap:.3rem;align-items:center">
<span style="color:#64748b;font-size:.7rem;white-space:nowrap">Test:</span>
<input id="test_<?= md5($domain) ?>" value="https://<?= htmlspecialchars($domain) ?>/product/example" style="flex:1;font-size:.75rem;font-family:monospace;padding:.45rem .6rem;background:#0f0f1a;border:1px solid #334155;color:#e2e8f0;border-radius:.375rem">
<button class="btn btn-gray" style="font-size:.7rem" onclick="var u=document.getElementById('test_<?= md5($domain) ?>').value; if(u){window.open('/go.php?url='+encodeURIComponent(u),'_blank')}else{alert('Enter a test URL first')}">🔗 Preview</button>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>

</div>

<script>
function filterMerchants() {
    var q = document.getElementById('merchantSearch').value.toLowerCase();
    var status = document.getElementById('statusFilter').value;
    var rows = document.querySelectorAll('.merchant-row');
    var shown = 0;
    rows.forEach(function(row) {
        var domain = row.getAttribute('data-domain') || '';
        var name = row.getAttribute('data-name') || '';
        var cat = row.getAttribute('data-category') || '';
        var st = row.getAttribute('data-status') || 'on';
        var matchQ = !q || domain.indexOf(q) > -1 || name.indexOf(q) > -1 || cat.indexOf(q) > -1;
        var matchS = status === 'all' || st === status;
        var show = matchQ && matchS;
        row.style.display = show ? '' : 'none';
        // Find and sync the matching test-row by data-for
        var testRow = document.querySelector('.test-row[data-for="' + domain + '"]');
        if (testRow) testRow.style.display = show ? '' : 'none';
        if (show) shown++;
    });
    document.getElementById('matchCount').textContent = shown + ' of ' + rows.length + ' shown';
}
filterMerchants();
</script>
</body>
</html>