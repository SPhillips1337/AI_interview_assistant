$(document).ready(function() {
    let debounceTimer;
    const debounceMs = window.appConfig?.debounceMs || 700;
    let conversationHistory = JSON.parse(localStorage.getItem('conversationHistory') || '[]');
    let queryQueue = [];
    let isProcessing = false;

    const autoSendToggle = $('#autoSendToggle');
    const promptInput = $('#prompt-input');
    const responseArea = $('#response-area');
    const quickResponseArea = $('#quick-response-area');
    const loadingIndicator = $('#loading-indicator');
    const queueIndicator = $('#queue-indicator');
    const queueCount = $('#queue-count');
    const topicNav = $('#topic-nav');

    let responseCounter = 0;

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
                queryQueue.push(prompt);
                promptInput.val('');
                updateQueueIndicator();
                processQueue();
            }
        }, debounceMs);
    });

    function sendQuickResponse(conversation) {
        return new Promise((resolve, reject) => {
            console.log('Sending quick response...');
            $.ajax({
                url: 'api/quick_response.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ conversation: conversation }),
                success: function(data) {
                    console.log('Quick response received:', data);
                    if (data.success) {
                        const quickResponseHtml = `<div class="card mb-3 bg-light"><div class="card-body"><div class="card-text"><strong>Quick Take:</strong> <div class="mt-2">${marked.parse(data.response)}</div></div></div></div>`;
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

    // Keep only the last 10 exchanges (20 messages) to maintain context without overwhelming the API
    function trimConversationHistory() {
        if (conversationHistory.length > 20) {
            conversationHistory = conversationHistory.slice(-20);
            localStorage.setItem('conversationHistory', JSON.stringify(conversationHistory));
        }
    }

    function addTopicNavigation(prompt, responseId) {
        const shortPrompt = prompt.length > 50 ? prompt.substring(0, 50) + '...' : prompt;
        const topicItem = $(`
            <div class="topic-item" data-target="${responseId}">
                <small class="text-muted d-block">${new Date().toLocaleTimeString()}</small>
                <div>${shortPrompt}</div>
            </div>
        `);
        
        topicItem.on('click', function() {
            const targetId = $(this).data('target');
            const targetElement = $(`#${targetId}`);
            if (targetElement.length) {
                targetElement[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        // Remove placeholder text if it exists
        if (topicNav.find('.text-muted.text-center').length) {
            topicNav.empty();
        }
        
        // Add new item to the top of the list
        topicNav.prepend(topicItem);
    }

    function updateQueueIndicator() {
        if (queryQueue.length > 0) {
            queueCount.text(queryQueue.length);
            queueIndicator.show();
        } else {
            queueIndicator.hide();
        }
    }

    function processQueue() {
        if (isProcessing || queryQueue.length === 0) {
            return;
        }
        
        const prompt = queryQueue.shift();
        updateQueueIndicator();
        sendPrompt(prompt);
    }

    async function sendPrompt(prompt) {
        isProcessing = true;
        loadingIndicator.show();
        quickResponseArea.empty();

        responseCounter++;
        const responseId = `response-${responseCounter}`;

        const userPromptHtml = `<div class="card mb-3" id="${responseId}"><div class="card-header response-anchor"><strong>You:</strong> ${prompt}</div></div>`;
        responseArea.prepend(userPromptHtml);

        addTopicNavigation(prompt, responseId);

        conversationHistory.push({ role: 'user', content: prompt });
        localStorage.setItem('conversationHistory', JSON.stringify(conversationHistory));
        console.log('Current conversation history:', conversationHistory);

        const assistantResponseCard = $('<div class="card mb-3"><div class="card-body"><p class="card-text"><strong>Assistant:</strong> </p></div></div>');
        responseArea.prepend(assistantResponseCard);
        const assistantResponseContent = assistantResponseCard.find('.card-text');

        let fullResponse = '';

        try {
            await sendQuickResponse(conversationHistory);
        } catch (error) {
            console.error("Quick response failed:", error);
        }

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
                                        const renderedResponse = marked.parse(fullResponse);
                                        assistantResponseContent.html(`<strong>Assistant:</strong> <div class="mt-2">${renderedResponse}</div>`);
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
            await mainResponsePromise;
        } catch (error) {
            console.error("Main response failed:", error);
        } finally {
            console.log('Hiding loading indicator.');
            conversationHistory.push({ role: 'assistant', content: fullResponse });
            trimConversationHistory();
            localStorage.setItem('conversationHistory', JSON.stringify(conversationHistory));
            isProcessing = false;
            loadingIndicator.hide();
            promptInput.focus();
            processQueue();
        }
    }
});
