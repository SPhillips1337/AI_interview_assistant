<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="container mt-5">
        <h1 class="text-center mb-4">Conversation Details</h1>

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="true">History</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="topic-tab" data-bs-toggle="tab" data-bs-target="#topic" type="button" role="tab" aria-controls="topic" aria-selected="false">Current Topic</button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="history" role="tabpanel" aria-labelledby="history-tab">
                <div id="history-timeline" class="mt-4"></div>
            </div>
            <div class="tab-pane fade" id="topic" role="tabpanel" aria-labelledby="topic-tab">
                <div id="topic-area" class="mt-4">
                    <!-- Topic will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Perplexica Modal -->
    <div class="modal fade" id="perplexicaModal" tabindex="-1" aria-labelledby="perplexicaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="perplexicaModalLabel">Dig Deeper with Perplexica</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Perplexica response will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/history.js"></script>
</body>
</html>