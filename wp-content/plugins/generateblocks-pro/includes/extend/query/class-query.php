<?php
/**
 * Extend the Query block.
 *
 * @package GenerateBlocksPro\Extend\Query
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Extend the default Query block.
 *
 * @since 2.0.0
 */
class GenerateBlocks_Pro_Block_Query extends GenerateBlocks_Pro_Singleton {
	// Add new Query types here.
	const TYPE_POST_META = 'post_meta';
	const TYPE_OPTION    = 'option';

	/**
	 * Init function.
	 */
	public function init() {
		if ( ! class_exists( 'GenerateBlocks_Meta_Handler' ) ) {
			return;
		}

		add_filter( 'generateblocks_query_data', [ $this, 'set_query_data' ], 10, 5 );
		add_filter( 'generateblocks_dynamic_tag_id', [ $this, 'set_dynamic_tag_id' ], 10, 3 );

		add_filter( 'generateblocks_query_wp_query_args', [ $this, 'exclude_current_post' ], 10, 4 );
		add_filter( 'generateblocks_query_wp_query_args', [ $this, 'include_current_author' ], 10, 4 );
		add_filter( 'generateblocks_query_wp_query_args', [ $this, 'exclude_current_author' ], 10, 4 );
		add_filter( 'generateblocks_query_wp_query_args', [ $this, 'current_post_terms' ], 10, 4 );
		add_filter( 'generateblocks_query_wp_query_args', [ $this, 'include_current_parent' ], 10, 4 );
		add_filter( 'generateblocks_query_wp_query_args', [ $this, 'exclude_current_parent' ], 10, 4 );
	}

	/**
	 * Update the dynamic tag's ID if it's a post tag, it has a valid loop item ID, and no other ID is set in options.
	 *
	 * @param int    $id The current ID value for the tag.
	 * @param array  $options The tag options.
	 * @param object $instance The block instance for the block containing the tag.
	 * @return int The ID for the dynamic tag.
	 */
	public function set_dynamic_tag_id( $id, $options, $instance ) {
		// If an ID is set in options, use the original ID.
		if ( $options['id'] ?? false ) {
			return $id;
		}

		$loop_item = $instance->context['generateblocks/loopItem'] ?? null;

		if ( ! $loop_item ) {
			return $id;
		}

		// Look for the ID or id keys and return the original $id if none can be found.
		if ( is_array( $loop_item ) ) {
			return $loop_item['ID'] ?? $loop_item['id'] ?? $id;
		} elseif ( is_object( $loop_item ) ) {
			return $loop_item->ID ?? $loop_item->id ?? $id;
		}

		return $id;
	}

	/**
	 *  Set the query data for certain types.
	 *
	 * @param array    $query_data The current query data.
	 * @param string   $query_type The type of query.
	 * @param array    $attributes An array of block attributes.
	 * @param WP_Block $block The block instance.
	 * @param int      $page The page number.
	 *
	 * @return array An array of query data.
	 */
	public function set_query_data( $query_data, $query_type, $attributes, $block, $page ) {
		$pro_types = [
			self::TYPE_POST_META,
			self::TYPE_OPTION,
		];

		if ( ! in_array( $query_type, $pro_types, true ) ) {
			return $query_data;
		}

		$query    = $attributes['query'] ?? [];
		$id       = $query['meta_key_id'] ?? '';
		$meta_key = $query['meta_key'] ?? '';

		if ( 'current' === $id || ! $id ) {
			$id = get_the_ID();
		}

		if ( self::TYPE_POST_META === $query_type ) {
			$value = GenerateBlocks_Meta_Handler::get_post_meta( $id, $meta_key, false );
		} elseif ( self::TYPE_OPTION === $query_type ) {
			$value = GenerateBlocks_Meta_Handler::get_option( $meta_key, false );
		}

		$data = is_array( $value ) ? $value : [];
		// Handle pagination.
		$posts_per_page = (int) ( $query['posts_per_page'] ?? get_option( 'posts_per_page' ) );
		$offset         = isset( $query['offset'] ) && is_numeric( $query['offset'] ) ? $query['offset'] : 0;

		// Get the total number of items less the user specified offset.
		$data      = array_slice( $data, $offset );
		$max_pages = $posts_per_page > 0
		? (int) ceil( count( $data ) / $posts_per_page )
		: 1;

		if ( 0 === $posts_per_page ) {
			$max_pages = 0;
		}

		return [
			'data'          => $data,
			'no_results'    => empty( $data ),
			'args'          => $query,
			'max_num_pages' => $max_pages,
		];
	}

	/**
	 * Exclude current post from the query.
	 *
	 * @param array         $query_args The query arguments.
	 * @param array         $attributes The block attributes.
	 * @param WP_Block|null $block The block instance.
	 * @param array         $current The current post data.
	 *
	 * @return array The query arguments without current post.
	 */
	public function exclude_current_post( $query_args, $attributes, $block, $current ) {
		$current_post_id = $current['post_id'] ?? get_the_ID();

		return self::add_current_post( $query_args, $current_post_id, 'post__not_in' );
	}

