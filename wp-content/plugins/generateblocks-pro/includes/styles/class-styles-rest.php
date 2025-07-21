<?php
/**
 * The Global Classes rest class file.
 *
 * @package GenerateBlocksPro\Global_Classes_Rest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main class for the Global Classes Rest functions.
 *
 * @since 1.9
 */
class GenerateBlocks_Pro_Styles_Rest extends GenerateBlocks_Pro_Singleton {

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
			'/global-classes/check_class_name',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'check_class_name' ),
				'permission_callback' => array( $this, 'can_create' ),
			)
		);

		register_rest_route(
			$namespace,
			'/global-classes/get',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_global_styles' ),
				'permission_callback' => array( $this, 'can_create' ),
			)
		);

		register_rest_route(
			$namespace,
			'/global-classes/get_css',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_global_class_css' ),
				'permission_callback' => array( $this, 'can_create' ),
			)
		);

		register_rest_route(
			$namespace,
			'/global-classes/get_styles',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_global_class_styles' ),
				'permission_callback' => array( $this, 'can_create' ),
			)
		);

		register_rest_route(
			$namespace,
			'/global-styles/update_menu_order',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_menu_order' ),
				'permission_callback' => array( $this, 'can_create' ),
			)
		);
	}

	/**
	 * Manage options permission callback.
	 *
	 * @return bool
	 */
	public function can_create(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if a class name already exists.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function check_class_name( WP_REST_Request $request ): WP_REST_RESPONSE {
		$class_name = $request->get_param( 'className' );

		if ( empty( $class_name ) ) {
			return $this->failed( __( 'Style name cannot be empty', 'generateblocks-pro' ) );
		}

		$existing_class = new WP_Query(
			[
				'post_type'      => 'gblocks_styles',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_query'     => [
					[
						'key'     => 'gb_style_selector',
						'value'   => $class_name,
						'compare' => '=',
					],
				],
			]
		);

		if ( ! empty( $existing_class->found_posts ) ) {
			return $this->failed( __( 'Style name already exists', 'generateblocks-pro' ) );
		}

		return $this->success( [ 'success' ] );
	}

	/**
	 * Returns a list of active or inactive classes.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_global_styles( WP_REST_Request $request ): WP_REST_Response {
		$status = $request->get_param( 'status' ) ?? 'publish';
		$custom_args = [
			'post_status' => $status,
		];

		$styles = GenerateBlocks_Pro_Styles::get_styles( $custom_args );

		$class_data = [
			'styles' => $styles,
		];

		return $this->success( $class_data );
	}

	/**
	 * Returns the CSS for the complete set of global classes or a specific class if specified.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_global_class_css( WP_REST_Request $request ): WP_REST_Response {
		$class_name = $request->get_param( 'className' );

		if ( $class_name ) {
			$class = GenerateBlocks_Pro_Styles::get_class_by_name( $class_name );

			if ( ! isset( $class->ID ) ) {
				return $this->failed( 'No CSS found.' );
			}

			$css = get_post_meta( $class->ID, 'gb_style_css', true );
		} else {
			$css = GenerateBlocks_Pro_Styles::get_styles_css();
		}

		return $this->success( [ $css ] );
	}

	/**
	 * Returns the styles for a specific class name.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_global_class_styles( WP_REST_Request $request ): WP_REST_Response {
		$class_name = $request->get_param( 'globalClass' );
		$class = GenerateBlocks_Pro_Styles::get_class_by_name( $class_name );

		if ( ! isset( $class ) ) {
			return $this->failed( __( 'Style does not exist', 'generateblocks-pro' ) );
		}

		$post_id = $class->ID;
		$styles = get_post_meta( $post_id, 'gb_style_data', true );

		return $this->success(
			[
				'postId' => $post_id,
				'styles' => $styles ?? [],
			]
		);
	}

	/**
	 * Handles updating global style menu order
	 *
	 * @param WP_REST_Request $request WP Request object.
	 * @return WP_REST_RESPONSE;
	 */
	public function update_menu_order( WP_REST_Request $request ): WP_REST_Response {
		$order = $request->get_param( 'order' );
		if ( empty( $order ) || ! is_array( $order ) ) {
			return $this->failed( __( 'Order parameter is invalid.', 'generateblocks-pro' ) );
		}

		global $wpdb;
		$sql = '';
		$rows_affected = 0;

		foreach ( $order as $index => $post_id ) {
			$query = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}posts SET menu_order = %d WHERE ID = %d;",
					[
						$index,
						$post_id,
					]
				)
			);

			if ( false === $query ) {
				return $this->failed( __( 'Failed to update menu order.', 'generateblocks-pro' ) );
			} else {
				$rows_affected += $query;
			}
		}

		$wpdb->flush();

		// Flush the cache after the operation completes.
		GenerateBlocks_Pro_Enqueue_Styles::get_instance()->build_css();

		return $this->success(
			[
				'message'       => __( 'Menu order updated', 'generateblocks-pro' ),
				'rows_affected' => $rows_affected,
			]
		);
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
	private function error( int $code, string $message = '' ): WP_REST_Response {
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

GenerateBlocks_Pro_Styles_Rest::get_instance()->init();
