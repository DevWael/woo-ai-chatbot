<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Functions;

use WoocommerceAIChatbot\Providers\Embeddings_Provider;
use WoocommerceAIChatbot\Storage\Data_Storage;

defined( '\ABSPATH' ) || exit;

class ProductsSearch {
	public function search_woocommerce_products( $query, $limit = 2 ) {
		$products = $this->vector_search( $query, (int) $limit );

		$results = [];
		foreach ( $products as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			$results[] = $this->html( $product );
		}

		return '<div class="found-products">' . implode( '' , $results ) . '</div>';
	}

	public function vector_search( $query, int $limit = 5 ) {
		$embeddings = Embeddings_Provider::provider()->embedText( $query );

		$storage = new Data_Storage( WC_AI_CHATBOT_PLUGIN_DIR );

		$results = $storage->search( $embeddings );

		return array_slice( array_column( $results, 'id' ), 0, $limit );
	}

	private function html( $product ) {
        /* @var \WC_Product $product */
		ob_start();
		?>
        <div class="product">
            <a href="<?php
			echo esc_url( $product->get_permalink() ); ?>">
                <?php echo $product->get_image() ?>
                <h2><?php
					echo esc_html( $product->get_name() ); ?></h2>
                <span><?php
					echo $product->get_price_html(); ?></span>
            </a>
        </div>
		<?php
		$ss =  ob_get_clean();
        file_put_contents(plugin_dir_path(__FILE__) . 'products.txt', $ss, FILE_APPEND);

        return $ss;
	}
}