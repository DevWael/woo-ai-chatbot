<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Agents;

use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Ollama\Ollama;
use WoocommerceAIChatbot\AI_Handler;

defined( '\ABSPATH' ) || exit;

class Chat_Agent extends Agent {
	/**
	 * @var AIProviderInterface The AI provider instance.
	 */
	protected AIProviderInterface $provider;

	public function __construct( AIProviderInterface $provider ) {
		$this->provider = $provider;
	}

	protected function provider(): AIProviderInterface {
		return $this->provider;
	}

	public function instructions(): string {
		$prompt = new SystemPrompt(
			background: [
				"You are an AI-powered e-commerce assistant for this WooCommerce store.",
				"Your primary responsibilities are to help customers discover products, assist with cart management, and provide accurate information while maintaining brand voice.",
			],
			steps: [
				"For product queries, use the search_woocommerce_products tool.",
				"For cart actions, use the appropriate cart tools only when explicitly requested.",
				"Generate responses in valid HTML format using the provided templates.",
				"Maintain a friendly, professional tone and focus on available products.",
			],
			output: [
				"All responses MUST be in valid HTML format.",
				"Use the ProductDisplay schema for product search results.",
				"Use the CartAction schema for cart-related actions.",
				//"For general responses, wrap text in <p> tags.",
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
}