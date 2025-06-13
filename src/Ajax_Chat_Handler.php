<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot;

use WoocommerceAIChatbot\Agents\Chat_Agent;
use NeuronAI\Chat\Messages\UserMessage;
use Inspector\Inspector;
use Inspector\Configuration;
use NeuronAI\Observability\AgentMonitoring;
use WoocommerceAIChatbot\Utilities\Renderer;

defined( '\ABSPATH' ) || exit;

class Ajax_Chat_Handler {

	/**
	 * Initializes the chat agent with optional monitoring.
	 *
	 * @return Chat_Agent
	 */
	private function initialize_chat_agent(): Chat_Agent {
		$chat_agent = Chat_Agent::get_instance();
		if ( defined( 'AIChatbot_INSPECTOR_API_KEY' ) && AIChatbot_INSPECTOR_API_KEY ) {
			$config    = new Configuration( AIChatbot_INSPECTOR_API_KEY );
			$inspector = new Inspector( $config );
			$chat_agent->observe( new AgentMonitoring( $inspector ) );
		}
		return $chat_agent;
	}

	/**
	 * Processes the user message and retrieves the chat response.
	 *
	 * @param Chat_Agent $chat_agent
	 * @param string     $message
	 * @return string
	 */
	private function process_chat_message( Chat_Agent $chat_agent, string $message ): string {
		$chat = $chat_agent->chat( new UserMessage( $message ) );
		return self::strip_think_tags( $chat->getContent() );
	}

	/**
	 * Renders the response data for the AJAX request.
	 *
	 * @param Chat_Agent $chat_agent
	 * @param string     $response
	 * @return array
	 */
	private function render_response_data( Chat_Agent $chat_agent, string $response ): array {
		$renderer = new Renderer( $chat_agent->responses );
		return [
			'content'   => $response,
			'responses' => $chat_agent->responses,
			'html'      => $renderer->render(),
		];
	}

	/**
	 * Handles the AJAX chat request.
	 */
	public function handle_chat_request() {
		try {
			$chat_agent = $this->initialize_chat_agent();
			$response   = $this->process_chat_message( $chat_agent, $_POST['message'] );
			$data       = $this->render_response_data( $chat_agent, $response );
			wp_send_json_success( $data );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Strips <think> tags and their content from a given string.
	 *
	 * @param string $text The input string potentially containing <think> tags.
	 * @return string The cleaned string with <think> tags and their content removed.
	 */
	public static function strip_think_tags( $text ) {
		$pattern = '/<think>(.*?)<\/think>/si';
		return preg_replace( $pattern, '', $text );
	}

	/**
	 * Registers AJAX hooks.
	 */
	public function load_hooks() {
		add_action( 'wp_ajax_wc_ai_chat', [ $this, 'handle_chat_request' ] );
		add_action( 'wp_ajax_nopriv_wc_ai_chat', [ $this, 'handle_chat_request' ] );
	}
}