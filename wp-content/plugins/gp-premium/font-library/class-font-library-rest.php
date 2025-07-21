<?php
/**
 * Rest API functions.
 *
 * @since 2.5.0
 *
 * @package GP Premium
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access, please.
}

/**
 * Font library REST API endpoints class.
 */
class GeneratePress_Pro_Font_Library_Rest extends WP_REST_Controller {
	/**
	 * Instance.
	 *
	 * @access private
	 * @var object Instance
	 */
	private static $instance;

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'generatepress-font-library/v';

	/**
	 * Version.
	 *
	 * @var string
	 */
	protected $version = '1';

	/**
	 * Initiator.
	 *
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * GenerateBlocks_Rest constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register rest routes.
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		// Get fonts from CPT.
		register_rest_route(
			$namespace,
			'/get-fonts/',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_fonts' ),
				'permission_callback' => array( $this, 'edit_posts_permission' ),
			)
		);

		// Download a Google font.
		register_rest_route(
			$namespace,
			'/download-google-font/',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'download_google_font' ),
				'permission_callback' => array( $this, 'edit_posts_permission' ),
			)
		);

		// Upload a font.
		register_rest_route(
			$namespace,
			'/upload-fonts/',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'upload_fonts' ),
				'permission_callback' => array( $this, 'edit_posts_permission' ),
			)
		);

		// Delete a font family.
		register_rest_route(
			$namespace,
			'/delete-font/',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'delete_font' ),
				'permission_callback' => array( $this, 'edit_posts_permission' ),
			)
		);

		// Get font library settings.
		register_rest_route(
			$namespace,
			'/get-settings/',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'edit_posts_permission' ),
			)
		);

		// Set font library settings.
		register_rest_route(
			$namespace,
			'/set-settings/',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'set_settings' ),
				'permission_callback' => array( $this, 'edit_posts_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/optimize-google-fonts/',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'optimize_google_fonts' ),
				'permission_callback' => array( $this, 'edit_posts_permission' ),
			)
		);

		register_rest_route(
			$namespace,
			'/update-font-post/',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_font_post' ),
				'permission_callback' => array( $this, 'edit_posts_permission' ),
			)
		);
	}

	/**
	 * Get font posts.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array The response.
	 */
	public function get_fonts( WP_REST_Request $request ) {
		$name     = $request->get_param( 'name' );
		$response = GeneratePress_Pro_Font_Library::get_fonts( $name );

		return $this->success( $response );
	}

	/**
	 * Generate font CSS.
	 *
	 * @return mixed
	 */
	public function build_css_file() {

		$result = GeneratePress_Pro_Font_Library::build_css_file();

		if ( is_wp_error( $result ) ) {
			return $this->error( 'font_css_generation_failed', __( 'Failed to generate font CSS.', 'gp-premium' ) );
		}

		return $this->success( $result );
	}

	/**
	 * Delete a specific font from the library and the associated CPT post.
	 *
	 * @param WP_REST_Request $request request object.
	 *
	 * @return mixed
	 */
	public function delete_font( WP_REST_Request $request ) {
		$font_id        = $request->get_param( 'fontId' );
		$slug           = get_post_field( 'post_name', $font_id );
		$upload_dir     = wp_get_upload_dir();
		$font_base_path = trailingslashit( $upload_dir['basedir'] ) . 'generatepress/fonts/' . $slug . '/';

		// Delete the font post.
		$success = wp_delete_post( $font_id, true );

		if ( ! $success ) {
			return $this->error(
				'font_post_delete_failed',
				__( 'Failed to delete font post.', 'gp-premium' )
			);
		}

		// Delete the font sub folder if it exists.
		if ( file_exists( $font_base_path ) ) {
			GeneratePress_Pro_Font_Library::delete_directory( $font_base_path );
		}

		// Regenerate the font CSS.
		$this->build_css_file();

		// Return success.
		return $this->success( __( 'Font successfully deleted!', 'gp-premium' ) );
	}

	/**
	 * Download a specific Google font and update the CPT.
	 *
	 * @param WP_REST_Request $request request object.
	 *
	 * @return mixed
	 */
	public function optimize_google_fonts( WP_REST_Request $request ) {
		$font     = $request->get_param( 'font' ) ?? array();
		$variants = $request->get_param( 'variants' ) ?? array();

		if ( ! $font || ! $variants ) {
			return $this->failed( 'No font or variants provided' );
		}

		$optimized_variants = GeneratePress_Pro_Font_Library_Optimize::get_variants( $font, $variants );

		if ( $optimized_variants ) {
			foreach ( $optimized_variants as $key => $optimized_variant ) {
				foreach ( $variants as &$variant ) {
					$style_match  = $variant['fontStyle'] === $optimized_variant['fontStyle'];
					$weight_match = $variant['fontWeight'] === $optimized_variant['fontWeight'];

					if ( $style_match && $weight_match ) {
						$variant['src'] = $optimized_variant['src'];
						break;
					}
				}
			}
		}

		return $this->success( $variants );
	}

