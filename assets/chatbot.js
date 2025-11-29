jQuery(document).ready(function($) {
    const chatMessages = $('#statica-chatbot-messages');
    const chatInput = $('#statica-chatbot-input');
    const sendBtn = $('#statica-chatbot-send');
    const toggleBtn = $('#statica-chatbot-toggle');
    const closeBtn = $('#statica-chatbot-close');
    const chatContainer = $('#statica-chatbot-container');
    
    // Conversation history
    let conversationHistory = [];
    
    // Welcome message
    const welcomeMessage = "Hi! 👋 I'm your Statica assistant. I can help you with product information, services, and answer questions about our website. How can I help you today?";
    
    // Toggle chatbot
    toggleBtn.on('click', function() {
        chatContainer.toggle();
        if (chatContainer.is(':visible') && chatMessages.children().length === 0) {
            addMessage(welcomeMessage, 'bot');
        }
    });
    
    closeBtn.on('click', function() {
        chatContainer.hide();
    });
    
    // Send message on button click
    sendBtn.on('click', function() {
        sendMessage();
    });
    
    // Send message on Enter key
    chatInput.on('keypress', function(e) {
        if (e.which === 13) {
            sendMessage();
        }
    });
    
    function sendMessage() {
        const message = chatInput.val().trim();
        
        if (message === '') {
            return;
        }
        
        // Add user message
        addMessage(message, 'user');
        conversationHistory.push({role: 'user', content: message});
        chatInput.val('');
        
        // Show typing indicator
        showTypingIndicator();
        
        // Disable input while processing
        chatInput.prop('disabled', true);
        sendBtn.prop('disabled', true);
        
        // Send to backend with conversation history
        $.ajax({
            url: staticaChatbot.ajaxUrl,
            method: 'POST',
            data: {
                action: 'statica_chat',
                message: message,
                history: JSON.stringify(conversationHistory),
                nonce: staticaChatbot.nonce
            },
            success: function(response) {
                hideTypingIndicator();
                
                if (response.success) {
                    const botResponse = response.data.response;
                    addMessage(botResponse, 'bot');
                    conversationHistory.push({role: 'assistant', content: botResponse});
                    
                    // Keep only last 10 messages (5 exchanges)
                    if (conversationHistory.length > 10) {
                        conversationHistory = conversationHistory.slice(-10);
                    }
                } else {
                    addMessage('Sorry, I encountered an error. Please try again.', 'bot');
                }
                
                chatInput.prop('disabled', false);
                sendBtn.prop('disabled', false);
                chatInput.focus();
            },
            error: function() {
                hideTypingIndicator();
                addMessage('Sorry, I couldn\'t connect. Please check if your API is configured correctly.', 'bot');
                chatInput.prop('disabled', false);
                sendBtn.prop('disabled', false);
                chatInput.focus();
            }
        });
    }
    
    function addMessage(text, sender) {
        const messageDiv = $('<div>').addClass('chatbot-message').addClass(sender);
        const contentDiv = $('<div>').addClass('message-content').html(formatMessage(text));
        messageDiv.append(contentDiv);
        chatMessages.append(messageDiv);
        scrollToBottom();
    }
    
    function formatMessage(text) {
        // Convert markdown-style formatting to HTML
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>'); // Bold
        text = text.replace(/\*(.*?)\*/g, '<em>$1</em>'); // Italic
        text = text.replace(/\n/g, '<br>'); // Line breaks
        
        // Make URLs clickable
        text = text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
        
        return text;
    }
    
    function showTypingIndicator() {
        const typingDiv = $('<div>').addClass('chatbot-message bot typing-message');
        const indicator = $('<div>').addClass('message-content typing-indicator');
        indicator.html('<span></span><span></span><span></span>');
        typingDiv.append(indicator);
        chatMessages.append(typingDiv);
        scrollToBottom();
    }
    
    function hideTypingIndicator() {
        $('.typing-message').remove();
    }
    
    function scrollToBottom() {
        chatMessages.animate({ scrollTop: chatMessages[0].scrollHeight }, 300);
    }
});