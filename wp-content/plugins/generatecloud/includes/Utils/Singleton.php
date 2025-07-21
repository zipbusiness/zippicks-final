<?php
/**
 * The singleton class file.
 *
 * @package GenerateCloud\Utils
 */

namespace GenerateCloud\Utils;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Singleton class.
 *
 * @since 1.0.0
 */
class Singleton {

	/**
	 * Child class instances.
	 *
	 * @var array<static>
	 */
	private static array $instances = [];

	/**
	 * The singleton constructor can not be public.
	 */
	final protected function __construct() {
	}

	/**
	 * Not allowed to clone a singleton.
	 */
	protected function __clone() {
	}

	/**
	 * Not allowed to un-serialize a singleton.
	 *
	 * @throws Exception Cannot un-serialize a singleton.
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot un-serialize singleton' );
	}

	/**
	 * Get the class instance.
	 *
	 * @return static
	 */
	public static function get_instance(): Singleton {
		$subclass = static::class;

		if ( ! isset( self::$instances[ $subclass ] ) ) {
			self::$instances[ $subclass ] = new static();
		}

		return self::$instances[ $subclass ];
	}
}
