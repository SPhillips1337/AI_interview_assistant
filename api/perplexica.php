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

$system_prompt = "You are a Specialized Researcher... Your sole purpose is to gather, verify, and synthesize information for the user.";

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
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Longer timeout for potentially longer searches

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
$final_response = isset($responseData['message']) ? $responseData['message'] : 'No parsable response from Perplexica.';

echo json_encode(['success' => true, 'response' => $final_response]);