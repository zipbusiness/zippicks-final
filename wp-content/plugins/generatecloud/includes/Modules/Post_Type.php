<?php
/**
 * The Public Keys post type class file.
 *
 * @package GenerateCloud
 */

namespace GenerateCloud\Modules;

use GenerateCloud\Modules\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Public Keys post type.
 *
 * @since 1.0.0
 */
class Post_Type extends Module {

	const POST_TYPE = 'gblocks_public_keys';

	public function load(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the Cloud patterns CPT.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => _x( 'Public Keys', 'Post Type General Name', 'generatecloud' ),
					'singular_name'      => _x( 'Public Key', 'Post Type Singular Name', 'generatecloud' ),
					'menu_name'          => __( 'Public keys', 'generatecloud' ),
					'parent_item_colon'  => __( 'Parent Public Key', 'generatecloud' ),
					'all_items'          => __( 'Public Keys', 'generatecloud' ),
					'view_item'          => __( 'View Public Key', 'generatecloud' ),
					'add_new_item'       => __( 'Add New Public Key', 'generatecloud' ),
					'add_new'            => __( 'Add New Public Key', 'generatecloud' ),
					'edit_item'          => __( 'Edit Public Key', 'generatecloud' ),
					'update_item'        => __( 'Update Public Key', 'generatecloud' ),
					'search_items'       => __( 'Search Public Keys', 'generatecloud' ),
					'not_found'          => __( 'Not Found', 'generatecloud' ),
					'not_found_in_trash' => __( 'Not found in Trash', 'generatecloud' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'show_ui'             => false,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'rewrite'             => false,
				'hierarchical'        => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => true,
				'supports'            => array(),
				'capabilities'        => array(
					'publish_posts'       => 'manage_options',
					'edit_posts'          => 'manage_options',
					'edit_others_posts'   => 'manage_options',
					'delete_posts'        => 'manage_options',
					'delete_others_posts' => 'manage_options',
					'read_private_posts'  => 'manage_options',
					'edit_post'           => 'manage_options',
					'delete_post'         => 'manage_options',
					'read_post'           => 'manage_options',
				),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'gb_public_key',
			array(
				'type'         => 'string',
				'default'      => '',
				'show_in_rest' => false,
			)
		);

		register_rest_field(
			self::POST_TYPE,
			'public_key',
			array(
				'get_callback'    => function( $data ) {
					return get_post_meta( $data['id'], 'gb_public_key', true );
				},
				'update_callback' => function( $value, $post ) {
					update_post_meta( $post->ID, 'gb_public_key', $value );
				},
				'schema'          => array(
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => function ( $value ) {
							return sanitize_text_field( $value );
						},
						'validate_callback' => function ( $value ) {
							return ! ! $value; // TODO: Add proper validation.
						},
					),
				),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'gb_permissions',
			array(
				'type'         => 'object',
				'default'      => array(),
				'show_in_rest' => false,
			)
		);

		register_rest_field(
			self::POST_TYPE,
			'permissions',
			array(
				'get_callback'    => function( $data ) {
					return get_post_meta( $data['id'], 'gb_permissions', true );
				},
				'update_callback' => function( $value, $post ) {
					update_post_meta( $post->ID, 'gb_permissions', $value );
				},
				'schema'          => array(
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => function ( $value ) {
							return map_deep( $value, 'sanitize_text_field' );
						},
						'validate_callback' => function ( $value ) {
							return is_array( $value );
						},
					),
				),
			)
		);
	}
}
