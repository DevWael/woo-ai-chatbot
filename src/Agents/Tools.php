<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Agents;

use NeuronAI\Tools\PropertyType;
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
			'Creates a new post, story, or articles.'
		)->addProperty(
			new ToolProperty( 'post_name', PropertyType::STRING, 'The title of the post.', true )
		)->addProperty(
			new ToolProperty( 'content', PropertyType::STRING, 'The body content of the post.', true )
		)->setCallable(
			array( $instance, 'create_post' )
		);
	}

	public static function products_search() {
		$instance = new ProductsSearch();

		return Tool::make(
			'search_products',
			'Searches the catalog for a single product term. – use only with one item per call.',
		)->addProperty(
			new ToolProperty( 'query', PropertyType::STRING, 'The user’s search terms.', true )
		)->addProperty(
			new ToolProperty( 'limit', PropertyType::INTEGER, 'Exact number of matches to return.' )
		)->setCallable(
			array( $instance, 'search_products' )
		);
	}

	public static function add_to_cart() {
		$instance = new CartController();

		return Tool::make(
			'add_to_cart',
			'Adds a specified product to the user’s cart. Use only when the user explicitly requests to add a product.'
		)->addProperty(
			new ToolProperty( 'product_name', PropertyType::STRING, 'The exact name of the product for verification.', false )
		)->addProperty(
			new ToolProperty( 'quantity', PropertyType::INTEGER, 'The number of items to add (default: 1).', false )
		)->setCallable(
			array( $instance, 'add_to_cart' )
		);
	}

	public static function cart_products_count() {
		$instance = new CartController();

		return Tool::make(
			'cart_products_count',
			'Get the total number of items currently in the user’s cart.'
		)->setCallable(
			array( $instance, 'cart_products_count' )
		);
	}

	public static function empty_cart() {
		$instance = new CartController();

		return Tool::make(
			'empty_cart',
			'Removes all items from the user’s cart. Use only when the USER explicitly request.'
		)->setCallable(
			array( $instance, 'empty_cart' )
		);
	}
}