<?php

class WC_AI_OpenAI_Handler {
	private $api_key;
	private $model;
	private $api_domain;

	public function __construct() {
		$options          = get_option( 'wc_ai_chat_settings' );
		$this->api_key    = $options['api_key'] ?? '';
		$this->model      = 'llama3.2:3b';
		$this->api_domain = $options['api_domain'] ?? 'https://api.op/byeenai.com/v1';
	}

	public function process_message( $message ) {
		$functions    = $this->get_functions();
		$conversation = $this->initialize_conversation( $message );

		// First attempt - try to get a complete response
		$response = $this->make_openai_request( $conversation, $functions );

		// Handle tool calls if any
		if ( isset( $response['message']['tool_calls'] ) ) {
			return $this->handle_tool_calls( $response, $conversation, $functions );
		}

		return $response['message']['content'] ?? '<p>I couldn\'t process your request. Please try again.</p>';
	}

	private function make_openai_request( $messages, $functions = [] ) {
		$url  = trailingslashit( $this->api_domain ) . 'api/chat';
		$args = [
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'      => wp_json_encode( [
				'model'    => $this->model,
				'messages' => $messages,
				'stream'   => false,
				//				'raw'      => true,
				'tools'    => ! empty( $functions ) ? $functions : null,
			] ),
			'timeout'   => 160,
			'sslverify' => false,
		];

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'API Request Failed: ' . $response->get_error_message() );

			return [ 'message' => [ 'content' => 'Sorry, there was an error connecting to the AI service.' ] ];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			error_log( 'API Error: ' . print_r( $body['error'], true ) );

			return [ 'message' => [ 'content' => 'Sorry, the AI service returned an error.' ] ];
		}

		return $body;
	}

	private function initialize_conversation( $message ) {
		return [
			[
				'role'    => 'system',
				'content' => 'You are a helpful e-commerce assistant for a WooCommerce store. ' .
				             'You MUST respond in HTML format ONLY. ' .
				             'When showing products, format them as: ' .
				             '<div class="product">' .
				             '<div class="product-image"><img src="{image}" alt="{product_name}"></div>' .
				             '<h3><a href="{product_link}">{product_name}</a></h3>' .
				             '<p class="price">Price: {price}</p>' .
				             '<p class="description">{short_description}</p>' .
				             '</div>' .
				             'For cart actions, respond with: ' .
				             '<div class="cart-action">' .
				             '<p>{message}</p>' .
				             '<a href="/cart/" class="button">View Cart</a> ' .
				             '<a href="/checkout/" class="button">Checkout</a>' .
				             '</div>' .
				             'If no product found, aplogize and suggest searching again or rewrite if there is a typo, don\'t offer a product from your head.',
			],
			[
				'role'    => 'system',
				'content' => 'FUNCTION CALLING RULES: ' .
				             '1. FIRST search for products when customers ask about products. ' .
				             '2. When adding to cart: ' .
				             '   - If product ID is known, use it directly ' .
				             '   - If only name is known, FIRST find the ID by searching ' .
				             '   - NEVER add items without explicit consent ' .
				             '3. For product questions, ALWAYS return formatted HTML with product details ' .
				             '4. After cart actions, show cart status with links',
			],
			[ 'role' => 'user', 'content' => $message ],
		];
	}

	private function handle_tool_calls( $response, $conversation, $functions ) {
		$function_responses = [];

		foreach ( $response['message']['tool_calls'] as $tool_call ) {
			$function_name     = $tool_call['function']['name'];
			$function_args     = $tool_call['function']['arguments'];
			$function_response = $this->execute_function( $function_name, $function_args );

			$function_responses[] = [
				'role'    => 'tool',
				'name'    => $function_name,
				'content' => $function_response,
			];
		}

		// Prepare follow-up with all context
		$follow_up_messages = array_merge(
			$conversation,
			[
				[
					'role'       => 'assistant',
					'content'    => null,
					'tool_calls' => $response['message']['tool_calls'],
				],
			],
			$function_responses
		);

		// Get final response
		$final_response = $this->make_openai_request( $follow_up_messages, [] );

		// If the final response contains more tool calls (chained requests)
		if ( isset( $final_response['message']['tool_calls'] ) ) {
			return $this->handle_tool_calls( $final_response, $follow_up_messages, $functions );
		}

		return $final_response['message']['content'] ?? '<p>Sorry, I couldn\'t complete your request.</p>';
	}

	private function get_functions() {
		return [
			[
				'type'     => 'function',
				'function' => [
					'name'        => 'search_woocommerce_products',
					'description' => 'Search products by name or keywords. Returns array of products with IDs, names, prices, and links.',
					'parameters'  => [
						'type'       => 'object',
						'properties' => [
							'query' => [
								'type'        => 'string',
								'description' => 'Search terms to find matching products',
							],
							'limit' => [
								'type'        => 'integer',
								'description' => 'Max number of products to return',
								'default'     => 5,
							],
						],
						'required'   => [ 'query' ],
					],
				],
			],
			[
				'type'     => 'function',
				'function' => [
					'name'        => 'add_product_to_cart',
					'description' => 'Add product to cart using ID or name. If using name, will first search for matching product.',
					'parameters'  => [
						'type'       => 'object',
						'properties' => [
							'product_id'   => [
								'type'        => 'integer',
								'description' => 'Exact product ID to add',
							],
							'product_name' => [
								'type'        => 'string',
								'description' => 'Exact product name to add',
							],
							'quantity'     => [
								'type'    => 'integer',
								'default' => 1,
							],
						],
						'anyOf'      => [
							[ 'required' => [ 'product_id' ] ],
							[ 'required' => [ 'product_name' ] ],
						],
					],
				],
			],
			[
				'type'     => 'function',
				'function' => [
					'name'        => 'cart_products_count',
					'description' => 'Get the number of products in the cart',
				],
			],
		];
	}

	private function execute_function( $name, $args ) {
		switch ( $name ) {
			case 'search_woocommerce_products':
				return $this->search_products( $args );
			case 'add_product_to_cart':
				return $this->add_to_cart( $args );
			case 'cart_products_count':
				return $this->cart_products_count( $args );
			default:
				return json_encode( [ 'error' => 'Function not found' ] );
		}
	}

	private function cart_products_count() {
		$count = WC()->cart->get_cart_contents_count();

		return $count;
	}

	private function search_products( $args ) {
		$args     = wp_parse_args( $args, [ 'limit' => 5 ] );
		$products = wc_get_products( [
			'status' => 'publish',
			'limit'  => $args['limit'],
			's'      => $args['query'],
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

	private function add_to_cart( $args ) {
		$args = wp_parse_args( $args, [ 'quantity' => 1 ] );

		// If we have a name but no ID, search for the product first
		if ( isset( $args['product_name'] ) && ! isset( $args['product_id'] ) ) {
			$products = wc_get_products( [
				'status' => 'publish',
				'limit'  => 1,
				'name'   => $args['product_name'],
			] );

			if ( ! empty( $products ) ) {
				$args['product_id'] = $products[0]->get_id();
			} else {
				return json_encode( [
					'success' => false,
					'message' => 'Product not found',
				] );
			}
		}

		if ( isset( $args['product_id'] ) ) {
			WC()->cart->add_to_cart( $args['product_id'], $args['quantity'] );

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

	private function dd( $data, $file_append = false ) {
		file_put_contents( plugin_dir_path( __FILE__ ) . 'log.txt', print_r( $data, true ) );
	}
}