<?php
// Widget template for chatbot
if (!defined('ABSPATH')) exit;
?>

<div id="statica-chatbot-widget">
    <div id="statica-chatbot-toggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
    </div>
    <div id="statica-chatbot-container" style="display: none;">
        <div id="statica-chatbot-header">
            <h3>Statica Assistant</h3>
            <button id="statica-chatbot-close">&times;</button>
        </div>
        <div id="statica-chatbot-messages"></div>
        <div id="statica-chatbot-input-container">
            <input type="text" id="statica-chatbot-input" placeholder="Ask me anything about our products..." />
            <button id="statica-chatbot-send">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>
    </div>
</div>