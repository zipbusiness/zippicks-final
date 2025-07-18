<?php
/**
 * Font Optimizations
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
class GeneratePress_Pro_Font_Library_Optimize extends GeneratePress_Pro_Singleton {
	/**
	 * User Agent to be used to make requests to the Google Fonts API.
	 */
	const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0';

	/**
	 * Get the optimized font variants for download.
	 *
	 * @param array $font Array of data for the font to optimize.
	 * @param array $variants The variants to optimize.
	 *
	 * @return array The fonts object.
	 */
	public static function get_variants( $font, $variants ) {
		$font_family = $font['name'] ?? '';
		$slug        = $font['slug'] ?? '';

		$variants = wp_list_sort(
			wp_list_sort( $variants, 'fontWeight', 'ASC' ),
			'fontStyle',
			'DESC'
		);

		$fonts_object = self::convert_to_fonts_object(
			self::fetch_stylesheet(
				$font_family,
				$variants
			)
		);

		return $fonts_object[ $slug ]['variants'] ?? $fonts_object;
	}

	/**
	 * Get the URL for the google fonts stylesheet to fetch.
	 *
	 * @param string $font_family The font-family name with no fallback.
	 * @param array  $variants The list of variants to include.
	 * @return string
	 */
	private static function get_google_css_url( $font_family, $variants ) {
		$encoded_name = str_replace( ' ', '+', $font_family );
		$weights      = wp_list_pluck( $variants, 'fontWeight' );
		$styles       = wp_list_pluck( $variants, 'fontStyle' );
		$has_italics  = in_array( 'italic', $styles, true );
		// Build the URL.
		$url = 'https://fonts.googleapis.com/css2?family=' . $encoded_name;

		// If there's only one variant and it's regular, return the URL immediately.
		$only_regular = count( $variants ) === 1 && 'normal' === $styles[0];
		if ( $only_regular ) {
			return $url;
		}

		if ( $has_italics ) {
			$url .= ':ital,wght@';
		} else {
			$url .= ':wght@' . implode( ';', $weights );

			return $url;
		}

		// If some variants are italic, build the weight string.
		foreach ( $variants as $variant ) {

			$is_italic   = 'italic' === $variant['fontStyle'];
			$first_value = $is_italic ? 1 : 0;
			$url        .= "{$first_value},{$variant['fontWeight']};";
		}

		return rtrim( $url, ';' );
	}

	/**
	 * Fetch Stylesheet.
	 *
	 * @param string $font_family The font-family name.
	 * @param array  $variants The variants to optimize.
	 *
	 * @return string
	 */
	private static function fetch_stylesheet( $font_family, $variants ) {
		$url = self::get_google_css_url( $font_family, $variants );

		// Get the remote stylesheet.
		$response = wp_remote_get(
			$url,
			array(
				'user-agent' => self::USER_AGENT,
			)
		);

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return '';
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Parse the stylesheet and build it into a font object which OMGF can understand.
	 *
	 * @param string $stylesheet A valid CSS stylesheet.
	 *
	 * @return array
	 */
	private static function convert_to_fonts_object( $stylesheet ) {
		preg_match_all( '/font-family:\s\'(.*?)\';/', $stylesheet, $font_families );

		if ( empty( $font_families[1] ) ) {
			return array();
		}

		$font_families = array_unique( $font_families[1] );
		$object        = array();

		foreach ( $font_families as $font_family ) {
			$slug            = sanitize_title( $font_family );
			$object[ $slug ] = array(
				'slug'       => $slug,
				'fontFamily' => $font_family,
				'variants'   => self::parse_variants( $stylesheet, $font_family ),
				'subsets'    => self::parse_subsets( $stylesheet, $font_family ),
			);
		}

		return $object;
	}

	/**
	 * Parse a stylesheet from Google Fonts' API into a valid Font Object.
	 *
	 * @param string $stylesheet The stylesheet to parse.
	 * @param string $font_family The font family used in the parse.
	 *
	 * @return array
	 */
	private static function parse_variants( $stylesheet, $font_family ) {
		/**
		 * This also captures the commented Subset name.
		 */
		preg_match_all( '/\/\*\s.*?}/s', $stylesheet, $font_faces );

		if ( empty( $font_faces[0] ) ) {
			return array();
		}

		$font_object = array();

		foreach ( $font_faces[0] as $font_face ) {
			// Check for exact match of font-family.
			if ( ! preg_match( '/font-family:[\s\'"]*?' . $font_family . '[\'"]?;/', $font_face ) ) {
				continue;
			}

			preg_match( '/font-style:\s(normal|italic);/', $font_face, $font_style );
			preg_match( '/font-weight:\s([0-9]+);/', $font_face, $font_weight );
			preg_match( '/src:\surl\((.*?woff2)\)/', $font_face, $font_src );
			preg_match( '/\/\*\s([a-z\-0-9\[\]]+?)\s\*\//', $font_face, $subset );
			preg_match( '/unicode-range:\s(.*?);/', $font_face, $range );

			$subset = ! empty( $subset[1] ) ? trim( $subset[1], '[]' ) : '';

			/**
			 * Remove variants that have subset the user doesn't need.
			 */
			$allowed_subsets = apply_filters(
				'generatepress_google_font_subsets',
				GeneratePress_Pro_Font_Library::get_settings( 'preferred_subset' )
			);

			if ( empty( $allowed_subsets ) ) {
				$allowed_subsets = array( 'latin' );
			}

			if ( ! empty( $subset ) && ! in_array( $subset, $allowed_subsets, true ) && ! is_numeric( $subset ) ) {
				continue;
			}

			/**
			 * If $subset is empty, assume it's a logographic (Chinese, Japanese, etc.) character set.
			 *
			 * @TODO: Apply subset setting here.
			 */
			if ( is_numeric( $subset ) ) {
				$subset = 'logogram-' . $subset;
			}

			$key = $subset . '-' . $font_weight[1] . ( 'normal' === $font_style[1] ? '' : '-' . $font_style[1] );

			// Setup font object data.
			$font_object[ $key ] = array(
				'fontFamily' => $font_family,
				'fontStyle'  => $font_style[1],
				'fontWeight' => $font_weight[1],
				'src'        => $font_src[1],
			);

			if ( ! empty( $subset ) ) {
				$font_object[ $key ]['subset'] = $subset;
			}

			if ( ! empty( $range ) && isset( $range[1] ) ) {
				$font_object[ $key ]['range'] = $range[1];
			}
		}

		return $font_object;
	}

	/**
	 * Parse stylesheets for subsets, which in Google Fonts stylesheets are always
	 * included, commented above each @font-face statements, e.g. /* latin-ext
	 *
	 * @param string $stylesheet The stylesheet to parse.
	 * @param string $font_family The font family used in the parse.
	 *
	 * @return array
	 */
	private static function parse_subsets( $stylesheet, $font_family ) {

		preg_match_all( '/\/\*\s([a-z\-]+?)\s\*\//', $stylesheet, $subsets );

		if ( empty( $subsets[1] ) ) {
			return array();
		}

		$subsets = array_unique( $subsets[1] );

		return $subsets;
	}

}
