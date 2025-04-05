<?php
class WC_AI_Chat_Frontend {
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_footer', [$this, 'render_chat_interface']);
    }
    
    public function enqueue_scripts() {
        if (!is_admin()) {
            wp_enqueue_style(
                'wc-ai-chatbot',
                WC_AI_CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css',
                [],
                WC_AI_CHATBOT_VERSION
            );
            
            wp_enqueue_script(
                'wc-ai-chatbot',
                WC_AI_CHATBOT_PLUGIN_URL . 'assets/js/chatbot.js',
                ['jquery'],
                WC_AI_CHATBOT_VERSION,
                true
            );
            
            wp_localize_script(
                'wc-ai-chatbot',
                'wc_ai_chatbot',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wc_ai_chatbot_nonce')
                ]
            );
        }
    }
    
    public function render_chat_interface() {
        if (is_woocommerce()) {
            $options = get_option('wc_ai_chat_settings');
            ?>
            <div id="wc-ai-chatbot-container" class="hidden">
                <div class="wc-ai-chatbot-header">
                    <h3><?php echo esc_html($options['chat_title'] ?? 'How can I help you?'); ?></h3>
                    <button class="wc-ai-chatbot-close">×</button>
                </div>
                <div class="wc-ai-chatbot-messages"></div>
                <div class="wc-ai-chatbot-input">
                    <input type="text" placeholder="Type your message...">
                    <button class="wc-ai-chatbot-send">Send</button>
                </div>
            </div>
            <button class="wc-ai-chatbot-toggle"></button>
            <?php
        }
    }
}