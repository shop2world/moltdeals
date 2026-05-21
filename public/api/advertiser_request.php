<?php
error_reporting(0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }

$root = realpath(__DIR__ . '/../..');
$env = [];
foreach (file($root . '/.env') as $line) {
    if (trim($line) && trim($line)[0] !== '#' && strpos($line, '=') !== false) {
        list($k, $v) = explode('=', trim($line), 2);
        $env[trim($k)] = trim($v);
    }
}
try {
    $pdo = new PDO('mysql:host='.$env['DB_HOST'].';port='.$env['DB_PORT'].';dbname='.$env['DB_DATABASE'].';charset=utf8mb4', $env['DB_USERNAME'], $env['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>'DB error']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$botAnswer = trim(isset($input['bot_answer']) ? $input['bot_answer'] : '');
$botExpected = trim(isset($input['bot_expected']) ? $input['bot_expected'] : '');
$honeypot = trim(isset($input['website_url']) ? $input['website_url'] : '');

if (!empty($honeypot)) { echo json_encode(['success'=>true,'message'=>'OK']); exit; }
if ($botAnswer !== $botExpected || empty($botExpected)) { http_response_code(400); echo json_encode(['error'=>'Anti-bot check failed. Please answer the math question.']); exit; }

$company = trim(isset($input['company_name']) ? $input['company_name'] : '');
$contact = trim(isset($input['contact_name']) ? $input['contact_name'] : '');
$email   = trim(isset($input['email']) ? $input['email'] : '');
$type    = isset($input['campaign_type']) ? $input['campaign_type'] : 'cpa';
$budget  = isset($input['budget_range']) ? $input['budget_range'] : '';
$message = trim(isset($input['message']) ? $input['message'] : '');

if (!$company || !$contact || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); echo json_encode(['error'=>'Valid company, contact, email required']); exit;
}

$stmt = $pdo->prepare('INSERT INTO advertiser_requests (company_name,contact_name,email,website,campaign_type,budget_range,message) VALUES (?,?,?,?,?,?,?)');
$stmt->execute([$company, $contact, $email, '', $type, $budget, $message]);
$reqId = $pdo->lastInsertId();

$adminEmail = isset($env['ADMIN_EMAIL']) ? $env['ADMIN_EMAIL'] : 'moltdeals.net@gmail.com';
$subj = 'New Advertiser Request #'.$reqId.' - '.$company;
$body = 'Company: '.$company."\n".'Contact: '.$contact."\n".'Email: '.$email."\n".'Type: '.$type."\n".'Budget: '.$budget."\n".'Msg: '.$message;
require_once __DIR__ . '/mailer.php';
$mailer = new MoltMailer();
$htmlBody = '<h2>New Advertiser Request #'.$reqId.'</h2>';
$htmlBody .= '<p><b>Company:</b> '.htmlspecialchars($company).'</p>';
$htmlBody .= '<p><b>Contact:</b> '.htmlspecialchars($contact).'</p>';
$htmlBody .= '<p><b>Email:</b> '.htmlspecialchars($email).'</p>';
$htmlBody .= '<p><b>Type:</b> '.htmlspecialchars($type).'</p>';
$htmlBody .= '<p><b>Budget:</b> '.htmlspecialchars($budget).'</p>';
$htmlBody .= '<p><b>Message:</b><br>'.nl2br(htmlspecialchars($message)).'</p>';
$htmlBody .= '<hr><p>Send Stripe payment link to: '.htmlspecialchars($email).'</p>';
$mailer->send('orangedigm@gmail.com', $subj, $htmlBody);

http_response_code(201);
echo json_encode(['success'=>true, 'message'=>'Thank you! We will contact you within 24 hours with a Stripe payment link.', 'request_id'=>(int)$reqId]);
