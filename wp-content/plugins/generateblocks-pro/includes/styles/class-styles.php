<?php
/**
 * The Global Class file.
 *
 * @package GenerateBlocks\Global_Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for handling global classes.
 *
 * @since 1.9.0
 */
class GenerateBlocks_Pro_Styles extends GenerateBlocks_Pro_Singleton {
	/**
	 * Initialize the class filters.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'generateblocks_dashboard_tabs', [ $this, 'add_tab' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'generateblocks_dashboard_screens', [ $this, 'add_to_dashboard_pages' ] );
		add_filter( 'block_editor_settings_all', [ $this, 'add_editor_styles_css' ], 20 );

		// Add global classes to dynamic elements.
		add_filter( 'generateblocks_attr_container', [ $this, 'add_global_class_names' ], 10, 2 );
		add_filter( 'generateblocks_attr_grid-wrapper', [ $this, 'add_global_class_names' ], 10, 2 );
		add_filter( 'generateblocks_attr_image', [ $this, 'add_global_class_names' ], 10, 2 );
		add_filter( 'generateblocks_attr_dynamic-headline', [ $this, 'add_global_class_names' ], 10, 2 );
		add_filter( 'generateblocks_attr_dynamic-button', [ $this, 'add_global_class_names' ], 10, 2 );
	}

	/**
	 * Add our Dashboard menu item.
	 */
	public function add_menu() {
		$settings = add_submenu_page(
			'generateblocks',
			__( 'Global Styles', 'generateblocks-pro' ),
			__( 'Global Styles', 'generateblocks-pro' ),
			'manage_options',
			'generateblocks-styles',
			array( $this, 'styles_dashboard' ),
			3
		);
	}

	/**
	 * Add a Local Templates tab to the GB Dashboard tabs.
	 *
	 * @param array $tabs The existing tabs.
	 */
	public function add_tab( $tabs ) {
		$screen = get_current_screen();

		$tabs['styles'] = array(
			'name' => __( 'Global Styles', 'generateblocks-pro' ),
			'url' => admin_url( 'admin.php?page=generateblocks-styles' ),
			'class' => 'generateblocks_page_generateblocks-styles' === $screen->id ? 'active' : '',
		);

		return $tabs;
	}

	/**
	 * Enqueue our scripts.
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		if ( 'generateblocks_page_generateblocks-styles' !== $screen->id ) {
			return;
		}

		$assets = generateblocks_pro_get_enqueue_assets( 'global-class-dashboard' );

		wp_enqueue_script(
			'generateblocks-pro-global-class-dashboard',
			GENERATEBLOCKS_PRO_DIR_URL . 'dist/global-class-dashboard.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		wp_set_script_translations( 'generateblocks-pro-global-class-dashboard', 'generateblocks-pro', GENERATEBLOCKS_PRO_DIR . 'languages' );

		wp_localize_script(
			'generateblocks-pro-global-class-dashboard',
			'generateBlocksPro',
			array(
				'canManageStyles' => self::can_manage_styles(),
				'legacyGlobalStylesUrl' => admin_url( 'edit.php?post_type=gblocks_global_style' ),
				'legacyGlobalStylesCount' => wp_count_posts( 'gblocks_global_style' ),
			)
		);

		wp_enqueue_style(
			'generateblocks-pro-global-class-dashboard',
			GENERATEBLOCKS_PRO_DIR_URL . 'dist/global-class-dashboard.css',
			array( 'wp-components' ),
			GENERATEBLOCKS_PRO_VERSION
		);
	}

	/**
	 * Add to our Dashboard pages.
	 *
	 * @since 1.0.0
	 * @param array $pages The existing pages.
	 */
	public function add_to_dashboard_pages( $pages ) {
		$pages[] = 'generateblocks_page_generateblocks-styles';

		return $pages;
	}

