$(document).ready(function() {
    let debounceTimer;
    const debounceMs = 700; // Should be fetched from .env, but hardcoded for now

    const autoSendToggle = $('#autoSendToggle');
    const promptInput = $('#prompt-input');
    const responseArea = $('#response-area');

    // Restore toggle state from localStorage
    if (localStorage.getItem('autoSend') === 'false') {
        autoSendToggle.prop('checked', false);
    }

    autoSendToggle.on('change', function() {
        localStorage.setItem('autoSend', $(this).prop('checked'));
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

        // Add a loading indicator
        responseArea.html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');

        $.ajax({
            url: 'api/ask.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ prompt: prompt }),
            success: function(data) {
                if (data.success) {
                    responseArea.html(`<div class="alert alert-secondary">${data.response}</div>`);
                } else {
                    responseArea.html(`<div class="alert alert-danger">Error: ${data.error}</div>`);
                }
            },
            error: function() {
                responseArea.html('<div class="alert alert-danger">An unknown error occurred.</div>');
            },
            complete: function() {
                promptInput.prop('disabled', false);
                promptInput.focus();
            }
        });
    }
});
