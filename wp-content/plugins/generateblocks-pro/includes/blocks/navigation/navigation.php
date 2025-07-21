<?php
/**
 * Handle the navigation block.
 *
 * @package GenerateBlocks Pro
 */

// Include our files.
require_once 'class-navigation.php';

add_filter( 'block_editor_settings_all', 'generateblocks_pro_navigation_block_editor_settings', 20 );
/**
 * Add block editor settings for the navigation block.
 *
 * @param array $settings The block editor settings.
 */
function generateblocks_pro_navigation_block_editor_settings( $settings ) {
	$blocks_to_reset = [
		'.editor-styles-wrapper .wp-block-generateblocks-pro-classic-menu',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-menu-container',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-menu-toggle',
		'.editor-styles-wrapper .wp-block-generateblocks-pro-navigation',
	];
	$css = implode( ',', $blocks_to_reset ) . ' {max-width:unset;margin:0}';
	$settings['styles'][] = [ 'css' => $css ];

	return $settings;
}

add_filter( 'generateblocks_block_css', 'generateblocks_pro_navigation_block_css', 10, 2 );
/**
 * Add block CSS for the navigation block.
 *
 * @param string $css The CSS to add.
 * @param array  $block The block data.
 *
 * @return string
 */
function generateblocks_pro_navigation_block_css( $css, $block ) {
	if ( ! isset( $block['attributes']['uniqueId'] ) ) {
		return $css;
	}

	$block_name = $block['block_name'] ?? '';

	if ( 'generateblocks-pro/navigation' !== $block_name ) {
		return $css;
	}

	$selector = '.gb-navigation-' . $block['attributes']['uniqueId'];

	$mobile_breakpoint = $block['attributes']['htmlAttributes']['data-gb-mobile-breakpoint'] ?? '';

	if ( $mobile_breakpoint ) {
		$css .= "@media (width > {$mobile_breakpoint}) {{$selector} .gb-menu-toggle {display: none;}}";
		$css .= "@media (max-width: {$mobile_breakpoint}) {{$selector} .gb-menu-container:not(.gb-menu-container--toggled) {display: none;}}";
	} else {
		$css .= "{$selector} .gb-menu-toggle {display: none;}";
	}

	return $css;
}
