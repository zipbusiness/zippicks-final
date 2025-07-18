<?php
/**
 * The Utility Class post type file.
 *
 * @package GenerateBlocksPro\Post_Types
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class to register post type Utility Classes.
 *
 * @since 1.7.0
 */
class GenerateBlocks_Pro_Styles_Post_Type extends GenerateBlocks_Pro_Singleton {

	/**
	 * Initialize the class filters.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			'gblocks_styles',
			array(
				'labels' => array(
					'name'               => _x( 'Global Styles', 'Post Type General Name', 'generateblocks-pro' ),
					'singular_name'      => _x( 'Global Style', 'Post Type Singular Name', 'generateblocks-pro' ),
					'menu_name'          => __( 'Global Styles', 'generateblocks-pro' ),
					'parent_item_colon'  => __( 'Parent Global Style', 'generateblocks-pro' ),
					'all_items'          => __( 'Global Styles', 'generateblocks-pro' ),
					'view_item'          => __( 'View Global Style', 'generateblocks-pro' ),
					'add_new_item'       => __( 'Add New Global Style', 'generateblocks-pro' ),
					'add_new'            => __( 'Add New Global Style', 'generateblocks-pro' ),
					'edit_item'          => __( 'Edit Global Style', 'generateblocks-pro' ),
					'update_item'        => __( 'Update Global Style', 'generateblocks-pro' ),
					'search_items'       => __( 'Search Global Styles', 'generateblocks-pro' ),
					'not_found'          => __( 'Not Found', 'generateblocks-pro' ),
					'not_found_in_trash' => __( 'Not found in Trash', 'generateblocks-pro' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'rewrite'             => false,
				'hierarchical'        => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => true,
				'supports'            => array( 'title', 'page-attributes' ),
				'capabilities' => array(
					'publish_posts'       => GenerateBlocks_Pro_Styles::get_manage_styles_capability(),
					'edit_posts'          => GenerateBlocks_Pro_Styles::get_manage_styles_capability(),
					'edit_others_posts'   => GenerateBlocks_Pro_Styles::get_manage_styles_capability(),
					'delete_posts'        => GenerateBlocks_Pro_Styles::get_manage_styles_capability(),
					'delete_others_posts' => GenerateBlocks_Pro_Styles::get_manage_styles_capability(),
					'read_private_posts'  => GenerateBlocks_Pro_Styles::get_manage_styles_capability(),
					'edit_post'           => GenerateBlocks_Pro_Styles::get_manage_styles_capability(),
					'delete_post'         => GenerateBlocks_Pro_Styles::get_manage_styles_capability(),
					'read_post'           => GenerateBlocks_Pro_Styles::get_manage_styles_capability(),
				),
			)
		);

		register_post_meta(
			'gblocks_styles',
			'gb_style_selector',
			array(
				'type' => 'string',
				'default' => '',
				'show_in_rest' => false,
			)
		);

		register_post_meta(
			'gblocks_styles',
			'gb_style_css',
			array(
				'type' => 'string',
				'default' => '',
				'show_in_rest' => false,
			)
		);

		register_post_meta(
			'gblocks_styles',
			'gb_style_data',
			array(
				'type' => 'array',
				'show_in_rest' => false,
			)
		);

		register_rest_field(
			'gblocks_styles',
			'gb_style_selector',
			array(
				'get_callback'    => function( $data ) {
					return get_post_meta( $data['id'], 'gb_style_selector', true );
				},
				'update_callback' => function( $value, $post ) {
					update_post_meta( $post->ID, 'gb_style_selector', $value );
				},
				'schema'          => array(
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => function ( $value ) {
							return wp_strip_all_tags( $value );
						},
					),
				),
			)
		);

		register_rest_field(
			'gblocks_styles',
			'gb_style_css',
			array(
				'get_callback'    => function( $data ) {
					return get_post_meta( $data['id'], 'gb_style_css', true );
				},
				'update_callback' => function( $value, $post ) {
					update_post_meta( $post->ID, 'gb_style_css', $value );
				},
				'schema'          => array(
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => function ( $value ) {
							return wp_strip_all_tags( $value );
						},
					),
				),
			)
		);

		register_rest_field(
			'gblocks_styles',
			'gb_style_data',
			array(
				'get_callback'    => function( $data ) {
					return get_post_meta( $data['id'], 'gb_style_data', true );
				},
				'update_callback' => function( $value, $post ) {
					update_post_meta( $post->ID, 'gb_style_data', $value );
				},
			)
		);
	}
}

GenerateBlocks_Pro_Styles_Post_Type::get_instance()->init();