	/**
	 * Include current parent post from the query.
	 *
	 * @param array         $query_args The query arguments.
	 * @param array         $attributes The block attributes.
	 * @param WP_Block|null $block The block instance.
	 * @param array         $current The current post data.
	 *
	 * @return array The query arguments without current post.
	 */
	public function include_current_parent( $query_args, $attributes, $block, $current ) {
		$current_post_id = $current['post_id'] ?? get_the_ID();

		return self::add_current_post( $query_args, $current_post_id, 'post_parent__in' );
	}

	/**
	 * Exclude current parent post from the query.
	 *
	 * @param array         $query_args The query arguments.
	 * @param array         $attributes The block attributes.
	 * @param WP_Block|null $block The block instance.
	 * @param array         $current The current post data.
	 *
	 * @return array The query arguments without current post.
	 */
	public function exclude_current_parent( $query_args, $attributes, $block, $current ) {
		$current_post_id = $current['post_id'] ?? get_the_ID();

		return self::add_current_post( $query_args, $current_post_id, 'post_parent__not_in' );
	}

	/**
	 * Adds the current post ID to the query args.
	 *
	 * @param array  $query_args The query arguments.
	 * @param int    $current_post_id The current post ID.
	 * @param string $key The key to check.
	 *
	 * @return array The query arguments without current post.
	 */
	public static function add_current_post( $query_args, $current_post_id, $key ) {
		if (
			isset( $query_args[ $key ] ) &&
			in_array( 'current', $query_args[ $key ] )
		) {
			if ( ! in_array( $current_post_id, $query_args[ $key ] ) ) {
				$query_args[ $key ][] = $current_post_id;
			}

			$exclude_current_index = array_search( 'current', $query_args[ $key ] );
			array_splice( $query_args[ $key ], $exclude_current_index, 1 );

			if ( 'post__not_in' === $key ) {
				// This is to avoid current post being dynamically added to post__in which will show him in the result set.
				if (
					isset( $query_args['post__in'] ) &&
					in_array( $current_post_id, $query_args['post__in'] )
				) {
					$current_post_index = array_search( $current_post_id, $query_args['post__in'] );
					array_splice( $query_args['post__in'], $current_post_index, 1 );
				}
			}
		}

		return $query_args;
	}

	/**
	 * Include posts of current post author to the query.
	 *
	 * @since 1.3.0
	 * @param array $query_args The query arguments.
	 *
	 * @return array The query arguments.
	 */
	public function include_current_author( $query_args ) {
		return self::add_current_author( $query_args, 'author__in' );
	}

	/**
	 * Exclude posts of current post author to the query.
	 *
	 * @since 1.3.0
	 * @param array $query_args The query arguments.
	 *
	 * @return array The query arguments.
	 */
	public function exclude_current_author( $query_args ) {
		return self::add_current_author( $query_args, 'author__not_in' );
	}

	/**
	 * Include current author to a query argument.
	 *
	 * @since 1.3.0
	 * @param array  $query_args The query arguments.
	 * @param string $key The query argument key.
	 *
	 * @return array The query arguments.
	 */
	public function add_current_author( $query_args, $key ) {
		if (
			isset( $query_args[ $key ] ) &&
			in_array( 'current', $query_args[ $key ] )
		) {
			$current_post_author_index = array_search( 'current', $query_args[ $key ] );
			array_splice( $query_args[ $key ], $current_post_author_index, 1 );

			if ( ! in_array( get_the_author_meta( 'ID' ), $query_args[ $key ] ) ) {
				$query_args[ $key ][] = get_the_author_meta( 'ID' );
			}
		}

		return $query_args;
	}

	/**
	 * Process the "current" post terms.
	 *
	 * @param array         $query_args The query arguments.
	 * @param array         $attributes The block attributes.
	 * @param WP_Block|null $block The block instance.
	 * @param array         $current The current post data.
	 *
	 * @return array The query arguments.
	 */
	public function current_post_terms( $query_args, $attributes, $block, $current ) {
		if (
			$current['post_id'] &&
			isset( $query_args['tax_query'] )
		) {
			$query_args['tax_query'] = array_map(
				function( $tax ) use ( $current ) {

					if ( ! isset( $tax['terms'] ) ) {
						return $tax;
					}

					if ( in_array( 'current', $tax['terms'], true ) ) {
						$registered_taxonomies = get_object_taxonomies( get_post_type( $current['post_id'] ) );

						if ( in_array( $tax['taxonomy'], $registered_taxonomies, true ) ) {
							$related_terms = wp_get_object_terms(
								$current['post_id'],
								$tax['taxonomy'],
								array( 'fields' => 'ids' )
							);

							$tax['terms'] = array_merge( $tax['terms'], $related_terms );
						}

						$current_terms_index = array_search( 'current', $tax['terms'] );
						array_splice( $tax['terms'], $current_terms_index, 1 );
					}

					return $tax;
				},
				$query_args['tax_query']
			);
		}

		return $query_args;
	}
}

GenerateBlocks_Pro_Block_Query::get_instance()->init();
