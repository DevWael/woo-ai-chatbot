<?php

namespace WoocommerceAIChatbot\CLI;

use WP_CLI;
use WP_CLI\Utils;
use WoocommerceAIChatbot\Utilities\Products_Indexer;
use WoocommerceAIChatbot\Storage\Data_Storage;

class Products_Indexer_Command {
	private Products_Indexer $indexer;

	public function __construct() {
		$storage       = new Data_Storage( WC_AI_CHATBOT_PLUGIN_DIR );
		$this->indexer = new Products_Indexer( $storage );
	}

	public function index( $args, $assoc_args ) {
		$total_chunks = $this->indexer->get_total_chunks();

		WP_CLI::log( "Total chunks to process: {$total_chunks}" );

		$progress = Utils\make_progress_bar( 'Indexing products...', $total_chunks );

		for ( $i = 1; $i <= $total_chunks; $i ++ ) {
			$success = $this->indexer->index_product_chunk( $i );

			if ( $success ) {
				$progress->tick();
				WP_CLI::success( "Chunk {$i}/{$total_chunks} indexed successfully" );
			} else {
				$progress->tick();
				WP_CLI::warning( "No products found in chunk {$i}" );
			}
		}

		$progress->finish();
		WP_CLI::success( 'Product indexing completed' );
	}

	public function load_hooks() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'wc-ai index-products', [ $this, 'index' ] );
		}
	}
}