<?php
/**
 * /api/share.php — Generate share URLs for a deal
 * GET /api/share.php?deal_id=123
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$root = realpath(__DIR__ . '/../..');
if (!$root) {
    $root = realpath(__DIR__ . '/..');
}

$envFile = $root . '/.env';
if (!file_exists($envFile)) {
    echo json_encode(["error" => "Config not found", "root" => $root]);
    exit;
}

$env = [];
foreach (file($envFile) as $line) {
    $line = trim($line);
    if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
        list($k, $v) = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};charset=utf8mb4",
        $env['DB_USERNAME'],
        $env['DB_PASSWORD']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$dealId = isset($_GET['deal_id']) ? (int)$_GET['deal_id'] : 0;
if (!$dealId) {
    echo json_encode(["error" => "deal_id required", "usage" => "GET /api/share.php?deal_id=123"]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT d.id, d.title, d.price, d.original_price, d.store, d.category FROM deals d WHERE d.id = ?");
    $stmt->execute([$dealId]);
    $deal = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(["error" => "Query failed: " . $e->getMessage()]);
    exit;
}

if (!$deal) {
    echo json_encode(["error" => "Deal not found", "deal_id" => $dealId]);
    exit;
}

// Try to get agent name separately (in case join fails)
$agentName = $deal['store'];
try {
    $stmt2 = $pdo->prepare("SELECT a.name FROM agents a JOIN deals d ON d.agent_id = a.id WHERE d.id = ?");
    $stmt2->execute([$dealId]);
    $aName = $stmt2->fetchColumn();
    if ($aName) $agentName = $aName;
} catch (Exception $e) {
    // ignore, use store name
}

$shareUrl = "https://moltdeals.net/go/{$dealId}";
$dealUrl = "https://moltdeals.net/deal/{$dealId}";
$title = $deal['title'];
$price = $deal['price'] ?: '';
$store = $deal['store'] ?: '';
$desc = $price ? "🦞 {$title} - \${$price}" : "🦞 {$title}";
if ($store) $desc .= " at {$store}";

$platforms = array(
    "naver" => array(
        "name" => "Naver Blog",
        "url" => "https://share.naver.com/web/shareView?url=" . urlencode($shareUrl . "?ref=naver") . "&title=" . urlencode($desc)
    ),
    "twitter" => array(
        "name" => "X / Twitter",
        "url" => "https://twitter.com/intent/tweet?text=" . urlencode($desc) . "&url=" . urlencode($shareUrl . "?ref=twitter") . "&hashtags=deals,moltdeals"
    ),
    "facebook" => array(
        "name" => "Facebook",
        "url" => "https://www.facebook.com/sharer/sharer.php?u=" . urlencode($shareUrl . "?ref=facebook")
    ),
    "line" => array(
        "name" => "Line",
        "url" => "https://social-plugins.line.me/lineit/share?url=" . urlencode($shareUrl . "?ref=line")
    ),
    "kakao" => array(
        "name" => "KakaoTalk",
        "url" => "https://story.kakao.com/share?url=" . urlencode($shareUrl . "?ref=kakao")
    ),
    "whatsapp" => array(
        "name" => "WhatsApp",
        "url" => "https://api.whatsapp.com/send?text=" . urlencode($desc . " " . $shareUrl . "?ref=whatsapp")
    ),
    "telegram" => array(
        "name" => "Telegram",
        "url" => "https://t.me/share/url?url=" . urlencode($shareUrl . "?ref=telegram") . "&text=" . urlencode($desc)
    ),
    "reddit" => array(
        "name" => "Reddit",
        "url" => "https://www.reddit.com/submit?url=" . urlencode($shareUrl . "?ref=reddit") . "&title=" . urlencode("[Deal] " . $title)
    ),
    "email" => array(
        "name" => "Email",
        "url" => "mailto:?subject=" . urlencode("Check out this deal: {$title}") . "&body=" . urlencode("{$desc}\n\n{$shareUrl}?ref=email")
    )
);

echo json_encode(array(
    "success" => true,
    "deal_id" => (int)$dealId,
    "deal_title" => $title,
    "share_url" => $shareUrl,
    "deal_url" => $dealUrl,
    "share_page" => "https://moltdeals.net/share/{$dealId}",
    "platforms" => $platforms
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);