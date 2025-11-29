<?php
/**
 * Plugin Name: Statica Free Cloud RAG Chatbot
 * Description: Free cloud-based RAG chatbot using Hugging Face Inference API
 * Version: 2.2.0
 * Author: Shantanu Harkulkar
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

define('STATICA_CHATBOT_VERSION', '2.0.0');
define('STATICA_CHATBOT_PATH', plugin_dir_path(__FILE__));
define('STATICA_CHATBOT_URL', plugin_dir_url(__FILE__));

class StaticaFreeCloudChatbot {
    
    private $vector_db_file;
    
    public function __construct() {
        $this->vector_db_file = STATICA_CHATBOT_PATH . 'data/vector_db.json';
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_footer', array($this, 'render_chatbot_widget'));
        add_action('wp_ajax_statica_chat', array($this, 'handle_chat_request'));
        add_action('wp_ajax_nopriv_statica_chat', array($this, 'handle_chat_request'));
        add_action('wp_ajax_statica_index_content', array($this, 'index_website_content'));
        
        $this->create_directories();
    }
    
    private function create_directories() {
        $data_dir = STATICA_CHATBOT_PATH . 'data';
        if (!file_exists($data_dir)) {
            wp_mkdir_p($data_dir);
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Statica Chatbot',
            'Statica Chatbot',
            'manage_options',
            'statica-chatbot',
            array($this, 'render_admin_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>🤖 Statica Free Cloud RAG Chatbot</h1>
            
            <div class="notice notice-info">
                <p><strong>100% Free Options Available!</strong></p>
                <ul>
                    <li>✅ <strong>Hugging Face</strong> - Free tier, no credit card needed</li>
                    <li>✅ <strong>Groq</strong> - Free 14,400 requests/day</li>
                    <li>✅ <strong>Together AI</strong> - $5 free credit</li>
                </ul>
            </div>
            
            <div class="card">
                <h2>API Configuration</h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('statica_chatbot_settings');
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">API Provider</th>
                            <td>
                                <select name="statica_api_provider" id="api-provider">
                                    <option value="huggingface" <?php selected(get_option('statica_api_provider'), 'huggingface'); ?>>
                                        Hugging Face (FREE - Recommended)
                                    </option>
                                    <option value="groq" <?php selected(get_option('statica_api_provider'), 'groq'); ?>>
                                        Groq (FREE - 14,400 req/day)
                                    </option>
                                    <option value="together" <?php selected(get_option('statica_api_provider'), 'together'); ?>>
                                        Together AI ($5 free credit)
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">API Key</th>
                            <td>
                                <input type="password" name="statica_api_key" 
                                       value="<?php echo esc_attr(get_option('statica_api_key')); ?>" 
                                       class="regular-text" 
                                       placeholder="Enter your API key" />
                                <p class="description" id="api-instructions">
                                    Get free API key from: <a href="https://huggingface.co/settings/tokens" target="_blank">Hugging Face</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save API Settings'); ?>
                </form>
            </div>
            
            <div class="card">
                <h2>Content Indexing</h2>
                <p>Index your website content for RAG retrieval.</p>
                <button id="index-content-btn" class="button button-primary">
                    📑 Index Website Content
                </button>
                <div id="indexing-status" style="margin-top: 10px;"></div>
                <p style="margin-top: 15px;">
                    <strong>Indexed documents:</strong> 
                    <span style="font-size: 18px; color: #2271b1;"><?php echo $this->get_indexed_count(); ?></span>
                </p>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#api-provider').on('change', function() {
                    var provider = $(this).val();
                    var instructions = {
                        'huggingface': 'Get free API key from: <a href="https://huggingface.co/settings/tokens" target="_blank">Hugging Face</a>',
                        'groq': 'Get free API key from: <a href="https://console.groq.com" target="_blank">Groq Console</a>',
                        'together': 'Get API key from: <a href="https://api.together.xyz" target="_blank">Together AI</a>'
                    };
                    $('#api-instructions').html(instructions[provider]);
                });
                
                $('#index-content-btn').on('click', function() {
                    var btn = $(this);
                    btn.prop('disabled', true).text('⏳ Indexing...');
                    $('#indexing-status').html('<p style="color: #0073aa;">Processing your website content...</p>');
                    
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'statica_index_content',
                            nonce: '<?php echo wp_create_nonce('statica_index_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#indexing-status').html('<p style="color: #00a32a; font-weight: bold;">✅ ' + response.data.message + '</p>');
                                location.reload();
                            } else {
                                $('#indexing-status').html('<p style="color: #d63638;">❌ Error: ' + response.data.message + '</p>');
                            }
                            btn.prop('disabled', false).text('📑 Index Website Content');
                        },
                        error: function() {
                            $('#indexing-status').html('<p style="color: #d63638;">❌ Connection error. Please try again.</p>');
                            btn.prop('disabled', false).text('📑 Index Website Content');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function index_website_content() {
        check_ajax_referer('statica_index_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $indexed_data = array();
        
        // Index WooCommerce products
        if (class_exists('WooCommerce')) {
            $products = wc_get_products(array('limit' => -1, 'status' => 'publish'));
            foreach ($products as $product) {
                $indexed_data[] = array(
                    'type' => 'product',
                    'id' => $product->get_id(),
                    'title' => $product->get_name(),
                    'content' => strip_tags($product->get_description()),
                    'short_description' => strip_tags($product->get_short_description()),
                    'price' => $product->get_price(),
                    'regular_price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price(),
                    'stock_status' => $product->get_stock_status(),
                    'url' => get_permalink($product->get_id()),
                    'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names')),
                    'tags' => wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'))
                );
            }
        }
        
        // Index pages
        $pages = get_posts(array('post_type' => 'page', 'posts_per_page' => -1, 'post_status' => 'publish'));
        foreach ($pages as $page) {
            $indexed_data[] = array(
                'type' => 'page',
                'id' => $page->ID,
                'title' => $page->post_title,
                'content' => wp_strip_all_tags($page->post_content),
                'excerpt' => wp_trim_words(wp_strip_all_tags($page->post_content), 50),
                'url' => get_permalink($page->ID),
                'categories' => array()
            );
        }
        
        // Index posts
        $posts = get_posts(array('post_type' => 'post', 'posts_per_page' => -1, 'post_status' => 'publish'));
        foreach ($posts as $post) {
            $cats = wp_get_post_categories($post->ID, array('fields' => 'names'));
            $indexed_data[] = array(
                'type' => 'post',
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => wp_strip_all_tags($post->post_content),
                'excerpt' => wp_trim_words(wp_strip_all_tags($post->post_content), 50),
                'url' => get_permalink($post->ID),
                'categories' => is_array($cats) ? $cats : array()
            );
        }
        
        file_put_contents($this->vector_db_file, json_encode($indexed_data, JSON_PRETTY_PRINT));
        
        wp_send_json_success(array('message' => 'Successfully indexed ' . count($indexed_data) . ' items!'));
    }
    
    private function get_indexed_count() {
        if (file_exists($this->vector_db_file)) {
            $data = json_decode(file_get_contents($this->vector_db_file), true);
            return is_array($data) ? count($data) : 0;
        }
        return 0;
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style('statica-chatbot-css', STATICA_CHATBOT_URL . 'assets/chatbot.css', array(), STATICA_CHATBOT_VERSION);
        wp_enqueue_script('statica-chatbot-js', STATICA_CHATBOT_URL . 'assets/chatbot.js', array('jquery'), STATICA_CHATBOT_VERSION, true);
        
        wp_localize_script('statica-chatbot-js', 'staticaChatbot', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('statica_chat_nonce')
        ));
    }
    
    public function render_chatbot_widget() {
        $template_file = STATICA_CHATBOT_PATH . 'includes/widget-template.php';
        if (file_exists($template_file)) {
            include $template_file;
        }
    }
    
    public function handle_chat_request() {
        check_ajax_referer('statica_chat_nonce', 'nonce');
        
        $user_message = sanitize_text_field($_POST['message']);
        $conversation_history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
        $api_key = get_option('statica_api_key');
        $api_provider = get_option('statica_api_provider', 'huggingface');
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'Please configure API key in admin settings.'));
            return;
        }
        
        // Detect user intent
        $intent = $this->detect_intent($user_message, $conversation_history);
        
        // Check for instant responses first
        $instant_response = $this->get_instant_response($user_message, $intent);
        if ($instant_response) {
            wp_send_json_success(array('response' => $instant_response));
            return;
        }
        
        // Retrieve relevant context based on intent
        $context = $this->retrieve_relevant_context($user_message, $intent);
        
        // Call AI API with intent and history
        $response = $this->call_ai_api($user_message, $context, $intent, $conversation_history, $api_provider, $api_key);
        
        wp_send_json_success(array('response' => $response));
    }
    
    private function detect_intent($message, $history = array()) {
        $msg = strtolower($message);
        
        if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening|sup|yo)[\s\?\!]*$/i', $msg)) {
            return 'greeting';
        }
        
        if (preg_match('/(how are you|how r u|wassup|what\'s up|whats up)/i', $msg)) {
            return 'casual';
        }
        
        if (preg_match('/^(thanks|thank you|thx|bye|goodbye|see ya)[\s\?\!]*$/i', $msg)) {
            return 'closing';
        }
        
        if (preg_match('/(show me|looking for|need|want to buy|search for|find|get me)/i', $msg)) {
            return 'product_search';
        }
        
        if (preg_match('/(best|top|popular|trending|most|list of|5|10)/i', $msg)) {
            return 'product_list';
        }
        
        if (preg_match('/(price|cost|how much|expensive|cheap|under|budget)/i', $msg)) {
            return 'pricing';
        }
        
        if (preg_match('/(what is|who are|tell me about|information about|details about|explain)/i', $msg)) {
            return 'information';
        }
        
        if (preg_match('/(compare|difference between|vs|versus|better)/i', $msg)) {
            return 'comparison';
        }
        
        if (preg_match('/(ship|delivery|deliver|shipping|courier|tracking)/i', $msg)) {
            return 'shipping';
        }
        
        if (preg_match('/(payment|pay|cod|upi|card|credit|debit)/i', $msg)) {
            return 'payment';
        }
        
        if (preg_match('/(return|refund|exchange|replace|warranty|guarantee)/i', $msg)) {
            return 'returns';
        }
        
        if (count($history) > 0 && str_word_count($msg) <= 3) {
            return 'followup';
        }
        
        return 'general';
    }
    
    private function get_instant_response($message, $intent) {
        switch ($intent) {
            case 'greeting':
                return "Hi there! 👋 Welcome to Statica.in! I'm here to help you find products, answer questions, and assist with your shopping. What are you looking for today?";
            
            case 'casual':
                return "I'm doing great, thanks for asking! 😊 Ready to help you with anything you need at Statica.in. What can I help you find today?";
            
            case 'closing':
                return "You're welcome! Feel free to come back anytime if you have more questions. Happy shopping! 🛍️";
            
            case 'shipping':
                return "We ship across India! 🚚\n\n• Standard Delivery: 3-5 business days\n• Free shipping on orders above ₹500\n• Track your order via email/SMS\n\nLooking for something specific to order?";
            
            case 'payment':
                return "We accept multiple payment methods: 💳\n\n• UPI (Google Pay, PhonePe, Paytm)\n• Credit/Debit Cards\n• Net Banking\n• Cash on Delivery (COD)\n\nAll payments are 100% secure!";
            
            case 'returns':
                return "Our return policy: ✅\n\n• 7-day returns on most products\n• Items must be unused and in original packaging\n• Free return pickup available\n• Refund processed within 5-7 days\n\nNeed help with a specific order?";
            
            default:
                return null;
        }
    }
    
    private function retrieve_relevant_context($query, $intent = 'general') {
        if (!file_exists($this->vector_db_file)) {
            return '';
        }
        
        $indexed_data = json_decode(file_get_contents($this->vector_db_file), true);
        if (!is_array($indexed_data)) {
            return '';
        }
        
        $query_lower = strtolower($query);
        
        $limit = 3;
        $type_filter = null;
        
        switch ($intent) {
            case 'product_search':
            case 'pricing':
                $limit = 5;
                $type_filter = 'product';
                break;
            case 'product_list':
                $limit = 10;
                $type_filter = 'product';
                break;
            case 'information':
                $limit = 3;
                break;
            case 'comparison':
                $limit = 5;
                $type_filter = 'product';
                break;
        }
        
        $relevant_items = array();
        foreach ($indexed_data as $item) {
            if ($type_filter && isset($item['type']) && $item['type'] !== $type_filter) {
                continue;
            }
            
            $score = 0;
            $title = isset($item['title']) ? $item['title'] : '';
            $content = isset($item['content']) ? $item['content'] : '';
            $categories = isset($item['categories']) ? (is_array($item['categories']) ? $item['categories'] : array()) : array();
            
            $searchable = strtolower($title . ' ' . $content . ' ' . implode(' ', $categories));
            
            $keywords = array_filter(explode(' ', $query_lower), function($k) { return strlen($k) > 2; });
            
            foreach ($keywords as $keyword) {
                if (strpos(strtolower($title), $keyword) !== false) {
                    $score += 5;
                }
                if (strpos(strtolower(implode(' ', $categories)), $keyword) !== false) {
                    $score += 3;
                }
                if (strpos($searchable, $keyword) !== false) {
                    $score += substr_count($searchable, $keyword);
                }
            }
            
            if ($intent === 'pricing' && isset($item['price']) && $item['price']) {
                $score += 2;
            }
            
            if ($score > 0) {
                $item['score'] = $score;
                $relevant_items[] = $item;
            }
        }
        
        usort($relevant_items, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        $context_items = array_slice($relevant_items, 0, $limit);
        
        if (empty($context_items)) {
            return "No specific products found. General website information available.";
        }
        
        $context = "Relevant Information:\n\n";
        
        foreach ($context_items as $item) {
            $context .= "📌 " . $item['title'] . "\n";
            
            if ($intent === 'product_list' || $intent === 'product_search') {
                if (isset($item['price']) && $item['price']) {
                    $context .= "   Price: ₹" . $item['price'];
                    if (isset($item['sale_price']) && $item['sale_price']) {
                        $context .= " (Sale: ₹" . $item['sale_price'] . ")";
                    }
                    $context .= "\n";
                }
                if (isset($item['stock_status'])) {
                    $context .= "   Status: " . ($item['stock_status'] === 'instock' ? 'In Stock ✅' : 'Out of Stock ❌') . "\n";
                }
            } else {
                $desc = isset($item['short_description']) ? $item['short_description'] : (isset($item['excerpt']) ? $item['excerpt'] : substr(isset($item['content']) ? $item['content'] : '', 0, 200));
                if ($desc) {
                    $context .= "   " . $desc . "\n";
                }
                if (isset($item['price']) && $item['price']) {
                    $context .= "   Price: ₹" . $item['price'] . "\n";
                }
            }
            
            $context .= "   Link: " . $item['url'] . "\n\n";
        }
        
        return $context;
    }
    
    private function call_ai_api($user_message, $context, $intent, $history, $provider, $api_key) {
        $system_prompt = $this->build_smart_prompt($intent, $context);
        $conversation = $this->format_conversation_history($history, $user_message);
        
        switch ($provider) {
            case 'groq':
                return $this->call_groq_api($system_prompt, $conversation, $api_key);
            case 'together':
                return $this->call_together_api($system_prompt, $conversation, $api_key);
            case 'huggingface':
            default:
                $full_prompt = $system_prompt . "\n\n" . $conversation;
                return $this->call_huggingface_api($full_prompt, $api_key);
        }
    }
    
    private function build_smart_prompt($intent, $context) {
        $base = "You are a friendly, intelligent customer service assistant for Statica, an e-commerce website in India.\n\n";
        
        switch ($intent) {
            case 'product_search':
                $base .= "TASK: Help the customer find specific products.\n";
                $base .= "STYLE: Be helpful and suggest relevant items. Include prices if available.\n";
                $base .= "LENGTH: 3-4 sentences with bullet points if listing products.\n";
                break;
                
            case 'product_list':
                $base .= "TASK: Provide a ranked list of products.\n";
                $base .= "STYLE: Use numbered list or bullets. Be organized and clear.\n";
                $base .= "LENGTH: List format with brief descriptions and prices.\n";
                break;
                
            case 'pricing':
                $base .= "TASK: Answer pricing questions clearly.\n";
                $base .= "STYLE: Direct and specific. Compare prices if relevant.\n";
                $base .= "LENGTH: 2-3 sentences, always mention exact prices.\n";
                break;
                
            case 'information':
                $base .= "TASK: Provide detailed, educational information.\n";
                $base .= "STYLE: Be informative but not overwhelming. Use examples.\n";
                $base .= "LENGTH: 4-6 sentences with clear explanations.\n";
                break;
                
            case 'comparison':
                $base .= "TASK: Compare products or features objectively.\n";
                $base .= "STYLE: Balanced comparison with pros/cons.\n";
                $base .= "LENGTH: Structured comparison in 4-5 sentences.\n";
                break;
                
            case 'followup':
                $base .= "TASK: Continue the conversation naturally.\n";
                $base .= "STYLE: Conversational, reference previous context.\n";
                $base .= "LENGTH: 1-2 sentences, keep it flowing.\n";
                break;
                
            default:
                $base .= "TASK: Answer the question helpfully.\n";
                $base .= "STYLE: Friendly and conversational.\n";
                $base .= "LENGTH: 2-3 sentences unless more detail is clearly needed.\n";
        }
        
        $base .= "\nGUIDELINES:\n";
        $base .= "- Use emojis sparingly (1-2 per response) for friendliness\n";
        $base .= "- Always be accurate based on the provided context\n";
        $base .= "- If you don't have specific info, say so and offer alternatives\n";
        $base .= "- Use Indian Rupee (₹) for prices\n";
        $base .= "- Ask clarifying questions if the query is vague\n\n";
        
        $base .= "CONTEXT:\n" . $context;
        
        return $base;
    }
    
    private function format_conversation_history($history, $current_message) {
        $conversation = "";
        
        if (is_array($history)) {
            $recent_history = array_slice($history, -6);
            
            foreach ($recent_history as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $role = $msg['role'] === 'user' ? 'Customer' : 'Assistant';
                    $conversation .= $role . ": " . $msg['content'] . "\n";
                }
            }
        }
        
        $conversation .= "Customer: " . $current_message . "\nAssistant:";
        
        return $conversation;
    }
    
    private function call_huggingface_api($prompt, $api_key) {
        $response = wp_remote_post('https://api-inference.huggingface.co/models/mistralai/Mistral-7B-Instruct-v0.2', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'inputs' => $prompt,
                'parameters' => array(
                    'max_new_tokens' => 500,
                    'temperature' => 0.7,
                    'return_full_text' => false
                )
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return "Sorry, I'm having trouble connecting. Please try again.";
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body[0]['generated_text'])) {
            return trim($body[0]['generated_text']);
        }
        
        return "I couldn't process that. Please try rephrasing your question.";
    }
    
    private function call_groq_api($system_prompt, $conversation, $api_key) {
        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'llama-3.3-70b-versatile',
                'messages' => array(
                    array('role' => 'system', 'content' => $system_prompt),
                    array('role' => 'user', 'content' => $conversation)
                ),
                'max_tokens' => 1000,
                'temperature' => 0.7,
                'top_p' => 0.9
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return "Sorry, I'm experiencing connectivity issues.";
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['choices'][0]['message']['content'])) {
            return trim($body['choices'][0]['message']['content']);
        }
        
        return "I couldn't understand that. Could you rephrase?";
    }
    
    private function call_together_api($system_prompt, $conversation, $api_key) {
        $response = wp_remote_post('https://api.together.xyz/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'mistralai/Mistral-7B-Instruct-v0.1',
                'messages' => array(
                    array('role' => 'system', 'content' => $system_prompt),
                    array('role' => 'user', 'content' => $conversation)
                ),
                'max_tokens' => 1000,
                'temperature' => 0.7
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return "Connection issue. Please try again.";
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['choices'][0]['message']['content'])) {
            return trim($body['choices'][0]['message']['content']);
        }
        
        return "Error processing request.";
    }
}

function statica_free_cloud_chatbot_init() {
    new StaticaFreeCloudChatbot();
}
add_action('plugins_loaded', 'statica_free_cloud_chatbot_init');

function statica_chatbot_register_settings() {
    register_setting('statica_chatbot_settings', 'statica_api_provider');
    register_setting('statica_chatbot_settings', 'statica_api_key');
}
add_action('admin_init', 'statica_chatbot_register_settings');