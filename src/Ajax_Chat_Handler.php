<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot;

defined( '\ABSPATH' ) || exit;

class Ajax_Chat_Handler {

	public function handle_chat_request() {
		$openai_handler = new AI_Handler();
		try {
			$response = $openai_handler->process_message( $_POST['message'] );

			wp_send_json_success( $response );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function load_hooks() {
		add_action( 'wp_ajax_wc_ai_chat', [ $this, 'handle_chat_request' ] );
		add_action( 'wp_ajax_nopriv_wc_ai_chat', [ $this, 'handle_chat_request' ] );
	}
}