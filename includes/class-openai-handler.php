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
		$this->model      = 'llama3.1:latest';
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
		$systemMessage = "You are an AI-powered e-commerce assistant for this WooCommerce store. Your primary responsibilities are to:
			1. Help customers discover products using our catalog data
			2. Assist with cart management
			3. Provide accurate, helpful information while maintaining brand voice
			
			<strong>Response Format:</strong>
			- All responses MUST be in valid HTML format
			- For product-related content, generate detailed, structured information based on provided data
			
			<strong>HTML Templates:</strong>
			
			<b>Product Display Template (use for search results):</b>
			<div class=\"product\">
			  <div class=\"product-image\"><img src=\"{image_url}\" alt=\"{product_name}\"></div>
			  <h3><a href=\"{product_link}\">{product_name}</a></h3>
			  <p class=\"price\">Price: {price}</p>
			  <p class=\"description\">{short_description}</p>
			  <a href=\"{product_link}\" class=\"button\">View Details</a>
			</div>
			
			<b>Cart Action Template:</b>
			<div class=\"cart-action\">
			  <p>{confirmation_message}</p>
			  <div class=\"action-buttons\">
			    <a href=\"/cart/\" class=\"button\">View Cart</a>
			    <a href=\"/checkout/\" class=\"button\">Proceed to Checkout</a>
			  </div>
			</div>
			
			<strong>Operational Guidelines:</strong>
			1. <b>Product Discovery:</b>
			   - Always use the search tool for product queries
			   - For empty results: Politely suggest refining search terms
			   - Never invent products or specifications
			
			2. <b>User Experience:</b>
			   - Maintain a friendly, professional tone
			   - Focus responses on available products and services
			   - Direct users to appropriate self-service options
			
			3. <b>Data Integrity:</b>
			   - Only reference existing products from our catalog
			   - Clearly indicate when information is unavailable";

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
			"product searches, category browsing, feature requests, or general shopping inquiries. You can try the plural and singular forms of the product name.",
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

		return json_encode( $count ); // Simplified for example
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
		// Create a specific prompt for content generation
		$prompt = "Generate a detailed, long, 2000 words, SEO-friendly blog post content based on the following title: \"{$title}\". " .
		          "The content should be well-structured with paragraphs, headings, and bullet points where appropriate. " .
		          "Write in a professional but engaging tone. Format the response in proper HTML with <p> tags for paragraphs " .
		          "and appropriate heading tags (<h2>, <h3>) for sections.";

		// Generate the content using the chat
		$response = $this->chat->generateChat( [ Message::user( $prompt ) ] );

		return $response;
	}
}