<?php
/**
 * Plugin Name: WooCommerce AI Chatbot
 * Description: A powerful AI-powered assistant for WooCommerce stores that helps customers discover products, manage carts, and get e-commerce support through natural language interactions.
 * Version: 1.0
 * Author: Ahmad Wael
 * Author URI: https://www.bbioon.com
 * License: GPL2
 * Plugin URI: https://github.com/DevWael/woo-ai-chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// show errors
ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );

define( 'WC_AI_CHATBOT_VERSION', '1.0' );
define( 'WC_AI_CHATBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_AI_CHATBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WC_AI_CHATBOT_PLUGIN_DIR . 'vendor/autoload.php'; // Ensure you have the OpenAI PHP client installed via Composer

$instance = \WoocommerceAIChatbot\Core::get_instance();
$instance->load_hooks();