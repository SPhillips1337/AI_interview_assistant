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

$historyFile = __DIR__ . '/../history.json';
if (!file_exists($historyFile)) {
    echo json_encode(['topic' => 'No history yet.']);
    exit;
}

$history = json_decode(file_get_contents($historyFile), true);

if (empty($history)) {
    echo json_encode(['topic' => 'No history yet.']);
    exit;
}

// Consolidate the conversation history into a single string
$conversationText = "";
foreach ($history as $entry) {
    $conversationText .= "User: " . $entry['prompt'] . "\n";
    $conversationText .= "Assistant: " . $entry['response'] . "\n";
}

$prompt = "Review the following conversation and identify the main topic. If it is just general chit-chat, respond with 'General Conversation'. Otherwise, respond with only the name of the topic (e.g., 'Bitcoin', 'History of Rome').\n\nConversation:\n" . $conversationText;

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

    if ($error || $httpcode >= 400) {
        echo json_encode(['topic' => 'Error determining topic.']);
        exit;
    }

    $responseData = json_decode($response, true);
    $topic = isset($responseData['response']) ? trim($responseData['response']) : 'Could not determine topic.';
    echo json_encode(['topic' => $topic]);

} else if ($provider === 'openai') {
    $apiKey = isset($config['OPENAI_API_KEY']) ? $config['OPENAI_API_KEY'] : '';
    $baseUrl = isset($config['OPENAI_BASE_URL']) ? $config['OPENAI_BASE_URL'] : 'https://api.openai.com';
    $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 30;

    if (empty($apiKey)) {
        echo json_encode(['topic' => 'OpenAI API key not set.']);
        exit;
    }

    $data = [
        'model' => 'gpt-3.5-turbo',
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

    if ($error || $httpcode >= 400) {
        echo json_encode(['topic' => 'Error determining topic.']);
        exit;
    }

    $responseData = json_decode($response, true);
    $topic = isset($responseData['choices'][0]['message']['content']) ? trim($responseData['choices'][0]['message']['content']) : 'Could not determine topic.';
    echo json_encode(['topic' => $topic]);
} else {
    echo json_encode(['topic' => 'Invalid provider.']);
}