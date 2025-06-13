<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Functions;

use WoocommerceAIChatbot\Providers\Embeddings_Provider;
use WoocommerceAIChatbot\Storage\Data_Storage;

defined( '\ABSPATH' ) || exit;

class ProductsSearch {
	public function search_products( $query, $limit = 2 ) {
		$products = $this->vector_search( $query, (int) $limit );

		$results = [];
		foreach ( $products as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			$results[] = $product->get_id();
		}

		return wp_json_encode(array(
			'type'    => 'products_search',
			'results' => $results,
		));
	}

	public function vector_search( $query, int $limit = 5 ) {
		$embeddings = Embeddings_Provider::provider()->embedText( $query );

		$storage = new Data_Storage( WC_AI_CHATBOT_PLUGIN_DIR );

		$results = $storage->search( $embeddings );

		return array_slice( array_column( $results, 'id' ), 0, $limit );
	}
}