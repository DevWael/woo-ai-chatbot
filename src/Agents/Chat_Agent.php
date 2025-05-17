<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Agents;

use NeuronAI\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Deepseek;
use NeuronAI\Providers\Mistral;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Ollama\Ollama;
use WoocommerceAIChatbot\Providers\OpenRouterAI;
use WoocommerceAIChatbot\Providers\TogetherAI;

defined( '\ABSPATH' ) || exit;

class Chat_Agent extends Agent {

	/**
	 * @var array|string[]
	 */
	private array $providers_map;

	/**
	 * @var array|string[]
	 */
	private array $options;

	public function __construct() {
		$this->load_plugin_settings();
		$this->load_providers_map();
	}

	protected function provider(): AIProviderInterface {
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

		return $provider;
	}

	private function load_plugin_settings() {
		$this->options = apply_filters(
			'woocommerce_ai_chatbot_settings',
			get_option( 'wc_ai_chat_settings', [
				'api_key'    => '',
				'api_domain' => 'http://localhost:11434/api/',
				'provider'   => 'ollama',
				'model'      => 'qwen3:14b',
				'chat_title' => 'How can I help you?',
			] )
		);
	}

	/**
	 * Maps provider keys to their respective classes.
	 *
	 * @return array<string, class-string<AIProviderInterface>>
	 */
	private function load_providers_map() {
		$this->providers_map = array(
			'ollama'      => Ollama::class,
			'anthropic'   => Anthropic::class,
			'openai'      => OpenAI::class,
			'mistral'     => Mistral::class,
			'deepseek'    => Deepseek::class,
			'together_ai' => TogetherAI::class,
			'open_router' => OpenRouterAI::class,
		);
	}

	public function instructions(): string {
		$prompt = new SystemPrompt(
			background: [
				"You are an AI-powered e-commerce assistant for this WooCommerce store.",
			],
			steps: [
				"For product search, use the search_woocommerce_products tool and return the found products response as it is. DON'T modify the response.",
				"For cart actions, use the appropriate cart tools only when explicitly requested.",
			],
			output: [
				"Generate responses in valid HTML format using the provided templates if no HTML provided.",
			]
		);

		return $prompt->__toString();
	}

	protected function tools(): array {
		return [
			// Create Post Tool
			Tools::create_post(),

			// Search Products Tool
			Tools::products_search(),

			// Add to Cart Tool
			Tools::add_to_cart(),

			// Cart Count Tool
			Tools::cart_products_count(),

			// Empty Cart Tool
			Tools::empty_cart(),
		];
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

		$response = $this->chat( $user_message );
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