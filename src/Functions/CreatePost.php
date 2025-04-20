<?php

declare( strict_types=1 );

namespace WoocommerceAIChatbot\Functions;

defined( '\ABSPATH' ) || exit;

class CreatePost {
	public function create_post( $post_name, $content = '' ) {
		$post_id = wp_insert_post( [
			'post_title'   => $post_name,
			'post_content' => $content,
			'post_status'  => 'publish',
		] );

		if ( is_wp_error( $post_id ) ) {
			return json_encode( [
				'success' => false,
				'message' => 'Failed to create post',
			] );
		}

		return json_encode( [
			'success' => true,
			'post_id' => $post_id,
			'message' => 'Post created successfully',
		] );
	}
}