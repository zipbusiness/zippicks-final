<?php
/**
 * Handle the navigation block.
 *
 * @package GenerateBlocks Pro
 */

// Include our files.
require_once 'class-site-header.php';

add_filter( 'block_editor_settings_all', 'generateblocks_pro_site_header_block_editor_settings', 20 );
/**
 * Add block editor settings for the navigation block.
 *
 * @param array $settings The block editor settings.
 */
function generateblocks_pro_site_header_block_editor_settings( $settings ) {
	$blocks_to_reset = [
		'.editor-styles-wrapper .wp-block-generateblocks-pro-site-header',
	];
	$css = implode( ',', $blocks_to_reset ) . ' {max-width:unset;margin:0}';
	$settings['styles'][] = [ 'css' => $css ];

	return $settings;
}
