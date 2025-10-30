<?php
header('Content-Type: application/json');

// Simple .env file parser
function parseEnv($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $config[trim($name)] = trim($value);
    }
    return $config;
}

$config = parseEnv(__DIR__ . '/../.env');
$perplexicaEnabled = !empty($config['PERPLEXICA_URL']);

$historyFile = __DIR__ . '/../history.json';

if (!file_exists($historyFile)) {
    echo json_encode([]);
    exit;
}

$history = json_decode(file_get_contents($historyFile), true);

if (is_array($history)) {
    foreach ($history as &$item) {
        $item['perplexica_enabled'] = $perplexicaEnabled;
    }
}

// To prevent caching of the history file
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

echo json_encode($history);