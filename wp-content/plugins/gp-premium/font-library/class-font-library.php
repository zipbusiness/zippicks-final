<?php
/**
 * This file handles the Font Library.
 *
 * @since 2.5.0
 *
 * @package GP Premium
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access, please.
}

/**
 * Font library class.
 */
class GeneratePress_Pro_Font_Library extends GeneratePress_Pro_Singleton {
	const FONT_LIBRARY_CPT = 'gp_font';
	const FONTS_MAX_QUERY  = 100;
	const CSS_VAR_PREFIX   = '--gp-font--';
	const SETTINGS_OPTION  = 'gp_font_library_settings';

	/**
	 * Constructor.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_font_css' ), 1 );

		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'block_editor_settings_all', array( $this, 'add_fonts_to_editor' ) );
		add_filter( 'generate_dashboard_tabs', array( $this, 'add_dashboard_tab' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'generate_dashboard_screens', array( $this, 'add_dashboard_screen' ) );
		add_action( 'admin_head', array( $this, 'add_head_tags' ), 0 );
		add_action( 'import_post_meta', array( $this, 'update_post_meta' ), 100, 3 );
		add_action( 'wp_import_existing_post', array( $this, 'maybe_font_exists' ), 10, 2 );
		add_action( 'save_post_' . self::FONT_LIBRARY_CPT, array( $this, 'build_css_file' ), 100, 3 );

	}

	/**
	 * Add the Font Library tab to our Dashboard tabs.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array New tabs.
	 */
	public function add_dashboard_tab( $tabs ) {
		$screen = get_current_screen();

		$tabs['Fonts'] = array(
			'name' => __( 'Font Library', 'gp-premium' ),
			'url' => self::get_font_library_uri(),
			'class' => 'appearance_page_generatepress-font-library' === $screen->id ? 'active' : '',
			'id' => 'gp-font-library-tab',
		);

		return $tabs;
	}

	/**
	 * Add our menu item.
	 */
	public function add_menu() {
		add_submenu_page(
			'themes.php',
			__( 'Font Library', 'gp-premium' ),
			__( 'Font Library', 'gp-premium' ),
			'manage_options',
			'generatepress-font-library',
			array( $this, 'library_page' )
		);
	}

	/**
	 * Add our page.
	 */
	public function library_page() {
		echo '<div id="gp-font-library" class="gp-font-library gp-premium"></div>';
	}

	/**
	 * Add tags to the head element for the font library page.
	 */
	public function add_head_tags() {
		$screen = get_current_screen();
		$user_id = get_current_user_id();
		$google_gdpr = (bool) self::get_settings( 'google_gdpr' );

		// Stop if we're not on the right page or the user hasn't opted in to google fonts.
		if ( 'appearance_page_generatepress-font-library' !== $screen->id || ! $google_gdpr ) {
			return;
		}

		echo '
			<link href="https://fonts.gstatic.com" rel="preconnect" crossorigin="anonymous" id="gp-preconnect-gstatic">
			<link href="https://fonts.googleapis.com" rel="preconnect" id="gp-preconnect-google-api">
			<link href="https://s.w.org" rel="preconnect" id="gp-preconnect-wp-cdn">
		';
	}

	/**
	 * Add our scripts.
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		if ( 'appearance_page_generatepress-font-library' === $screen->id ) {
			$assets     = generate_premium_get_enqueue_assets( 'font-library' );
			$upload_dir = wp_get_upload_dir();

			wp_enqueue_script(
				'generatepress-pro-font-library',
				GP_PREMIUM_DIR_URL . 'dist/font-library.js',
				$assets['dependencies'],
				$assets['version'],
				true
			);

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'generatepress-pro-font-library', 'gp-premium', GP_PREMIUM_DIR_PATH . 'langs' );
			}

			wp_localize_script(
				'generatepress-pro-font-library',
				'gppFontLibrary',
				array(
					'uploadsUrl' => $upload_dir['baseurl'],
				)
			);

			wp_enqueue_style(
				'generatepress-pro-font-library',
				GP_PREMIUM_DIR_URL . 'dist/font-library.css',
				array( 'wp-components' ),
				GP_PREMIUM_VERSION
			);
		}
	}

	/**
	 * Tell GeneratePress this is an admin page.
	 *
	 * @param array $screens Existing screens.
	 */
	public function add_dashboard_screen( $screens ) {
		$screens[] = 'appearance_page_generatepress-font-library';

		return $screens;
	}

