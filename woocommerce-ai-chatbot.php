<?php
/**
 * Plugin Name: WooCommerce AI Chatbot
 * Description: AI-powered chatbot for WooCommerce product search and cart management.
 * Version: 1.0
 * Author: Ahmad Wael
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// show errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('WC_AI_CHATBOT_VERSION', '1.0');
define('WC_AI_CHATBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_AI_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WC_AI_CHATBOT_PLUGIN_DIR . 'vendor/autoload.php'; // Ensure you have the OpenAI PHP client installed via Composer

// Include necessary files
require_once WC_AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chat-admin.php';
require_once WC_AI_CHATBOT_PLUGIN_DIR . 'includes/class-ai-chat-frontend.php';
require_once WC_AI_CHATBOT_PLUGIN_DIR . 'includes/class-openai-handler.php';

class WooCommerce_AI_Chatbot {

    public function __construct() {
        // Initialize admin settings
        if (is_admin()) {
            new WC_AI_Chat_Admin();
        }

        // Initialize frontend
        new WC_AI_Chat_Frontend();

        // Handle AJAX requests
        add_action('wp_ajax_wc_ai_chat', [$this, 'handle_chat_request']);
        add_action('wp_ajax_nopriv_wc_ai_chat', [$this, 'handle_chat_request']);
    }

    public function handle_chat_request() {
        $openai_handler = new WC_AI_OpenAI_Handler();
        $response = $openai_handler->process_message($_POST['message']);
        
        wp_send_json_success($response);
    }
}

// Initialize the plugin
new WooCommerce_AI_Chatbot();

// Activation and deactivation hooks
register_activation_hook(__FILE__, ['WooCommerce_AI_Chatbot', 'activate']);
register_deactivation_hook(__FILE__, ['WooCommerce_AI_Chatbot', 'deactivate']);