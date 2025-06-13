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
			return wp_json_encode( [
				'type'    => 'create_post',
				'results' => 'fail',
				'message' => 'Failed to create post',
			] );
		}

		$post_content = get_post_field( 'post_content', $post_id );

		return wp_json_encode( [
			'type'    => 'create_post',
			'results' => 'success',
			'post_id' => $post_id,
			'title'   => get_the_title( $post_id ),
			'content' => $post_content,
			'url'     => get_permalink( $post_id ),
			'message' => 'Post created successfully',
		] );
	}
}
