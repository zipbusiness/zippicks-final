<?php
/**
 * The module class file.
 *
 * @package GenerateCloud\Modules
 */

namespace GenerateCloud\Modules;

use GenerateCloud\Utils\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Module class.
 *
 * @since 1.0.0
 */
abstract class Module extends Singleton {

	/**
	 * Loads the module actions and filters.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	abstract public function load(): void;
}
