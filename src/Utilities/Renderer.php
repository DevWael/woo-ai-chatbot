<?php

namespace WoocommerceAIChatbot\Utilities;

/**
 * Class Renderer
 * Handles rendering of various types of data into HTML.
 */
class Renderer {

	/**
	 * @var array The data to be rendered.
	 */
	private array $data;

	/**
	 * Renderer constructor.
	 *
	 * @param array $data The data to be rendered.
	 */
	public function __construct( $data ) {
		$this->data = $data;
	}

	/**
	 * Renders the main HTML structure based on the provided data.
	 *
	 * @return string The rendered HTML.
	 */
	public function render() {
		$html = '<div class="ai-response">';

		if ( ! empty( $this->data ) ) {
			foreach ( $this->data as $item ) {
				if ( $item['type'] === 'products_search' ) {
					$html .= $this->products_search( $item );
				} elseif ( $item['type'] === 'add_to_cart' ) {
					$html .= $this->add_to_cart( $item );
				} elseif ( $item['type'] === 'cart_products_count' ) {
					$html .= $this->cart_products_count( $item );
				} elseif ( $item['type'] === 'clear_cart' ) {
					$html .= $this->clear_cart( $item );
				} elseif ( $item['type'] === 'create_post' ) {
					$html .= $this->create_post( $item );
				}
			}
		} else {
			$html .= '<div>Nah! I\'m still learning!</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Renders a list of products from a search result.
	 *
	 * @param array $data The search result data containing product IDs.
	 * @return string The rendered HTML for product search results.
	 */
	public function products_search( $data ) {
		$html     = '<div class="products-search">';
		$products = $data['results'] ?? [];

		if ( ! empty( $products ) ) {
			$html .= '<h3>Found products</h3><ul class="products-list">';
			foreach ( $products as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$html .= '<li class="product-item">';
					$html .= '<div class="product-image">';
					$html .= '<a href="' . esc_url( $product->get_permalink() ) . '" class="product-name">';
					$html .= $product->get_image( 'thumbnail' );
					$html .= '</a>';
					$html .= '</div>';
					$html .= '<div class="product-details">';
					$html .= '<a href="' . esc_url( $product->get_permalink() ) . '" class="product-name">';
					$html .= esc_html( wp_trim_words( $product->get_name(), 5 ) );
					$html .= '</a>';
					$html .= '<span class="product-price">' . $product->get_price_html() . '</span>';
					$html .= '<div class="product-actions">';
					$html .= '<a href="' . esc_url( $product->add_to_cart_url() ) . '" class="add-to-cart-btn" data-product_id="' . esc_attr( $product_id ) . '">Add to Cart</a>';
					$html .= '</div>';
					$html .= '</div>';
					$html .= '</li>';
				}
			}
			$html .= '</ul>';
		} else {
			$html .= '<p>No products found.</p>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Renders the result of adding a product to the cart.
	 *
	 * @param array $data The data containing add to cart result and URLs.
	 * @return string The rendered HTML for add to cart response.
	 */
	public function add_to_cart( $data ) {
		$html = '<div class="add-to-cart">';
		if ( $data['results'] === 'success' ) {
			$html .= '<p class="success-message">' . esc_html( $data['message'] ) . '</p>';
			$html .= '<div class="cart-links">';
			$html .= '<a href="' . esc_url( $data['cart_url'] ) . '" class="cart-link">View Cart</a>';
			$html .= '<a href="' . esc_url( $data['checkout_url'] ) . '" class="checkout-link">Proceed to Checkout</a>';
			$html .= '</div>';
		} else {
			$html .= '<p class="error-message">Failed to add product to cart.</p>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Renders the current count of items in the cart.
	 *
	 * @param array $data The data containing the cart items count.
	 * @return string The rendered HTML for cart products count.
	 */
	public function cart_products_count( $data ) {
		$html  = '<div class="cart-products-count">';
		$count = (int) $data['results'];
		$html  .= '<div>Items in cart: <span class="count">' . esc_html( $count ) . '</span></div>';
		$html  .= '</div>';

		return $html;
	}

	/**
	 * Renders a message indicating the cart has been cleared.
	 *
	 * @param array $data The data related to clearing the cart.
	 * @return string The rendered HTML for cart clearance message.
	 */
	public function clear_cart( $data ) {
		return '<div class="clear-cart"><p>Your cart has been cleared.</p></div>';
	}

	/**
	 * Renders a newly created post or an error message if creation failed.
	 *
	 * @param array $data The data containing post creation result and details.
	 * @return string The rendered HTML for post creation response.
	 */
	public function create_post( $data ) {
		$html = '';

		if ( $data['results'] === 'success' ) {
			$html .= '<div class="create-post">';
			$html .= '<h3>';
			$html .= '<a href="' . esc_url( $data['url'] ) . '" target="_blank">';
			$html .= esc_html( $data['title'] );
			$html .= '</a>';
			$html .= '</h3>';
			$html .= '<div>' . wp_kses_post( $data['content'] ) . '</div>';
			$html .= '</div>';
		} else {
			$html .= '<div class="create-post-error">';
			$html .= '<p>Error creating post: ' . esc_html( $data['message'] ) . '</p>';
			$html .= '</div>';
		}

		return $html;
	}
}