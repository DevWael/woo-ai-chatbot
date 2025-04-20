<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Functions;

defined( '\ABSPATH' ) || exit;

class CartController {

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

	public function cart_products_count() {
		$count = WC()->cart->get_cart_contents_count();

		return wp_json_encode( $count );
	}

	public function empty_cart() {
		WC()->cart->empty_cart();

		return 'done';
	}
}