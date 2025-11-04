<?php
header('Content-Type: application/json');

// Simple .env file parser
function parseEnv($filePath) {
    if (!file_exists($filePath)) return [];
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
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

$provider = isset($config['PROVIDER']) ? $config['PROVIDER'] : 'ollama';

$quickResponsePrompt = "Identify the main topic of the following user query and provide a single, short paragraph of information about it. User Query: " . $prompt;

$response = null;

if ($provider === 'ollama') {
    $ollamaUrl = isset($config['OLLAMA_URL']) ? $config['OLLAMA_URL'] : 'http://127.0.0.1:11434';
    // Use a lightweight model for the quick response
    $ollamaModel = isset($config['OLLAMA_QUICK_MODEL']) ? $config['OLLAMA_QUICK_MODEL'] : 'phi3';
    $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 10;

    $data = [
        'model' => $ollamaModel,
        'prompt' => $quickResponsePrompt,
        'stream' => false
    ];

    $ch = curl_init($ollamaUrl . '/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $apiResponse = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        $response = ['success' => false, 'error' => 'cURL Error: ' . $error];
    } else if ($httpcode >= 400) {
        $response = ['success' => false, 'error' => 'API Error: ' . $apiResponse, 'code' => $httpcode];
    } else {
        $responseData = json_decode($apiResponse, true);
        $llmResponse = isset($responseData['response']) ? $responseData['response'] : 'No response from LLM.';
        $response = ['success' => true, 'response' => $llmResponse];
    }
} else if ($provider === 'openai') {
    $apiKey = isset($config['OPENAI_API_KEY']) ? $config['OPENAI_API_KEY'] : '';
    $baseUrl = isset($config['OPENAI_BASE_URL']) ? $config['OPENAI_BASE_URL'] : 'https://api.openai.com';
    $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 10;

    if (empty($apiKey)) {
        $response = ['success' => false, 'error' => 'OPENAI_API_KEY is not set in .env file.'];
    } else {
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [['role' => 'user', 'content' => $quickResponsePrompt]],
            'max_tokens' => 150
        ];

        $ch = curl_init($baseUrl . '/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $apiResponse = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $response = ['success' => false, 'error' => 'cURL Error: ' . $error];
        } else if ($httpcode >= 400) {
            $response = ['success' => false, 'error' => 'API Error: ' . $apiResponse, 'code' => $httpcode];
        } else {
            $responseData = json_decode($apiResponse, true);
            $llmResponse = isset($responseData['choices'][0]['message']['content']) ? $responseData['choices'][0]['message']['content'] : 'No response from OpenAI.';
            $response = ['success' => true, 'response' => $llmResponse];
        }
    }
} else {
    $response = ['success' => false, 'error' => 'Invalid provider specified in .env file.'];
}

echo json_encode($response);
