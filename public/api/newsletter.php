<?php
/**
 * /api/newsletter.php — Deal Newsletter Subscription
 * 
 * POST /api/newsletter.php         → Subscribe (email, frequency)
 * GET  /api/newsletter.php?verify=TOKEN → Verify email
 * GET  /api/newsletter.php?unsub=TOKEN  → Unsubscribe
 */
error_reporting(0);
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
    $pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "error" => "Database error"]);
    exit;
}

// GET: Verify or Unsubscribe
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Verify email
    if (!empty($_GET['verify'])) {
        $token = preg_replace('/[^a-f0-9]/', '', $_GET['verify']);
        $stmt = $pdo->prepare("SELECT * FROM newsletter_subscribers WHERE verify_token = ? AND verified = 0");
        $stmt->execute([$token]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sub) {
            $pdo->prepare("UPDATE newsletter_subscribers SET verified = 1, verified_at = NOW(), verify_token = NULL WHERE id = ?")
                ->execute([$sub['id']]);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Subscription Confirmed</title>';
            echo '<style>body{background:#0a0a14;color:#e0e0e0;font-family:system-ui;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}';
            echo '.card{background:#12121e;border:1px solid #2a2a40;border-radius:1rem;padding:2.5rem;max-width:400px;text-align:center}';
            echo 'h1{color:#10b981;font-size:1.5rem}p{color:#888;margin-top:1rem}a{color:#ff4b2b;text-decoration:none}</style></head>';
            echo '<body><div class="card"><h1>✅ Subscription Confirmed!</h1>';
            echo '<p>You\'ll receive ' . htmlspecialchars($sub['frequency']) . ' deal digests at <strong>' . htmlspecialchars($sub['email']) . '</strong></p>';
            echo '<p style="margin-top:2rem"><a href="/">← Back to MoltDeals</a></p></div></body></html>';
            exit;
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invalid Link</title>';
            echo '<style>body{background:#0a0a14;color:#e0e0e0;font-family:system-ui;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}';
            echo '.card{background:#12121e;border:1px solid #2a2a40;border-radius:1rem;padding:2.5rem;max-width:400px;text-align:center}';
            echo 'h1{color:#ff4b2b;font-size:1.5rem}p{color:#888;margin-top:1rem}a{color:#ff4b2b;text-decoration:none}</style></head>';
            echo '<body><div class="card"><h1>⚠️ Invalid or Expired Link</h1>';
            echo '<p>This link may have already been used or expired.</p>';
            echo '<p style="margin-top:2rem"><a href="/">← Back to MoltDeals</a></p></div></body></html>';
            exit;
        }
    }
    
    // Unsubscribe
    if (!empty($_GET['unsub'])) {
        $token = preg_replace('/[^a-f0-9]/', '', $_GET['unsub']);
        $stmt = $pdo->prepare("SELECT * FROM newsletter_subscribers WHERE unsubscribe_token = ?");
        $stmt->execute([$token]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sub) {
            $pdo->prepare("UPDATE newsletter_subscribers SET unsubscribed_at = NOW() WHERE id = ?")
                ->execute([$sub['id']]);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Unsubscribed</title>';
            echo '<style>body{background:#0a0a14;color:#e0e0e0;font-family:system-ui;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}';
            echo '.card{background:#12121e;border:1px solid #2a2a40;border-radius:1rem;padding:2.5rem;max-width:400px;text-align:center}';
            echo 'h1{font-size:1.5rem}p{color:#888;margin-top:1rem}a{color:#ff4b2b;text-decoration:none}</style></head>';
            echo '<body><div class="card"><h1>👋 Unsubscribed</h1>';
            echo '<p>You\'ve been unsubscribed from MoltDeals newsletter.</p>';
            echo '<p style="margin-top:2rem"><a href="/">← Back to MoltDeals</a></p></div></body></html>';
            exit;
        }
        header('Location: /');
        exit;
    }
    
    // Default: return subscriber count
    header('Content-Type: application/json');
    $count = $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE verified = 1 AND unsubscribed_at IS NULL")->fetchColumn();
    echo json_encode(["success" => true, "subscribers" => (int)$count]);
    exit;
}

// POST: Subscribe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    
    $email = trim($input['email'] ?? '');
    $frequency = in_array($input['frequency'] ?? '', ['daily', 'weekly']) ? $input['frequency'] : 'daily';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "error" => "Please enter a valid email address."]);
        exit;
    }
    
    // Check existing
    $stmt = $pdo->prepare("SELECT * FROM newsletter_subscribers WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        if ($existing['verified'] && !$existing['unsubscribed_at']) {
            echo json_encode(["success" => false, "error" => "This email is already subscribed."]);
            exit;
        }
        if ($existing['unsubscribed_at']) {
            // Re-subscribe
            $verifyToken = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE newsletter_subscribers SET verified = 0, verify_token = ?, frequency = ?, unsubscribed_at = NULL, created_at = NOW() WHERE id = ?")
                ->execute([$verifyToken, $frequency, $existing['id']]);
        } else {
            // Resend verification
            $verifyToken = $existing['verify_token'] ?: bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE newsletter_subscribers SET verify_token = ?, frequency = ? WHERE id = ?")
                ->execute([$verifyToken, $frequency, $existing['id']]);
        }
    } else {
        $verifyToken = bin2hex(random_bytes(32));
        $unsubToken = bin2hex(random_bytes(32));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $pdo->prepare("INSERT INTO newsletter_subscribers (email, frequency, verify_token, unsubscribe_token, ip_address) VALUES (?, ?, ?, ?, ?)")
            ->execute([$email, $frequency, $verifyToken, $unsubToken, $ip]);
    }
    
    // Send verification email
    $verifyUrl = "https://moltdeals.net/api/newsletter.php?verify=" . $verifyToken;
    
    // Try using existing mailer
    $mailerPath = __DIR__ . '/mailer.php';
    if (file_exists($mailerPath)) {
        require_once $mailerPath;
        $mailer = new MoltMailer();
        $result = $mailer->send($email, "Confirm your MoltDeals Newsletter",
            "<div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;background:#1a1a2e;color:#e0e0e0;padding:2rem;border-radius:12px;'>
            <div style='text-align:center;margin-bottom:1.5rem'>
                <span style='font-size:2rem'>🦞</span>
                <h1 style='color:#ff4b2b;margin:0.5rem 0;font-size:1.3rem'>MoltDeals Newsletter</h1>
            </div>
            <p style='color:#bbb'>Click below to confirm your <strong>{$frequency}</strong> deal newsletter subscription:</p>
            <div style='text-align:center;margin:1.5rem 0'>
                <a href='{$verifyUrl}' style='background:linear-gradient(135deg,#ff4b2b,#ff6b4a);color:#fff;padding:0.8rem 2rem;text-decoration:none;border-radius:8px;font-weight:700;display:inline-block'>Confirm Subscription</a>
            </div>
            <p style='color:#666;font-size:0.8rem'>If you didn't request this, ignore this email.</p>
        </div>");
        
        if ($result['ok']) {
            echo json_encode(["success" => true, "message" => "Check your email to confirm your subscription."]);
        } else {
            echo json_encode(["success" => true, "message" => "Subscribed! (Email verification pending)", "note" => "Mailer issue: " . ($result['error'] ?? 'unknown')]);
        }
    } else {
        // No mailer available, auto-verify
        $pdo->prepare("UPDATE newsletter_subscribers SET verified = 1, verified_at = NOW() WHERE verify_token = ?")
            ->execute([$verifyToken]);
        echo json_encode(["success" => true, "message" => "Subscribed successfully!"]);
    }
    exit;
}