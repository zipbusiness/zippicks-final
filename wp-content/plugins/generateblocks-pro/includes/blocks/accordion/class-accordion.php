<?php
/**
 * Handles the Accordion block.
 *
 * @package GenerateBlocksPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Accordion block class.
 */
class GenerateBlocks_Block_Accordion extends GenerateBlocks_Block {
	/**
	 * Keep track of all blocks of this type on the page.
	 *
	 * @var array $block_ids The current block id.
	 */
	protected static $block_ids = [];

	/**
	 * Store our block name.
	 *
	 * @var string $block_name The block name.
	 */
	public static $block_name = 'generateblocks-pro/accordion';

	/**
	 * Render the Element block.
	 *
	 * @param array  $attributes    The block attributes.
	 * @param string $block_content The block content.
	 * @param array  $block         The block.
	 */
	public static function render_block( $attributes, $block_content, $block ) {
		// Add styles to this block if needed.
		$block_content = generateblocks_maybe_add_block_css(
			$block_content,
			[
				'class_name' => __CLASS__,
				'attributes' => $attributes,
				'block_ids' => self::$block_ids,
			]
		);

		if ( ! wp_style_is( 'generateblocks-accordion', 'enqueued' ) ) {
			self::enqueue_style();
		}

		if ( ! wp_script_is( 'generateblocks-accordion', 'enqueued' ) ) {
			self::enqueue_assets();
		}

		return $block_content;
	}

	/**
	 * Enqueue block styles.
	 */
	private static function enqueue_style() {
		wp_enqueue_style(
			'generateblocks-accordion',
			GENERATEBLOCKS_PRO_DIR_URL . 'dist/accordion-style.css',
			[],
			GENERATEBLOCKS_PRO_VERSION
		);
	}

	/**
	 * Enqueue block scripts.
	 */
	private static function enqueue_scripts() {
		wp_enqueue_script(
			'generateblocks-accordion',
			GENERATEBLOCKS_PRO_DIR_URL . 'dist/accordion.js',
			[],
			GENERATEBLOCKS_PRO_VERSION,
			true
		);
	}

	/**
	 * Enqueue block assets.
	 */
	public static function enqueue_assets() {
		self::enqueue_scripts();
		self::enqueue_style();
	}
}
