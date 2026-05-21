<?php
$env = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$config = [];
foreach ($env as $line) {
    if ($line[0] !== '#' && strpos($line, '=') !== false) {
        list($k, $v) = explode('=', $line, 2);
        $config[trim($k)] = trim($v);
    }
}
try {
    $pdo = new PDO('mysql:host='.$config['DB_HOST'].';dbname='.$config['DB_DATABASE'], $config['DB_USERNAME'], $config['DB_PASSWORD']);
    $stmt = $pdo->query('SELECT api_key FROM agents ORDER BY id DESC LIMIT 1');
    $key = trim($stmt->fetchColumn());
    file_put_contents(__DIR__ . '/key_out.txt', $key);
    echo "Key written to key_out.txt: $key";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