	/**
	 * Output our Dashboard HTML.
	 *
	 * @since 1.0.0
	 */
	public function styles_dashboard() {
		?>
		<div class="wrap gblocks-dashboard-wrap">
			<div class="generateblocks-settings-area generateblocks-global-classes-area">
				<div id="gblocks-global-classes" class="generateblocks-settings-area__inner"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get an array of our global styles.
	 * These styles are sorted so child styles show up immediately after their parents.
	 *
	 * @param array $custom_args Any custom args to be passed to the query.
	 */
	public static function get_styles( array $custom_args = [] ) {
		$args = array_merge(
			[
				'post_type'      => 'gblocks_styles',
				'posts_per_page' => apply_filters( 'generateblocks_styles_posts_per_page', 150 ), // phpcs:ignore
				'post_status'    => 'publish',
				'order'          => 'ASC',
				'orderby'        => 'menu_order',

			],
			$custom_args
		);

		$query  = new WP_Query( $args );
		$styles = [];

		// Create an array of styles from the CPT query.
		foreach ( (array) $query->posts as $post ) {
			$styles[] = [
				'ID'         => $post->ID,
				'style'      => $post->post_title,
				'status'     => $post->post_status,
				'menu_order' => $post->menu_order,
			];
		}

		return $styles;
	}

	/**
	 * Build our global class CSS.
	 *
	 * @param boolean $cached Whether we should the cached data or not.
	 */
	public static function get_styles_css( $cached = true ) {
		$cached_css = get_option( 'generateblocks_style_css', '' );

		if ( $cached && ! empty( $cached_css ) ) {
			return $cached_css;
		}

		$classes = self::get_styles();
		$css = '';

		foreach ( (array) $classes as $class ) {
			$class_css = get_post_meta( $class['ID'], 'gb_style_css', true );
			$css .= $class_css;
		}

		// Cache our results.
		update_option( 'generateblocks_style_css', $css );

		return $css;
	}

	/**
	 * Add our global CSS to the editor.
	 *
	 * This adds a single instance per class, which allows us to live edit each
	 * class in the editor without affecting the others.
	 *
	 * @param array $editor_settings Existing editor settings.
	 */
	public function add_editor_styles_css( $editor_settings ) {
		$classes = self::get_styles();

		foreach ( (array) $classes as $class ) {
			$class_css = get_post_meta( $class['ID'], 'gb_style_css', true );
			$class_name = get_post_meta( $class['ID'], 'gb_style_selector', true );
			$editor_settings['styles'][] = [
				'css' => $class_css,
				'source' => 'gb_class:' . $class_name,
			];
		}

		return $editor_settings;
	}

	/**
	 * Add global classes to dynamic HTML blocks.
	 *
	 * @param array $htmlAttributes Current HTML attributes.
	 * @param array $blockAttributes Attributes for the current block.
	 */
	public function add_global_class_names( $htmlAttributes, $blockAttributes ) {
		if ( ! empty( $blockAttributes['globalClasses'] ) ) {
			$classes = implode( ' ', $blockAttributes['globalClasses'] );

			if ( ! empty( $classes ) ) {
				$htmlAttributes['class'] .= ' ' . esc_attr( $classes );
			}
		}

		return $htmlAttributes;
	}

	/**
	 * The default capability for users who can manage classes.
	 */
	public static function get_manage_styles_capability() {
		return apply_filters(
			'generateblocks_manage_classes_capability',
			'manage_options'
		);
	}

	/**
	 * Check if we can manage classes.
	 */
	public static function can_manage_styles() {
		$can_manage = current_user_can( self::get_manage_styles_capability() );
		$additional_checks = apply_filters(
			'generateblocks_can_manage_styles',
			true
		);

		return $can_manage && $additional_checks;
	}

	/**
	 * Get our class post object using the class name.
	 *
	 * @param string $class_name The name of the class to query.
	 */
	public static function get_class_by_name( $class_name ) {
		$query = new WP_Query(
			[
				'post_type'      => 'gblocks_styles',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => [
					[
						'key'     => 'gb_style_selector',
						'value'   => $class_name,
						'compare' => '=',
					],
				],
			]
		);

		if ( empty( $query->found_posts ) ) {
			return false;
		}

		return $query->posts[0];
	}
}

GenerateBlocks_Pro_Styles::get_instance()->init();
