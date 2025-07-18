<?php
/**
 * The Public Keys post type class file.
 *
 * @package GenerateCloud
 */

namespace GenerateCloud\Modules;

use GenerateCloud\Modules\Module;
use GenerateCloud\Modules\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Public Keys post type.
 *
 * @since 1.0.0
 */
class Dashboard extends Module {

	public function load(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'generateblocks_dashboard_tabs', [ $this, 'add_tab' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'generateblocks_dashboard_screens', [ $this, 'add_to_dashboard_pages' ] );
		add_action( 'save_post_' . Post_Type::POST_TYPE, [ $this, 'permanently_delete_keys' ], 10, 2 );
		add_action( 'generateblocks_settings_area', array( $this, 'add_license_key_area' ), 25 );
	}

	/**
	 * Add our Dashboard menu item.
	 */
	public function add_menu() {
		$settings = add_submenu_page(
			'generateblocks',
			__( 'Cloud', 'generatecloud' ),
			__( 'Cloud', 'generatecloud' ),
			'manage_options',
			'generateblocks-public-keys',
			array( $this, 'public_keys' ),
			5
		);
	}

	/**
	 * Add a Local Templates tab to the GB Dashboard tabs.
	 *
	 * @param array $tabs The existing tabs.
	 */
	public function add_tab( $tabs ) {
		$screen = get_current_screen();

		$tabs['public-keys'] = array(
			'name'  => __( 'Cloud', 'generatecloud' ),
			'url'   => admin_url( 'admin.php?page=generateblocks-public-keys' ),
			'class' => 'generateblocks_page_generateblocks-public-keys' === $screen->id ? 'active' : '',
		);

		return $tabs;
	}

	/**
	 * Enqueue our scripts.
	 */
	public function enqueue_scripts() {
		$this->enqueue_public_key_scripts();
		$this->enqueue_licensing_scripts();
	}

	/**
	 * Enqueue our public key scripts.
	 */
	public function enqueue_public_key_scripts() {
		$screen = get_current_screen();

		if ( 'generateblocks_page_generateblocks-public-keys' !== $screen->id ) {
			return;
		}

		$assets_file     = GENERATECLOUD_DIR . 'dist/dashboard.asset.php';
		$compiled_assets = file_exists( $assets_file )
			? require $assets_file
			: false;

		$assets =
			isset( $compiled_assets['dependencies'] ) &&
			isset( $compiled_assets['version'] )
			? $compiled_assets
			: [
				'dependencies' => [],
				'version'      => GENERATECLOUD_VERSION,
			];

		wp_enqueue_script(
			'generatecloud-dashboard',
			GENERATECLOUD_URL . 'dist/dashboard.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		$available_permissions = apply_filters(
			'generatecloud_available_permissions',
			array(
				'patterns' => array(
					'label'  => array(
						'singular' => __( 'Pattern', 'generatecloud' ),
						'plural'   => __( 'Patterns', 'generatecloud' ),
					),
					'type'   => 'taxonomy',
					'object' => 'gblocks_pattern_collections',
				),
			)
		);

		wp_localize_script(
			'generatecloud-dashboard',
			'generateCloud',
			[
				'connectionUrl'        => site_url(),
				'availablePermissions' => $available_permissions,
			]
		);

		wp_set_script_translations(
			'generatecloud-dashboard',
			'generatecloud',
			GENERATECLOUD_DIR . 'languages'
		);

		wp_enqueue_style(
			'generatecloud-dashboard',
			GENERATECLOUD_URL . 'dist/dashboard.css',
			array( 'wp-components' ),
			GENERATECLOUD_VERSION
		);
	}

	/**
	 * Enqueue our licensing scripts.
	 */
	public function enqueue_licensing_scripts() {
		$screen = get_current_screen();

		if ( 'generateblocks_page_generateblocks-settings' !== $screen->id ) {
			return;
		}

		$assets_file     = GENERATECLOUD_DIR . 'dist/license-key.asset.php';
		$compiled_assets = file_exists( $assets_file )
			? require $assets_file
			: false;

		$assets =
			isset( $compiled_assets['dependencies'] ) &&
			isset( $compiled_assets['version'] )
			? $compiled_assets
			: [
				'dependencies' => [],
				'version'      => GENERATECLOUD_VERSION,
			];

		wp_enqueue_script(
			'generatecloud-licensing',
			GENERATECLOUD_URL . 'dist/license-key.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		$license_key_data = get_option( 'generatecloud_licensing', [] );
		$license_key      = $license_key_data['key'] ?? '';

		wp_localize_script(
			'generatecloud-licensing',
			'generateCloud',
			[
				'license' => [
					'key'    => $license_key ? '****************************' . substr( $license_key, -4 ) : '',
					'status' => $license_key_data['status'] ?? '',
					'beta'   => $license_key_data['beta'] ?? false,
				],
			]
		);

		wp_set_script_translations(
			'generatecloud-licensing',
			'generatecloud',
			GENERATECLOUD_DIR . 'languages'
		);

		wp_enqueue_style(
			'generatecloud-licensing',
			GENERATECLOUD_URL . 'dist/license-key.css',
			array( 'wp-components' ),
			GENERATECLOUD_VERSION
		);
	}

	/**
	 * Add to our Dashboard pages.
	 *
	 * @since 1.0.0
	 * @param array $pages The existing pages.
	 */
	public function add_to_dashboard_pages( $pages ) {
		$pages[] = 'generateblocks_page_generateblocks-public-keys';

		return $pages;
	}

	/**
	 * Output our Dashboard HTML.
	 *
	 * @since 1.0.0
	 */
	public function public_keys() {
		?>
		<div class="wrap gblocks-dashboard-wrap">
			<div class="generateblocks-settings-area generateblocks-public-keys-area">
				<div id="gblocks-public-keys"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Skip the trash when we delete public keys.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post The post object.
	 */
	public function permanently_delete_keys( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if it is an autosave or a revision.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'trash' === $post->post_status ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Add license key container.
	 *
	 * @since 1.2.0
	 */
	public function add_license_key_area() {
		echo '<div id="generatecloud-licensing"></div>';
	}
}
