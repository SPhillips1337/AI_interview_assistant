<?php
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header('Content-Type: application/json');
}

// Simple .env file parser
if (!function_exists('parseEnv')) {
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
}

function getTopicsFromText($text, $config) {
    $prompt = "Extract the key topics or keywords from the following text. Respond with only a comma-separated list (e.g., topic1, topic2, topic3).\n\nText:\n" . $text;
    $provider = isset($config['PROVIDER']) ? $config['PROVIDER'] : 'ollama';

    $topics_str = '';

    if ($provider === 'ollama') {
        $ollamaUrl = isset($config['OLLAMA_URL']) ? $config['OLLAMA_URL'] : 'http://127.0.0.1:11434';
        $ollamaModel = isset($config['OLLAMA_MODEL']) ? $config['OLLAMA_MODEL'] : 'llama2';
        $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 15;

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

        if (!$error && $httpcode < 400) {
            $responseData = json_decode($response, true);
            $topics_str = isset($responseData['response']) ? trim($responseData['response']) : '';
        }
    } else if ($provider === 'openai') {
        $apiKey = isset($config['OPENAI_API_KEY']) ? $config['OPENAI_API_KEY'] : '';
        $baseUrl = isset($config['OPENAI_BASE_URL']) ? $config['OPENAI_BASE_URL'] : 'https://api.openai.com';
        $timeout = isset($config['TIMEOUT_SECONDS']) ? (int)$config['TIMEOUT_SECONDS'] : 15;

        if (!empty($apiKey)) {
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

            if (!$error && $httpcode < 400) {
                $responseData = json_decode($response, true);
                $topics_str = isset($responseData['choices'][0]['message']['content']) ? trim($responseData['choices'][0]['message']['content']) : '';
            }
        }
    }
    return $topics_str;
}

function getTopicsFromHistory($history, $config) {
    $topics_data = [];
    $history_count = count($history);

    foreach ($history as $index => $entry) {
        $conversationText = "User: " . $entry['prompt'] . "\nAssistant: " . $entry['response'];
        $topics_str = getTopicsFromText($conversationText, $config);

        if (!empty($topics_str)) {
            $topics = array_map('trim', explode(',', $topics_str));
            $recency_score = $history_count - $index;

            foreach ($topics as $topic) {
                if (empty($topic)) continue;

                if (!isset($topics_data[$topic])) {
                    $topics_data[$topic] = ['frequency' => 0, 'recency' => 0];
                }
                $topics_data[$topic]['frequency']++;
                $topics_data[$topic]['recency'] += $recency_score;
            }
        }
    }

    $wordcloud_data = [];
    foreach ($topics_data as $topic => $data) {
        $weight = ($data['frequency'] * 1.5) + ($data['recency'] * 1.0);
        $wordcloud_data[] = [$topic, $weight];
    }

    usort($wordcloud_data, function($a, $b) {
        return $b[1] - $a[1];
    });

    return $wordcloud_data;
}

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    $config = parseEnv(__DIR__ . '/../.env');

    $historyFile = __DIR__ . '/../history.json';
    if (!file_exists($historyFile)) {
        echo json_encode([]);
        exit;
    }

    $history = json_decode(file_get_contents($historyFile), true);

    if (empty($history)) {
        echo json_encode([]);
        exit;
    }

    $wordcloud_data = getTopicsFromHistory($history, $config);

    echo json_encode($wordcloud_data);
}
