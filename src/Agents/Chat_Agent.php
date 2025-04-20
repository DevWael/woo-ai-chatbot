<?php

namespace WoocommerceAIChatbot\Agents;

use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use WoocommerceAIChatbot\AI_Handler;

defined( '\ABSPATH' ) || exit;

class Chat_Agent extends Agent {

	private $api_domain;
	private $model;
	/**
	 * @var AI_Handler
	 */
	private $handler;

	public function __construct( $api_domain, $model, $handler ) {
		$this->api_domain = $api_domain;
		$this->model      = $model;
		$this->handler    = $handler;
	}

	protected function provider(): AIProviderInterface {
		return new Ollama(
			$this->api_domain,
			$this->model,
			[
				'top_k'   => 55,
				'top_p'   => 0.6,
				//				'temperature' => 0.3,
				'num_ctx' => 30000,
			]
		);
	}

	public function instructions(): string {
		return new SystemPrompt(
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
	}

	protected function tools(): array {
		return [
			// Create Post Tool
			Tool::make(
				'create_post',
				'Create a new post in the store. Use only when explicitly requested.',
			)->addProperty(
				new ToolProperty( 'post_name', 'string', 'The name of the post to be created.', true )
			)->addProperty(
				new ToolProperty( 'content', 'string', 'The content of the post.', false )
			)->setCallable(
				array( $this->handler, 'create_post' )
			),

			// Search Products Tool
			Tool::make(
				'search_woocommerce_products',
				'Searches the WooCommerce product catalog. Use for product-related queries.',
			)->addProperty(
				new ToolProperty( 'query', 'string', 'The user’s search terms.', true )
			)->addProperty(
				new ToolProperty( 'limit', 'integer', 'Maximum number of products to return.', false )
			)->setCallable(
				array( $this->handler, 'search_woocommerce_products' )
			),

			// Add to Cart Tool
			Tool::make(
				'add_to_cart',
				'Adds a product to the cart. Use only when explicitly requested.',
			)->addProperty(
				new ToolProperty( 'product_id', 'integer', 'The product ID.', false )
			)->addProperty(
				new ToolProperty( 'product_name', 'string', 'The exact product name.', false )
			)->addProperty(
				new ToolProperty( 'quantity', 'integer', 'The quantity to add.', false )
			)->setCallable(
				array( $this->handler, 'add_to_cart' )
			),

			// Cart Count Tool
			Tool::make(
				'cart_products_count',
				'Retrieves the total number of items in the cart.',
			)->setCallable(
				array( $this->handler, 'cart_products_count' )
			),

			// Empty Cart Tool
			Tool::make(
				'empty_cart',
				'Clears all items from the cart. Use only when explicitly requested.',
			)->setCallable(
				array( $this->handler, 'empty_cart' )
			),
		];
	}
}