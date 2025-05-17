<?php

namespace WoocommerceAIChatbot\Utilities;

use WoocommerceAIChatbot\Storage\Storage;
use WC_Product;

class Products_Indexer {
	private Storage $data_storage;
	private string $directory;
	private int $chunk_size;

	public function __construct( Storage $data_storage, string $directory = '', int $chunk_size = 50 ) {
		$this->data_storage = $data_storage;
		$this->directory    = $directory ?: plugin_dir_path( __FILE__ );
		$this->chunk_size   = $chunk_size;
		$this->create_dir( $this->directory );
	}

	public function create_dir( $dir ) {
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
	}

	public function get_total_chunks(): int {
		$total_products = wp_count_posts( 'product' )->publish;

		return (int) ceil( $total_products / $this->chunk_size );
	}

	public function index_product_chunk( int $chunk_number ): bool {
		$offset = ( $chunk_number - 1 ) * $this->chunk_size;

		$products = wc_get_products( [
			'status'  => 'publish',
			'limit'   => $this->chunk_size,
			'offset'  => $offset,
			'orderby' => 'ID',
			'order'   => 'ASC',
		] );

		if ( empty( $products ) ) {
			return false;
		}

		$product_data = array_map( [ $this, 'prepare_product_data' ], $products );
		$this->data_storage->supply_data( $product_data );
		$this->data_storage->store();

		return true;
	}

	private function prepare_product_data( WC_Product $product ): array {
		$categories = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
		$tags       = wp_get_post_terms( $product->get_id(), 'product_tag', [ 'fields' => 'names' ] );

		$variations = '';
		if ( $product->is_type( 'variable' ) ) {
			$variations_array = array_map( function ( $variation ) {
				$attributes = isset( $variation['attributes'] ) ? $variation['attributes'] : [];

				return implode( ', ', array_filter( $attributes ) );
			}, $product->get_available_variations() );
			$variations       = implode( '; ', $variations_array );
		}

		return array(
			'id'      => $product->get_id(),
			'content' => implode( ' | ', array_filter( [
				$product->get_name(),
				$product->get_description(),
				implode( ', ', $categories ),
				$product->get_short_description(),
				implode( ', ', $tags ),
				$variations,
			] ) ),
		);
	}
}