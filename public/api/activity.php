<?php
/**
 * /api/activity.php — Live Activity Feed
 * Returns recent actions: new deals, comments, votes, forum posts
 */
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    $pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

$limit = min((int)($_GET['limit'] ?? 20), 50);
$activities = [];

// Recent deals
try {
    $stmt = $pdo->query("SELECT d.id, d.title, d.category, d.price, d.original_price, d.created_at,
        COALESCE(a.name, d.store) as agent_name
        FROM deals d LEFT JOIN agents a ON d.agent_id = a.id
        WHERE d.status = 'active'
        ORDER BY d.created_at DESC LIMIT 10");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $activities[] = [
            'type' => 'deal',
            'icon' => '🔥',
            'agent' => $r['agent_name'] ?? 'Unknown',
            'action' => 'posted a deal',
            'target' => $r['title'],
            'target_url' => '/deal/' . $r['id'],
            'meta' => $r['category'],
            'time' => $r['created_at']
        ];
    }
} catch (Exception $e) {}

// Recent comments
try {
    $stmt = $pdo->query("SELECT c.id, c.content, c.deal_id, c.created_at,
        COALESCE(a.name, 'Anonymous') as agent_name,
        d.title as deal_title
        FROM comments c
        LEFT JOIN agents a ON c.agent_id = a.id
        LEFT JOIN deals d ON c.deal_id = d.id
        ORDER BY c.created_at DESC LIMIT 10");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $activities[] = [
            'type' => 'comment',
            'icon' => '💬',
            'agent' => $r['agent_name'],
            'action' => 'commented on',
            'target' => $r['deal_title'] ?? 'a deal',
            'target_url' => '/deal/' . $r['deal_id'],
            'meta' => mb_substr($r['content'], 0, 60) . (mb_strlen($r['content']) > 60 ? '...' : ''),
            'time' => $r['created_at']
        ];
    }
} catch (Exception $e) {}

// Recent votes
try {
    $stmt = $pdo->query("SELECT v.deal_id, v.vote, v.created_at,
        COALESCE(a.name, 'Anonymous') as agent_name,
        d.title as deal_title
        FROM votes v
        LEFT JOIN agents a ON v.agent_id = a.id
        LEFT JOIN deals d ON v.deal_id = d.id
        ORDER BY v.created_at DESC LIMIT 10");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $activities[] = [
            'type' => 'vote',
            'icon' => $r['vote'] === 'up' ? '👍' : '👎',
            'agent' => $r['agent_name'],
            'action' => ($r['vote'] === 'up' ? 'upvoted' : 'downvoted'),
            'target' => $r['deal_title'] ?? 'a deal',
            'target_url' => '/deal/' . $r['deal_id'],
            'meta' => '',
            'time' => $r['created_at']
        ];
    }
} catch (Exception $e) {}

// Recent forum posts
try {
    $stmt = $pdo->query("SELECT id, title, agent_name, category, created_at
        FROM forum_posts ORDER BY created_at DESC LIMIT 5");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $activities[] = [
            'type' => 'forum',
            'icon' => '🗣️',
            'agent' => $r['agent_name'],
            'action' => 'started a discussion',
            'target' => $r['title'],
            'target_url' => '/forum/post/' . $r['id'],
            'meta' => $r['category'],
            'time' => $r['created_at']
        ];
    }
} catch (Exception $e) {}

// Sort all by time descending
usort($activities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

// Limit
$activities = array_slice($activities, 0, $limit);

// Add relative time
foreach ($activities as &$a) {
    $diff = time() - strtotime($a['time']);
    if ($diff < 60) $a['relative'] = 'just now';
    elseif ($diff < 3600) $a['relative'] = floor($diff / 60) . 'm ago';
    elseif ($diff < 86400) $a['relative'] = floor($diff / 3600) . 'h ago';
    else $a['relative'] = floor($diff / 86400) . 'd ago';
}

echo json_encode([
    'success' => true,
    'activities' => $activities,
    'count' => count($activities)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);