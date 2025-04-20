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

// Define schema for intent classification
class UserIntent {
	#[SchemaProperty( description: 'The user intent: product_search, cart_action, blog_post, unknown' )]
	#[NotBlank]
	public string $intent;
}

// Supervisor Agent
class WC_Supervisor_Agent extends Agent {
	protected function provider(): AIProviderInterface {
		return new Ollama( 'http://localhost:11434/api', 'llama3.1:latest' );
	}

	public function instructions(): string {
		return new SystemPrompt(
			background: [
				'You are a supervisor AI agent responsible for classifying user intents in a WooCommerce store.',
			],
			steps: [
				'Determine whether the intent is product_search, cart_action, blog_post, or unknown.',
			]
		);
	}
}

abstract class WC_AI_Chat_Agent extends Agent {
	protected string $api_domain;
	protected string $model;
	protected mixed $handler;

	public function __construct( string $api_domain, string $model, mixed $handler = null ) {
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
				'num_ctx' => 30000,
			]
		);
	}
}


// Product Agent
class WC_Product_Agent extends WC_AI_Chat_Agent {
	public function instructions(): string {
		return new SystemPrompt(
			background: [
				'You assist users in finding products on this WooCommerce store.',
			],
			steps: [
				'Use the search_woocommerce_products tool to retrieve products.',
			],
			output: [
				'Use the ProductDisplay schema.',
			]
		);
	}

	protected function tools(): array {
		return [
			Tool::make( 'search_woocommerce_products', 'Search the WooCommerce catalog.' )
			    ->addProperty( new ToolProperty( 'query', 'string', 'Search terms', true ) )
			    ->addProperty( new ToolProperty( 'limit', 'integer', 'Limit', false ) )
			    ->setCallable( [ $this->handler, 'search_woocommerce_products' ] ),
		];
	}
}

// Cart Agent
class WC_Cart_Agent extends WC_AI_Chat_Agent {
	public function instructions(): string {
		return new SystemPrompt(
			background: [ 'You help users manage their WooCommerce cart.' ],
			steps: [
				'Use tools like add_to_cart, empty_cart, cart_products_count.',
			],
			output: [ 'Use the CartAction schema.' ]
		);
	}

	protected function tools(): array {
		return [
			Tool::make( 'add_to_cart', 'Adds product to cart.' )
			    ->addProperty( new ToolProperty( 'product_id', 'integer', 'Product ID', false ) )
			    ->addProperty( new ToolProperty( 'product_name', 'string', 'Product name', false ) )
			    ->addProperty( new ToolProperty( 'quantity', 'integer', 'Quantity', false ) )
			    ->setCallable( [ $this->handler, 'add_to_cart' ] ),

			Tool::make( 'empty_cart', 'Clears the cart.' )
			    ->setCallable( [ $this->handler, 'empty_cart' ] ),

			Tool::make( 'cart_products_count', 'Gets cart item count.' )
			    ->setCallable( [ $this->handler, 'cart_products_count' ] ),
		];
	}
}

// Blog Agent
class WC_Blog_Agent extends WC_AI_Chat_Agent {
	public function instructions(): string {
		return new SystemPrompt(
			background: [ 'You help users generate blog posts.' ],
			steps: [
				'Use the create_post tool for content generation.',
			]
		);
	}

	protected function tools(): array {
		return [
			Tool::make( 'create_post', 'Creates a blog post.' )
			    ->addProperty( new ToolProperty( 'post_name', 'string', 'Post title', true ) )
			    ->addProperty( new ToolProperty( 'content', 'string', 'Optional content', false ) )
			    ->setCallable( [ $this->handler, 'create_post' ] ),
		];
	}
}

// Main orchestrator
class WC_AI_OpenAI_Handler {
	private $agents;
	private $supervisor;

	public function __construct() {
		$options    = get_option( 'wc_ai_chat_settings' );
		$api_domain = $options['api_domain'] ?? 'http://localhost:11434/api';
		$model      = 'llama3.1:latest';

		$this->supervisor = new WC_Supervisor_Agent();

		$this->agents = [
			'product_search' => new WC_Product_Agent( $api_domain, $model, $this ),
			'cart_action'    => new WC_Cart_Agent( $api_domain, $model, $this ),
			'blog_post'      => new WC_Blog_Agent( $api_domain, $model, $this ),
		];
	}

	public function process_message( $message ) {
		$intentResult = $this->supervisor->structured( new UserMessage( $message ), UserIntent::class );
		$intent       = $intentResult->intent;

		if ( isset( $this->agents[ $intent ] ) ) {
			$agent    = $this->agents[ $intent ];
			$response = $agent->chat( new UserMessage( $message ) );
		} else {
			$response = $this->supervisor->chat( new UserMessage( $message ) );
		}

		$content = $response->getContent();

		return preg_match( '/^<.*>$/s', $content ) ? $content : "<p>$content</p>";
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