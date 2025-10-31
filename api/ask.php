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

$provider = isset($config['PROVIDER']) ? $config['PROVIDER'] : 'ollama';

if ($provider === 'ollama') {
    $ollamaUrl = isset($config['OLLAMA_URL']) ? $config['OLLAMA_URL'] : 'http://127.0.0.1:11434';
    $ollamaModel = isset($config['OLLAMA_MODEL']) ? $config['OLLAMA_MODEL'] : 'llama2';
    $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 30;

    $data = [
        'model' => $ollamaModel,
        'prompt' => $prompt,
        'stream' => false
    ];

    $ch = curl_init($ollamaUrl . '/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

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
    $llmResponse = isset($responseData['response']) ? $responseData['response'] : 'No response from LLM.';

    // Save to history
    $historyFile = __DIR__ . '/../history.json';
    $history = json_decode(file_get_contents($historyFile), true);
    $history[] = [
        'prompt' => $prompt,
        'response' => $llmResponse,
        'timestamp' => time()
    ];
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT), LOCK_EX);

    echo json_encode(['success' => true, 'response' => $llmResponse]);

} else if ($provider === 'openai') {
    $apiKey = isset($config['OPENAI_API_KEY']) ? $config['OPENAI_API_KEY'] : '';
    $baseUrl = isset($config['OPENAI_BASE_URL']) ? $config['OPENAI_BASE_URL'] : 'https://api.openai.com';
    $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 30;

    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'OPENAI_API_KEY is not set in .env file.']);
        exit;
    }

    $data = [
        'model' => 'gpt-3.5-turbo', // Or another model from config
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
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
    $llmResponse = isset($responseData['choices'][0]['message']['content']) ? $responseData['choices'][0]['message']['content'] : 'No response from OpenAI.';

    // Save to history
    $historyFile = __DIR__ . '/../history.json';
    $history = json_decode(file_get_contents($historyFile), true);
    $history[] = [
        'prompt' => $prompt,
        'response' => $llmResponse,
        'timestamp' => time()
    ];
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT), LOCK_EX);

    echo json_encode(['success' => true, 'response' => $llmResponse]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid provider specified in .env file.']);
}