	/**
	 * Check if a font post exists by slug and create it if it doesn't exist.
	 *
	 * @param array $variant The font variant to check.
	 * @param array $slug    The font slug.
	 * @return mixed
	 */
	public static function get_font_post( $variant, $slug ) {
		global $wpdb;

		$font_post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s",
				$slug,
				GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT
			)
		);

		if ( $font_post ) {
			return $font_post->ID;
		}

		$font_post = wp_insert_post(
			array(
				'post_title'  => $variant['fontFamily'],
				'post_name'   => $slug,
				'post_type'   => GeneratePress_Pro_Font_Library::FONT_LIBRARY_CPT,
				'post_status' => 'publish',
				'wp_error'    => true,
				'meta_input'  => array(
					'gp_font_family_alias' => '',
					'gp_font_variants'     => array(),
					'gp_font_display'      => 'auto',
					'gp_font_fallback'     => '',
					'gp_font_variable'     => GeneratePress_Pro_Font_Library::CSS_VAR_PREFIX . $slug,
				),
			)
		);

		return $font_post;
	}

	/**
	 * Upload a specific custom font and update the CPT.
	 *
	 * @param WP_REST_Request $request request object.
	 *
	 * @return mixed
	 */
	public function upload_fonts( WP_REST_Request $request ) {
		$font     = $request->get_param( 'font' ) ?? array();
		$variants = $request->get_param( 'variants' );
		$source   = $request->get_param( 'source' );
		$slug     = $request->get_param( 'slug' ) ?? $font['slug'] ?? '';
		$results  = array(
			'ID'       => null,
			'variants' => array(),
		);

		// Tweaks variants based on the source if needed.
		if ( 'custom' === $source ) {
			// Decode the FormData sent via POST.
			$variants = json_decode( $variants, true );
		}

		foreach ( $variants as $variant ) {
			// Move the uploaded font asset from the temp folder to the fonts directory.
			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$file = $variant['src'];

			// If custom assume the font is being uploaded.
			if ( 'custom' === $source ) {
				$file_params = $request->get_file_params();
				$file        = $file_params[ $variant['src'] ] ?? $variant['src'];
			}

			$font_file = GeneratePress_Pro_Font_Library::handle_font_file_upload( $variant, $slug, $file );

			if ( is_wp_error( $font_file ) ) {
				$results['error'][] = array(
					'font'    => $variant,
					'message' => $font_file->get_error_message(),
				);

				continue;
			}

			// Get the font post for this variant.
			$font_post = self::get_font_post( $variant, $slug );

			if ( is_wp_error( $font_post ) ) {
				return $this->error( 500, __( 'Failed to create font post.', 'gp-premium' ) );
			}

			if ( 'google' === $source ) {
				$font_family = explode( ', ', $font['fontFamily'] ?? '' );
				// Remove the main font-family.
				array_shift( $font_family );

				// Set the fallback if we can infer one.
				if ( $font_family ) {
					$fallback = implode( ', ', $font_family );
					update_post_meta( $font_post, 'gp_font_fallback', $fallback );
				}
			}

			$existing_variants = get_post_meta( $font_post, 'gp_font_variants', true );

			if ( ! is_array( $variants ) ) {
				$existing_variants = array();
			}

			$checked_variants = GeneratePress_Pro_Font_Library::check_variants(
				$existing_variants,
				array(
					'src'        => $font_file['url'],
					'fontFamily' => $variant['fontFamily'],
					'fontStyle'  => $variant['fontStyle'],
					'fontWeight' => $variant['fontWeight'],
					'name'       => GeneratePress_Pro_Font_Library::get_variant_name( $variant ),
					'isVariable' => $variant['isVariable'] ?? false,
					'source'     => 'custom',
					'disabled'   => false,
					'preview'    => '',
				)
			);

			// Update the font post meta with merged variants.
			update_post_meta( $font_post, 'gp_font_variants', $checked_variants );

			// Generate the font CSS.
			$generate_css = $this->build_css_file();

			if ( false === $generate_css->data['success'] ) {
				return $this->error( 500, __( 'CSS Generation failed', 'gp-premium' ) );
			}

			$results['ID']       = $font_post;
			$results['variants'] = $checked_variants;
		}

		return $this->success( $results );
	}

	/**
	 * Get font library settings.
	 *
	 * @return mixed
	 */
	public function get_settings() {
		return $this->success( get_option( 'gp_font_library_settings', array() ) );
	}

	/**
	 * Update font library settings.
	 *
	 * @param WP_REST_Request $request request object.
	 *
	 * @return mixed
	 */
	public function set_settings( WP_REST_Request $request ) {
		$settings = $request->get_param( 'settings' );
		$sanitized_settings = array();

		foreach ( $settings as $setting => $value ) {
			if ( 'google_gdpr' === $setting ) {
				$sanitized_settings[ $setting ] = (bool) $value;
			} elseif ( 'preferred_subset' === $setting ) {
				// Stored as an array to support multiple preferred subsets in the future.
				$sanitized_settings[ $setting ] = array( sanitize_text_field( $value ) );
			} else {
				$sanitized_settings[ $setting ] = sanitize_text_field( $value );
			}
		}

		$updated = update_option(
			GeneratePress_Pro_Font_Library::SETTINGS_OPTION,
			$sanitized_settings,
			false
		);

		if ( $updated ) {
			// Return success.
			return $this->success(
				array(
					'message' => __( 'Font settings successfully updated!', 'gp-premium' ),
					'response' => $updated,
					'settings' => $sanitized_settings,
				)
			);
		}

		return $this->failed(
			array(
				'message'  => __( 'Failed to update font settings.', 'gp-premium' ),
				'settings' => $sanitized_settings,
			)
		);

	}

	/**
	 * Update a font post.
	 *
	 * @param WP_REST_Request $request request object.
	 *
	 * @return mixed
	 */
	public function update_font_post( WP_REST_Request $request ) {
		$font_id           = $request->get_param( 'id' );
		$status            = $request->get_param( 'status' );
		$font_family_alias = $request->get_param( 'alias' );
		$new_variants      = $request->get_param( 'newVariants' );
		$delete_variants   = $request->get_param( 'deleteVariants' );
		$font_display      = $request->get_param( 'fontDisplay' );
		$fallback          = $request->get_param( 'fallback' );
		$css_variable      = $request->get_param( 'cssVariable' );
		$slug              = get_post_field( 'post_name', $font_id );

		// Update the font post.
		wp_update_post(
			array(
				'ID'          => $font_id,
				'post_status' => $status,
				'meta_input'  => array(
					'gp_font_family_alias' => $font_family_alias,
					'gp_font_variants'     => $new_variants,
					'gp_font_display'      => $font_display,
					'gp_font_fallback'     => $fallback,
					'gp_font_variable'     => $css_variable,
				),
			)
		);

		$upload_dir = wp_get_upload_dir();
		$base_path  = trailingslashit( $upload_dir['basedir'] ) . 'generatepress/fonts/' . $slug . '/';
		foreach ( $delete_variants as $variant ) {
			if ( isset( $variant['deleteStatus'] ) && $variant['deleteStatus'] ) {
				$file_path = $base_path . basename( $variant['src'] );
				if ( file_exists( $file_path ) ) {
					unlink( $file_path );
				}
			}
		}

		// Regenerate the font CSS.
		$this->build_css_file();

		// Return success.
		return $this->success( __( 'Font post successfully updated!', 'gp-premium' ) );
	}

	/**
	 * Get edit options permissions.
	 *
	 * @return bool
	 */
	public function update_settings_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get edit posts permissions.
	 *
	 * @return bool
	 */
	public function edit_posts_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Success rest.
	 *
	 * @param mixed $response response data.
	 * @param mixed $data     data.
	 * @return mixed
	 */
	public function success( $response, $data = null ) {
		return new WP_REST_Response(
			array(
				'success'  => true,
				'response' => $response,
				'data'     => $data,
			),
			200
		);
	}

	/**
	 * Failed rest.
	 *
	 * @param mixed $response response data.
	 * @return mixed
	 */
	public function failed( $response ) {
		return new WP_REST_Response(
			array(
				'success'  => false,
				'response' => $response,
			),
			200
		);
	}

	/**
	 * Error rest.
	 *
	 * @param mixed $code     error code.
	 * @param mixed $response response data.
	 * @return mixed
	 */
	public function error( $code, $response ) {
		return new WP_REST_Response(
			array(
				'error'      => true,
				'success'    => false,
				'error_code' => $code,
				'response'   => $response,
			),
			500
		);
	}
}

GeneratePress_Pro_Font_Library_Rest::get_instance();
