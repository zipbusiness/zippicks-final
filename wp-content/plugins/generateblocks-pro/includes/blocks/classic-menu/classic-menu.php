<?php
/**
 * Handle the menu block.
 *
 * @package GenerateBlocks Pro
 */

// Include our files.
require_once 'class-classic-menu.php';

add_filter( 'block_editor_settings_all', 'generateblocks_pro_classic_menu_block_settings' );
/**
 * Add menu block settings.
 * We need to add CSS this way as it prepends `.editor-styles-wrapper` to the selectors.
 * Including the stylesheet via block.json does not, which causes specificity issues.
 *
 * @param array $settings Block editor settings.
 * @return array
 */
function generateblocks_pro_classic_menu_block_settings( $settings ) {
	$navigation_css = file_get_contents( GENERATEBLOCKS_PRO_DIR . 'dist/classic-menu-style.css' ); // phpcs:ignore -- correct function to use.

	if ( ! $navigation_css ) {
		return $settings;
	}

	$settings['styles'][] = [
		'css' => $navigation_css,
		'source' => 'generateblocks-pro/class-menu-style',
	];

	return $settings;
}
