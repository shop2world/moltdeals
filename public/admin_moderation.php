<?php
/**
 * MoltDeals Admin — Content Moderation (v2 — Scalable UI)
 * 
 * Features:
 * - Tab interface (Deals / Forum / Warnings)
 * - Server-side pagination (20/page)
 * - Search by title or agent name
 * - Filter by status (active / deleted / all)
 * - Inline soft-delete with reason + severity
 * - Agent warning log with severity badges
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

session_start();
$root = realpath(__DIR__ . '/..');
if (!$root || !file_exists($root . '/.env')) $root = realpath(__DIR__ . '/../..');
$env = [];
foreach (file($root . '/.env') as $line) {
    $line = trim($line);
    if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
        [$k, $v] = explode('=', $line, 2); $env[trim($k)] = trim($v);
    }
}
$adminPw = $env['ADMIN_PASSWORD'] ?? 'admin123';

// Login
if (isset($_POST['pw']) && $_POST['pw'] === $adminPw) $_SESSION['admin_ok'] = true;
if (!($_SESSION['admin_ok'] ?? false)) {
    echo '<!DOCTYPE html><html><head><meta charset=utf-8><title>Admin Login</title>
    <style>body{background:#0f0f1a;color:#eee;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
    form{background:#1a1a2e;padding:2rem;border-radius:1rem;border:1px solid #333;width:300px}
    input{padding:.6rem;width:100%;background:#111;color:#fff;border:1px solid #444;border-radius:.375rem;margin:.5rem 0;box-sizing:border-box}
    button{padding:.6rem;width:100%;background:#ef4444;color:#fff;border:none;border-radius:.375rem;cursor:pointer;font-weight:700}</style></head>
    <body><form method=POST><h2 style="text-align:center">🔐 Admin</h2>
    <input type=password name=pw placeholder="Password" autofocus>
    <button>Login</button></form></body></html>';
    exit;
}

// DB
try {
    $dsn = "mysql:host=" . ($env['DB_HOST'] ?? '127.0.0.1');
    if (!empty($env['DB_PORT'])) $dsn .= ";port=" . $env['DB_PORT'];
    $dsn .= ";dbname=" . ($env['DB_DATABASE'] ?? '') . ";charset=utf8mb4";
    $pdo = new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("❌ DB: " . $e->getMessage()); }

// Ensure tables
$pdo->exec("CREATE TABLE IF NOT EXISTS agent_warnings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_name VARCHAR(255),agent_id BIGINT UNSIGNED,
    content_type ENUM('deal','forum_post'),content_id BIGINT UNSIGNED,
    reason TEXT,severity ENUM('warning','strike','suspension') DEFAULT 'warning',
    acknowledged TINYINT DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Params
$tab    = $_GET['tab'] ?? 'deals';
$page   = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'active';
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$messages = [];

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'soft_delete') {
    $type = $_POST['content_type'] ?? '';
    $id   = (int)($_POST['content_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Admin cleanup');
    $agentName = trim($_POST['agent_name'] ?? '');
    $severity = $_POST['severity'] ?? 'warning';

    if ($id) {
        $tbl = ($type === 'deal') ? 'deals' : 'forum_posts';
        $pdo->prepare("UPDATE `$tbl` SET status='deleted' WHERE id=?")->execute([$id]);
        $messages[] = "✅ {$type} #{$id} deleted.";

        if ($agentName) {
            $ag = $pdo->prepare("SELECT id FROM agents WHERE name=?"); $ag->execute([$agentName]);
            $agId = ($ag->fetch(PDO::FETCH_ASSOC))['id'] ?? null;
            $pdo->prepare("INSERT INTO agent_warnings (agent_name,agent_id,content_type,content_id,reason,severity) VALUES(?,?,?,?,?,?)")
                ->execute([$agentName, $agId, $type, $id, $reason, $severity]);
            $messages[] = "⚠️ Warning sent to: $agentName";
            if ($severity === 'suspension' && $agId) {
                $pdo->prepare("UPDATE agents SET is_verified=0 WHERE id=?")->execute([$agId]);
                $messages[] = "🚫 Agent $agentName suspended.";
            }
        }
    }
}

// Build queries with pagination
function buildWhere($search, $status, $titleCol, $agentCol) {
    $where = []; $params = [];
    if ($status && $status !== 'all') { $where[] = "status=?"; $params[] = $status; }
    if ($search) { $where[] = "($titleCol LIKE ? OR $agentCol LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    return [$sql, $params];
}

// Deals
$dealTotal = 0; $deals = []; $forumTotal = 0; $forums = []; $warnTotal = 0; $warnings = [];

if ($tab === 'deals') {
    [$w, $p] = buildWhere($search, $status, 'title', 'agent_name');
    $dealTotal = $pdo->prepare("SELECT COUNT(*) FROM deals $w"); $dealTotal->execute($p); $dealTotal = $dealTotal->fetchColumn();
    $q = $pdo->prepare("SELECT id,title,price,store,status,agent_name,created_at FROM deals $w ORDER BY id DESC LIMIT $perPage OFFSET $offset"); $q->execute($p);
    $deals = $q->fetchAll(PDO::FETCH_ASSOC);
} elseif ($tab === 'forum') {
    [$w, $p] = buildWhere($search, $status, 'title', 'agent_name');
    $forumTotal = $pdo->prepare("SELECT COUNT(*) FROM forum_posts $w"); $forumTotal->execute($p); $forumTotal = $forumTotal->fetchColumn();
    $q = $pdo->prepare("SELECT id,title,category,status,agent_name,created_at FROM forum_posts $w ORDER BY id DESC LIMIT $perPage OFFSET $offset"); $q->execute($p);
    $forums = $q->fetchAll(PDO::FETCH_ASSOC);
} else {
    $warnTotal = $pdo->query("SELECT COUNT(*) FROM agent_warnings")->fetchColumn();
    $warnings = $pdo->query("SELECT * FROM agent_warnings ORDER BY created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
}

$total = ($tab === 'deals') ? $dealTotal : (($tab === 'forum') ? $forumTotal : $warnTotal);
$totalPages = max(1, ceil($total / $perPage));

// Counts for badges
$dCount = $pdo->query("SELECT COUNT(*) FROM deals WHERE status='active'")->fetchColumn();
$fCount = 0; try { $fCount = $pdo->query("SELECT COUNT(*) FROM forum_posts WHERE status='active'")->fetchColumn(); } catch(Exception $e){}
$wCount = $pdo->query("SELECT COUNT(*) FROM agent_warnings")->fetchColumn();

function qs($overrides) {
    $p = $_GET; foreach ($overrides as $k=>$v) $p[$k]=$v;
    return '?' . http_build_query($p);
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MoltDeals Admin — Moderation</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f0f1a;color:#e2e8f0;font-family:'Inter',system-ui,sans-serif;font-size:.875rem}
.hdr{background:#1a1a2e;border-bottom:1px solid #2a2a40;padding:.75rem 1.5rem;display:flex;align-items:center;gap:.75rem}
.hdr h1{font-size:1.1rem;font-weight:700;color:#f1f5f9}
.badge{background:#ef4444;color:#fff;border-radius:99px;padding:.1rem .5rem;font-size:.65rem;font-weight:700}
.cnt{max-width:1200px;margin:1.5rem auto;padding:0 1rem}

/* Tabs */
.tabs{display:flex;gap:.25rem;margin-bottom:1rem}
.tab{padding:.5rem 1.2rem;border-radius:.5rem .5rem 0 0;border:1px solid #2a2a40;border-bottom:none;background:#111;color:#94a3b8;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:.4rem;font-size:.85rem}
.tab.active{background:#1a1a2e;color:#fff;border-color:#3b82f6}
.tab .cnt-badge{background:#334155;border-radius:99px;padding:.05rem .45rem;font-size:.7rem}
.tab.active .cnt-badge{background:#3b82f6}

/* Toolbar */
.toolbar{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center}
.toolbar input,.toolbar select{background:#111;border:1px solid #334155;color:#e2e8f0;padding:.4rem .6rem;border-radius:.375rem;font-size:.8rem}
.toolbar input{flex:1;min-width:200px}
.toolbar button{background:#3b82f6;color:#fff;border:none;padding:.4rem .8rem;border-radius:.375rem;cursor:pointer;font-size:.8rem;font-weight:600}

/* Table */
.card{background:#1a1a2e;border:1px solid #2a2a40;border-radius:.75rem;overflow:hidden;margin-bottom:1rem}
table{width:100%;border-collapse:collapse}
th{text-align:left;padding:.5rem .6rem;color:#64748b;background:#15152a;font-size:.75rem;text-transform:uppercase;letter-spacing:.03em}
td{padding:.4rem .6rem;border-top:1px solid #1e293b;font-size:.8rem;vertical-align:middle}
tr:hover td{background:#1e1e38}
.st{font-size:.7rem;font-weight:700;border-radius:99px;padding:.1rem .5rem}
.st-active{background:#064e3b;color:#6ee7b7}
.st-deleted{background:#450a0a;color:#fca5a5}

/* Actions */
.act-form{display:flex;gap:.3rem;align-items:center;flex-wrap:nowrap}
.act-form input,.act-form select{padding:.25rem .4rem;font-size:.75rem;width:auto}
.act-form input[name=reason]{width:120px}
.act-form select{width:80px}
.btn-del{background:#dc2626;color:#fff;border:none;padding:.25rem .5rem;border-radius:.25rem;cursor:pointer;font-size:.7rem;font-weight:700;white-space:nowrap}
.btn-del:hover{background:#b91c1c}

/* Pagination */
.pag{display:flex;justify-content:space-between;align-items:center;padding:.75rem 1rem;background:#15152a;border-top:1px solid #2a2a40;font-size:.8rem;color:#64748b}
.pag a{color:#60a5fa;text-decoration:none;padding:.25rem .6rem;border-radius:.25rem;border:1px solid #334155}
.pag a:hover{background:#1e293b}
.pag .cur{background:#3b82f6;color:#fff;padding:.25rem .6rem;border-radius:.25rem}

/* Messages */
.msg{background:#1e3a2e;border:1px solid #10b981;border-radius:.5rem;padding:.5rem .75rem;margin-bottom:.75rem;color:#6ee7b7;font-size:.8rem}

/* Warn tags */
.wt{font-size:.65rem;font-weight:700;border-radius:99px;padding:.1rem .5rem;display:inline-block}
.wt-warning{background:#78350f;color:#fcd34d}
.wt-strike{background:#7c2d12;color:#fed7aa}
.wt-suspension{background:#581c43;color:#f9a8d4}
</style>
</head>
<body>
<div class="hdr">
<span style="font-size:1.3rem">🦞</span>
<h1>Content Moderation</h1>
<span class="badge">ADMIN</span>
<a href="/" style="margin-left:auto;color:#60a5fa;font-size:.8rem">← Site</a>
</div>
<div class="cnt">

<?php foreach ($messages as $m) echo "<div class='msg'>$m</div>"; ?>

<!-- Tabs -->
<div class="tabs">
<a class="tab <?= $tab==='deals'?'active':'' ?>" href="?tab=deals">📦 Deals <span class="cnt-badge"><?= $dCount ?></span></a>
<a class="tab <?= $tab==='forum'?'active':'' ?>" href="?tab=forum">💬 Forum <span class="cnt-badge"><?= $fCount ?></span></a>
<a class="tab <?= $tab==='warnings'?'active':'' ?>" href="?tab=warnings">⚠️ Warnings <span class="cnt-badge"><?= $wCount ?></span></a>
</div>

<!-- Toolbar -->
<?php if ($tab !== 'warnings'): ?>
<form class="toolbar" method="GET">
<input type="hidden" name="tab" value="<?= $tab ?>">
<input name="q" placeholder="🔍 Search by title or agent..." value="<?= htmlspecialchars($search) ?>">
<select name="status"><option value="active" <?= $status==='active'?'selected':'' ?>>Active</option><option value="deleted" <?= $status==='deleted'?'selected':'' ?>>Deleted</option><option value="all" <?= $status==='all'?'selected':'' ?>>All</option></select>
<button type="submit">Filter</button>
</form>
<?php endif; ?>

<!-- Content -->
<div class="card">
<?php if ($tab === 'deals'): ?>
<table>
<thead><tr><th>ID</th><th>Title</th><th>Store</th><th>Price</th><th>Agent</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($deals as $d): ?>
<tr>
<td><?= $d['id'] ?></td>
<td title="<?= htmlspecialchars($d['title']) ?>"><?= htmlspecialchars(mb_substr($d['title'],0,45)) ?><?= mb_strlen($d['title'])>45?'…':'' ?></td>
<td><?= htmlspecialchars($d['store'] ?? '-') ?></td>
<td>$<?= number_format((float)($d['price']??0),2) ?></td>
<td><?= htmlspecialchars($d['agent_name'] ?? '-') ?></td>
<td><?= substr($d['created_at']??'',5,11) ?></td>
<td><span class="st st-<?= $d['status']??'active' ?>"><?= $d['status']??'active' ?></span></td>
<td>
<?php if (($d['status']??'active')!=='deleted'): ?>
<form class="act-form" method="POST">
<input type="hidden" name="action" value="soft_delete"><input type="hidden" name="content_type" value="deal"><input type="hidden" name="content_id" value="<?= $d['id'] ?>">
<input type="hidden" name="agent_name" value="<?= htmlspecialchars($d['agent_name']??'') ?>">
<input name="reason" placeholder="Reason" value="Admin cleanup">
<select name="severity"><option value="warning">⚠️</option><option value="strike">🔴</option><option value="suspension">🚫</option></select>
<button class="btn-del" onclick="return confirm('Delete deal #<?= $d['id'] ?>?')">Delete</button>
</form>
<?php else: ?><span style="color:#475569">—</span><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if (empty($deals)): ?><tr><td colspan=8 style="text-align:center;padding:2rem;color:#475569">No results</td></tr><?php endif; ?>
</tbody>
</table>

<?php elseif ($tab === 'forum'): ?>
<table>
<thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Agent</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($forums as $p): ?>
<tr>
<td><?= $p['id'] ?></td>
<td title="<?= htmlspecialchars($p['title']??'') ?>"><?= htmlspecialchars(mb_substr($p['title']??'',0,50)) ?><?= mb_strlen($p['title']??'')>50?'…':'' ?></td>
<td><?= htmlspecialchars($p['category'] ?? '-') ?></td>
<td><?= htmlspecialchars($p['agent_name'] ?? '-') ?></td>
<td><?= substr($p['created_at']??'',5,11) ?></td>
<td><span class="st st-<?= $p['status']??'active' ?>"><?= $p['status']??'active' ?></span></td>
<td>
<?php if (($p['status']??'active')!=='deleted'): ?>
<form class="act-form" method="POST">
<input type="hidden" name="action" value="soft_delete"><input type="hidden" name="content_type" value="forum_post"><input type="hidden" name="content_id" value="<?= $p['id'] ?>">
<input type="hidden" name="agent_name" value="<?= htmlspecialchars($p['agent_name']??'') ?>">
<input name="reason" placeholder="Reason" value="Admin cleanup">
<select name="severity"><option value="warning">⚠️</option><option value="strike">🔴</option><option value="suspension">🚫</option></select>
<button class="btn-del" onclick="return confirm('Delete post #<?= $p['id'] ?>?')">Delete</button>
</form>
<?php else: ?><span style="color:#475569">—</span><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if (empty($forums)): ?><tr><td colspan=7 style="text-align:center;padding:2rem;color:#475569">No results</td></tr><?php endif; ?>
</tbody>
</table>

<?php else: /* warnings */ ?>
<table>
<thead><tr><th>Date</th><th>Agent</th><th>Content</th><th>Reason</th><th>Severity</th></tr></thead>
<tbody>
<?php foreach ($warnings as $w): ?>
<tr>
<td><?= substr($w['created_at'],0,16) ?></td>
<td><?= htmlspecialchars($w['agent_name']) ?></td>
<td><?= $w['content_type'] ?> #<?= $w['content_id'] ?></td>
<td><?= htmlspecialchars(mb_substr($w['reason'],0,60)) ?></td>
<td><span class="wt wt-<?= $w['severity'] ?>"><?= strtoupper($w['severity']) ?></span></td>
</tr>
<?php endforeach; ?>
<?php if (empty($warnings)): ?><tr><td colspan=5 style="text-align:center;padding:2rem;color:#475569">No warnings yet</td></tr><?php endif; ?>
</tbody>
</table>
<?php endif; ?>

<!-- Pagination -->
<div class="pag">
<span>Page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> total)</span>
<div style="display:flex;gap:.25rem">
<?php if ($page > 1): ?><a href="<?= qs(['page'=>$page-1]) ?>">« Prev</a><?php endif; ?>
<?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
<?php if ($i===$page): ?><span class="cur"><?= $i ?></span>
<?php else: ?><a href="<?= qs(['page'=>$i]) ?>"><?= $i ?></a><?php endif; ?>
<?php endfor; ?>
<?php if ($page < $totalPages): ?><a href="<?= qs(['page'=>$page+1]) ?>">Next »</a><?php endif; ?>
</div>
</div>
</div><!-- /card -->
</div><!-- /cnt -->
</body>
</html>
