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

function callLlm($prompt, $config) {
    $provider = isset($config['PROVIDER']) ? $config['PROVIDER'] : 'ollama';

    if ($provider === 'ollama') {
        $ollamaUrl = isset($config['OLLAMA_URL']) ? $config['OLLAMA_URL'] : 'http://127.0.0.1:11434';
        $ollamaModel = isset($config['OLLAMA_MODEL']) ? $config['OLLAMA_MODEL'] : 'llama2';
        $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 30;

        $data = ['model' => $ollamaModel, 'prompt' => $prompt, 'stream' => false];

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
            return null;
        }

        $responseData = json_decode($response, true);
        return isset($responseData['response']) ? trim($responseData['response']) : null;

    } else if ($provider === 'openai') {
        $apiKey = isset($config['OPENAI_API_KEY']) ? $config['OPENAI_API_KEY'] : '';
        $baseUrl = isset($config['OPENAI_BASE_URL']) ? $config['OPENAI_BASE_URL'] : 'https://api.openai.com';
        $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 30;

        if (empty($apiKey)) {
            return null;
        }

        $data = ['model' => 'gpt-3.5-turbo', 'messages' => [['role' => 'user', 'content' => $prompt]]];

        $ch = curl_init($baseUrl . '/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpcode >= 400) {
            return null;
        }

        $responseData = json_decode($response, true);
        return isset($responseData['choices'][0]['message']['content']) ? trim($responseData['choices'][0]['message']['content']) : null;
    }
    return null;
}

$config = parseEnv(__DIR__ . '/../.env');

$historyFile = __DIR__ . '/../history.json';
if (!file_exists($historyFile)) {
    echo json_encode(['topic_full' => 'No history yet.', 'topic_short' => 'N/A']);
    exit;
}

$history = json_decode(file_get_contents($historyFile), true);
if (empty($history)) {
    echo json_encode(['topic_full' => 'No history yet.', 'topic_short' => 'N/A']);
    exit;
}

// Step 1: Get the full topic summary
$conversationText = "";
foreach ($history as $entry) {
    $conversationText .= "User: " . $entry['prompt'] . "\n";
    $conversationText .= "Assistant: " . $entry['response'] . "\n";
}
$prompt_full = "Review the following conversation and identify the main topic. If it is just general chit-chat, respond with 'General Conversation'. Otherwise, respond with only the name of the topic (e.g., 'Bitcoin', 'History of Rome').\n\nConversation:\n" . $conversationText;

$topic_full = callLlm($prompt_full, $config);

if ($topic_full === null) {
    echo json_encode(['topic_full' => 'Error determining topic.', 'topic_short' => 'Error']);
    exit;
}

// Step 2: Get the short topic name from the full summary
$prompt_short = "Summarize the following text into a 2-3 word topic name. Respond with only the topic name.\n\nText:\n" . $topic_full;
$topic_short = callLlm($prompt_short, $config);

if ($topic_short === null) {
    // If we can't get a short topic, just use a truncated version of the full one as a fallback
    $topic_short = substr($topic_full, 0, 25) . (strlen($topic_full) > 25 ? '...' : '');
}

// Step 3: Return both
echo json_encode(['topic_full' => $topic_full, 'topic_short' => $topic_short]);
