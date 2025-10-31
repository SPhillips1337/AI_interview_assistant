$(document).ready(function() {
    const historyTimeline = $('#history-timeline');
    const topicArea = $('#topic-area');
    const perplexicaModal = new bootstrap.Modal($('#perplexicaModal'));
    const perplexicaModalBody = $('#perplexicaModal .modal-body');
    let lastTimestamp = 0;

    function fetchHistory() {
        $.ajax({
            url: 'api/history.php',
            type: 'GET',
            dataType: 'json',
            success: function(history) {
                if (history.length > 0) {
                    const latestTimestamp = history[history.length - 1].timestamp;
                    if (latestTimestamp > lastTimestamp) {
                        renderHistory(history);
                        lastTimestamp = latestTimestamp;
                    }
                }
            },
            error: function() {
                console.error('Could not fetch history.');
            }
        });
    }

    function renderHistory(history) {
        historyTimeline.empty();
        history.slice().reverse().forEach(item => {
            const perplexicaButton = item.perplexica_enabled ? `<button class="btn btn-sm btn-primary float-end dig-deeper" data-prompt="${escape(item.response)}">Dig Deeper</button>` : '';
            const historyItem = `
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>You:</strong> ${item.prompt}
                    </div>
                    <div class="card-body">
                        <p class="card-text"><strong>Assistant:</strong> ${item.response}</p>
                        ${perplexicaButton}
                    </div>
                </div>`;
            historyTimeline.append(historyItem);
        });
    }

    function fetchTopic() {
        $.ajax({
            url: 'api/topic.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                topicArea.html(`<div class="card"><div class="card-body"><h4>Current Topic</h4><p class="card-text">${data.topic}</p></div></div>`);
            },
            error: function() {
                console.error('Could not fetch topic.');
            }
        });
    }

    historyTimeline.on('click', '.dig-deeper', function() {
        const prompt = unescape($(this).data('prompt'));
        perplexicaModalBody.html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        perplexicaModal.show();

        $.ajax({
            url: 'api/perplexica.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ prompt: prompt }),
            success: function(data) {
                if (data.success) {
                    perplexicaModalBody.html(`<p>${data.response}</p>`);
                } else {
                    perplexicaModalBody.html(`<div class="alert alert-danger">Error: ${data.error}</div>`);
                }
            },
            error: function() {
                perplexicaModalBody.html('<div class="alert alert-danger">An unknown error occurred.</div>');
            }
        });
    });

    // Fetch initial data on page load
    fetchHistory();
    fetchTopic();

    // Poll for new entries
    setInterval(fetchHistory, 5000);
    setInterval(fetchTopic, 5000); // Poll every 5 seconds
});