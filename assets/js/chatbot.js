(function($) {
    $(document).ready(function() {
        const $container = $('#wc-ai-chatbot-container');
        const $toggle = $('.wc-ai-chatbot-toggle');
        const $messages = $('.wc-ai-chatbot-messages');
        const $input = $('.wc-ai-chatbot-input input');
        const $send = $('.wc-ai-chatbot-send');
        const $close = $('.wc-ai-chatbot-close');

        // Toggle chat visibility
        $toggle.on('click', function(e) {
            e.preventDefault();
            $container.toggleClass('hidden');
        });

        $close.on('click', function() {
            $container.addClass('hidden');
        });

        // Handle sending messages
        const sendMessage = function() {
            const message = $input.val().trim();
            if (message === '') return;

            // Add user message to chat
            addMessage('user', sanitizeMessage(message));
            $input.val('').focus();

            // AJAX request to server
            $.ajax({
                url: wc_ai_chatbot.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_ai_chat',
                    message: message,
                    _ajax_nonce: wc_ai_chatbot.nonce
                },
                dataType: 'json',
                beforeSend: function() {
                    $send.prop('disabled', true);
                    addMessage('assistant', '<div class="wc-ai-chatbot-typing">Typing...</div>');
                },
                success: function(response) {
                    // Remove typing indicator
                    $messages.find('.wc-ai-chatbot-typing').remove();

                    if (response.success) {
                        addMessage('assistant', response.data);
                    } else {
                        addMessage('assistant', '<p>Sorry, there was an error processing your request.</p>');
                    }
                },
                error: function() {
                    // Remove typing indicator
                    $messages.find('.wc-ai-chatbot-typing').remove();
                    addMessage('assistant', '<p>Sorry, there was an error connecting to the server.</p>');
                },
                complete: function() {
                    $send.prop('disabled', false);
                }
            });
        };

        // Basic HTML sanitization (consider using DOMPurify for more robust sanitization)
        const sanitizeMessage = function(message) {
            // Convert to text if it's not the assistant's HTML response
            return $('<div>').text(message).html();
        };

        $send.on('click', sendMessage);
        $input.on('keypress', function(e) {
            if (e.which === 13) {
                sendMessage();
            }
        });

        // Add messages to chat
        const addMessage = function(role, message) {
            const $message = $('<div class="wc-ai-chatbot-message"></div>')
                .addClass('wc-ai-chatbot-message-' + role);

            // Only sanitize user messages, allow HTML for assistant responses
            if (role === 'user') {
                $message.text(message); // Convert to text for user messages
            } else {
                $message.html(message); // Allow HTML for assistant responses
            }

            $messages.append($message);
            $messages.scrollTop($messages[0].scrollHeight);
        };
    });
})(jQuery);