	/**
	 * Get font posts.
	 *
	 * @param string $name font name.
	 *
	 * @return mixed
	 */
	public static function get_fonts( $name = null ) {
		$args = array(
			'post_type'              => self::FONT_LIBRARY_CPT,
			'post_status'            => 'any',
			'numberposts'            => GeneratePress_Pro_Font_Library::FONTS_MAX_QUERY, // phpcs:ignore
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'order'                  => 'ASC',
		);

		if ( $name ) {
			$args['name'] = $name;
		}

		$all_fonts = get_posts( $args );
		$response  = array();

		if ( is_array( $all_fonts ) ) {
			foreach ( $all_fonts as $font_post ) {
				$font_name       = get_the_title( $font_post );
				$alias           = get_post_meta( $font_post, 'gp_font_family_alias', true );
				$slug            = get_post_field( 'post_name', $font_post );
				$status          = get_post_status( $font_post );
				$fallback        = get_post_meta( $font_post, 'gp_font_fallback', true );
				$preview         = get_post_meta( $font_post, 'gp_font_preview', true );
				$font_family     = empty( $alias ) ? $font_name : $alias;

				$font_family = "\"$font_family\"";

				if ( $fallback ) {
					$font_family .= ", $fallback";
				}

				// Setup the font data.
				$response[] = array(
					'id'          => $font_post,
					'name'        => $font_name,
					'fontFamily'  => $font_family,
					'disabled'    => 'publish' !== $status,
					'slug'        => get_post_field( 'post_name', $font_post ),
					'alias'       => get_post_meta( $font_post, 'gp_font_family_alias', true ),
					'variants'    => get_post_meta( $font_post, 'gp_font_variants', true ),
					'source'      => get_post_meta( $font_post, 'gp_font_source', true ),
					'fallback'    => $fallback,
					'fontDisplay' => get_post_meta( $font_post, 'gp_font_display', true ),
					'preview'     => empty( $preview ) ? '' : $preview,
					'cssVariable' => get_post_meta( $font_post, 'gp_font_variable', true ),
				);
			}

			return $response;
		} else {
			return array();
		}
	}

	/**
	 * Get the font library URI.
	 *
	 * @return string
	 */
	public static function get_font_library_uri() {
		return admin_url( 'themes.php?page=generatepress-font-library' );
	}

	/**
	 * Font format mappings.
	 *
	 * @param array $font Array of font data.
	 * @return string
	 */
	public static function get_font_face_rule( $font ) {
		$css = '';
		if ( ! empty( $font['variants'] ) ) {
			$font_family = $font['alias'] ? $font['alias'] : $font['name'];

			foreach ( $font['variants'] as $variant ) {
				$is_disabled = $variant['disabled'] ?? false;

				if ( $is_disabled ) {
					continue;
				}

				$format = self::get_font_format( $variant['src'] );
				$css .= "@font-face {
	font-display: {$font['fontDisplay']};
	font-family: \"$font_family\";
	font-style: {$variant['fontStyle']};
	font-weight: {$variant['fontWeight']};
	src: url('{$variant['src']}')$format;
}\n";
			}
		}

