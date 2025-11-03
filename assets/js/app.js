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

    async function sendPrompt(prompt) {
        promptInput.val('');
        promptInput.prop('disabled', true);
        loadingIndicator.show();

        const userPromptHtml = `<div class="card mb-3"><div class="card-header"><strong>You:</strong> ${prompt}</div></div>`;
        responseArea.prepend(userPromptHtml);

        conversationHistory.push({ role: 'user', content: prompt });

        const assistantResponseCard = $('<div class="card mb-3"><div class="card-body"><p class="card-text"><strong>Assistant:</strong> </p></div></div>');
        responseArea.prepend(assistantResponseCard);
        const assistantResponseContent = assistantResponseCard.find('.card-text');

        let fullResponse = '';

        try {
            const response = await fetch('api/ask.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ conversation: conversationHistory })
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            while (true) {
                const { done, value } = await reader.read();
                if (done) {
                    break;
                }

                const chunk = decoder.decode(value, { stream: true });
                const lines = chunk.split('\n');

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const jsonStr = line.substring(6);
                        if (jsonStr) {
                            try {
                                const data = JSON.parse(jsonStr);
                                if (data.success) {
                                    fullResponse += data.response;
                                    assistantResponseContent.html(`<strong>Assistant:</strong> ${fullResponse}`);
                                } else if (data.error) {
                                    const errorHtml = `<div class="alert alert-danger">Error: ${data.error}</div>`;
                                    responseArea.prepend(errorHtml);
                                    conversationHistory.pop();
                                }
                            } catch (e) {
                                // Ignore JSON parsing errors which can happen with partial chunks
                            }
                        }
                    }
                }
            }
        } catch (error) {
            const errorHtml = `<div class="alert alert-danger">An unknown error occurred.</div>`;
            responseArea.prepend(errorHtml);
            conversationHistory.pop();
        } finally {
            conversationHistory.push({ role: 'assistant', content: fullResponse });
            loadingIndicator.hide();
            promptInput.prop('disabled', false);
            promptInput.focus();
        }
    }
});
