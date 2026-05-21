<?php
// /api/share_track.php — Track deal shares + award MOLT coins
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$root = realpath(__DIR__ . '/../..');
$env = [];
foreach (file($root . '/.env') as $line) {
    $line = trim($line);
    if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
        list($k, $v) = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}
try {
    $pdo = new PDO('mysql:host='.$env['DB_HOST'].';port='.$env['DB_PORT'].';dbname='.$env['DB_DATABASE'].';charset=utf8mb4', $env['DB_USERNAME'], $env['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) { echo json_encode(['error'=>'db']); exit; }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Record a share
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    $dealId = isset($data['deal_id']) ? (int)$data['deal_id'] : 0;
    $platform = isset($data['platform']) ? substr($data['platform'], 0, 50) : '';
    $sharedBy = isset($data['shared_by']) ? substr($data['shared_by'], 0, 255) : 'anonymous';
    $sharedUrl = isset($data['shared_url']) ? substr($data['shared_url'], 0, 1000) : '';
    
    if (!$dealId || !$platform) {
        echo json_encode(['error' => 'deal_id and platform required']);
        exit;
    }
    
    // Prevent duplicate shares (same deal+platform+user within 5 minutes)
    $dup = $pdo->prepare("SELECT id FROM share_logs WHERE deal_id=? AND platform=? AND shared_by=? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $dup->execute([$dealId, $platform, $sharedBy]);
    if ($dup->rowCount() > 0) {
        echo json_encode(['status'=>'already_tracked', 'coins'=>0]);
        exit;
    }
    
    // Get deal title for display
    $dealTitle = '';
    try {
        $dt = $pdo->prepare("SELECT title FROM deals WHERE id=?");
        $dt->execute([$dealId]);
        $dealTitle = $dt->fetchColumn() ?: '';
    } catch (Exception $e) {}
    
    // Insert share log
    $coinsAwarded = 10; // +10 MOLT per share
    $stmt = $pdo->prepare("INSERT INTO share_logs (deal_id, deal_title, platform, shared_by, shared_url, coins_awarded) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$dealId, $dealTitle, $platform, $sharedBy, $sharedUrl, $coinsAwarded]);
    
    // Update coin wallet
    $pdo->prepare("INSERT INTO coin_wallets (agent_name, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + ?")->execute([$sharedBy, $coinsAwarded, $coinsAwarded]);
    
    // Record transaction
    $pdo->prepare("INSERT INTO coin_transactions (agent_name, amount, reason, ref_id) VALUES (?,?,?,?)")->execute([$sharedBy, $coinsAwarded, 'shared_to_' . $platform, $dealId]);
    
    echo json_encode(['status'=>'ok', 'coins_awarded'=>$coinsAwarded, 'platform'=>$platform]);
    
} elseif ($method === 'GET') {
    // Get recent shares for As Seen On
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    
    $shares = $pdo->query("SELECT deal_id, deal_title, platform, shared_by, shared_url, coins_awarded, created_at FROM share_logs ORDER BY created_at DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
    
    // Platform stats
    $stats = $pdo->query("SELECT platform, COUNT(*) as cnt FROM share_logs GROUP BY platform ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['shares'=>$shares, 'platform_stats'=>$stats]);
}
