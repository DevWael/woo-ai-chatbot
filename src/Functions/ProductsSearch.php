<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Functions;

defined( '\ABSPATH' ) || exit;

class ProductsSearch {
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

		return wp_json_encode( $result );
	}
}