<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

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
$conversation = isset($requestBody['conversation']) ? $requestBody['conversation'] : [];

if (empty($conversation)) {
    echo "data: {\"error\": \"Conversation is empty.\"}\n\n";
    flush();
    exit;
}

$lastMessage = end($conversation);
$prompt = isset($lastMessage['content']) ? $lastMessage['content'] : '';

$provider = isset($config['PROVIDER']) ? $config['PROVIDER'] : 'ollama';

$fullResponse = '';

$writeCallback = function($curl, $data) use (&$fullResponse) {
    global $provider;
    $lines = explode("\n", $data);

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;

        $decodedLine = json_decode($line, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // For OpenAI, the line starts with 'data: '
            if (strpos($line, 'data: ') === 0) {
                $jsonPart = substr($line, 6);
                if (trim($jsonPart) === '[DONE]') {
                    continue;
                }
                $decodedLine = json_decode($jsonPart, true);
            }
        }

        $content = '';
        if ($provider === 'ollama' && isset($decodedLine['message']['content'])) {
            $content = $decodedLine['message']['content'];
        } else if ($provider === 'openai' && isset($decodedLine['choices'][0]['delta']['content'])) {
            $content = $decodedLine['choices'][0]['delta']['content'];
        }

        if (!empty($content)) {
            $fullResponse .= $content;
            $response = ['success' => true, 'response' => $content];
            echo "data: " . json_encode($response) . "\n\n";
            flush();
        }
    }
    return strlen($data);
};

if ($provider === 'ollama') {
    $ollamaUrl = isset($config['OLLAMA_URL']) ? $config['OLLAMA_URL'] : 'http://127.0.0.1:11434';
    $ollamaModel = isset($config['OLLAMA_MODEL']) ? $config['OLLAMA_MODEL'] : 'llama2';
    $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 30;

    $data = [
        'model' => $ollamaModel,
        'messages' => $conversation,
        'stream' => true
    ];

    $ch = curl_init($ollamaUrl . '/api/chat');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, $writeCallback);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    curl_exec($ch);
    curl_close($ch);

} else if ($provider === 'openai') {
    $apiKey = isset($config['OPENAI_API_KEY']) ? $config['OPENAI_API_KEY'] : '';
    $baseUrl = isset($config['OPENAI_BASE_URL']) ? $config['OPENAI_BASE_URL'] : 'https://api.openai.com';
    $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 30;

    if (empty($apiKey)) {
        echo "data: {\"error\": \"OPENAI_API_KEY is not set in .env file.\"}\n\n";
        flush();
        exit;
    }

    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => $conversation,
        'stream' => true
    ];

    $ch = curl_init($baseUrl . '/v1/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, $writeCallback);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    curl_exec($ch);
    curl_close($ch);
} else {
    echo "data: {\"error\": \"Invalid provider specified in .env file.\"}\n\n";
    flush();
    exit;
}

// Save the full response to history
if (!empty($fullResponse)) {
    $historyFile = __DIR__ . '/../history.json';
    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true);
    } else {
        $history = [];
    }
    $history[] = [
        'prompt' => $prompt,
        'response' => $fullResponse,
        'timestamp' => time()
    ];
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT), LOCK_EX);
}
