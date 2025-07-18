<?php
/**
 * Handles the accordion block.
 *
 * @package GenerateBlocks Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'class-tabs.php';
require_once 'class-tabs-menu.php';
require_once 'class-tab-menu-item.php';
require_once 'class-tab-items.php';
require_once 'class-tab-item.php';

add_filter( 'block_editor_settings_all', 'generateblocks_pro_tabs_block_editor_settings', 20 );
/**
 * Add block editor settings for the tabs block.
 *
 * @param array $settings The block editor settings.
 */
function generateblocks_pro_tabs_block_editor_settings( $settings ) {
	$blocks_to_reset = [
		'.editor-styles-wrapper .wp-block-generateblocks-pro-tabs',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-tabs-menu',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-tab-menu-item',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-tab-items',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-tab-item',
	];
	$css = implode( ',', $blocks_to_reset ) . ' {max-width:unset;margin:0}';
	$settings['styles'][] = [ 'css' => $css ];

	return $settings;
}
