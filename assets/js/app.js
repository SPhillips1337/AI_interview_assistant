$(document).ready(function() {
    let debounceTimer;
    const debounceMs = 700;
    let conversationHistory = [];

    const autoSendToggle = $('#autoSendToggle');
    const promptInput = $('#prompt-input');
    const responseArea = $('#response-area');
    const quickResponseArea = $('#quick-response-area');
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

    function sendQuickResponse(prompt) {
        return new Promise((resolve, reject) => {
            console.log('Sending quick response...');
            $.ajax({
                url: 'api/quick_response.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ prompt: prompt }),
                success: function(data) {
                    console.log('Quick response received:', data);
                    if (data.success) {
                        const quickResponseHtml = `<div class="card mb-3 bg-light"><div class="card-body"><p class="card-text"><strong>Quick Take:</strong> ${data.response}</p></div></div>`;
                        quickResponseArea.html(quickResponseHtml);
                        resolve();
                    } else {
                        console.error('Quick response error:', data.error);
                        reject(data.error);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Quick response request failed:', textStatus, errorThrown);
                    reject(errorThrown);
                }
            });
        });
    }

    async function sendPrompt(prompt) {
        promptInput.val('');
        promptInput.prop('disabled', true);
        loadingIndicator.show();
        quickResponseArea.empty();

        const userPromptHtml = `<div class="card mb-3"><div class="card-header"><strong>You:</strong> ${prompt}</div></div>`;
        responseArea.prepend(userPromptHtml);

        conversationHistory.push({ role: 'user', content: prompt });

        const assistantResponseCard = $('<div class="card mb-3"><div class="card-body"><p class="card-text"><strong>Assistant:</strong> </p></div></div>');
        responseArea.prepend(assistantResponseCard);
        const assistantResponseContent = assistantResponseCard.find('.card-text');

        let fullResponse = '';

        const quickResponsePromise = sendQuickResponse(prompt);

        const mainResponsePromise = new Promise(async (resolve, reject) => {
            console.log('Sending main request...');
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
                        console.log('Main response stream complete.');
                        resolve();
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
                console.error('Main request error:', error);
                const errorHtml = `<div class="alert alert-danger">An unknown error occurred.</div>`;
                responseArea.prepend(errorHtml);
                conversationHistory.pop();
                reject(error);
            }
        });

        try {
            await Promise.all([quickResponsePromise, mainResponsePromise]);
            console.log('Both promises resolved.');
        } catch (error) {
            console.error("An error occurred in one of the promises:", error);
        } finally {
            console.log('Hiding loading indicator.');
            conversationHistory.push({ role: 'assistant', content: fullResponse });
            loadingIndicator.hide();
            promptInput.prop('disabled', false);
            promptInput.focus();
        }
    }
});
