<?php
/**
 * The Dynamic Tags class file.
 *
 * @package GeneratePress_Pro\Dynamic_Tags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for handling dynamic tags.
 *
 * @since 2.0.0
 */
class GeneratePress_Pro_Dynamic_Tags_Register extends GeneratePress_Pro_Singleton {
	/**
	 * Initialize all hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register' ] );
	}

	/**
	 * Register the tags.
	 *
	 * @return void
	 */
	public function register() {
		// Don't register these tags if we don't have the registration class, or if GenerateBlocks Pro is already registering them.
		if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) || class_exists( 'GenerateBlocks_Pro_Dynamic_Tags_Register' ) ) {
			return;
		}

		new GenerateBlocks_Register_Dynamic_Tag(
			[
				'title'       => __( 'Archive Title', 'gp-premium' ),
				'tag'         => 'archive_title',
				'type'        => 'archive',
				'supports'    => [],
				'description' => __( 'Get the title for the current archive being viewed.', 'gp-premium' ),
				'return'      => [ $this, 'get_archive_title' ],
			]
		);

		new GenerateBlocks_Register_Dynamic_Tag(
			[
				'title'       => __( 'Archive Description', 'gp-premium' ),
				'tag'         => 'archive_description',
				'type'        => 'archive',
				'supports'    => [],
				'description' => __( 'Get the description for the current archive being viewed.', 'gp-premium' ),
				'return'      => [ $this, 'get_archive_description' ],
			]
		);

		new GenerateBlocks_Register_Dynamic_Tag(
			[
				'title'       => __( 'Term Meta', 'gp-premium' ),
				'tag'         => 'term_meta',
				'type'        => 'term',
				'supports'    => [ 'meta', 'source' ],
				'description' => __( 'Access term meta by key for the specified term. Return value must be a string.', 'gp-premium' ),
				'return'      => [ $this, 'get_term_meta' ],
			]
		);

		new GenerateBlocks_Register_Dynamic_Tag(
			[
				'title'    => __( 'Current year', 'gp-premium' ),
				'tag'      => 'current_year',
				'type'     => 'site',
				'supports' => [],
				'return'   => [ $this, 'get_current_year' ],
			]
		);

		new GenerateBlocks_Register_Dynamic_Tag(
			[
				'title'    => __( 'Previous Posts URL', 'gp-premium' ),
				'tag'      => 'previous_posts_page_url',
				'type'     => 'post',
				'supports' => [ 'source', 'instant-pagination' ],
				'return'   => [ $this, 'get_previous_posts_page_url' ],
			]
		);

		new GenerateBlocks_Register_Dynamic_Tag(
			[
				'title'    => __( 'Next Posts URL', 'gp-premium' ),
				'tag'      => 'next_posts_page_url',
				'type'     => 'post',
				'supports' => [ 'source', 'instant-pagination' ],
				'return'   => [ $this, 'get_next_posts_page_url' ],
			]
		);
	}

	/**
	 * Get the archive title.
	 *
	 * @param array  $options The options.
	 * @param object $block The block.
	 * @param object $instance The block instance.
	 * @return string
	 */
	public static function get_archive_title( $options, $block, $instance ) {
		$output = '';
		$id     = $options['id'] ?? 0;

		if ( is_category() ) {
			$output = single_cat_title( '', false );
		} elseif ( is_tag() ) {
			$output = single_tag_title( '', false );
		} elseif ( is_author() ) {
			$output = get_the_author();
		} elseif ( is_post_type_archive() ) {
			$output = post_type_archive_title( '', false );
		} elseif ( is_tax() ) {
			$output = single_term_title( '', false );
		} elseif ( is_home() ) {
			$page = get_option( 'page_for_posts' ) ?? 0;

			if ( $page ) {
				$output = get_the_title( $page );
			} else {
				$output = __( 'Blog', 'generateblocks-pro' );
			}
		} elseif ( is_search() ) {
			$output = get_search_query();
		} elseif ( $id ) {
			if ( term_exists( (int) $id ) ) {
				$term = get_term( $id );
				$output = $term->name;
			} elseif ( is_string( $id ) ) {
				// Assume it's a post type archive title.
				$post_type_obj = get_post_type_object( $id );
				$title         = $post_type_obj->labels->name ?? '';

				if ( $title ) {
					/**
					 * Core Filter. Filters the post type archive title.
					 *
					 * @param string $post_type_name Post type 'name' label.
					 * @param string $post_type      Post type.
					 */
					$output = apply_filters( 'post_type_archive_title', $title, $id );
				}
			}
		}

		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $options, $instance );
	}

	/**
	 * Get the archive description.
	 *
	 * @param array  $options The options.
	 * @param object $block The block.
	 * @param object $instance The block instance.
	 * @return string
	 */
	public static function get_archive_description( $options, $block, $instance ) {
		$output = get_the_archive_description();

		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $options, $instance );
	}

	/**
	 * Get the term meta.
	 *
	 * @param array  $options The options.
	 * @param array  $block The block.
	 * @param object $instance The block instance.
	 * @return string
	 */
	public static function get_term_meta( $options, $block, $instance ) {
		$id = GenerateBlocks_Dynamic_Tags::get_id( $options, 'term', $instance );

		if ( ! $id ) {
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
		}

		$key    = $options['key'] ?? '';
		$output = '';

		if ( empty( $key ) ) {
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $options, $instance );
		}

		$value = GenerateBlocks_Meta_Handler::get_term_meta( $id, $key, true );

		if ( ! $value ) {
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $options, $instance );
		}

		add_filter( 'wp_kses_allowed_html', [ 'GenerateBlocks_Dynamic_Tags', 'expand_allowed_html' ], 10, 2 );
		$output = wp_kses_post( $value );
		remove_filter( 'wp_kses_allowed_html', [ 'GenerateBlocks_Dynamic_Tags', 'expand_allowed_html' ], 10, 2 );

		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $options, $instance );
	}

	/**
	 * Get the current year.
	 *
	 * @param array  $options The options.
	 * @param array  $block The block.
	 * @param object $instance The block instance.
	 *
	 * @return string
	 */
	public static function get_current_year( $options, $block, $instance ) {
		$output = wp_date( 'Y' );

		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $options, $instance );
	}

	/**
	 * Get the previous post page URL.
	 *
	 * @param array  $options The options.
	 * @param object $block The block.
	 * @param object $instance The block instance.
	 * @return string
	 */
	public static function get_previous_posts_page_url( $options, $block, $instance ) {
		$page_key      = isset( $instance->context['generateblocks/queryId'] ) ? 'query-' . $instance->context['generateblocks/queryId'] . '-page' : 'query-page';
		$page          = empty( $_GET[ $page_key ] ) ? 1 : (int) $_GET[ $page_key ]; // phpcs:ignore -- No data processing happening.
		$inherit_query = $instance->context['generateblocks/inheritQuery'] ?? false;
		$output   = '';

		if ( $inherit_query ) {
			global $paged;

			if ( $paged > 1 ) {
				$output = previous_posts( false );
			}
		} elseif ( 1 !== $page ) {
			$output = esc_url( add_query_arg( $page_key, $page - 1 ) );
		}

		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $options, $instance );
	}

	/**
	 * Get the next post page URL.
	 *
	 * @param array  $options The options.
	 * @param object $block The block.
	 * @param object $instance The block instance.
	 * @return string
	 */
	public static function get_next_posts_page_url( $options, $block, $instance ) {
		$page_key      = isset( $instance->context['generateblocks/queryId'] ) ? 'query-' . $instance->context['generateblocks/queryId'] . '-page' : 'query-page';
		$page          = empty( $_GET[ $page_key ] ) ? 1 : (int) $_GET[ $page_key ]; // phpcs:ignore -- No data processing happening.
		$args          = $instance->context['generateblocks/query'] ?? [];
		$inherit_query = $instance->context['generateblocks/inheritQuery'] ?? false;
		$per_page      = $args['per_page'] ?? apply_filters( 'generateblocks_query_per_page_default', 10, $args );
		$output        = '';

		if ( $inherit_query ) {
			global $wp_query, $paged;

			if ( ! $paged ) {
				$paged = 1; // phpcs:ignore -- Need to overrite global here.
			}

			$next_page = (int) $paged + 1;

			if ( $next_page <= $wp_query->max_num_pages ) {
				$output = next_posts( $wp_query->max_num_pages, false );
			}
		} else {
			$query_data  = $instance->context['generateblocks/queryData'] ?? null;
			$query_type  = $instance->context['generateblocks/queryType'] ?? GenerateBlocks_Block_Query::TYPE_WP_QUERY;
			$is_wp_query = GenerateBlocks_Block_Query::TYPE_WP_QUERY === $query_type;

			if ( ! $query_data || ( ! $is_wp_query && ! is_array( $query_data ) ) ) {
				return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $options, $instance );
			}

			$next_page              = $page + 1;
			$custom_query_max_pages = $is_wp_query
				? (int) $query_data->max_num_pages
				: ceil( count( $query_data ) / $per_page );

			if ( $custom_query_max_pages < $next_page ) {
				return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $options, $instance );
			}

			if ( $custom_query_max_pages && $custom_query_max_pages !== $page ) {
				$output = esc_url( add_query_arg( $page_key, $page + 1 ) );
			}

			wp_reset_postdata(); // Restore original Post Data.
		}

		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $options, $instance );
	}
}

GeneratePress_Pro_Dynamic_Tags_Register::get_instance()->init();
