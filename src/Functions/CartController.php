<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Functions;

defined( '\ABSPATH' ) || exit;

class CartController {

	public function add_to_cart( $product_name = '', $quantity = 1 ) {
		if ( $product_name ) {
			$products = wc_get_products( [
				'status' => 'publish',
				'limit'  => 1,
				'name'   => $product_name,
			] );

			if ( ! empty( $products ) ) {
				$product_id = $products[0]->get_id();
			} else {
				return wp_json_encode( [
					'type'    => 'add_to_cart',
					'results' => 'fail',
					'message' => 'Product not found',
				] );
			}
		}

		if ( ! empty( $product_id ) ) {
			WC()->cart->add_to_cart( $product_id, $quantity );

			return wp_json_encode( [
				'type'         => 'add_to_cart',
				'results'      => 'success',
				'product_id'   => $product_id,
				'message'      => 'Product added to cart',
				'cart_url'     => wc_get_cart_url(),
				'checkout_url' => wc_get_checkout_url(),
			] );
		}

		return wp_json_encode( [
			'type'    => 'add_to_cart',
			'results' => 'fail',
			'message' => 'Missing product identifier',
		] );
	}

	public function cart_products_count() {
		$count = WC()->cart->get_cart_contents_count();

		return wp_json_encode( [
			'type'    => 'cart_products_count',
			'results' => $count,
		] );
	}

	public function empty_cart() {
		WC()->cart->empty_cart();

		return wp_json_encode( array(
			'type'    => 'clear_cart',
			'results' => 'done',
		) );
	}
}