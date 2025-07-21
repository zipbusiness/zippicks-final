<?php
/**
 * The class to integrate adjacent post dynamic tags.
 *
 * @package Generateblocks/Extend/DynamicTags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * GenerateBlocks Pro adjacted post dynamic tags.
 *
 * @since 1.4.0
 */
class GenerateBlocks_Pro_Dynamic_Tags_Adjacent_Posts extends GenerateBlocks_Pro_Singleton {
	/**
	 * Init.
	 */
	public function init() {
		add_filter( 'generateblocks_dynamic_tag_id', array( $this, 'set_adjacent_post_ids' ), 10, 2 );
	}

	/**
	 * Set adjacent post ids.
	 *
	 * @param int   $id      The post id.
	 * @param array $options The options.
	 */
	public function set_adjacent_post_ids( $id, $options ) {
		$source = $options['source'] ?? '';

		if ( 'next-post' === $source ) {
			$in_same_term  = $options['inSameTerm'] ?? false;
			$term_taxonomy = $options['sameTermTaxonomy'] ?? 'category';
			$next_post     = get_next_post( $in_same_term, '' );

			if ( ! is_object( $next_post ) ) {
				return false;
			}

			return $next_post->ID;
		}

		if ( 'previous-post' === $source ) {
			$in_same_term  = $options['inSameTerm'] ?? false;
			$term_taxonomy = $options['sameTermTaxonomy'] ?? 'category';
			$previous_post = get_previous_post( $in_same_term, '', $term_taxonomy );

			if ( ! is_object( $previous_post ) ) {
				return false;
			}

			return $previous_post->ID;
		}

		return $id;
	}
}

GenerateBlocks_Pro_Dynamic_Tags_Adjacent_Posts::get_instance()->init();
