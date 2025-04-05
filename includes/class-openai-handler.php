<?php

use LLPhant\Chat\FunctionInfo\FunctionInfo;
use LLPhant\Chat\OllamaChat;
use LLPhant\Chat\FunctionInfo\Parameter;
use LLPhant\Chat\Message;

class WC_AI_OpenAI_Handler {
	private $chat;
	private $model;
	private $api_domain;

	public function __construct() {
		$options          = get_option( 'wc_ai_chat_settings' );
		$api_key          = $options['api_key'] ?? '';
		$this->model      = 'llama3.2:3b';
		$this->api_domain = $options['api_domain'];

		// Initialize LLPhant's OpenAIChat with configuration
		$config = new \LLPhant\OllamaConfig();
//		$config->apiKey = $api_key;
		$config->url          = $this->api_domain;
		$config->model        = $this->model;
		$config->modelOptions = array(
			'top_k'       => 55,
			'top_p'       => 0.6,
			'temperature' => 0.3,
			'num_ctx'     => 30000,
		);
		$this->chat           = new OllamaChat( $config );

		// Set system message
		$systemMessage = "You are a friendly and helpful e-commerce assistant for this WooCommerce store. " .
		                 "Your primary goal is to help users find products and manage their shopping cart. " .
		                 "You MUST respond ONLY in HTML format. \n" .
		                 "--- HTML Formatting Rules ---\n" .
		                 "When displaying products (e.g., after a search), use this exact structure for each product:\n" .
		                 "<div class=\"product\">\n" .
		                 "  <div class=\"product-image\"><img src=\"{image_url}\" alt=\"{product_name}\"></div>\n" .
		                 "  <h3><a href=\"{product_link}\">{product_name}</a></h3>\n" .
		                 "  <p class=\"price\">Price: {price}</p>\n" .
		                 "  <p class=\"description\">{short_description}</p>\n" .
		                 "</div>\n" .
		                 "When confirming a cart action (like adding an item), use this exact structure:\n" .
		                 "<div class=\"cart-action\">\n" .
		                 "  <p>{confirmation_message}</p>\n" .
		                 "  <a href=\"/cart/\" class=\"button\">View Cart</a> \n" .
		                 "  <a href=\"/checkout/\" class=\"button\">Checkout</a>\n" .
		                 "</div>\n" .
		                 "--- General Guidelines ---\n" .
		                 " - If a user asks to find products, use the available search tool.\n" .
		                 " - If a search yields no results, apologize politely and suggest revising the search query (e.g., check spelling, try different terms). Do not invent products.\n";

		$this->chat->setSystemMessage( $systemMessage );

		// Add functions
		$this->addFunctions();
	}

	public function process_message( $message ) {
		// Create user message
		$user_message = new Message();

		// Generate response
		return $this->chat->generateChat( [ $user_message::user( $message ) ] );
	}

	private function addFunctions() {
		// Create post function
		$post_name          = new Parameter( 'post_name', 'string', "The name of the post to be created." );
		$content            = new Parameter( 'content', 'string', "The content of the post.", array() );
		$createPostFunction = new FunctionInfo(
			'create_post',
			$this,
			"create a new post in the store. Use this function only when the user explicitly asks to create a new post or product.",
			[
				$post_name,
				$content,
			]
		);
		$this->chat->addTool( $createPostFunction );

		$query = new Parameter( 'query', 'string', "The user's search terms" );
		$limit = new Parameter( 'limit', 'integer', "Maximum number of products to return" );
		// Search products function
		$searchProductsFunction = new FunctionInfo(
			'search_woocommerce_products',
			$this,
			"Searches the WooCommerce product catalog. Automatically triggered for any product-related queries including: " .
			"product searches, category browsing, feature requests, or general shopping inquiries.",
			[
				$query,
				$limit,
			],
			[
				$query,
			]
		);
		$this->chat->addTool( $searchProductsFunction );

		$product_id   = new Parameter( 'product_id', 'integer', "The product ID", array() );
		$product_name = new Parameter( 'product_name', 'string', "The exact product name to add", array() );
		$quantity     = new Parameter( 'quantity', 'integer', "The quantity to add", array(), 1 );
		// Add to cart function
		$addToCartFunction = new FunctionInfo(
			'add_to_cart',
			$this,
			"Adds a specific product to the user's shopping cart. Use this *only* after a user explicitly confirms they want to add an item, or directly asks to add a specific item. Requires identifying the product using *either* its unique `product_id` (preferred, usually obtained from a previous `search_woocommerce_products` call) *or* the exact `product_name`. If only `product_name` is provided, the system will first attempt to find the corresponding `product_id`. Returns a confirmation message upon success.",
			[
				$product_id,
				$product_name,
				$quantity,
			]
		);
		$this->chat->addTool( $addToCartFunction );

		// Cart count function
		$cartCountFunction = new FunctionInfo(
			'cart_products_count',
			$this,
			"Retrieves the current total number of individual items in the user's shopping cart.",
			array()
		);
		$this->chat->addTool( $cartCountFunction );

		// Empty cart function
		$emptyCartFunction = new FunctionInfo(
			'empty_cart',
			$this,
			"Completely clears all items from the shopping cart. Only invoke when the customer explicitly requests cart clearance.",
			array()
		);
		$this->chat->addTool( $emptyCartFunction );
	}

	// Function implementations remain the same as in the original class
	public function create_post( $post_name, $content = '' ) {
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

		return json_encode( $count ); // Simplified for example
	}

	public function empty_cart() {
		WC()->cart->empty_cart();

		return 'done';
	}

	public function search_woocommerce_products( $query, $limit = 5 ) {
//		$args     = wp_parse_args( $args, [ 'limit' => 5 ] );
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
}