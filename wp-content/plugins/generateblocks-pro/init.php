<?php
/**
 * Set up the plugin.
 *
 * @package GenerateBlocks Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Load necessary files.
require_once GENERATEBLOCKS_PRO_DIR . 'includes/defaults.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/general.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/generate-css.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/functions.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/deprecated.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/class-singleton.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/class-local-templates.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/class-global-styles.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/class-rest.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/class-settings.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/class-asset-library.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/class-plugin-update.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/pattern-library/class-patterns-post-type.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/pattern-library/class-pattern-library.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/pattern-library/class-pattern-library-rest.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/styles/class-styles-post-type.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/styles/class-styles.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/styles/class-styles-rest.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/styles/class-styles-enqueue.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/query-loop/class-related-post.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/query-loop/class-related-parent.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/query-loop/class-related-author.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/query-loop/class-related-terms.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/query/class-query.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/looper/class-looper.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/dynamic-content/class-advanced-custom-fields.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/dynamic-tags/class-acf.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/dynamic-tags/class-register.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/dynamic-tags/class-adjacent-posts.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/interactions/class-tabs.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/extend/interactions/class-accordion.php';
require_once GENERATEBLOCKS_PRO_DIR . 'includes/blocks/blocks.php';
