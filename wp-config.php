<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'db_zippicks' );

/** Database username */
define( 'DB_USER', 'zippicks' );

/** Database password */
define( 'DB_PASSWORD', 'RjVcnKSzkY9cPsppglmi' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' ); 

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', 'utf8mb4_unicode_520_ci' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'gEbLdp#zXunAoPBd@e?6LZFL8&szytRHMWxuPWlXq9%lF662nugGPNlA7V2qmaQYL' );
define( 'SECURE_AUTH_KEY',   'tPDhJQ!FlTYE76zB?5#fppquhMGgYjO%CrpgleHnFCCk5c^&CLmS^exmqVyMXs^rJ' );
define( 'LOGGED_IN_KEY',     'xhJ#!6#EKRrtlmnp^58G*AVSP?8MOmxM6KtSYVOAZgtSXNst4ef7Of3j^CQX2KlV^' );
define( 'NONCE_KEY',         '4HeZF@Y*EEdpariKibFoaCwkS5#Tb3MrQ!8AQ9%T7m5F99cXg24U0I7hgEjXR@dx@' );
define( 'AUTH_SALT',         '3NF1aElhxWrYpq$7GJHoz&8tz^PwC0s$%fHSA0zr#XnE/UZdSMdPLJk$ebx&ScYLI' );
define( 'SECURE_AUTH_SALT',  'hQI&I2l?u8Vglim/R@vLYoIFH*eSDyc^yEY!GQA@IqtIxTb&#/MRvJKvSe8iDLEDm' );
define( 'LOGGED_IN_SALT',    'Yk*0gvzo0QV6s&tN#4Sn/4#6YF8h$N0ZFNLe^GRTp&#yh@hII?Es3E?9DTjVr%/de' );
define( 'NONCE_SALT',        'u6RMfg1VywuBT*tIgPq%L#cfYLNK@F0z%#8%JXDUqq8yg7xj@4#uOclq0^HoCDK5m' );
define( 'WP_CACHE_KEY_SALT', 'bLNNc3jZ#AbS8?V&F5i^$Iyj9BdoknSbxh^MI$hM!6*nj!s0HYWCb@PacLzdDFrP%' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'xowDfVTe_';



/* Add any custom values between this line and the "stop editing" line. */

/* don't remove the line below. Repeat, *DON'T* remove the line below! */
if ( file_exists( __DIR__ . "/wp-config-pressidium.php" ) ) {
    require_once( __DIR__ . "/wp-config-pressidium.php" );
}
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  define('WP_DEBUG_DISPLAY', false);

require_once(ABSPATH . 'wp-content/zippicks-debug.php');

// ZipPicks API Configuration (Shared across all plugins)
define('ZIPPICKS_API_URL', 'https://zipbusiness-api.onrender.com');
define('ZIPPICKS_API_KEY', 'SzfHh+mInQWzE8fFIR4WAqkMf24KEDDVNAIU4o9kLVg=');

// Taste Graph Connector API Configuration (Backward compatibility)
define('TGC_API_URL', ZIPPICKS_API_URL);
define('TGC_API_KEY', ZIPPICKS_API_KEY);

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';