$(document).ready(function() {
    const historyTimeline = $('#history-timeline');
    const perplexicaModal = new bootstrap.Modal($('#perplexicaModal'));
    const perplexicaModalBody = $('#perplexicaModal .modal-body');
    const wordCloudCanvas = $('#wordcloud-canvas')[0];
    const currentTopicDisplay = $('#current-topic-display');
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
                        fetchAndDrawWordCloud();
                        updateTopicDisplay(); // Single function for all topic updates
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

    function fetchAndDrawWordCloud() {
        $.ajax({
            url: 'api/topics.php',
            type: 'GET',
            dataType: 'json',
            success: function(topics) {
                if (topics.length > 0) {
                    WordCloud(wordCloudCanvas, {
                        list: topics,
                        gridSize: 10,
                        weightFactor: 5,
                        minSize: 10, // Minimum font size
                        fontFamily: 'Arial, sans-serif',
                        fontWeight: 'bold',
                        color: 'random-dark',
                        backgroundColor: '#f0f0f0',
                        click: function(item, dimension, event) {
                            const topic = item[0];
                            perplexicaModalBody.html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
                            perplexicaModal.show();

                            $.ajax({
                                url: 'api/perplexica.php',
                                type: 'POST',
                                contentType: 'application/json',
                                data: JSON.stringify({ prompt: `Provide a detailed explanation of the topic: ${topic}` }),
                                success: function(data) {
                                    if (data.success) {
                                        perplexicaModalBody.html(`<h4>${topic}</h4>${data.response}`);
                                    } else {
                                        perplexicaModalBody.html(`<div class="alert alert-danger">Error: ${data.error}</div>`);
                                    }
                                },
                                error: function() {
                                    perplexicaModalBody.html('<div class="alert alert-danger">An unknown error occurred.</div>');
                                }
                            });
                        }
                    });
                }
            },
            error: function() {
                console.error('Could not fetch topics for word cloud.');
            }
        });
    }

    function updateTopicDisplay() {
        $.ajax({
            url: 'api/topic.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                const topic_full = data.topic_full;
                const topic_short = data.topic_short;

                // Ensure the card structure is there first, then update its contents
                currentTopicDisplay.html(`<div class="card"><div class="card-body"><h4 class="card-title"></h4><p class="card-text"></p></div></div>`);
                
                $('#current-topic-display .card-title').text(`Current Topic - ${topic_short}`);
                $('#current-topic-display .card-text').text(topic_full);
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
                    perplexicaModalBody.html(data.response);
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
    updateTopicDisplay();

    // Set up polling
    setInterval(fetchHistory, 5000); // This will trigger other updates as needed
});