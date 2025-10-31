$(document).ready(function() {
    let debounceTimer;
    const debounceMs = 700;
    let conversationHistory = [];

    const autoSendToggle = $('#autoSendToggle');
    const promptInput = $('#prompt-input');
    const responseArea = $('#response-area');
    const loadingIndicator = $('#loading-indicator');

    // Restore toggle state from localStorage
    if (localStorage.getItem('autoSend') === 'false') {
        autoSendToggle.prop('checked', false);
    }

    autoSendToggle.on('change', function() {
        const isChecked = $(this).prop('checked');
        localStorage.setItem('autoSend', isChecked);
        if (isChecked) {
            promptInput.val('').focus();
        }
    });

    promptInput.on('input', function() {
        if (!autoSendToggle.prop('checked')) {
            return;
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const prompt = promptInput.val().trim();
            if (prompt) {
                sendPrompt(prompt);
            }
        }, debounceMs);
    });

    function sendPrompt(prompt) {
        promptInput.val('');
        promptInput.prop('disabled', true);
        loadingIndicator.show();

        // Add user's prompt to the display
        const userPromptHtml = `<div class="card mb-3"><div class="card-header"><strong>You:</strong> ${prompt}</div></div>`;
        responseArea.prepend(userPromptHtml);

        conversationHistory.push({ role: 'user', content: prompt });

        $.ajax({
            url: 'api/ask.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ conversation: conversationHistory }),
            success: function(data) {
                if (data.success) {
                    const assistantResponseHtml = `<div class="card mb-3"><div class="card-body"><p class="card-text"><strong>Assistant:</strong> ${data.response}</p></div></div>`;
                    responseArea.prepend(assistantResponseHtml);
                    conversationHistory.push({ role: 'assistant', content: data.response });
                } else {
                    const errorHtml = `<div class="alert alert-danger">Error: ${data.error}</div>`;
                    responseArea.prepend(errorHtml);
                    conversationHistory.pop(); // Remove the failed user message
                }
            },
            error: function() {
                const errorHtml = `<div class="alert alert-danger">An unknown error occurred.</div>`;
                responseArea.prepend(errorHtml);
                conversationHistory.pop();
            },
            complete: function() {
                loadingIndicator.hide();
                promptInput.prop('disabled', false);
                promptInput.focus();
            }
        });
    }
});
