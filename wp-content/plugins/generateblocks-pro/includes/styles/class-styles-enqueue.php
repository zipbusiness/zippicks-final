<?php
/**
 * Handles the Global CSS Output.
 *
 * @package GenerateBlocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Build and enqueue our global CSS.
 */
class GenerateBlocks_Pro_Enqueue_Styles extends GenerateBlocks_Pro_Singleton {
	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_after_insert_post', [ $this, 'build_css_file_on_save' ], 100, 2 );
		add_action( 'after_delete_post', [ $this, 'build_css_on_delete' ], 100, 2 );
		add_action( 'customize_after_save', [ $this, 'build_css' ] );
	}

	/**
	 * Enqueue our assets.
	 */
	public function enqueue_assets() {
		add_action(
			'wp_enqueue_scripts',
			[ $this, 'enqueue_css' ],
			apply_filters( 'generateblocks_global_css_priority', 20 ) // Less than block-specific stylesheets.
		);
	}

	/**
	 * Check whether we're using file or inline mode.
	 */
	public function mode() {
		$mode = apply_filters( 'generateblocks_global_css_print_method', 'file' );

		if (
			( function_exists( 'is_customize_preview' ) && is_customize_preview() ) ||
			is_preview() ||
			// AMP inlines all CSS, so inlining from the start improves CSS processing performance.
			( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() )
		) {
			return 'inline';
		}

		return $mode;
	}

	/**
	 * This builds the CSS file.
	 * It will also delete an existing file if creating fails for any reason.
	 *
	 * @return boolean Whether the file has been built or not.
	 */
	public function build_css_file() {
		$built = $this->create_css_file();

		// Delete the file if `create_css_file()` fails for any reason.
		if ( ! $built && file_exists( $this->file( 'path' ) ) ) {
			wp_delete_file( $this->file( 'path' ) );
		}

		return $built;
	}

	/**
	 * Build our cache of utility classes.
	 */
	public function build_css_cache() {
		update_option(
			'generateblocks_style_css',
			GenerateBlocks_Pro_Styles::get_styles_css( false )
		);
	}

	/**
	 * Check to see if we have a global css file.
	 */
	public function has_css_file() {
		return file_exists( $this->file( 'path' ) );
	}

	/**
	 * Enqueue the CSS.
	 */
	public function enqueue_css() {
		if ( 'inline' === $this->mode() || ! $this->has_css_file() ) {
			$css = apply_filters(
				'generateblocks_global_css',
				GenerateBlocks_Pro_Styles::get_styles_css()
			);

			// Add a "dummy" handle we can add inline styles to.
			wp_register_style( 'generateblocks-global', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_style( 'generateblocks-global' );

			wp_add_inline_style(
				'generateblocks-global',
				wp_strip_all_tags( $css ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}

		if ( 'file' === $this->mode() && $this->has_css_file() ) {
			wp_enqueue_style( 'generateblocks-global', esc_url( $this->file( 'uri' ) ), array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	}

	/**
	 * Creates our CSS file and puts it on the server.
	 */
	public function create_css_file() {
		$css = apply_filters(
			'generateblocks_global_css',
			GenerateBlocks_Pro_Styles::get_styles_css()
		);

		if ( ! $css || ! $this->can_write() || ! function_exists( 'generateblocks_get_wp_filesystem' ) ) {
			return false;
		}

		$filesystem = generateblocks_get_wp_filesystem();

		if ( ! $filesystem ) {
			return false;
		}

		// Take care of domain mapping.
		if ( defined( 'DOMAIN_MAPPING' ) && DOMAIN_MAPPING ) {
			if ( function_exists( 'domain_mapping_siteurl' ) && function_exists( 'get_original_url' ) ) {
				$mapped_domain = domain_mapping_siteurl( false );
				$original_domain = get_original_url( 'siteurl' );

				$css = str_replace( $original_domain, $mapped_domain, $css );
			}
		}

		if ( is_writable( $this->file( 'path' ) ) || ( ! file_exists( $this->file( 'path' ) ) && is_writable( dirname( $this->file( 'path' ) ) ) ) ) {
			$chmod_file = 0644;

			if ( defined( 'FS_CHMOD_FILE' ) ) {
				$chmod_file = FS_CHMOD_FILE;
			}

			if ( ! $filesystem->put_contents( $this->file( 'path' ), wp_strip_all_tags( $css ), $chmod_file ) ) {
				return false;
			} else {
				// Do a quick double-check to make sure the file was created.
				if ( ! file_exists( $this->file( 'path' ) ) ) {
					return false;
				}

				return true;
			}
		}
	}

	/**
	 * Determines if the CSS file is writable.
	 */
	public function can_write() {
		global $blog_id;

		// Get the upload directory for this site.
		$upload_dir = wp_get_upload_dir();

		// If this is a multisite installation, append the blogid to the filename.
		$css_blog_id = ( is_multisite() && $blog_id > 1 ) ? '_blog-' . $blog_id : null;

		$file_name   = '/style' . $css_blog_id . '-global.css';
		$folder_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'generateblocks';

		if ( ! file_exists( $folder_path ) ) {
			// returns true if yes and false if not.
			return wp_mkdir_p( $folder_path );
		}

		$file_path = $folder_path . $file_name;

		if (
			! is_writable( $folder_path ) &&
			( ! file_exists( $file_path ) || ! is_writable( $file_path ) )
		) {
			// Folder not writable & file does not exist or it's not writable.
			return false;
		}

		if (
			is_writable( $folder_path ) &&
			( file_exists( $file_path ) && ! is_writable( $file_path ) )
		) {
			// Folder is writable but the file is not writable.
			return false;
		}

		// All is well!
		return true;
	}

	/**
	 * Gets the css path or url to the stylesheet
	 *
	 * @param string $target path/url.
	 */
	public function file( $target = 'path' ) {
		global $blog_id;

		// Get the upload directory for this site.
		$upload_dir = wp_get_upload_dir();

		// If this is a multisite installation, append the blogid to the filename.
		$css_blog_id = ( is_multisite() && $blog_id > 1 ) ? '_blog-' . $blog_id : null;

		$file_name   = 'style' . $css_blog_id . '-global.css';
		$folder_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'generateblocks';

		// The complete path to the file.
		$file_path = $folder_path . DIRECTORY_SEPARATOR . $file_name;

		// Get the URL directory of the stylesheet.
		$css_uri_folder = $upload_dir['baseurl'];

		$css_uri = trailingslashit( $css_uri_folder ) . 'generateblocks/' . $file_name;

		// Take care of domain mapping.
		if ( defined( 'DOMAIN_MAPPING' ) && DOMAIN_MAPPING ) {
			if ( function_exists( 'domain_mapping_siteurl' ) && function_exists( 'get_original_url' ) ) {
				$mapped_domain   = domain_mapping_siteurl( false );
				$original_domain = get_original_url( 'siteurl' );
				$css_uri         = str_replace( $original_domain, $mapped_domain, $css_uri );
			}
		}

		$css_uri = set_url_scheme( $css_uri );

		if ( 'path' === $target ) {
			return $file_path;
		} elseif ( 'url' === $target || 'uri' === $target ) {
			$timestamp = ( file_exists( $file_path ) ) ? '?ver=' . filemtime( $file_path ) : '';
			return $css_uri . $timestamp;
		}
	}

	/**
	 * Rebuild our CSS cache and file after we save a class.
	 *
	 * @param int    $post_id The post ID being saved.
	 * @param object $post The post object.
	 */
	public function build_css_file_on_save( $post_id, $post ) {
		if ( 'gblocks_styles' !== $post->post_type ) {
			return;
		}

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

		$this->build_css();
	}

	/**
	 * Rebuild our CSS cache and file after we delete a style.
	 *
	 * @param int    $post_id The post ID being deleted.
	 * @param object $post The post object.
	 */
	public function build_css_on_delete( $post_id, $post ) {
		if ( 'gblocks_styles' !== $post->post_type ) {
			return;
		}

		$this->build_css();
	}

	/**
	 * Rebuild our CSS cache and file.
	 */
	public function build_css() {
		$this->build_css_cache();
		$this->build_css_file();
	}
}

GenerateBlocks_Pro_Enqueue_Styles::get_instance()->init();
