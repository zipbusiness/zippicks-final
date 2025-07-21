<?php
/**
 * The Libraries class file.
 *
 * @package GenerateBlocks\Pattern_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'GenerateBlocks_Libraries' ) ) :
	/**
	 * Class for handling with libraries.
	 *
	 * @since 1.9.0
	 */
	class GenerateBlocks_Pro_Pattern_Library extends GenerateBlocks_Pro_Singleton {

		/**
		 * The default id.
		 *
		 * @var string The default library id.
		 */
		protected $default_library_id = 'gb_default_pro_library';

		/**
		 * Initialize the class filters.
		 *
		 * @return void
		 */
		public function init() {
			add_filter(
				'generateblocks_pattern_libraries',
				array( $this, 'register_pro_libraries' ),
				5
			);

			add_action( 'wp_after_insert_post', [ $this, 'after_save' ], 10, 2 );
		}

		/**
		 * Register PRO libraries.
		 *
		 * @param array $libraries The registered libraries.
		 *
		 * @return array
		 */
		public function register_pro_libraries( array $libraries ): array {
			$libraries_instance = GenerateBlocks_Libraries::get_instance();

			// Force to always have the PRO library registered.
			if ( ! $libraries_instance->exists( $libraries, $this->default_library_id ) ) {
				$pro_library = $this->get_default();
				$libraries = array_merge( array( $pro_library ), $libraries );
			}

			$collections = $this->get_collections();

			$local_libraries = array_map(
				function( WP_term $term ) use ( $libraries_instance ) {
					return $libraries_instance->create(
						array(
							'id' => $term->slug,
							'name' => $term->name,
							'domain' => get_site_url(),
							'publicKey' => $term->slug,
							'isEnabled' => false,
							'isDefault' => false,
							'isLocal' => true,
						)
					);
				},
				$collections
			);

			return array_merge( $libraries, $local_libraries );
		}

		/**
		 * Returns the list of collections.s
		 *
		 * @return int[]|string|string[]|WP_Error|WP_Term[]
		 */
		protected function get_collections() {
			return get_terms(
				[
					'taxonomy' => 'gblocks_pattern_collections',
					'hide_empty' => true,
				]
			);
		}

		/**
		 * Return the default library.
		 *
		 * @return GenerateBlocks_Library_DTO
		 */
		protected function get_default(): GenerateBlocks_Library_DTO {
			$use_legacy_pattern_library = ! function_exists( 'generateblocks_use_v1_blocks' ) ||
				generateblocks_use_v1_blocks();

			$domain = 'https://patterns.generatepress.com';
			$public_key = 'NPhxc91jLH5yGB4Ni6KryXN6HKKggte0';

			if (
				$use_legacy_pattern_library ||
				apply_filters( 'generateblocks_force_v1_pattern_library', false )
			) {
				$domain = 'https://patterns.generateblocks.com';
				$public_key = 'c4ngBQvKWeqG17W8SAWVJ0FuyD9uCVvq';
			}

			return ( new GenerateBlocks_Library_DTO() )
				->set( 'id', $this->default_library_id )
				->set( 'name', __( 'Pro', 'generateblocks-pro' ) )
				->set( 'domain', $domain )
				->set( 'public_key', $public_key )
				->set( 'is_enabled', true )
				->set( 'is_default', true );
		}

		/**
		 * Given a key searches for all pattern collections.
		 *
		 * @param string $public_key The public key.
		 *
		 * @return array|WP_Error|null
		 */
		public function get_collections_by_public_key( string $public_key ) {
			$keys = get_posts(
				array(
					'fields'         => 'ids',
					'post_type'      => 'gblocks_public_keys',
					'meta_key'       => 'gb_public_key',
					'meta_value'     => $public_key,
				)
			);

			if ( isset( $keys[0] ) ) {
				$permissions = get_post_meta( $keys[0], 'gb_permissions', true );
				$collections = $permissions['patterns']['includes'] ?? [];

				return $collections;
			}

			return null;
		}

		/**
		 * Saves our pattern tree when saving a pattern.
		 *
		 * @param int     $post_id The post ID.
		 * @param WP_Post $post The post object.
		 *
		 * @return void
		 */
		public function after_save( int $post_id, WP_Post $post ): void {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			// Check if it is an autosave or a revision.
			if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			$post_type = get_post_type( $post_id );

			if ( 'wp_block' !== $post_type ) {
				return;
			}

			$tree = $this->build_tree( $post->post_content, $post_id );
			update_post_meta( $post_id, 'generateblocks_patterns_tree', $tree );

			// Clear our block pattern cache so it can regenerate.
			delete_option( 'generateblocks_block_patterns' );
		}

		/**
		 * Enable inline styles.
		 * We need a function so we can remove_action it after.
		 */
		public function enable_inline_styles() {
			return true;
		}

		/**
		 * Extracts global styles from blocks.
		 *
		 * @param array $blocks The parsed blocks on the page.
		 * @param array $data Optional. Additional data to be populated.
		 *
		 * @return void
		 */
		private function extract_global_style_selectors(
			$blocks,
			$data = [
				'selectors' => [],
				'reusableBlockIds' => [],
			]
		) {
			$global_styles = [];

			if ( ! is_array( $blocks ) || empty( $blocks ) ) {
				return;
			}

			foreach ( $blocks as $index => $block ) {
				if ( ! isset( $block['attrs'] ) || ! isset( $block['blockName'] ) ) {
					continue;
				}

				$block_global_styles = $block['attrs']['globalClasses'] ?? [];

				if ( ! empty( $block_global_styles ) ) {
					foreach ( $block_global_styles as $global_style ) {
						if ( ! in_array( $global_style, $global_styles ) ) {
							$data['selectors'][] = '.' . $global_style;
						}
					}
				}

				if ( 'core/block' === $block['blockName'] ) {
					if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
						$atts = $block['attrs'];

						if ( isset( $atts['ref'] ) && ( empty( $data['reusableBlockIds'] ) || ! in_array( $atts['ref'], (array) $data['reusableBlockIds'] ) ) ) {
							$reusable_block = get_post( $atts['ref'] );

							if ( $reusable_block && 'wp_block' === $reusable_block->post_type && 'publish' === $reusable_block->post_status ) {
								$reuse_data_block = parse_blocks( $reusable_block->post_content );

								if ( ! empty( $reuse_data_block ) ) {
									$data['reusableBlockIds'][] = $atts['ref'];
									$data = $this->extract_global_style_selectors( $reuse_data_block, $data );
								}
							}
						}
					}
				}

				if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
					$data = $this->extract_global_style_selectors( $block['innerBlocks'], $data );
				}
			}

			return $data;
		}

		/**
		 * Build our pattern tree.
		 *
		 * @param string $post_content The post content from our pattern.
		 * @param int    $post_id The ID of our post.
		 *
		 * @return array
		 */
		public function build_tree( string $post_content, $post_id ): array {
			// Force our previews to print inline styles above each block.
			add_filter( 'generateblocks_do_inline_styles', [ $this, 'enable_inline_styles' ] );

			// Get our full pattern to insert into the editor.
			$preview = do_blocks( $post_content );

			// Get any script URLs that need to be added later.
			$scripts = [];
			$styles  = [];

			if ( strpos( $preview, 'gb-accordion' ) !== false ) {
				$scripts[] = GENERATEBLOCKS_PRO_DIR_URL . 'dist/accordion.js';
				$styles[]  = GENERATEBLOCKS_PRO_DIR_URL . 'dist/accordion-style.css';
			}

			if ( strpos( $preview, 'gb-tabs' ) !== false ) {
				$scripts[] = GENERATEBLOCKS_PRO_DIR_URL . 'dist/tabs.js';
				$styles[]  = GENERATEBLOCKS_PRO_DIR_URL . 'dist/blocks/tabs/tabs.css';
			}

			if ( strpos( $preview, 'gb-menu' ) !== false ) {
				$scripts[] = GENERATEBLOCKS_PRO_DIR_URL . 'dist/classic-menu.js';
				$styles[]  = GENERATEBLOCKS_PRO_DIR_URL . 'dist/classic-menu-style.css';
			}

			$blocks = parse_blocks( $post_content );
			$global_style_selectors = $this->extract_global_style_selectors( $blocks );

			$patterns[] = [
				'id' => 'pattern-' . $post_id,
				'label' => get_the_title( $post_id ) ?? 'pattern-' . $post_id,
				'pattern' => wp_slash( $post_content ),
				'preview' => $preview,
				'scripts' => apply_filters(
					'generateblocks_pattern_preview_scripts',
					$scripts,
					[
						'preview' => $preview,
						'post_content' => $post_content,
					]
				),
				'styles' => apply_filters(
					'generateblocks_pattern_preview_styles',
					$styles,
					[
						'preview' => $preview,
						'post_content' => $post_content,
					]
				),
				'categories' => wp_get_post_terms( $post_id, 'wp_pattern_category', [ 'fields' => 'ids' ] ),
				'globalStyleSelectors' => $global_style_selectors['selectors'] ?? [],
			];

			// Cleanup.
			remove_filter( 'generateblocks_do_inline_styles', [ $this, 'enable_inline_styles' ] );

			return $patterns;
		}
	}

	GenerateBlocks_Pro_Pattern_Library::get_instance()->init();
endif;
