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

function markdownToHtml($markdown) {
    // Headings
    $markdown = preg_replace('/^### (.*)/m', '<h3>$1</h3>', $markdown);
    $markdown = preg_replace('/^## (.*)/m', '<h2>$1</h2>', $markdown);
    $markdown = preg_replace('/^# (.*)/m', '<h1>$1</h1>', $markdown);

    // Bold
    $markdown = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $markdown);

    // Unordered lists
    $markdown = preg_replace('/^\* (.*)/m', '<ul><li>$1</li></ul>', $markdown);
    $markdown = preg_replace('/^- (.*)/m', '<ul><li>$1</li></ul>', $markdown);
    $markdown = str_replace('</ul><ul>', '', $markdown);

    // Paragraphs and line breaks
    $markdown = '<p>' . str_replace("\n\n", '</p><p>', $markdown) . '</p>';
    $markdown = preg_replace('/<p><(h[1-6])>/i', '<$1>', $markdown);
    $markdown = preg_replace('/<\/(h[1-6])><\/p>/i', '<\/$1>', $markdown);
    $markdown = preg_replace('/<p><ul>/i', '<ul>', $markdown);
    $markdown = preg_replace('/<\/ul><\/p>/i', '<\/ul>', $markdown);
    $markdown = str_replace("\n", '<br>', $markdown);
    $markdown = str_replace('<p></p>', '', $markdown);

    return $markdown;
}

$config = parseEnv(__DIR__ . '/../.env');

$requestBody = json_decode(file_get_contents('php://input'), true);
$prompt = isset($requestBody['prompt']) ? $requestBody['prompt'] : '';

if (empty($prompt)) {
    echo json_encode(['success' => false, 'error' => 'Prompt is empty.']);
    exit;
}

$perplexicaUrl = isset($config['PERPLEXICA_URL']) ? $config['PERPLEXICA_URL'] : '';

if (empty($perplexicaUrl)) {
    echo json_encode(['success' => false, 'error' => 'PERPLEXICA_URL is not set in .env file.']);
    exit;
}

$data = [
    'chatModel' => [
        'provider' => 'openai',
        'name' => 'gpt-4.1-mini'
    ],
    'embeddingModel' => [
        'provider' => 'openai',
        'name' => 'text-embedding-3-small'
    ],
    'optimizationMode' => 'speed',
    'focusMode' => 'webSearch',
    'query' => $prompt
];

$ch = curl_init($perplexicaUrl . '/api/search');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(['success' => false, 'error' => 'cURL Error: ' . $error]);
    exit;
}

if ($httpcode >= 400) {
    echo json_encode(['success' => false, 'error' => 'API Error: ' . $response, 'code' => $httpcode]);
    exit;
}

$responseData = json_decode($response, true);
$markdown_response = isset($responseData['message']) ? $responseData['message'] : 'No parsable response from Perplexica.';

$html_response = markdownToHtml($markdown_response);

echo json_encode(['success' => true, 'response' => $html_response]);