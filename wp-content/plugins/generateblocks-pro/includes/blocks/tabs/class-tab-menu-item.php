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
 * Tabs menu block class.
 */
class GenerateBlocks_Block_Tab_Menu_Item extends GenerateBlocks_Block {
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
	public static $block_name = 'generateblocks-pro/tab-menu-item';

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

		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		$processor       = new WP_HTML_Tag_Processor( $block_content );
		$tag_name        = $attributes['tagName'] ?? 'div';
		$main_tag        = $processor->next_tag( $tag_name );
		$updated_html    = false;

		if ( $processor && $main_tag ) {
			if ( $attributes['tabItemOpen'] ) {
				$processor->add_class( 'gb-block-is-current' );
				$updated_html = true;
			}

			if ( 'div' === $tag_name ) {
				if ( ! $processor->get_attribute( 'tabindex' ) ) {
					$processor->set_attribute( 'tabindex', '0' );
				}

				if ( ! $processor->get_attribute( 'role' ) ) {
					$processor->set_attribute( 'role', 'button' );
				}

				$updated_html = true;
			}

			if ( $updated_html ) {
				$block_content = $processor->get_updated_html();
			}
		}

		return $block_content;
	}
}
