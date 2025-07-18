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
class GenerateBlocks_Pro_Local_Templates {
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
		$admin_settings = wp_parse_args(
			get_option( 'generateblocks_admin', array() ),
			generateblocks_pro_get_admin_option_defaults()
		);

		if ( ! $admin_settings['enable_local_templates'] ) {
			return;
		}

		add_action( 'init', array( $this, 'add_custom_post_types' ) );
		add_action( 'admin_head', array( $this, 'fix_menu' ) );
		add_action( 'admin_footer', [ $this, 'add_scripts' ] );
	}

	/**
	 * Register custom post type.
	 */
	public function add_custom_post_types() {
		register_post_type(
			'gblocks_templates',
			array(
				'labels' => array(
					'name'                => _x( 'Local Patterns (Legacy)', 'Post Type General Name', 'generateblocks-pro' ),
					'singular_name'       => _x( 'Local Pattern', 'Post Type Singular Name', 'generateblocks-pro' ),
					'menu_name'           => __( 'Local Patterns', 'generateblocks-pro' ),
					'parent_item_colon'   => __( 'Parent Local Pattern', 'generateblocks-pro' ),
					'all_items'           => __( 'Local Patterns', 'generateblocks-pro' ),
					'view_item'           => __( 'View Local Pattern', 'generateblocks-pro' ),
					'add_new_item'        => __( 'Add New Local Pattern', 'generateblocks-pro' ),
					'add_new'             => __( 'Add New', 'generateblocks-pro' ),
					'edit_item'           => __( 'Edit Local Pattern', 'generateblocks-pro' ),
					'update_item'         => __( 'Update Local Pattern', 'generateblocks-pro' ),
					'search_items'        => __( 'Search Local Pattern', 'generateblocks-pro' ),
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
				'show_in_admin_bar'   => false,
				'show_in_rest'        => true,
				'capabilities' => array(
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
	 * Trick the WordPress menu to highlight our Patterns menu item
	 * when we're dealing with collections or categories.
	 */
	public function fix_menu() {
		global $parent_file, $submenu_file, $post_type;

		$screen = get_current_screen();

		if ( 'edit-gblocks_templates' === $screen->id ) {
			$parent_file = 'generateblocks'; // phpcs:ignore -- Override necessary.
			$submenu_file = 'edit.php?post_type=wp_block'; // phpcs:ignore -- Override necessary.
		}
	}

	/**
	 * Add a link to the new patterns.
	 */
	public function add_scripts() {
		$screen = get_current_screen();

		if ( 'edit-gblocks_templates' !== $screen->id ) {
			return;
		}
		?>
		<script>
			document.addEventListener( 'DOMContentLoaded', () => {
				const button = document.querySelector( '.page-title-action' );

				if ( ! button ) {
					return;
				}

				button.style.pointerEvents = 'none';
				button.style.opacity = '0.5';

				const newPatternsButton = document.createElement( 'a' );
				newPatternsButton.classList.add( 'page-title-action' );
				newPatternsButton.href = '<?php echo esc_url( admin_url( 'edit.php?post_type=wp_block' ) ); ?>';
				newPatternsButton.textContent = '<?php echo esc_html( __( 'New Pattern Library', 'generateblocks-pro' ) ); ?>';
				button.parentNode.insertBefore( newPatternsButton, button );
			} );
		</script>
		<?php
	}
}

GenerateBlocks_Pro_Local_Templates::get_instance();
