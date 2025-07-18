<?php
/**
 * The Patterns post type file.
 *
 * @package GenerateBlocksPro\Post_Types
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Extend the core Patterns post type.
 *
 * @since 1.7.0
 */
class GenerateBlocks_Pro_Patterns_Post_Type extends GenerateBlocks_Pro_Singleton {
	/**
	 * Initialize the class filters.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_filter( 'register_taxonomy_args', array( $this, 'modify_taxonomy_args' ), 10, 2 );
		add_filter( 'views_edit-wp_block', array( $this, 'taxonomy_links' ) );
		add_action( 'admin_head', array( $this, 'fix_menu' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_scripts' ) );
		add_action( 'init', array( $this, 'register_post_meta' ) );
		add_action( 'generateblocks_dashboard_tabs', array( $this, 'add_tab' ) );
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_footer', [ $this, 'add_scripts' ] );
		add_filter( 'wp_sitemaps_taxonomies', [ $this, 'sitemaps_taxonomies' ] );
	}

	/**
	 * Add our Dashboard menu item.
	 */
	public function add_menu() {
		add_submenu_page(
			'generateblocks',
			__( 'Local Patterns', 'generateblocks-pro' ),
			__( 'Local Patterns', 'generateblocks-pro' ),
			'manage_options',
			'edit.php?post_type=wp_block',
			'',
			2
		);
	}

	/**
	 * Enqueue our editor scripts.
	 */
	public function enqueue_scripts() {
		if ( 'wp_block' !== get_post_type() ) {
			return;
		}

		$assets = generateblocks_pro_get_enqueue_assets( 'pattern-library' );

		wp_enqueue_script(
			'generateblocks-pro-pattern-library',
			GENERATEBLOCKS_PRO_DIR_URL . 'dist/pattern-library.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		wp_enqueue_style(
			'generateblocks-pro-pattern-library',
			GENERATEBLOCKS_PRO_DIR_URL . 'dist/pattern-library.css',
			array( 'wp-components' ),
			GENERATEBLOCKS_PRO_VERSION
		);
	}

