<?php
/**
 * Handles the accordion block.
 *
 * @package GenerateBlocks Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'class-accordion.php';
require_once 'class-accordion-item.php';
require_once 'class-accordion-toggle.php';
require_once 'class-accordion-toggle-icon.php';
require_once 'class-accordion-content.php';

add_filter( 'block_editor_settings_all', 'generateblocks_pro_accordion_block_editor_settings', 20 );
/**
 * Add block editor settings for the navigation block.
 *
 * @param array $settings The block editor settings.
 */
function generateblocks_pro_accordion_block_editor_settings( $settings ) {
	$blocks_to_reset = [
		'.editor-styles-wrapper .wp-block-generateblocks-pro-accordion',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-accordion-item',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-accordion-toggle',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-accordion-toggle-icon',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-accordion-content',
	];
	$css = implode( ',', $blocks_to_reset ) . ' {max-width:unset;margin:0}';
	$settings['styles'][] = [ 'css' => $css ];

	return $settings;
}
