<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot;

use NeuronAI\Chat\Messages\UserMessage;
use WoocommerceAIChatbot\Agents\Chat_Agent;

defined( '\ABSPATH' ) || exit;

class AI_Handler {

	private $agent;
	private $model;
	private $api_domain;

	public function __construct() {
		$options          = get_option( 'wc_ai_chat_settings' );
		$api_key          = $options['api_key'] ?? '';
		$this->model      = 'llama3.1:latest';
		$this->api_domain = $options['api_domain'] ?? 'http://localhost:11434';

		// Initialize Neuron AI Agent
		$this->agent = new Chat_Agent( $this->api_domain, $this->model, $this );
	}

	public function process_message( $message ) {
		$response = $this->agent->chat( new UserMessage( $message ) );
		$content  = $response->getContent();

		// Ensure response is in HTML format
		if ( ! preg_match( '/^<.*>$/s', $content ) ) {
			$content = "<p>$content</p>";
		}

		return $content;
	}
}