		return $css;
	}

	/**
	 * Font format mappings.
	 *
	 * @param string $font_url File extension.
	 * @return string|null
	 */
	private static function get_font_format( $font_url ) {
		$extension = pathinfo( $font_url, PATHINFO_EXTENSION );

		$format_map = array(
			'woff' => 'woff',
			'woff2' => 'woff2',
			'ttf' => 'truetype',
			'otf' => 'opentype',
		);

		$format_string = isset( $format_map[ $extension ] ) ? $format_map[ $extension ] : null;
		return $format_string ? " format('$format_string')" : '';
	}

	/**
	 * Parses a font variant string to determine weight and style.
	 * Returns an array with 'weight', 'style'.
	 *
	 * @param string $variant Font variant string.
	 * @return array
	 */
	private static function parse_font_variant( $variant ) {
		$weight = '400';
		$style = 'normal';

		if ( 'regular' === $variant ) {
			return array(
				'weight' => $weight,
				'style' => $style,
			);
		}

		if ( 'italic' === $variant || strpos( $variant, 'italic' ) !== false ) {
			$style = 'italic';
			if ( strpos( $variant, 'italic' ) !== false ) {
				$variant = str_replace( 'italic', '', $variant );
			}
		}

		return array(
			'weight' => empty( $variant ) ? $weight : $variant,
			'style' => $style,
		);
	}

	/**
	 * Checks if the existing font variant exists.
	 *
	 * Overwrite it if it exists, and delete associated font file if different.
	 * Otherwise, add new variant if not found in the list.
	 *
	 * @param array  $variants Font variants.
	 * @param int    $new_variant New variant to be added.
	 * @param string $base_path Base path.
	 *
	 * @return array The resolved list of variants.
	 */
	public static function check_variants( $variants, $new_variant ) {
		$checked_variants = $variants;
		if ( empty( $variants ) ) {
			return array( $new_variant );
		}

		$found = false;
		foreach ( $variants as $key => $variant ) {
			if ( $variant['name'] === $new_variant['name'] ) {
				$checked_variants[ $key ] = $new_variant;
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			$checked_variants[] = $new_variant;
		}

		return $checked_variants;
	}

	/**
	 * Format uploaded font variant.
	 *
	 * @param array $variant Font variant.
	 * @return string The formatted variant name.
	 */
	public static function get_variant_name( $variant ) {
		// Force variant to array-like structure.
		$is_italic = 'italic' === $variant['fontStyle'];

		$labels = array(
			'100'       => 'Thin 100',
			'200'       => 'ExtraLight 200',
			'250'       => 'ExtraLight 250',
			'300'       => 'Light 300',
			'400'       => 'Regular 400',
			'regular'   => 'Regular 400',
			'500'       => 'Medium 500',
			'600'       => 'SemiBold 600',
			'700'       => 'Bold 700',
			'800'       => 'ExtraBold 800',
			'900'       => 'Black 900',
		);

		$resolved_label = $labels[ $variant['fontWeight'] ];

		if ( $resolved_label ) {
			return $resolved_label . ( $is_italic ? ' Italic' : '' );
		}

		return str_replace( ' ', '-', $variant['fontWeight'] ) . ' ' . __( '(Variable)', 'gp-premium' );
	}

	/**
	 * Format a font file name to remove spaces, commas.
	 *
	 * @param string $name Font name.
	 * @return string
	 */
	public static function format_font_filename( $name ) {
		// Replace spaces and commas in file name with hyphen.
		$name = preg_replace( '/[ ,]/', '-', $name );
		return $name;
	}

	/**
	 * Returns the expected mime-type values for font files, depending on PHP version.
	 *
	 * This is needed because font mime types vary by PHP version, so checking the PHP version
	 * is necessary until a list of valid mime-types for each file extension can be provided to
	 * the 'upload_mimes' filter.
	 *
	 * @return array A collection of mime types keyed by file extension.
	 */
	public static function get_allowed_font_mime_types() {
		$php_7_ttf_mime_type = PHP_VERSION_ID >= 70300 ? 'application/font-sfnt' : 'application/x-font-ttf';

		return array(
			'otf'   => 'application/vnd.ms-opentype',
			'ttf'   => PHP_VERSION_ID >= 70400 ? 'font/sfnt' : $php_7_ttf_mime_type,
			'woff'  => PHP_VERSION_ID >= 80112 ? 'font/woff' : 'application/font-woff',
			'woff2' => PHP_VERSION_ID >= 80112 ? 'font/woff2' : 'application/font-woff2',
		);
	}

	/**
	 * Get the font CSS file.
	 *
	 * @param string $type Type of path to return. Can return the `path` or `url` to the file.
	 * @return string
	 */
	public static function get_font_css_file( $type ) {
		$upload_dir = wp_get_upload_dir();
		$file_path  = 'generatepress/fonts/fonts.css';
		$base       = '';

		if ( 'url' === $type ) {
			$base = $upload_dir['baseurl'];
		} elseif ( 'path' === $type ) {
			$base = $upload_dir['basedir'];
		}

		return $base ? trailingslashit( $base ) . $file_path : '';
	}

	/**
	 * Get the font CSS file URL.
	 *
	 * @return string
	 */
	public static function get_font_css_file_url() {
		$css_file_url = self::get_font_css_file( 'url' );
		$css_file_dir = self::get_font_css_file( 'path' );

		return file_exists( $css_file_dir )
			? $css_file_url
			: '';
	}

	/**
	 * Add our font CSS.
	 */
	public function enqueue_font_css() {
		$font_file_url = self::get_font_css_file_url();

		// Enqueue the custom fonts CSS if the file exists.
		if ( $font_file_url ) {
			$version = filemtime( self::get_font_css_file( 'path' ) ) ?? GP_PREMIUM_VERSION;

			wp_enqueue_style( 'generatepress-fonts', $font_file_url, array(), $version );
		}
	}

	/**
	 * Add a font to the uploads directory either from $_FILES or a remote URL.
	 *
	 * @param array      $variant Font variant object.
	 * @param string     $slug Font slug.
	 * @param array|null $file Single file item from $_FILES or null.
	 * @return array|WP_Error Array containing uploaded file attributes on success, or WP_Error object on failure.
	 */
	public static function handle_font_file_upload( $variant, $slug, $file ) {
		if ( ! $slug ) {
			$slug = $variant['slug'] ?? '';
		}
		$upload_dir = wp_get_upload_dir();
		$base_path  = trailingslashit( $upload_dir['basedir'] ) . 'generatepress/fonts/' . $slug . '/';

		// Ensure the directory exists.
		if ( ! file_exists( $base_path ) ) {
			wp_mkdir_p( $base_path );
		}

		/**
		 * If $file is an array, assume it's a param from $_FILES.
		 */
		if ( is_array( $file ) ) {
			$file_name = basename( $file['name'] );
			$file_path = $base_path . $file_name;

			// Check if the font file exists and delete it if so.
			if ( file_exists( $file_path ) ) {
				unlink( $file_path );
			}

			$set_upload_dir = function ( $font_dir ) use ( $base_path, $slug ) {
				$font_dir['path'] = $base_path;
				$font_dir['url']  = untrailingslashit(
					content_url( 'uploads/generatepress/fonts/' . $slug )
				);
				$font_dir['subdir'] = '';
				return $font_dir;
			};

			add_filter( 'upload_mimes', array( __CLASS__, 'get_allowed_font_mime_types' ) );
			add_filter( 'upload_dir', $set_upload_dir );

			$overrides = array(
				'upload_error_handler' => array( __CLASS__, 'handle_font_file_upload_error' ),
				// Not testing a form submission.
				'test_form'            => false,
				// Only allow uploading font files for this request.
				'mimes'                => self::get_allowed_font_mime_types(),
			);

			$uploaded_file = wp_handle_upload( $file, $overrides );

			remove_filter( 'upload_dir', $set_upload_dir );
			remove_filter( 'upload_mimes', array( __CLASS__, 'get_allowed_font_mime_types' ) );

			return $uploaded_file;
		}

		$file_name = basename( $variant['src'] );
		$file_path = $base_path . $file_name;

		$response = wp_remote_get( $variant['src'] );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			return new WP_Error( 500, "Failed to download {$variant['fontFamily']} from {$variant['src']}: $error_message" );
		}

		// Save the file.
		$filesystem = generate_premium_get_wp_filesystem();

		if ( ! $filesystem ) {
			return new WP_Error( 500, 'Error setting up the file system object.' );
		}

		$file_contents = wp_remote_retrieve_body( $response );

		if ( ! $file_contents ) {
			return new WP_Error( 500, "Failed to download $variant from {$variant['src']}: Empty body" );
		}

		// Assuming $filesystem is already set up correctly.
		$chmod_file = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;

		if ( is_writable( $file_path ) || is_writable( dirname( $file_path ) ) ) {
			if ( $filesystem->put_contents( $file_path, $file_contents, $chmod_file ) ) {
				return array(
					'file' => $file_path,
					'url'  => trailingslashit( $upload_dir['baseurl'] ) . 'generatepress/fonts/' . $slug . '/' . $file_name,
				);
			} else {
				return new WP_Error( 500, "Failed to download $variant from {$variant['src']}." );
			}
		}

		return new WP_Error( 500, 'Unable to write to file path.' );
	}

	/**
	 * Handles file upload error.
	 *
	 * @param array  $file    File upload data.
	 * @param string $message Error message from wp_handle_upload().
	 * @return WP_Error WP_Error object.
	 */
	public static function handle_font_file_upload_error( $file, $message ) {
		$status = 500;
		$code   = 'rest_font_upload_unknown_error';

		// Note: The absence of a text domain is intentional here as it's checking against a WP core string.
		if ( __( 'Sorry, you are not allowed to upload this file type.' ) === $message ) {
			$status = 400;
			$code   = 'rest_font_upload_invalid_file_type';
		}

		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Runs on wp_after_insert_post to download remote font files.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key The meta key that was imported.
	 * @param mixed  $value The meta value that was imported.
	 * @return void
	 */
	public function update_post_meta( $post_id, $key, $value ) {
		$upload_dir = wp_get_upload_dir();
		// Bail if we're not working with a font library post variant meta value.
		if ( get_post_type( $post_id ) !== self::FONT_LIBRARY_CPT || 'gp_font_variants' !== $key ) {
			return;
		}

		// Check the src of each variant and if the URL is remote, download the file.
		$variants = $value;

		// Stop here if variants aren't found.
		if ( ! $variants ) {
			return;
		}

		foreach ( $variants as &$variant ) {
			$site_hostname = wp_parse_url( site_url(), PHP_URL_HOST );

			// Bail if the variant src is already on this site.
			if ( strpos( $variant['src'], $site_hostname ) !== false ) {
				continue;
			}

			$font_slug     = get_post_field( 'post_name', $post_id );
			$font_dir      = trailingslashit( $upload_dir['basedir'] ) . 'generatepress/fonts/' . $font_slug . '/';
			$font_base_url = trailingslashit( $upload_dir['baseurl'] ) . 'generatepress/fonts/' . $font_slug . '/';
			$response      = wp_remote_get( $variant['src'] );
			$response_code = (int) wp_remote_retrieve_response_code( $response );

			if ( is_wp_error( $response ) || 200 !== $response_code ) {
				continue;
			}

			$file_name = basename( $variant['src'] );
			$file_path = $font_dir . $file_name;

			// If the directory exists, remove it and it's contents.
			if ( ! file_exists( $font_dir ) ) {
				wp_mkdir_p( $font_dir );
			}

			// Setup filesystem.
			$filesystem = generate_premium_get_wp_filesystem();

			// Bail here if the filesystem can't initialize.
			if ( ! $filesystem ) {
				continue;
			}

			$file_contents = wp_remote_retrieve_body( $response );

			// Bail if file contents are empty or not found.
			if ( ! $file_contents ) {
				continue;
			}

			$chmod_file = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;

			if ( is_writable( $file_path ) || is_writable( dirname( $file_path ) ) ) {
				// Bail if the file can't be written.
				if ( ! $filesystem->put_contents( $file_path, $file_contents, $chmod_file ) ) {
					continue;
				}
			}

			$variant['src'] = $font_base_url . $file_name;
		}

		// Update the meta value with the new src for each variant.
		update_post_meta( $post_id, 'gp_font_variants', $variants );
	}

	/**
	 * Recursive function to delete a directory and its contents.
	 *
	 * @param string $dir directory path.
	 * @return bool
	 */
	public static function delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return true;
		}

		if ( ! is_dir( $dir ) ) {
			return unlink( $dir );
		}

		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			if ( ! self::delete_directory( $dir . DIRECTORY_SEPARATOR . $item ) ) {
				return false;
			}
		}

		return rmdir( $dir );
	}

	/**
	 * Check if the post exists by checking the title.
	 *
	 * @param bool  $post_exists Unused. The default post_exists function value.
	 * @param array $font The font post array.
	 * @return int Post ID on success, 0 on failure.
	 */
	public function maybe_font_exists( $post_exists, $font ) {
		/**
		 * The value of $font here is a post array from the XML import, not our standard
		 * font array. We need to check if the font exists by title.
		 */
		return post_exists( $font['post_title'] );
	}

	/**
	 * Get the CSS variables and values for each font-family.
	 *
	 * @return string The color palette variable CSS declaration.
	 */
	public static function get_css_variables() {
		$fonts = self::get_fonts();

		if ( ! $fonts ) {
			return '';
		}

		$variables = ":root {\n";

		foreach ( $fonts as $font ) {
			if ( isset( $font['disabled'] ) && $font['disabled'] ) {
				continue;
			}

			$variables .= sprintf(
				"%s: %s;\n",
				$font['cssVariable'],
				$font['fontFamily']
			);
		}

		$variables .= "}\n";

		return $variables;
	}

	/**
	 * Add CSS variable definitions to the block editor.
	 *
	 * @param string $css The generated CSS for the stylesheet.
	 * @return void
	 **/
	public function add_variable_definitions_to_editor( $css ) {
		wp_add_inline_style( 'generateblocks-pro', self::get_css_variables() );
	}

	/**
	 * Build the font CSS file.
	 *
	 * @return string|WP_Error The file path on success, WP_Error on failure.
	 */
	public static function build_css_file() {
		$generated_css = self::generate_font_css();
		$upload_dir    = wp_get_upload_dir();

		// Save the generated font CSS to a file.
		$base_path_dir = trailingslashit( $upload_dir['basedir'] ) . 'generatepress/fonts/';
		$file_path     = $base_path_dir . 'fonts.css';
		$filesystem    = generate_premium_get_wp_filesystem();

		if ( ! $filesystem ) {
			return new WP_Error( 500, __( 'Error setting up the file system object.', 'gp-premium' ) );
		}

		// Assuming $filesystem is already set up correctly.
		$chmod_file = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;

		if ( empty( $generated_css ) ) {
			if ( file_exists( $file_path ) ) {
				$filesystem->delete( $file_path );
			}
		} else {
			if ( is_writable( $file_path ) || is_writable( dirname( $file_path ) ) ) {
				if ( ! $filesystem->put_contents( $file_path, $generated_css, $chmod_file ) ) {
					return new WP_Error( 500, __( 'Failed to write Google font CSS to file.', 'gp-premium' ) );
				}
			}
		}

		return $file_path;
	}

	/**
	 * Generate font CSS.
	 *
	 * @return mixed
	 */
	public static function generate_font_css() {
		$fonts     = self::get_fonts();
		$variables = self::get_css_variables();
		$css       = $variables . "\n";

		if ( $fonts ) {
			foreach ( $fonts as $font ) {
				// Add the generated CSS.
				$css .= self::get_font_face_rule( $font );
			}
		}

		return apply_filters( 'generatepress_font_css', $css, $fonts );
	}

	/**
	 * Add the font CSS to the block editor.
	 *
	 * @param array $settings The block editor settings.
	 * @return array
	 */
	public function add_fonts_to_editor( $settings ) {
		$font_file_url = self::get_font_css_file_url();

		if ( ! $font_file_url ) {
			return $settings;
		}

		$fonts_import = sprintf(
			'@import url("%s");',
			$font_file_url
		);

		$settings['styles'][] = array( 'css' => $fonts_import );

		return $settings;
	}

	/**
	 * Get font library settings. At the moment this is just the Google GDPR setting.
	 *
	 * @param string $setting The setting to retrieve.
	 * @return mixed
	 */
	public static function get_settings( $setting = null ) {

		$settings = get_option( self::SETTINGS_OPTION, array() );

		if ( $setting ) {
			return $settings[ $setting ] ?? null;
		}

		return $settings;
	}
}

GeneratePress_Pro_Font_Library::get_instance()->init();
