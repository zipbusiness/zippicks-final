<?php
/**
 * Register our blocks.
 *
 * @package GenerateBlocks Pro
 */

add_action( 'init', 'generateblocks_pro_register_blocks' );
/**
 * Register the GenerateBlocks Pro blocks.
 *
 * @since 1.0.0
 */
function generateblocks_pro_register_blocks() {
	if ( ! class_exists( 'GenerateBlocks_Block' ) ) {
		return;
	}

	require_once 'accordion/accordion.php';
	require_once 'tabs/tabs.php';
	require_once 'classic-menu/classic-menu.php';
	require_once 'classic-menu-item/classic-menu-item.php';
	require_once 'classic-sub-menu/classic-sub-menu.php';
	require_once 'navigation/navigation.php';
	require_once 'menu-container/menu-container.php';
	require_once 'menu-toggle/menu-toggle.php';
	require_once 'site-header/site-header.php';

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/accordion',
		[
			'render_callback' => 'GenerateBlocks_Block_Accordion::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/accordion-item',
		[
			'render_callback' => 'GenerateBlocks_Block_Accordion_Item::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/accordion-toggle',
		[
			'render_callback' => 'GenerateBlocks_Block_Accordion_Toggle::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/accordion-toggle-icon',
		[
			'render_callback' => 'GenerateBlocks_Block_Accordion_Toggle_Icon::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/accordion-content',
		[
			'render_callback' => 'GenerateBlocks_Block_Accordion_Content::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/tabs',
		[
			'render_callback' => 'GenerateBlocks_Block_Tabs::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/tabs-menu',
		[
			'render_callback' => 'GenerateBlocks_Block_Tabs_Menu::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/tab-menu-item',
		[
			'render_callback' => 'GenerateBlocks_Block_Tab_Menu_Item::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/tab-items',
		[
			'render_callback' => 'GenerateBlocks_Block_Tab_Items::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/tab-item',
		[
			'render_callback' => 'GenerateBlocks_Block_Tab_Item::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/classic-menu',
		[
			'render_callback' => 'GenerateBlocks_Block_Classic_Menu::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/classic-menu-item',
		[
			'render_callback' => 'GenerateBlocks_Block_Classic_Menu_Item::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/classic-sub-menu',
		[
			'render_callback' => 'GenerateBlocks_Block_Classic_Sub_Menu::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/navigation',
		[
			'render_callback' => 'GenerateBlocks_Block_Navigation::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/menu-toggle',
		[
			'render_callback' => 'GenerateBlocks_Block_Menu_Toggle::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/menu-container',
		[
			'render_callback' => 'GenerateBlocks_Block_Menu_Container::render_block',
		]
	);

	register_block_type_from_metadata(
		GENERATEBLOCKS_PRO_DIR . '/dist/blocks/site-header',
		[
			'render_callback' => 'GenerateBlocks_Block_Site_Header::render_block',
		]
	);
}
