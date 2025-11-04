<?php
// Load environment variables
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Assistant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Interview Assistant</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">History</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-5">
        <div class="row">
            <!-- Topic Navigation Column -->
            <div class="col-lg-3 col-md-4 d-none d-md-block">
                <div class="sticky-top" style="top: 20px;">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Topic Navigation</h6>
                        </div>
                        <div class="card-body p-0">
                            <div id="topic-nav" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                                <div class="text-muted text-center p-3">
                                    <small>Topics will appear here as you chat</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Column -->
            <div class="col-lg-9 col-md-8 col-12">
                <h1 class="text-center mb-4">Interview Assistant</h1>

                <div class="row">
                    <!-- Input Column -->
                    <div class="col-lg-6 col-12">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="autoSendToggle" checked>
                            <label class="form-check-label" for="autoSendToggle">Auto-send on pause</label>
                        </div>

                        <div class="mb-3">
                            <textarea class="form-control" id="prompt-input" rows="4" placeholder="Speak or type your thoughts here..."></textarea>
                        </div>

                        <div id="queue-indicator" class="mb-3" style="display: none;">
                            <div class="alert alert-info">
                                <span id="queue-count">0</span> query(s) queued
                            </div>
                        </div>

                        <div id="loading-indicator" class="mt-4" style="display: none;">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Response Column -->
                    <div class="col-lg-6 col-12">
                        <div id="quick-response-area" class="mt-4 mt-lg-0">
                            <!-- Quick response will be displayed here -->
                        </div>
                    </div>
                </div>

                <div id="response-area" class="mt-4">
                    <!-- LLM responses will be displayed here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        window.appConfig = {
            debounceMs: <?php echo $_ENV['DEBOUNCE_MS'] ?? 700; ?>
        };
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
