<?php
/**
 * The Plugin class file.
 *
 * @package GenerateCloud
 */

namespace GenerateCloud;

use GenerateCloud\Modules\Post_Type;
use GenerateCloud\Modules\Dashboard;
use GenerateCloud\Modules\Rest_Api;
use GenerateCloud\Modules\Module;
use GenerateCloud\Utils\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main class for the plugin.
 *
 * @since 1.0.0
 */
class Plugin extends Singleton {

	public function load_modules(): void {
		$modules = array(
			Post_Type::get_instance(),
			Dashboard::get_instance(),
			Rest_Api::get_instance(),
		);

		array_walk(
			$modules,
			function( Module $module ) {
				$module->load();
			}
		);
	}

	/**
	 * Plugins loaded action callback.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function plugins_loaded(): void {
		if (
			! defined( 'GENERATEBLOCKS_VERSION' ) ||
			! defined( 'GENERATEBLOCKS_PRO_VERSION' )
		) {
			add_action( 'admin_notices', [ $this, 'show_admin_notice' ] );

			return;
		}

		$this->load_modules();
	}

	/**
	 * Show admin notice.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function show_admin_notice() {
		$screen = get_current_screen();

		if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
			return;
		}

		printf(
			'<div class="error"><p>%s</p></div>',
			esc_html__( 'GenerateCloud is not working because you need to activate GenerateBlocks and GenerateBlocks Pro.', 'generatecloud' )
		);
	}
}
