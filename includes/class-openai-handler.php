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
		$functions = $this->get_functions();

		$messages = [
			[
				'role'    => 'system',
				'content' => 'You are a helpful e-commerce assistant for a WooCommerce store. ' .
				             'You MUST respond in HTML format ONLY. ' .
				             'When showing products, format them as: ' .
				             '<div class="product">' .
				             '<h3><a href="{product_link}">{product_name}</a></h3>' .
				             '<div class="product-image">{image}</div>' .
				             '<p>Price: {price}</p>' .
				             '<p>{short_description}</p>' .
				             '<button class="add-to-cart" data-product-id="{product_id}">Add to Cart</button>' .
				             '</div>' .
				             'Always link product names to their pages using <a> tags. ' .
				             'For cart actions, include buttons with data-product-id attributes. ' .
				             'Never respond with plain text - always use proper HTML markup.',
			],
			[
				'role'    => 'system',
				'content' => 'IMPORTANT FUNCTION CALLING RULES: ' .
				             '1. FIRST search for products using `search_woocommerce_products` when customers ask about products. ' .
				             '2. Use `add_product_to_cart` ONLY when: ' .
				             '   - Customer explicitly says "add to cart" or "buy this" ' .
				             '   - Customer clicks an "Add to Cart" button ' .
				             '   - Customer agrees to your suggestion to add a product ' .
				             '3. NEVER add items to cart without explicit consent. ' .
				             '4. When showing products, always include: name, price, description, and Add to Cart button. ' .
				             '5. After adding to cart, confirm with: ' .
				             '   "<p>Product added to cart! <a href="/cart/">View Cart</a> or <a href="/checkout/">Proceed to Checkout</a></p>"',
			],
			[
				'role'    => 'system',
				'content' => 'EXAMPLE INTERACTIONS: ' .
				             'USER: "Show me blue jeans" ' .
				             'ASSISTANT: Product results in HTML format with Add to Cart buttons ' .
				             'USER: "Add the Levi\'s 501 to my cart" ' .
				             'ASSISTANT: Calls add_product_to_cart function ' .
				             'USER: "I want to buy this" (referring to a product) ' .
				             'ASSISTANT: Calls add_product_to_cart function ' .
				             'USER: "Yes, add it" (after your suggestion) ' .
				             'ASSISTANT: Calls add_product_to_cart function',
			],
			[ 'role' => 'user', 'content' => $message ],
		];

		// Make the initial request
		$response = $this->make_openai_request( $messages, $functions );

		// Check for tool calls in the response
		if ( isset( $response['message']['tool_calls'] ) && ! empty( $response['message']['tool_calls'] ) ) {
			$function_call = $response['message']['tool_calls'][0]['function'];
			$function_name = $function_call['name'];
			$function_args = $function_call['arguments'];

			// Execute the function
			$function_response = $this->execute_function( $function_name, $function_args );

			// Prepare messages for the follow-up request with full context
			$follow_up_messages   = $messages;
			$follow_up_messages[] = [
				'role'       => 'assistant',
				'content'    => null,
				'tool_calls' => $response['message']['tool_calls'],
			];
			$follow_up_messages[] = [
				'role'    => 'tool',
				'name'    => $function_name,
				'content' => $function_response,
			];
			$this->dd( $follow_up_messages );
			// Get the final response with function results
			$final_response = $this->make_openai_request( $follow_up_messages, [] );

			return $final_response['message']['content'] ? $final_response['message']['content'] : '<p>Sorry, I couldn\'t process your request. Please try again.</p>';
		}

		// If no function call, return the AI's direct response
		return $response['message']['content'] ?? 'I couldn\'t find any relevant products. Could you clarify your request?';
	}

	private function make_openai_request( $messages, $functions = [] ) {
		$url  = trailingslashit( $this->api_domain ) . 'api/chat';
		$args = [
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'      => wp_json_encode( [
				'model'      => $this->model,
				'messages'   => $messages,
				'stream'     => false,
				//				'raw'      => true,
				'tools'      => ! empty( $functions ) ? $functions : null,
			] ),
			'timeout'   => 160,
			'sslverify' => false,
		];

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'API Request Failed: ' . $response->get_error_message() );

			return [ 'message' => [ 'content' => 'Sorry, there was an error connecting to the AI service.' ] ];
		}

		file_put_contents( plugin_dir_path( __FILE__ ) . 'log.txt', print_r( json_decode( wp_remote_retrieve_body( $response ), true ), true ) . "\n", FILE_APPEND );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			error_log( 'API Error: ' . print_r( $body['error'], true ) );

			return [ 'message' => [ 'content' => 'Sorry, the AI service returned an error.' ] ];
		}

		return $body;
	}

	private function get_functions() {
		return [
			[
				'type'     => 'function',
				'function' => [
					'name'        => 'search_woocommerce_products',
					'description' => 'Searches the WooCommerce product catalog for items matching the given search terms.',
					'parameters'  => [
						'type'       => 'object',
						'properties' => [
							'query' => [
								'type'        => 'string',
								'description' => 'Keywords or phrases to search for in product names, descriptions, and attributes.',
							],
							'limit' => [
								'type'        => 'integer',
								'description' => 'The maximum number of products to return in the search results.',
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
					'description' => 'Adds a specific WooCommerce product to the customer\'s shopping cart.',
					'parameters'  => [
						'type'       => 'object',
						'properties' => [
							'product_id' => [
								'type'        => 'integer',
								'description' => 'The unique identifier of the product to add to cart.',
							],
							'quantity'   => [
								'type'        => 'integer',
								'description' => 'The number of units of this product to add to the cart.',
								'default'     => 1,
							],
						],
						'required'   => [ 'product_id' ],
					],
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
			default:
				return json_encode( [ 'error' => 'Function not found' ] );
		}
	}

	private function search_products( $args ) {
		$args = wp_parse_args( $args, [
			'limit' => 5,
		] );

		$products = wc_get_products( [
			'status' => 'publish',
			'limit'  => $args['limit'],
			's'      => $args['query'],
		] );

		$result = [];
		foreach ( $products as $product ) {
			$result[] = [
				'id'    => $product->get_id(),
				'name'  => $product->get_name(),
				'price' => wc_price( $product->get_price() ), // Format price properly
				//				'description' => $product->get_short_description(),
				'link'  => get_permalink( $product->get_id() ),
				'image' => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
			];
		}

		return json_encode( $result );
	}

	private function add_to_cart( $args ) {
		$args = wp_parse_args( $args, [
			'quantity' => 1,
		] );

		WC()->cart->add_to_cart( $args['product_id'], $args['quantity'] );

		return json_encode( [
			'success' => true,
			'message' => 'Product added to cart successfully',
		] );
	}

	private function dd( $data, $file_append = false ) {
		file_put_contents( plugin_dir_path( __FILE__ ) . 'log.txt', print_r( $data, true ) );
	}
}