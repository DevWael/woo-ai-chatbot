<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot;

use NeuronAI\Chat\Messages\UserMessage;
use WoocommerceAIChatbot\Agents\Chat_Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Deepseek;
use NeuronAI\Providers\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\OpenAI;
use WoocommerceAIChatbot\Providers\OpenRouterAI;
use WoocommerceAIChatbot\Providers\TogetherAI;

defined( '\ABSPATH' ) || exit;

class AI_Handler {

	private array $options;
	private Chat_Agent $agent;
	private array $providers_map;

	/**
	 * Constructor initializes options and providers map.
	 */
	public function __construct() {
		$this->options = apply_filters(
			'woocommerce_ai_chatbot_settings',
			get_option( 'wc_ai_chat_settings', [
				'api_key'    => '',
				'api_domain' => 'http://localhost:11434/api/',
				'provider'   => 'ollama',
				'model'      => 'llama3.1:latest',
				'chat_title' => 'How can I help you?',
			] )
		);

		$this->providers_map = apply_filters(
			'woocommerce_ai_chatbot_providers_map',
			$this->get_providers_map()
		);

		$this->validate_settings();
		$this->initialize_agent();
	}

	/**
	 * Maps provider keys to their respective classes.
	 *
	 * @return array<string, class-string<AIProviderInterface>>
	 */
	private function get_providers_map() {
		return array(
			'ollama'      => Ollama::class,
			'anthropic'   => Anthropic::class,
			'openai'      => OpenAI::class,
			'mistral'     => Mistral::class,
			'deepseek'    => Deepseek::class,
			'together_ai' => TogetherAI::class,
			'open_router' => OpenRouterAI::class,
		);
	}

	/**
	 * Validates provider settings.
	 *
	 * @throws \InvalidArgumentException If settings are invalid.
	 */
	private function validate_settings(): void {
		$provider_key = $this->options['provider'] ?? 'ollama';

		if ( $provider_key === 'ollama' && empty( $this->options['api_domain'] ) ) {
			throw new \InvalidArgumentException( 'API domain is required for Ollama provider.' );
		}

		if ( $provider_key !== 'ollama' && empty( $this->options['api_key'] ) ) {
			throw new \InvalidArgumentException( 'API key is required for ' . $provider_key . ' provider.' );
		}
	}

	/**
	 * Initializes the chat agent with the selected provider.
	 *
	 * @throws \InvalidArgumentException If provider is invalid.
	 */
	private function initialize_agent(): void {
		$provider_key = $this->options['provider'] ?? 'ollama';

		if ( ! isset( $this->providers_map[ $provider_key ] ) ) {
			throw new \InvalidArgumentException( "Invalid provider: {$provider_key}" );
		}

		$provider_class = $this->providers_map[ $provider_key ];
		if ( $provider_key === 'ollama' ) {
			$provider = new $provider_class( $this->options['api_domain'], $this->options['model'] );
		} else {
			$provider = new $provider_class( $this->options['api_key'], $this->options['model'] );
		}

		$this->agent = apply_filters(
			'woocommerce_ai_chatbot_chat_agent',
			new Chat_Agent(
				$provider
			),
			$provider_key
		);
	}

	/**
	 * Processes a user message and returns formatted HTML response.
	 *
	 * @param string $message The user message to process.
	 *
	 * @return string HTML-formatted response.
	 */
	public function process_message( string $message ): string {
		$user_message = apply_filters(
			'woocommerce_ai_chatbot_user_message',
			new UserMessage( $message )
		);

		$response = $this->agent->chat( $user_message );
		$content  = $response->getContent();

		$formatted_content = $this->format_response( $content );

		return apply_filters(
			'woocommerce_ai_chatbot_response',
			$formatted_content,
			$message
		);
	}

	/**
	 * Ensures the response is in HTML format.
	 *
	 * @param string $content The raw response content.
	 *
	 * @return string HTML-formatted content.
	 */
	private function format_response( string $content ): string {
		return preg_match( '/^<.*>$/s', $content ) ? $content : "<p>{$content}</p>";
	}
}