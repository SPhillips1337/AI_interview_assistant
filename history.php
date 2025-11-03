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

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Interview Assistant</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="history.php">History</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-center">Conversation Details</h1>
            <a href="api/generate_report.php" class="btn btn-primary">Download Report</a>
        </div>

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
                    <div class="row">
                        <div class="col-lg-7">
                            <canvas id="wordcloud-canvas" style="width: 100%; height: 400px;"></canvas>
                        </div>
                        <div class="col-lg-5">
                            <div id="current-topic-display">
                                <!-- Single topic will be loaded here -->
                            </div>
                        </div>
                    </div>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/wordcloud2.js/1.1.1/wordcloud2.min.js"></script>
    <script src="assets/js/history.js"></script>
</body>
</html>