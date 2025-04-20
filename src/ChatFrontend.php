<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot;

defined( '\ABSPATH' ) || exit;

class ChatFrontend {
	public function load_hooks() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_footer', [ $this, 'render_chat_interface' ] );
	}

	public function enqueue_scripts() {
		if ( ! is_admin() ) {
			wp_enqueue_style(
				'wc-ai-chatbot',
				WC_AI_CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css',
				[],
				WC_AI_CHATBOT_VERSION
			);

			wp_enqueue_script(
				'wc-ai-chatbot',
				WC_AI_CHATBOT_PLUGIN_URL . 'assets/js/chatbot.js',
				[ 'jquery' ],
				WC_AI_CHATBOT_VERSION,
				true
			);

			wp_localize_script(
				'wc-ai-chatbot',
				'wc_ai_chatbot',
				[
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'wc_ai_chatbot_nonce' ),
				]
			);
		}
	}

	public function render_chat_interface() {
		if ( is_woocommerce() ) {
			$options = get_option( 'wc_ai_chat_settings' );
			?>
            <div id="wc-ai-chatbot-container" class="hidden">
                <div class="wc-ai-chatbot-header">
                    <h3><?php
						echo esc_html( $options['chat_title'] ?? 'How can I help you?' ); ?></h3>
                    <button class="wc-ai-chatbot-close">×</button>
                </div>
                <div class="wc-ai-chatbot-messages"></div>
                <div class="wc-ai-chatbot-input">
                    <input type="text" placeholder="Type your message...">
                    <button class="wc-ai-chatbot-send">Send</button>
                </div>
            </div>
            <button class="wc-ai-chatbot-toggle">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="#000000">
                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                    <g id="SVGRepo_iconCarrier">
                        <defs>
                            <style>.cls-1 {
                                    fill: #ffffff;
                                }

                                .cls-1, .cls-2 {
                                    fill-rule: evenodd;
                                }

                                .cls-2 {
                                    fill: #f7f7f7;
                                }</style>
                        </defs>
                        <title>Icon_24px_MLEngine_Color</title>
                        <g data-name="Product Icons">
                            <g>
                                <polygon class="cls-1"
                                         points="16.64 15.13 17.38 13.88 20.91 13.88 22 12 19.82 8.25 16.75 8.25 15.69 6.39 14.5 6.39 14.5 5.13 16.44 5.13 17.5 7 19.09 7 16.9 3.25 12.63 3.25 12.63 8.25 14.36 8.25 15.09 9.5 12.63 9.5 12.63 12 14.89 12 15.94 10.13 18.75 10.13 19.47 11.38 16.67 11.38 15.62 13.25 12.63 13.25 12.63 17.63 16.03 17.63 15.31 18.88 12.63 18.88 12.63 20.75 16.9 20.75 20.18 15.13 18.09 15.13 17.36 16.38 14.5 16.38 14.5 15.13 16.64 15.13"></polygon>
                                <polygon class="cls-2"
                                         points="7.36 15.13 6.62 13.88 3.09 13.88 2 12 4.18 8.25 7.25 8.25 8.31 6.39 9.5 6.39 9.5 5.13 7.56 5.13 6.5 7 4.91 7 7.1 3.25 11.38 3.25 11.38 8.25 9.64 8.25 8.91 9.5 11.38 9.5 11.38 12 9.11 12 8.06 10.13 5.25 10.13 4.53 11.38 7.33 11.38 8.38 13.25 11.38 13.25 11.38 17.63 7.97 17.63 8.69 18.88 11.38 18.88 11.38 20.75 7.1 20.75 3.82 15.13 5.91 15.13 6.64 16.38 9.5 16.38 9.5 15.13 7.36 15.13"></polygon>
                            </g>
                        </g>
                    </g>
                </svg>
            </button>
			<?php
		}
	}
}