<?php
/**
 * Extend the Looper block.
 *
 * @package GenerateBlocksPro\Extend\Looper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Extend the default Query block.
 *
 * @since 2.0.0
 */
class GenerateBlocks_Pro_Block_Looper extends GenerateBlocks_Pro_Singleton {

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'generateblocks_looper_render_loop_items', [ $this, 'render_loop_items' ], 10, 5 );
	}

	/**
	 * Render loop items based on the query type.
	 *
	 * @param string       $output The block output.
	 * @param string       $query_type The query type.
	 * @param array|object $query_data The query data.
	 * @param WP_Block     $block The block instance.
	 *
	 * @return string The render content.
	 */
	public function render_loop_items( $output, $query_type, $query_data, $block ) {
		if ( GenerateBlocks_Pro_Block_Query::TYPE_POST_META === $query_type ) {
			return self::render_post_meta_loop_items( $query_data, $block );
		}

		if ( GenerateBlocks_Pro_Block_Query::TYPE_OPTION === $query_type ) {
			return self::render_option_loop_items( $query_data, $block );
		}

		return $output;
	}

	/**
	 * Render the repeater items for the Looper block.
	 *
	 * @param array    $items      The items to loop over.
	 * @param WP_Block $block      The block instance.
	 * @return string  The rendered content.
	 */
	public static function render_post_meta_loop_items( $items, $block ) {
		$query_id    = $block->context['generateblocks/queryData']['id'] ?? null;
		$page_key    = $query_id ? 'query-' . $query_id . '-page' : 'query-page';
		$args        = $block->context['generateblocks/queryData']['args'] ?? [];
		$per_page    = $args['posts_per_page'] ?? get_option( 'posts_per_page' );
		$page        = empty( $_GET[ $page_key ] ) ? 1 : (int) $_GET[ $page_key ]; // phpcs:ignore -- No data processing happening.
		$page_index  = $page - 1; // Zero based index for pages.
		$offset      = $page_index * $per_page;
		$content     = '';

		$index = $offset + 1;

		// Adjust value to support array_slice.
		if ( '-1' === $per_page ) {
			$per_page = count( $items );
		}

		if ( is_array( $items ) ) {
			$items = array_slice( $items, $offset, $per_page );
			foreach ( $items as $item ) {
				// Get the current index of the Loop.
				$content .= (
					new WP_Block(
						$block->parsed_block['innerBlocks'][0],
						array(
							'postType'                 => get_post_type(),
							'postId'                   => get_the_ID(),
							'generateblocks/queryType' => GenerateBlocks_Pro_Block_Query::TYPE_POST_META,
							'generateblocks/loopIndex' => $index,
							'generateblocks/loopItem'  => $item,

						)
					)
							)->render( array( 'dynamic' => false ) );
							$index++;
			}

			return $content;
		}

		// Fallback to support previews in Elements.
		$content = (
			new WP_Block(
				$block->parsed_block['innerBlocks'][0],
				array(
					'postType'                 => 'post',
					'postId'                   => 0,
					'generateblocks/queryType' => GenerateBlocks_Pro_Block_Query::TYPE_POST_META,
					'generateblocks/loopIndex' => 1,
					'generateblocks/loopItem'  => [ 'ID' => 0 ],

				)
			)
		)->render( array( 'dynamic' => false ) );

		return $content;
	}

	/**
	 * Render the repeater items for the Looper block.
	 *
	 * @param array    $items      The items to loop over.
	 * @param WP_Block $block      The block instance.
	 * @return string  The rendered content.
	 */
	public static function render_option_loop_items( $items, $block ) {
		$query_id    = $block->context['generateblocks/queryData']['id'] ?? null;
		$page_key    = $query_id ? 'query-' . $query_id . '-page' : 'query-page';
		$args        = $block->context['generateblocks/queryData']['args'] ?? [];
		$per_page    = $args['posts_per_page'] ?? get_option( 'posts_per_page' );
		$page        = empty( $_GET[ $page_key ] ) ? 1 : (int) $_GET[ $page_key ]; // phpcs:ignore -- No data processing happening.
		$page_index  = $page - 1; // Zero based index for pages.
		$offset      = $page_index * $per_page;
		$content     = '';

		$index = $offset + 1;

		// Adjust value to support array_slice.
		if ( '-1' === $per_page ) {
			$per_page = count( $items );
		}

		$inner_blocks = $block->parsed_block['innerBlocks'];

		if ( ! $inner_blocks ) {
			return $content;
		}

		if ( $items ) {
			$items = array_slice( $items, $offset, $per_page );

			foreach ( $items as $item ) {
				// Get the current index of the Loop.
				$content .= (
					new WP_Block(
						$inner_blocks[0],
						array(
							'postType'                 => $item['post_type'] ?? null,
							'postId'                   => $item['ID'] ?? $item['id'] ?? 0,
							'generateblocks/queryType' => GenerateBlocks_Pro_Block_Query::TYPE_OPTION,
							'generateblocks/loopIndex' => $index,
							'generateblocks/loopItem'  => $item,

						)
					)
				)->render( array( 'dynamic' => false ) );
				$index++;
			}
			return $content;
		}

		// Fallback to support previews in Elements.
		$content = (
				new WP_Block(
					$inner_blocks[0],
					array(
						'postType'                 => 'post',
						'postId'                   => 0,
						'generateblocks/queryType' => GenerateBlocks_Pro_Block_Query::TYPE_OPTION,
						'generateblocks/loopIndex' => 1,
						'generateblocks/loopItem'  => [ 'ID' => 0 ],

					)
				)
			)->render( array( 'dynamic' => false ) );

		return $content;
	}
}
GenerateBlocks_Pro_Block_Looper::get_instance()->init();
