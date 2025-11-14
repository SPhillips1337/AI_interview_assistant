<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$whisperProvider = $_ENV['WHISPER_PROVIDER'] ?? 'openai';

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No audio file provided']);
    exit;
}

$audioFile = $_FILES['file'];

try {
    if ($whisperProvider === 'openai') {
        $transcription = transcribeWithOpenAI($audioFile);
    } else {
        $transcription = transcribeWithLocal($audioFile);
    }
    
    echo json_encode(['success' => true, 'text' => $transcription]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function transcribeWithOpenAI($audioFile) {
    $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
    $baseUrl = $_ENV['OPENAI_BASE_URL'] ?? 'https://api.openai.com';
    $model = $_ENV['WHISPER_MODEL'] ?? 'whisper-1';
    
    if (empty($apiKey)) {
        throw new Exception('OpenAI API key not configured');
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/audio/transcriptions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
    ]);
    
    $postFields = [
        'file' => new CURLFile($audioFile['tmp_name'], $audioFile['type'], $audioFile['name']),
        'model' => $model
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('OpenAI API error: ' . $response);
    }
    
    $data = json_decode($response, true);
    return $data['text'] ?? '';
}

function transcribeWithLocal($audioFile) {
    $whisperPath = $_ENV['WHISPER_LOCAL_PATH'] ?? '/usr/local/bin/whisper';
    
    if (!file_exists($whisperPath)) {
        throw new Exception('Local Whisper not found at: ' . $whisperPath);
    }
    
    $tempFile = tempnam(sys_get_temp_dir(), 'whisper_');
    move_uploaded_file($audioFile['tmp_name'], $tempFile);
    
    $command = escapeshellcmd($whisperPath) . ' --output-format txt --output-dir /tmp ' . escapeshellarg($tempFile);
    $output = shell_exec($command . ' 2>&1');
    
    $txtFile = '/tmp/' . basename($tempFile) . '.txt';
    if (file_exists($txtFile)) {
        $transcription = file_get_contents($txtFile);
        unlink($txtFile);
        unlink($tempFile);
        return trim($transcription);
    }
    
    unlink($tempFile);
    throw new Exception('Transcription failed: ' . $output);
}
?>
