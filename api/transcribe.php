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

$whisperProvider = $_ENV['WHISPER_PROVIDER'] ?? 'local';

// Check for audio file in common field names
$audioFile = null;
if (isset($_FILES['file'])) {
    $audioFile = $_FILES['file'];
} elseif (isset($_FILES['audio'])) {
    $audioFile = $_FILES['audio'];
} elseif (!empty($_FILES)) {
    $audioFile = reset($_FILES); // Get first uploaded file
}

if (!$audioFile) {
    http_response_code(400);
    echo json_encode(['error' => 'No audio file provided', 'debug' => array_keys($_FILES)]);
    exit;
}

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
    $whisperUrl = $_ENV['WHISPER_LOCAL_URL'] ?? 'http://192.168.5.227:8123';
    
    if ($audioFile['error'] !== UPLOAD_ERR_OK || $audioFile['size'] == 0) {
        throw new Exception('File upload error: ' . $audioFile['error'] . ', size: ' . $audioFile['size']);
    }
    
    // Check for WebM format and convert to WAV if needed
    $fileHeader = file_get_contents($audioFile['tmp_name'], false, null, 0, 4);
    if (bin2hex($fileHeader) === '1a45dfa3') {
        // WebM detected - try to convert to WAV using ffmpeg
        $wavFile = tempnam(sys_get_temp_dir(), 'whisper_') . '.wav';
        $cmd = "ffmpeg -i " . escapeshellarg($audioFile['tmp_name']) . " -ar 16000 -ac 1 " . escapeshellarg($wavFile) . " 2>/dev/null";
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($wavFile)) {
            $audioFile['tmp_name'] = $wavFile;
            $audioFile['type'] = 'audio/wav';
            $audioFile['name'] = 'converted.wav';
        }
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $whisperUrl . '/transcribe?language=en');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    
    $postFields = [
        'file' => new CURLFile($audioFile['tmp_name'], 'audio/wav', 'recording.wav')
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Clean up temp file if created
    if (isset($wavFile) && file_exists($wavFile)) {
        unlink($wavFile);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('Whisper API error (HTTP ' . $httpCode . '): ' . $response);
    }
    
    $data = json_decode($response, true);
    $text = isset($data['text']) ? trim($data['text']) : '';
    
    // Remove timestamp markers like [00:00:00.000 --> 00:00:02.000]
    $text = preg_replace('/\[[\d:.,\s\-\>]+\]\s*/', '', $text);
    
    // Filter out blank audio indicators
    if (empty($text) || $text === '[BLANK_AUDIO]' || preg_match('/^\[.*BLANK.*\]$/i', $text)) {
        throw new Exception('No speech detected');
    }
    
    $text = trim($text);
    
    if (empty($text)) {
        throw new Exception('No transcription result');
    }
    
    return $text;
}
?>
