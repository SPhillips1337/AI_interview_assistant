<?php
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

function callLlmForSummary($prompt, $config) {
    $provider = isset($config['PROVIDER']) ? $config['PROVIDER'] : 'ollama';
    $ch = null;
    $data = [];

    if ($provider === 'ollama') {
        $ollamaUrl = isset($config['OLLAMA_URL']) ? $config['OLLAMA_URL'] : 'http://127.0.0.1:11434';
        $ollamaModel = isset($config['OLLAMA_MODEL']) ? $config['OLLAMA_MODEL'] : 'llama2';
        $data = ['model' => $ollamaModel, 'prompt' => $prompt, 'stream' => false];
        $ch = curl_init($ollamaUrl . '/api/generate');
    } else if ($provider === 'openai') {
        $apiKey = isset($config['OPENAI_API_KEY']) ? $config['OPENAI_API_KEY'] : '';
        if (empty($apiKey)) return null;
        $baseUrl = isset($config['OPENAI_BASE_URL']) ? $config['OPENAI_BASE_URL'] : 'https://api.openai.com';
        $data = ['model' => 'gpt-3.5-turbo', 'messages' => [['role' => 'user', 'content' => $prompt]]];
        $ch = curl_init($baseUrl . '/v1/chat/completions');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
    }

    if (!$ch) return null;

    $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 60;
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpcode >= 400) {
        return null;
    }

    $responseData = json_decode($response, true);
    if ($provider === 'ollama') {
        return $responseData['response'] ?? null;
    } else if ($provider === 'openai') {
        return $responseData['choices'][0]['message']['content'] ?? null;
    }

    return null;
}

require_once 'topics.php'; // Reuse the topics logic

$config = parseEnv(__DIR__ . '/../.env');

// 1. Get History
$historyFile = __DIR__ . '/../history.json';
$history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];

// 2. Get Topics
$topics_data = getTopicsFromHistory($history, $config);

// 3. Generate AI Summary
$conversationText = "";
foreach ($history as $entry) {
    $conversationText .= "User: " . $entry['prompt'] . "\n";
    $conversationText .= "Assistant: " . $entry['response'] . "\n";
}
$summary_prompt = "Provide a concise, one or two paragraph summary of the following conversation.\n\n" . $conversationText;
$summary = callLlmForSummary($summary_prompt, $config) ?? 'Could not generate summary.';

// 4. Build HTML Report
$html = "<!DOCTYPE html><html lang='en'>\n<head>\n    <meta charset='UTF-8'>\n    <title>Conversation Report</title>\n    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>\n</head>\n<body class='container mt-5'>";
$html .= "<h1>Conversation Summary Report</h1>";

$html .= "<h2>Summary of Topics Discussed</h2><p>" . nl2br(htmlspecialchars($summary)) . "</p>";

$html .= "<h2>Full Transcript</h2>";
foreach ($history as $entry) {
    $html .= "<div class='card mb-3'><div class='card-header'><strong>You:</strong> " . htmlspecialchars($entry['prompt']) . "</div><div class='card-body'><strong>Assistant:</strong> " . nl2br(htmlspecialchars($entry['response'])) . "</div></div>";
}

$html .= "<h2>Further Resources</h2><p>Based on the topics discussed, here are some links for further research:</p><ul>";
// Sort topics by weight (descending)
usort($topics_data, function($a, $b) {
    return $b[1] - $a[1];
});
foreach ($topics_data as $topic) {
    $html .= "<li>" . htmlspecialchars($topic[0]) . " - <a href='https://www.google.com/search?q=" . urlencode($topic[0]) . "' target='_blank'>Google Search</a></li>";
}
$html .= "</ul>";

$html .= "</body></html>";

// 5. Send Headers and Output
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename=\"conversation_report.html\"');
echo $html;

