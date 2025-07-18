<?php
/**
 * The class to integrate adjacent post dynamic tags.
 *
 * @package GeneratePress/Extend/DynamicTags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * GeneratePress Pro adjacted post dynamic tags.
 *
 * @since 1.4.0
 */
class GeneratePress_Pro_Dynamic_Tags_Adjacent_Posts extends GeneratePress_Pro_Singleton {
	/**
	 * Init.
	 */
	public function init() {
		add_action( 'init', array( $this, 'setup' ) );
	}

	public function setup() {
		// Bail out if GenerateBlocks Pro is active.
		if ( class_exists( 'GenerateBlocks_Pro_Dynamic_Tags_Adjacent_Posts' ) ) {
			return;
		}

		add_filter( 'generateblocks_dynamic_tag_id', array( $this, 'set_adjacent_post_ids' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		$editor_assets = generate_premium_get_enqueue_assets( 'adjacent-posts' );

		wp_enqueue_script(
			'generatepress-pro-adjacent-posts',
			GP_PREMIUM_DIR_URL . 'dist/adjacent-posts.js',
			$editor_assets['dependencies'],
			$editor_assets['version'],
			true
		);
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

GeneratePress_Pro_Dynamic_Tags_Adjacent_Posts::get_instance()->init();
