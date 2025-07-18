<?php
/**
 * This file handles the Font Library CPT.
 *
 * @since 2.5.0
 *
 * @package GP Premium
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access, please.
}

/**
 * Font library CPT class.
 */
class GeneratePress_Pro_Font_Library_CPT extends GeneratePress_Pro_Singleton {
	/**
	 * Constructor.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_cpt' ) );
	}

	/**
	 * Set up our custom post type.
	 *
	 * @since 2.5.0
	 */
	public function register_cpt() {
		$labels = array(
			'name'                   => _x( 'Fonts', 'Post Type General Name', 'gp-premium' ),
			'singular_name'          => _x( 'Font', 'Post Type Singular Name', 'gp-premium' ),
			'menu_name'              => __( 'Fonts', 'gp-premium' ),
			'all_items'              => __( 'All Fonts', 'gp-premium' ),
			'add_new'                => __( 'Add New Font', 'gp-premium' ),
			'add_new_item'           => __( 'Add New Font', 'gp-premium' ),
			'new_item'               => __( 'New Font', 'gp-premium' ),
			'edit_item'              => __( 'Edit Font', 'gp-premium' ),
			'update_item'            => __( 'Update Font', 'gp-premium' ),
			'search_items'           => __( 'Search Font', 'gp-premium' ),
			'item_published'         => __( 'Font published.', 'gp-premium' ),
			'item_updated'           => __( 'Font updated.', 'gp-premium' ),
			'item_scheduled'         => __( 'Font scheduled.', 'gp-premium' ),
			'item_reverted_to_draft' => __( 'Font reverted to draft.', 'gp-premium' ),
		);

		$args = array(
			'labels'                => $labels,
			'supports'              => array( 'title', 'custom-fields' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => false,
			'show_in_menu'          => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'show_in_rest'          => true,
		);

		register_post_type( GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT, $args );

		// Font variants.
		register_post_meta(
			GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT,
			'gp_font_variants',
			array(
				'type'         => 'array',
				'show_in_rest' => false,
			)
		);

		// Font family alias.
		register_post_meta(
			GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT,
			'gp_font_family_alias',
			array(
				'type'         => 'string',
				'show_in_rest' => false,
			)
		);

		// Font display value.
		register_post_meta(
			GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT,
			'gp_font_display',
			array(
				'type'         => 'string',
				'show_in_rest' => false,
			)
		);

		// Font source.
		register_post_meta(
			GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT,
			'gp_font_source',
			array(
				'type'         => 'string',
				'show_in_rest' => false,
			)
		);

		// Font family fallback.
		register_post_meta(
			GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT,
			'gp_font_fallback',
			array(
				'type'         => 'string',
				'show_in_rest' => false,
			)
		);

		// Font family preview.
		register_post_meta(
			GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT,
			'gp_font_preview',
			array(
				'type'         => 'string',
				'show_in_rest' => false,
			)
		);

		// Font family variable.
		register_post_meta(
			GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT,
			'gp_font_variable',
			array(
				'type'         => 'string',
				'show_in_rest' => false,
			)
		);
	}
}

GeneratePress_Pro_Font_Library_CPT::get_instance()->init();
