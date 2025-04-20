<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Agents;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use WoocommerceAIChatbot\Functions\CartController;
use WoocommerceAIChatbot\Functions\CreatePost;
use WoocommerceAIChatbot\Functions\ProductsSearch;

defined( '\ABSPATH' ) || exit;

class Tools {
	public static function create_post() {
		$instance = new CreatePost();

		return Tool::make(
			'create_post',
			'Create a new post in the store. Use only when explicitly requested.',
		)->addProperty(
			new ToolProperty( 'post_name', 'string', 'The name of the post to be created.', true )
		)->addProperty(
			new ToolProperty( 'content', 'string', 'The content of the post.', true )
		)->setCallable(
			array( $instance, 'create_post' )
		);
	}

	public static function products_search() {
		$instance = new ProductsSearch();

		return Tool::make(
			'search_woocommerce_products',
			'Searches the WooCommerce product catalog. Use for product-related queries.',
		)->addProperty(
			new ToolProperty( 'query', 'string', 'The user’s search terms.', true )
		)->addProperty(
			new ToolProperty( 'limit', 'integer', 'Maximum number of products to return.', false )
		)->setCallable(
			array( $instance, 'search_woocommerce_products' )
		);
	}

	public static function add_to_cart() {
		$instance = new CartController();

		return Tool::make(
			'add_to_cart',
			'Adds a product to the cart. Use only when explicitly requested.',
		)->addProperty(
			new ToolProperty( 'product_id', 'integer', 'The product ID.', false )
		)->addProperty(
			new ToolProperty( 'product_name', 'string', 'The exact product name.', false )
		)->addProperty(
			new ToolProperty( 'quantity', 'integer', 'The quantity to add.', false )
		)->setCallable(
			array( $instance, 'add_to_cart' )
		);
	}

	public static function cart_products_count() {
		$instance = new CartController();

		return Tool::make(
			'cart_products_count',
			'Retrieves the total number of items in the cart.',
		)->setCallable(
			array( $instance, 'cart_products_count' )
		);
	}

	public static function empty_cart() {
		$instance = new CartController();

		return Tool::make(
			'empty_cart',
			'Clears all items from the cart. Use only when explicitly requested.',
		)->setCallable(
			array( $instance, 'empty_cart' )
		);
	}
}