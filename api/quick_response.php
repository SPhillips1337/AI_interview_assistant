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
$conversation = isset($requestBody['conversation']) ? $requestBody['conversation'] : [];

if (empty($conversation)) {
    echo json_encode(['success' => false, 'error' => 'Conversation is empty.']);
    exit;
}

$lastMessage = end($conversation);
$prompt = isset($lastMessage['content']) ? $lastMessage['content'] : '';

if (empty($prompt)) {
    echo json_encode(['success' => false, 'error' => 'Prompt is empty.']);
    exit;
}

$provider = isset($config['PROVIDER']) ? $config['PROVIDER'] : 'ollama';

$quickResponsePrompt = "Based on the conversation context, provide a brief one-paragraph summary about the main topic being discussed. Focus on the latest question while considering the overall conversation context.";

// Add conversation context for quick response
$quickMessages = $conversation;
$quickMessages[] = ['role' => 'user', 'content' => $quickResponsePrompt];

$response = null;

if ($provider === 'ollama') {
    $ollamaUrl = isset($config['OLLAMA_URL']) ? $config['OLLAMA_URL'] : 'http://127.0.0.1:11434';
    // Use a lightweight model for the quick response
    $ollamaModel = isset($config['OLLAMA_QUICK_MODEL']) ? $config['OLLAMA_QUICK_MODEL'] : 'phi3';
    $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 10;

    $data = [
        'model' => $ollamaModel,
        'messages' => $quickMessages,
        'stream' => false
    ];

    $ch = curl_init($ollamaUrl . '/api/chat');
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
        $llmResponse = isset($responseData['message']['content']) ? $responseData['message']['content'] : 'No response from LLM.';
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
            'messages' => $quickMessages,
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
