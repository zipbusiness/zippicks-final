<?php
/**
 * The Pattern library rest class file.
 *
 * @package GenerateBlocksPro\Pattern_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'GenerateBlocks_Pattern_Library_Rest' ) ) :
	/**
	 * Main class for the Pattern Library Rest functions.
	 *
	 * @since 1.9
	 */
	class GenerateBlocks_Pro_Pattern_Library_Rest extends GenerateBlocks_Pro_Singleton {
		/**
		 * Initialize all hooks.
		 *
		 * @return void
		 */
		public function init() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Register the REST routes.
		 *
		 * @return void
		 */
		public function register_routes(): void {
			$namespace = 'generateblocks-pro/v1';

			register_rest_route(
				$namespace,
				'/pattern-library/categories',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_categories' ),
					'permission_callback' => array( $this, 'can_list_patterns' ),
				)
			);

			register_rest_route(
				$namespace,
				'/pattern-library/patterns',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_patterns' ),
					'permission_callback' => array( $this, 'can_list_patterns' ),
				)
			);

			register_rest_route(
				$namespace,
				'/pattern-library/get-global-style-data',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_global_style_data' ),
					'permission_callback' => function() {
						return apply_filters(
							'generateblocks_can_view_pattern_library',
							$this->edit_posts()
						);
					},
				)
			);

			register_rest_route(
				$namespace,
				'/pattern-library/provide-global-style-data',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'provide_global_style_data' ),
					'permission_callback' => array( $this, 'can_list_patterns' ),
				)
			);

			register_rest_route(
				$namespace,
				'/pattern-library/import-styles',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_styles' ),
					'permission_callback' => array( $this, 'can_manage_classes' ),
				)
			);

			register_rest_route(
				$namespace,
				'/pattern-library/get-library-by-public-key',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_library_by_public_key' ),
					'permission_callback' => array( $this, 'has_valid_key' ),
				)
			);

			register_rest_route(
				$namespace,
				'/pattern-library/add-library',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_library' ),
					'permission_callback' => array( $this, 'can_manage_classes' ),
				)
			);
		}

		/**
		 * Check to see if we can import components.
		 */
		public function can_manage_classes(): bool {
			return current_user_can( 'manage_options' );
		}

		/**
		 * Check to see if the user can edit this post.
		 */
		public function edit_posts(): bool {
			return current_user_can( 'edit_posts' );
		}

		/**
		 * Check to see if the user can view patterns.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return bool
		 */
		public function can_list_patterns( WP_REST_Request $request ): bool {
			$is_local = $request->get_param( 'isLocal' );

			if ( $is_local ) {
				$current_host = $_SERVER['HTTP_HOST'];
				$request_host = $request->get_header( 'host' );

				return $current_host === $request_host
					? $this->edit_posts()
					: false;
			}

			return $this->has_valid_key( $request );
		}

		/**
		 * Check to see if the user has a valid public key.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return bool
		 */
		public function has_valid_key( WP_REST_Request $request ): bool {
			$public_key = $request->get_header( 'X-GB-Public-Key' );

			if ( is_null( $public_key ) || ! class_exists( 'GenerateCloud\Utils\Functions' ) ) {
				return false;
			}

			$public_key_post = GenerateCloud\Utils\Functions::get_public_key_post( $public_key );

			if ( ! isset( $public_key_post->ID ) ) {
				return false;
			}

			$permissions = get_post_meta( $public_key_post->ID, 'gb_permissions', true );
			$is_enabled  = $permissions['patterns']['enabled'] ?? false;

			return $is_enabled;
		}

		/**
		 * Returns a list of categories.
		 *
		 * @return WP_REST_Response
		 */
		public function list_categories(): WP_REST_Response {
			$terms = get_terms(
				array(
					'taxonomy' => 'wp_pattern_category',
					'hide_empty' => true,
				)
			);

			$data = array_map(
				function( WP_Term $term ) {
					return array(
						'id' => $term->term_id,
						'name' => $term->name,
					);
				},
				$terms
			);

			return $this->success( $data );
		}

		/**
		 * Returns a list of patterns.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return WP_REST_Response
		 */
		public function list_patterns( WP_REST_Request $request ): WP_REST_Response {
			$public_key = $request->get_header( 'X-GB-Public-Key' );
			$library_id = $request->get_param( 'libraryId' );
			$search = $request->get_param( 'search' );
			$category_id = $request->get_param( 'categoryId' );
			$cat_query = isset( $category_id ) && '' !== $category_id ? array(
				'taxonomy' => 'wp_pattern_category',
				'field'    => 'term_id',
				'terms'    => $category_id,
			) : null;

			$instance = GenerateBlocks_Pro_Pattern_Library::get_instance();

			$allowed_collections = $instance->get_collections_by_public_key( $public_key );

			// If the key is not attached to any collection.
			if ( is_null( $allowed_collections ) ) {
				$allowed_collections = get_terms(
					[
						'taxonomy' => 'gblocks_pattern_collections',
						'slug' => $library_id,
						'fields' => 'ids',
					]
				);

				if ( ! $allowed_collections ) {
					return $this->success( array() );
				}
			}

			$posts = get_posts(
				array(
					'post_type' => 'wp_block',
					'posts_per_page' => apply_filters( 'generateblocks_pattern_library_count', 250 ), // phpcs:disable -- We need to get a lot of results once to cache them.
					's'         => $search,
					'tax_query' => array(
						$cat_query,
						array(
							'taxonomy' => 'gblocks_pattern_collections',
							'field'    => 'term_id',
							'terms'    => $allowed_collections,
						),
					),
				)
			);

			$data = array_reduce(
				$posts,
				function( $patterns, $post ) use ( $category_id ) {
					$post_patterns = get_post_meta( $post->ID, 'generateblocks_patterns_tree', true );

					// Don't return patterns with no tree.
					if ( ! $post_patterns ) {
						return $patterns;
					}

					// Filter patterns by category.
					$filter_patterns = array_filter(
						$post_patterns,
						function( $pattern ) use ( $category_id ) {
							if ( isset( $category_id ) && '' !== $category_id ) {
								return in_array( $category_id, $pattern['categories'] );
							}

							return true;
						}
					);

					return array_merge(
						$patterns,
						$filter_patterns
					);
				},
				[]
			);

			return $this->success( $data );
		}

		/**
		 * Returns a list of required classes from the provider.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return WP_REST_Response
		 */
		public function get_global_style_data( WP_REST_Request $request ): WP_REST_Response {
			$url = $request->get_param( 'url' );
			$id = $request->get_param( 'id' );
			$public_key = $request->get_param( 'publicKey' );
			$cache_key = $id . '_global-style-data';
			$cache = GenerateBlocks_Libraries::get_cached_data( $cache_key );

			if ( false !== $cache ) {
				return $this->success( $cache );
			}

			$request = wp_remote_get(
				trailingslashit( $url ) . 'wp-json/generateblocks-pro/v1/pattern-library/provide-global-style-data',
				[
					'headers' => array(
						'X-GB-Public-Key' => esc_html( $public_key ),
					),
				]
			);

			if ( is_wp_error( $request ) ) {
				return $this->failed( __( 'Unable to request required classes.', 'generateblocks-pro' ) );
			}

			$body = wp_remote_retrieve_body( $request );
			$body = json_decode( $body, true );
			$data = $body['response']['data'] ?? [];

			// Cache our data.
			GenerateBlocks_Libraries::set_cached_data( $data, $cache_key );

			return $this->success( $data );
		}

		/**
		 * Returns an array of class data.
		 * This function is called on the providers server.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return WP_REST_Response
		 */
		public function provide_global_style_data( WP_REST_Request $request ): WP_REST_Response {
			$posts = get_posts( [
				'post_type' => 'gblocks_styles',
				'posts_per_page' => 500,
				'order' => 'ASC',
				'orderby' => 'ID',
			] );

			$data['css'] = GenerateBlocks_Pro_Styles::get_styles_css();

			foreach ( $posts as $post ) {
				$class_name = get_post_meta( $post->ID, 'gb_style_selector', true );
				$styles = get_post_meta( $post->ID, 'gb_style_data', true );
				$css = get_post_meta( $post->ID, 'gb_style_css', true );

				$data['styles'][] = [
					'title' => $class_name,
					'className' => $class_name,
					'styles' => json_encode( $styles ),
					'css' => $css,
				];
			}

			return $this->success( $data );
		}

		/**
		 * Get the next menu order for a new style
		 *
		 * @return int The new menu_order to use.
		 */
		public function get_next_style_menu_order() {
			$styles     = GenerateBlocks_Pro_Styles::get_styles();
			$last_style = end( $styles );

			if( $last_style ) {
				return ( $last_style['menu_order'] ?? 0 ) + 1;
			}

			return 0;
		}

		/**
		 * Import a set of global classes.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return WP_REST_Response
		 */
		public function import_styles( WP_REST_Request $request ): WP_Rest_Response {
			$styles = $request->get_param( 'styles' );
			$response = [
				'imported' => [],
				'existing' => [],
			];

			foreach ( $styles as $style ) {
				$existing_class = new WP_Query(
					[
						'post_type'      => 'gblocks_styles',
						'posts_per_page' => 1,
						'post_status'    => 'any',
						'meta_query'     => [
							[
								'key'     => 'gb_style_selector',
								'value'   => $style['className'],
								'compare' => '=',
							],
						],
					]
				);

				$existing_post_id = false;

				if ( ! empty( $existing_class->found_posts ) ) {
					$existing_class = $existing_class->posts[ 0 ];

					$response['existing'][] = [
						'id'       => $existing_class->ID,
						'selector' => get_post_meta( $existing_class->ID, 'gb_style_selector', true ),
						'styles'   => get_post_meta( $existing_class->ID, 'gb_style_data', true ),
					];

					// Bail out if we already have this class published.
					if ( 'publish' === $existing_class->post_status ) {
						continue;
					}

					$existing_post_id = $existing_class->ID;
				}

				if ( $existing_post_id ) {
					$post_id = wp_update_post(
						[
							'ID' => $existing_post_id,
							'post_status' => 'publish',
							'menu_order'  => $this->get_next_style_menu_order(),
						]
					);
				} else {
					$post_id = wp_insert_post(
						[
							'post_type' => 'gblocks_styles',
							'post_status' => 'publish',
							'post_title'  => $style['title'],
							'menu_order'  => $this->get_next_style_menu_order(),
							'meta_input'  => [
								'gb_style_selector' => $style['className'],
								'gb_style_data'     => json_decode( $style['styles'], true ),
								'gb_style_css'      => $style['css'],
							],
						]
					);

				}

				$response[ 'imported' ][] = get_post_meta( $post_id, 'gb_style_selector', true );

				if ( is_wp_error( $post_id ) ) {
					return $this->error(
						500,
						// Translators: %1$s Name of the component.
						sprintf(
							__( 'Could not import class %1$s'),
							$style['title']
						)
					);
				}
			}

			return $this->success( $response );
		}

		/**
		 * Returns some library data when given a public key.
		 * This function is called on the providers server.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return WP_REST_Response
		 */
		public function get_library_by_public_key( WP_REST_Request $request ): WP_REST_Response {
			$public_key = $request->get_param( 'publicKey' );

			if ( is_null( $public_key ) || ! class_exists( 'GenerateCloud\Utils\Functions' ) ) {
				return $this->failed( __( 'No library found.', 'generateblocks-pro' ) );
			}

			$public_key_post = GenerateCloud\Utils\Functions::get_public_key_post( $public_key );

			if ( ! $public_key_post ) {
				return $this->failed( __( 'No library found.', 'generateblocks-pro' ) );
			}

			$permissions  = get_post_meta( $public_key_post->ID, 'gb_permissions', true );
			$library_name = $permissions['patterns']['name'] ?? '';

			$data = [
				'name' => $library_name,
			];

			return $this->success( $data );
		}

		/**
		 * Adds a new library when given data.
		 * Gets the name of the library from the provider.
		 *
		 * @param WP_REST_Request $request The request.
		 *
		 * @return WP_REST_Response
		 */
		public function add_library( WP_REST_Request $request ): WP_REST_Response {
			$data = $request->get_param( 'data' );
			$public_key = $request->get_param( 'publicKey' );
			$domain = $request->get_param( 'domain' );

			if ( ! $public_key ) {
				return $this->failed( __( 'No public key provided.', 'generateblocks-pro' ) );
			}

			if ( ! $domain ) {
				return $this->failed( __( 'No domain provided.', 'generateblocks-pro' ) );
			}

			$request = wp_remote_get(
				add_query_arg(
					[
						'publicKey' => esc_html( $public_key ),
					],
					trailingslashit( esc_url( $domain ) ) . 'wp-json/generateblocks-pro/v1/pattern-library/get-library-by-public-key'
				),
				[
					'headers' => array(
						'X-GB-Public-Key' => esc_html( $public_key ),
					),
				]
			);

			if ( is_wp_error( $request ) ) {
				return $this->failed( $request->get_error_message() );
			}

			if ( 200 !== (int) wp_remote_retrieve_response_code( $request ) ) {
				return $this->failed( wp_remote_retrieve_response_message( $request ) );
			}

			$body = wp_remote_retrieve_body( $request );
			$body = json_decode( $body, true );
			$name = $body['response']['data']['name'] ?? '';

			if ( empty( $name ) ) {
				return $this->failed( __( 'Unable to get library name.', 'generateblocks-pro' ) );
			}

			$sanitized_data = [];
			$boolean_fields = [ 'isEnabled', 'isDefault', 'isLocal' ];

			foreach ( $data as $key => $value ) {
				if ( 'domain' === $key ) {
					$sanitized_data[ $key ] = esc_url_raw( $value );
				} elseif ( in_array( $key, $boolean_fields, true ) ) {
					$sanitized_data[ $key ] = (bool) $value;
				} else {
					$sanitized_data[ $key ] = sanitize_text_field( $value );
				}
			}

			$sanitized_data['name'] = sanitize_text_field( $name );
			$libraries = get_option( 'generateblocks_pattern_libraries', [] );
			$libraries[] = $sanitized_data;
			update_option( 'generateblocks_pattern_libraries', $libraries );

			return $this->success( $sanitized_data );
		}

		/**
		 * Returns a success response.
		 *
		 * @param array $data The data.
		 *
		 * @return WP_REST_Response
		 */
		private function success( array $data ): WP_REST_Response {
			return new WP_REST_Response(
				array(
					'success'  => true,
					'response' => array(
						'data' => $data,
					),
				),
				200
			);
		}

		/**
		 * Returns a success response.
		 *
		 * @param string $message The error message.
		 *
		 * @return WP_REST_Response
		 */
		private function failed( string $message ): WP_REST_Response {
			return new WP_REST_Response(
				array(
					'success'  => false,
					'response' => $message,
				),
				200
			);
		}

		/**
		 * Returns a error response.
		 *
		 * @param int    $code Error code.
		 * @param string $message Error message.
		 *
		 * @return WP_REST_Response
		 */
		private function error( int $code, string $message ): WP_REST_Response {
			return new WP_REST_Response(
				array(
					'error'      => true,
					'success'    => false,
					'error_code' => $code,
					'response'   => $message,
				),
				$code
			);
		}
	}

	GenerateBlocks_Pro_Pattern_Library_Rest::get_instance()->init();

endif;
