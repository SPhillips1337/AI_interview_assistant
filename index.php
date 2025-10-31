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

    <div class="container mt-5">
        <h1 class="text-center mb-4">Interview Assistant</h1>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="autoSendToggle" checked>
            <label class="form-check-label" for="autoSendToggle">Auto-send on pause</label>
        </div>

        <div class="mb-3">
            <textarea class="form-control" id="prompt-input" rows="4" placeholder="Speak or type your thoughts here..."></textarea>
        </div>

        <div id="loading-indicator" class="mt-4" style="display: none;">
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>

        <div id="response-area" class="mt-4">
            <!-- LLM responses will be displayed here -->
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
