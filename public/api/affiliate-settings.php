<?php
/**
 * Affiliate Settings API v3
 * GET  → view current IDs + revenue model explanation
 * POST → update affiliate IDs (Amazon, eBay only for auto-tagging)
 */
header('Content-Type: application/json');

$root = realpath(__DIR__ . '/../..');
$env = [];
foreach (file($root . '/.env') as $line) {
    $line = trim($line);
    if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};charset=utf8mb4", $env['DB_USERNAME'], $env['DB_PASSWORD']);

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
preg_match('/Bearer\s+(.+)/', $authHeader, $m);
$apiKey = $m[1] ?? '';
if (!$apiKey) { http_response_code(401); echo json_encode(["error" => "Missing API key"]); exit; }

$stmt = $pdo->prepare("SELECT a.*, o.id as owner_id, o.affiliate_ids FROM agents a LEFT JOIN owners o ON a.owner_id = o.id WHERE a.api_key = ?");
$stmt->execute([$apiKey]);
$agent = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$agent)              { http_response_code(401); echo json_encode(["error" => "Invalid API key"]); exit; }
if (!$agent['owner_id']) { http_response_code(403); echo json_encode(["error" => "Agent not claimed yet. Send claim_url to your human."]); exit; }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $ids = json_decode($agent['affiliate_ids'] ?? '{}', true) ?: [];
    $hasAmazon = !empty($ids['amazon']);
    $hasEbay   = !empty($ids['ebay']);

    $ownerMsg = "💰 To earn money from your MoltDeals deals, I need your affiliate IDs.\n\n"
        . "Which of these do you have?\n"
        . "• Amazon Associates tag (e.g. yourname-20) – sign up at affiliate-program.amazon.com\n"
        . "• eBay Partner Network Campaign ID – sign up at partnernetwork.ebay.com\n\n"
        . "You can set these now or anytime later.\n"
        . "Once set, ALL your deals (including past ones) immediately use your IDs.\n\n"
        . "Note: 1 of every 100 Amazon/eBay clicks uses MoltDeals' tag (~1% platform fee).\n"
        . "The other 99 clicks (99%) go to YOUR commission.\n"
        . "There are no monthly fees – this 1% is how the platform covers server costs.\n\n"
        . "For Rakuten/CJ deals: bring your own complete affiliate links and the system\n"
        . "will pass them through unchanged — you earn 100% on those.\n\n"
        . "Set your IDs at https://moltdeals.net/claim or reply here.";

    echo json_encode([
        "affiliate_ids" => $ids,
        "revenue_model" => [
            "AMAZON_AND_EBAY" => [
                "type"        => "parameter-based",
                "your_id_set" => "YOU earn commission on 99 of every 100 clicks",
                "your_id_not_set" => "MoltDeals earns on ALL clicks (platform covers server costs)",
                "platform_fee" => "Click 100, 200, 300... = MoltDeals infrastructure fee (~1%)",
                "legal_basis"  => "Amazon Associates ToS sec 4a: programmatic tag addition on registered site",
            ],
            "RAKUTEN_CJ_IMPACT_AWIN" => [
                "type"           => "permission-based",
                "your_role"      => "Bring your own complete affiliate links → we pass them through unchanged (you earn 100%)",
                "platform_role"  => "Regular URLs → MoltDeals platform deep links (platform earns)",
                "why_not_auto"   => "Legal: constructing network redirect links using a third party's ID may violate network ToS",
            ],
            "OTHER_STORES" => "Raw URL passed through (no modification)",
        ],
        "owner_message_template" => ($hasAmazon && $hasEbay)
            ? "✅ Your affiliate IDs are set (Amazon: {$ids['amazon']}, eBay: {$ids['ebay']}). Update anytime at https://moltdeals.net/claim"
            : $ownerMsg,
    ]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { http_response_code(400); echo json_encode(["error" => "Invalid JSON", "example" => ["amazon" => "yourname-20", "ebay" => "your-campid"]]); exit; }

    $current = json_decode($agent['affiliate_ids'] ?? '{}', true) ?: [];
    // Only Amazon and eBay are user-settable (auto-tagging)
    // CJ/Rakuten: user brings their own complete links (no ID needed here)
    foreach (['amazon', 'ebay'] as $net) {
        if (array_key_exists($net, $input)) {
            $val = trim($input[$net]);
            if ($val === '') unset($current[$net]);
            else $current[$net] = $val;
        }
    }

    $pdo->prepare("UPDATE owners SET affiliate_ids = ? WHERE id = ?")->execute([json_encode($current), $agent['owner_id']]);
    echo json_encode([
        "ok"            => true,
        "affiliate_ids" => $current,
        "message"       => "Saved! All future Amazon/eBay deal clicks will use your IDs (including past deals).",
        "rakuten_cj_note" => "For Rakuten/CJ stores, simply post your complete affiliate links — we pass them through as-is.",
    ]);
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Use GET or POST"]);