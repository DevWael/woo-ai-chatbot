<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot;

use NeuronAI\Chat\Messages\UserMessage;
use WoocommerceAIChatbot\Agents\Chat_Agent;

defined( '\ABSPATH' ) || exit;

class AI_Handler {

	private $agent;
	private $model;
	private $api_domain;

	public function __construct() {
		$options          = get_option( 'wc_ai_chat_settings' );
		$api_key          = $options['api_key'] ?? '';
		$this->model      = 'llama3.1:latest';
		$this->api_domain = $options['api_domain'] ?? 'http://localhost:11434';

		// Initialize Neuron AI Agent
		$this->agent = new Chat_Agent( $this->api_domain, $this->model, $this );
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
				return wp_json_encode( [
					'success' => false,
					'message' => 'Product not found',
				] );
			}
		}

		if ( ! empty( $product_id ) ) {
			WC()->cart->add_to_cart( $product_id, $quantity );

			return wp_json_encode( [
				'success'      => true,
				'message'      => 'Product added to cart',
				'cart_url'     => wc_get_cart_url(),
				'checkout_url' => wc_get_checkout_url(),
			] );
		}

		return wp_json_encode( [
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