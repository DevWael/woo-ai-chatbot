<?php

use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\StructuredOutput\SchemaProperty;
use Symfony\Component\Validator\Constraints\NotBlank;

// Define structured output schemas
if ( ! class_exists( 'ProductDisplay' ) ) {
	class ProductDisplay {
		#[SchemaProperty( description: 'The product image URL.', required: true )]
		#[NotBlank]
		public string $image_url;

		#[SchemaProperty( description: 'The product name.', required: true )]
		#[NotBlank]
		public string $product_name;

		#[SchemaProperty( description: 'The product link.', required: true )]
		#[NotBlank]
		public string $product_link;

		#[SchemaProperty( description: 'The product price.', required: true )]
		#[NotBlank]
		public string $price;

		#[SchemaProperty( description: 'The product short description.', required: true )]
		#[NotBlank]
		public string $short_description;
	}
}

if ( ! class_exists( 'CartAction' ) ) {
	class CartAction {
		#[SchemaProperty( description: 'The confirmation message.', required: true )]
		#[NotBlank]
		public string $confirmation_message;

		#[SchemaProperty( description: 'The cart URL.', required: true )]
		#[NotBlank]
		public string $cart_url;

		#[SchemaProperty( description: 'The checkout URL.', required: true )]
		#[NotBlank]
		public string $checkout_url;
	}
}

// Define the Neuron AI Agent class
class WC_AI_Chat_Agent extends Agent {
	private $api_domain;
	private $model;
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
				'top_k'       => 55,
				'top_p'       => 0.6,
//				'temperature' => 0.3,
				'num_ctx'     => 30000,
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

class WC_AI_OpenAI_Handler {
	private $agent;
	private $model;
	private $api_domain;

	public function __construct() {
		$options          = get_option( 'wc_ai_chat_settings' );
		$api_key          = $options['api_key'] ?? '';
		$this->model      = 'llama3.1:latest';
		$this->api_domain = $options['api_domain'] ?? 'http://localhost:11434';

		// Initialize Neuron AI Agent
		$this->agent = new WC_AI_Chat_Agent( $this->api_domain, $this->model, $this );
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

	public function create_post( $post_name, $content = '' ) {
		if ( empty( $content ) ) {
			$content = $this->generate_post_content( $post_name );
		}

		$post_id = wp_insert_post( [
			'post_title'   => $post_name,
			'post_content' => $content,
			'post_status'  => 'publish',
		] );

		if ( is_wp_error( $post_id ) ) {
			return json_encode( [
				'success' => false,
				'message' => 'Failed to create post',
			] );
		}

		return json_encode( [
			'success' => true,
			'post_id' => $post_id,
			'message' => 'Post created successfully',
		] );
	}

	public function cart_products_count() {
		$count = WC()->cart->get_cart_contents_count();

		return json_encode( $count );
	}

	public function empty_cart() {
		WC()->cart->empty_cart();

		return 'done';
	}

	public function search_woocommerce_products( $query, $limit = 5 ) {
		$products = wc_get_products( [
			'status' => 'publish',
			'limit'  => $limit,
			's'      => $query,
		] );

		$result = [];
		foreach ( $products as $product ) {
			$result[] = [
				'id'          => $product->get_id(),
				'name'        => $product->get_name(),
				'price'       => wc_price( $product->get_price() ),
				'description' => $product->get_short_description(),
				'link'        => get_permalink( $product->get_id() ),
				'image'       => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ),
			];
		}

		return json_encode( $result );
	}

	public function add_to_cart( $product_id = 0, $product_name = '', $quantity = 1 ) {
		if ( $product_name && empty( $product_id ) ) {
			$products = wc_get_products( [
				'status' => 'publish',
				'limit'  => 1,
				'name'   => $product_name,
			] );

			if ( ! empty( $products ) ) {
				$product_id = $products[0]->get_id();
			} else {
				return json_encode( [
					'success' => false,
					'message' => 'Product not found',
				] );
			}
		}

		if ( ! empty( $product_id ) ) {
			WC()->cart->add_to_cart( $product_id, $quantity );

			return json_encode( [
				'success'      => true,
				'message'      => 'Product added to cart',
				'cart_url'     => wc_get_cart_url(),
				'checkout_url' => wc_get_checkout_url(),
			] );
		}

		return json_encode( [
			'success' => false,
			'message' => 'Missing product identifier',
		] );
	}

	private function generate_post_content( $title ) {
		$prompt = "Generate a detailed, long, 2000-word, SEO-friendly blog post content based on the following title: \"{$title}\". " .
		          "The content should be well-structured with paragraphs, headings, and bullet points where appropriate. " .
		          "Write in a professional but engaging tone. Format the response in proper HTML with <p> tags for paragraphs " .
		          "and appropriate heading tags (<h2>, <h3>) for sections.";

		$response = $this->agent->chat( new UserMessage( $prompt ) );

		return $response->getContent();
	}
}