	/**
	 * Register our post meta.
	 */
	public function register_post_meta() {
		register_post_meta(
			'wp_block',
			'_editor_width',
			[
				'show_in_rest' => true,
				'single' => true,
				'type'   => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			]
		);
	}

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		register_taxonomy(
			'gblocks_pattern_collections',
			array( 'wp_block', 'gblocks_public_keys' ),
			array(
				'public'            => true,
				'show_in_menu'      => true,
				'show_in_nav_menus' => false,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'capabilities'      => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
				'labels'            => array(
					'name'                       => __( 'Collections', 'generateblocks-pro' ),
					'singular_name'              => __( 'Collection', 'generateblocks-pro' ),
					'menu_name'                  => __( 'Collections', 'generateblocks-pro' ),
					'all_items'                  => __( 'All Collections', 'generateblocks-pro' ),
					'parent_item'                => __( 'Parent Collection', 'generateblocks-pro' ),
					'parent_item_colon'          => __( 'Parent Collection:', 'generateblocks-pro' ),
					'new_item_name'              => __( 'New Collection Name', 'generateblocks-pro' ),
					'add_new_item'               => __( 'Add New Collection', 'generateblocks-pro' ),
					'edit_item'                  => __( 'Edit Collection', 'generateblocks-pro' ),
					'update_item'                => __( 'Update Collection', 'generateblocks-pro' ),
					'view_item'                  => __( 'View Collection', 'generateblocks-pro' ),
					'separate_items_with_commas' => __( 'Separate collections with commas', 'generateblocks-pro' ),
					'add_or_remove_items'        => __( 'Add or remove collections', 'generateblocks-pro' ),
					'choose_from_most_used'      => __( 'Choose from the most used', 'generateblocks-pro' ),
					'popular_items'              => __( 'Popular Collections', 'generateblocks-pro' ),
					'search_items'               => __( 'Search Collections', 'generateblocks-pro' ),
					'not_found'                  => __( 'No collections found.', 'generateblocks-pro' ),
					'no_terms'                   => __( 'No collections', 'generateblocks-pro' ),
					'items_list'                 => __( 'Collections list', 'generateblocks-pro' ),
					'items_list_navigation'      => __( 'Collections list navigation', 'generateblocks-pro' ),
				),
			)
		);
	}

	/**
	 * Modify the taxonomy args.
	 *
	 * @param array  $args     The taxonomy args.
	 * @param string $taxonomy The taxonomy name.
	 *
	 * @return array
	 */
	public function modify_taxonomy_args( $args, $taxonomy ) {
		if ( 'gblocks_pattern_collections' !== $taxonomy ) {
			return $args;
		}

		$use_default_term = apply_filters( 'generateblocks_use_default_pattern_term', true );

		if ( $use_default_term ) {
			$args['default_term'] = array(
				'name' => __( 'Local', 'generateblocks-pro' ),
				'slug' => 'local-patterns',
			);
		}

		return $args;
	}

	/**
	 * Add links to our taxonomies.
	 *
	 * @param array $views Links to display above the patterns.
	 */
	public function taxonomy_links( $views ) {
		$custom_links = [];
		$custom_links[ __( 'Collections', 'generateblocks-pro' ) ] = admin_url( 'edit-tags.php?taxonomy=gblocks_pattern_collections&post_type=wp_block' );
		$custom_links[ __( 'Categories', 'generateblocks-pro' ) ] = admin_url( 'edit-tags.php?taxonomy=wp_pattern_category&post_type=wp_block' );

		foreach ( $custom_links as $label => $url ) {
			$views[ $label ] = '<a href="' . esc_url( $url ) . '">' . $label . '</a>';
		}

		return $views;
	}

	/**
	 * Trick the WordPress menu to highlight our Patterns menu item
	 * when we're dealing with collections or categories.
	 */
	public function fix_menu() {
		global $parent_file, $submenu_file, $post_type;

		$screen = get_current_screen();

		if ( 'edit-gblocks_pattern_collections' === $screen->id || 'edit-wp_pattern_category' === $screen->id ) {
			$parent_file = 'generateblocks'; // phpcs:ignore -- Override necessary.
			$submenu_file = 'edit.php?post_type=wp_block'; // phpcs:ignore -- Override necessary.
		}
	}

	/**
	 * Add a Local Templates tab to the GB Dashboard tabs.
	 *
	 * @param array $tabs The existing tabs.
	 */
	public function add_tab( $tabs ) {
		$screen = get_current_screen();

		$tabs['local-templates'] = array(
			'name' => __( 'Local Patterns', 'generateblocks-pro' ),
			'url' => admin_url( 'edit.php?post_type=wp_block' ),
			'class' => 'edit-wp_block' === $screen->id ? 'active' : '',
		);

		return $tabs;
	}

	/**
	 * Add a link to the legacy patterns.
	 */
	public function add_scripts() {
		$screen = get_current_screen();

		if ( 'edit-wp_block' !== $screen->id ) {
			return;
		}

		$legacy_patterns = get_posts(
			array(
				'post_type'      => 'gblocks_templates',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		if ( empty( $legacy_patterns ) ) {
			return;
		}
		?>
		<script>
			document.addEventListener( 'DOMContentLoaded', () => {
				const button = document.querySelector( '.page-title-action' );

				if ( ! button ) {
					return;
				}

				const legacyButton = document.createElement( 'a' );
				legacyButton.classList.add( 'page-title-action' );
				legacyButton.href = '<?php echo esc_url( admin_url( 'edit.php?post_type=gblocks_templates' ) ); ?>';
				legacyButton.textContent = '<?php echo esc_html( __( 'Legacy Patterns', 'generateblocks-pro' ) ); ?>';
				legacyButton.textContent += ' (<?php echo count( (array) $legacy_patterns ); ?>)';
				button.parentNode.insertBefore( legacyButton, button );
			} );
		</script>
		<?php
	}

	/**
	 * Remove our collections taxonomy from sitemaps.
	 *
	 * @param array $taxonomies The existing taxonomies.
	 *
	 * @return array
	 */
	public function sitemaps_taxonomies( $taxonomies ) {
		if ( isset( $taxonomies['gblocks_pattern_collections'] ) ) {
			unset( $taxonomies['gblocks_pattern_collections'] );
		}

		return $taxonomies;
	}
}

GenerateBlocks_Pro_Patterns_Post_Type::get_instance()->init();
