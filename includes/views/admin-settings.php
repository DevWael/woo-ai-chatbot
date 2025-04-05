<?php
/**
 * Admin settings view for WooCommerce AI Chatbot
 * 
 * @package WooCommerce_AI_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<div class="wrap">
    <h1>WooCommerce AI Chatbot Settings</h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('wc_ai_chat_group');
        do_settings_sections('wc-ai-chatbot', 'wc_ai_chat_main');
        submit_button('Save Settings');
        ?>
    </form>
</div>