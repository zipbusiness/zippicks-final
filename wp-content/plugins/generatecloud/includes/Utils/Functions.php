<?php
/**
 * The functions class file.
 *
 * @package GenerateCloud\Utils
 */

namespace GenerateCloud\Utils;

use GenerateCloud\Modules\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Module class.
 *
 * @since 1.0.0
 */
abstract class Functions extends Singleton {
	/**
	 * Get the post object for a specific public key using the public key value.
	 *
	 * @param string $public_key The public key to query.
	 */
	public static function get_public_key_post( $public_key ) {
		$posts = get_posts(
			[
				'post_type'              => Post_Type::POST_TYPE,
				'posts_per_page'         => 1,
				'post_status'            => 'publish',
				'order'                  => 'ASC',
				'orderby'                => 'ID',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => [
					[
						'key'     => 'gb_public_key',
						'value'   => $public_key,
						'compare' => '=',
					],
				],
			]
		);

		if ( ! isset( $posts[0] ) ) {
			return false;
		}

		return $posts[0];
	}
}
