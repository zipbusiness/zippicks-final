<?php
/**
 * Handle post types in GenerateBlocks Pro.
 *
 * @package GenerateBlocks Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The Local templates class.
 */
class GenerateBlocks_Pro_Global_Styles {
	/**
	 * Instance.
	 *
	 * @access private
	 * @var object Instance
	 */
	private static $instance;

	/**
	 * Initiator.
	 *
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initiate class.
	 */
	public function __construct() {
		// Disable this deprecated feature if no posts exist.
		// We run this if `is_admin()` as we don't want the query on the frontend.
		if ( is_admin() ) {
			$global_styles = get_posts(
				[
					'post_type' => 'gblocks_global_style',
					'post_status' => [ 'any', 'trash' ],
					'posts_per_page' => 1,
					'fields' => 'ids',
				]
			);

			if ( 0 === count( (array) $global_styles ) ) {
				$viewing_post_type = isset( $_GET['post_type'] ) && 'gblocks_global_style' === sanitize_text_field( $_GET['post_type'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				if ( $viewing_post_type ) {
					// Prevent an "invalid post type" error when deleting the last global style.
					add_action( 'init', array( $this, 'add_custom_post_types' ), 100 );
				}

				return;
			}
		} else {
			// If we have no Global Styles on the frontend, we can bail now.
			$global_styles = get_option( 'generateblocks_global_styles', array() );

			if ( empty( $global_styles ) ) {
				return;
			}
		}

		add_action( 'init', array( $this, 'add_custom_post_types' ), 100 );
		add_action( 'save_post_gblocks_global_style', array( $this, 'build_css' ), 10, 2 );
		add_filter( 'generateblocks_do_content', array( $this, 'do_blocks' ) );
		add_filter( 'generateblocks_headline_selector_tagname', array( $this, 'change_headline_selector' ), 10, 2 );
		add_filter( 'generateblocks_dashboard_screens', array( $this, 'add_to_dashboard_pages' ) );
		add_filter( 'views_edit-gblocks_global_style', array( $this, 'deprecation_notice' ) );
	}

	/**
	 * Register custom post type.
	 */
	public function add_custom_post_types() {
		register_post_type(
			'gblocks_global_style',
			array(
				'labels' => array(
					'name'                => _x( 'Global Styles (Legacy)', 'Post Type General Name', 'generateblocks-pro' ),
					'singular_name'       => _x( 'Global Style', 'Post Type Singular Name', 'generateblocks-pro' ),
					'menu_name'           => __( 'Global Styles', 'generateblocks-pro' ),
					'parent_item_colon'   => __( 'Parent Global Style', 'generateblocks-pro' ),
					'all_items'           => __( 'Global Styles', 'generateblocks-pro' ),
					'view_item'           => __( 'View Global Style', 'generateblocks-pro' ),
					'add_new_item'        => __( 'Add New Global Style', 'generateblocks-pro' ),
					'add_new'             => __( 'Add New', 'generateblocks-pro' ),
					'edit_item'           => __( 'Edit Global Style', 'generateblocks-pro' ),
					'update_item'         => __( 'Update Global Style', 'generateblocks-pro' ),
					'search_items'        => __( 'Search Global Style', 'generateblocks-pro' ),
					'not_found'           => __( 'Not Found', 'generateblocks-pro' ),
					'not_found_in_trash'  => __( 'Not found in Trash', 'generateblocks-pro' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'show_ui'             => true,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'rewrite'             => false,
				'hierarchical'        => false,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => true,
				'show_in_rest'        => true,
				'capabilities' => array(
					'create_posts' => false,
					'publish_posts' => 'manage_options',
					'edit_posts' => 'manage_options',
					'edit_others_posts' => 'manage_options',
					'delete_posts' => 'manage_options',
					'delete_others_posts' => 'manage_options',
					'read_private_posts' => 'manage_options',
					'edit_post' => 'manage_options',
					'delete_post' => 'manage_options',
					'read_post' => 'manage_options',
				),
				'supports'            => array(
					'title',
					'editor',
					'thumbnail',
				),
			)
		);
	}

	/**
	 * Add to our Dashboard pages.
	 *
	 * @since 1.0.0
	 * @param array $pages The existing pages.
	 */
	public function add_to_dashboard_pages( $pages ) {
		$pages[] = 'edit-gblocks_global_style';

		return $pages;
	}

	/**
	 * Build our global CSS.
	 *
	 * @since 1.0.0
	 * @param int    $post_id The post ID.
	 * @param object $post The post.
	 */
	public function build_css( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$global_styles = get_option( 'generateblocks_global_styles', array() );
		$global_style_attrs = get_option( 'generateblocks_global_style_attrs', array() );

		// Set our content for this global style page.
		$global_styles[ $post_id ]['content'] = $post->post_content;

		$parsed_content = parse_blocks( $post->post_content );
		$block_data = generateblocks_get_block_data( $parsed_content );
		$defaults = generateblocks_get_block_defaults();

		// Empty our global style IDs so they can be re-populated.
		$global_style_attrs[ $post_id ]['ids'] = array();

		// Remove IDs from our front-end data as it's not needed anymore.
		if ( isset( $global_styles[ $post_id ]['ids'] ) ) {
			unset( $global_styles[ $post_id ]['ids'] );
		}

		// Save all of our attribute values so we can use them in the editor.
		foreach ( (array) $block_data as $name => $data ) {
			if ( ! empty( $data ) && is_array( $data ) ) {
				foreach ( $data as $attributes ) {
					$defaultBlockName = 'container';

					if ( 'button' === $name ) {
						$defaultBlockName = 'button';
					} elseif ( 'button-container' === $name ) {
						$defaultBlockName = 'buttonContainer';
					} elseif ( 'headline' === $name ) {
						$defaultBlockName = 'headline';
					} elseif ( 'grid' === $name ) {
						$defaultBlockName = 'gridContainer';
					}

					// Save block defaults as values.
					foreach ( $defaults[ $defaultBlockName ] as $key => $value ) {
						if ( ! array_key_exists( $key, $attributes ) && ( ! empty( $value ) || 0 === $value ) ) {
							$attributes[ $key ] = $value;
						}
					}

					if ( ! isset( $global_style_attrs[ $post_id ]['ids'][ $name ] ) ) {
						$global_style_attrs[ $post_id ]['ids'][ $name ][ $attributes['uniqueId'] ] = array();
					}

					if ( isset( $attributes['uniqueId'] ) ) {
						$global_style_attrs[ $post_id ]['ids'][ $name ][ $attributes['uniqueId'] ] = $attributes;
					}
				}
			}
		}

		if ( 'publish' !== $post->post_status ) {
			unset( $global_styles[ $post_id ] );
			unset( $global_style_attrs[ $post_id ] );
		}

		// Force regenerate our static CSS files.
		update_option( 'generateblocks_dynamic_css_posts', array() );
		update_option( 'generateblocks_global_styles', $global_styles );
		update_option( 'generateblocks_global_style_attrs', $global_style_attrs );
	}

	/**
	 * Tell GB about our styles.
	 *
	 * @param string $content The existing content.
	 */
	public function do_blocks( $content ) {
		$global_styles = get_option( 'generateblocks_global_styles', array() );

		foreach ( (array) $global_styles as $id => $data ) {
			$content = $data['content'] . $content;
		}

		return $content;
	}

	/**
	 * Remove the tagname from our global style Headline selectors.
	 *
	 * @param boolean $do Whether to include the tagname or not.
	 * @param array   $atts The attributes for the block.
	 */
	public function change_headline_selector( $do, $atts ) {
		if ( isset( $atts['isGlobalStyle'] ) && $atts['isGlobalStyle'] ) {
			$do = false;
		}

		return $do;
	}

	/**
	 * Show a deprecation notice in our post type.
	 *
	 * @param string $views Existing text in this filter.
	 */
	public function deprecation_notice( $views ) {
		?>
			<style>
				.gb-deprecation-notice {
					background: #fef8ee;
					border-left: 5px solid #f0b849;
					padding: 20px;
					margin: 20px 0;
				}

				.gb-deprecation-notice p {
					margin-top: 0;
				}

				.gb-deprecation-notice p:last-child {
					margin-bottom: 0;
				}
			</style>
			<div class="gb-deprecation-notice">
				<p><?php esc_html_e( 'This Global Styles system has been deprecated. You can still edit and use existing styles, but you cannot add new ones.', 'generateblocks-pro' ); ?></p>
				<p>
					<?php
						printf(
							/* translators: %1$s and %2$s are placeholders for the opening and closing anchor tags */
							esc_html__(
								'Please use the new %1$sGlobal Styles%2$s system instead.',
								'generateblocks-pro'
							),
							'<a href="' . esc_url( admin_url( 'admin.php?page=generateblocks-styles' ) ) . '">',
							'</a>'
						);
					?>
				</p>
				<p>
					<a href="https://docs.generateblocks.com/article/global-styles-overview/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Learn more about the new Global Styles', 'generateblocks-pro' ); ?> <svg xmlns="http://www.w3.org/2000/svg" style="fill: currentColor;width: 15px;height:15px;position: relative;top: 3px;left: 3px;" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M19.5 4.5h-7V6h4.44l-5.97 5.97 1.06 1.06L18 7.06v4.44h1.5v-7Zm-13 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3H17v3a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h3V5.5h-3Z"></path></svg></a></p>
			</div>
		<?php

		return $views;
	}
}

GenerateBlocks_Pro_Global_Styles::get_instance();
