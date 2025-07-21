<?php
/**
 * General actions and filters.
 *
 * @package GenerateBlocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get an array of string handles for the package styles.
 *
 * @return array An array of string handles.
 */
function generateblocks_pro_get_package_style_deps() {
	return [ 'generateblocks-pro-packages' ];
}

add_action( 'admin_enqueue_scripts', 'generateblocks_pro_packages_scripts', 1 );
/**
 * Register package stylesheets.
 * Update generateblocks_pro_get_package_style_deps above when adding new styles.
 */
function generateblocks_pro_packages_scripts() {
	$component_asset_info = generateblocks_pro_get_enqueue_assets( 'packages' );
	wp_enqueue_style(
		'generateblocks-pro-packages',
		GENERATEBLOCKS_PRO_DIR_URL . 'dist/packages.css',
		'',
		$component_asset_info['version']
	);

	// Enqueue scripts for all edge22 packages in the plugin.
	$package_json = GENERATEBLOCKS_PRO_DIR . 'package.json';

	if ( file_exists( $package_json ) ) {
		$package_json_parsed = json_decode(
			file_get_contents( $package_json ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			true
		);

		$edge22_packages = array_filter(
			$package_json_parsed['dependencies'],
			function( $package_name ) {
				return 0 === strpos( $package_name, '@edge22/' );
			},
			ARRAY_FILTER_USE_KEY
		);

		foreach ( $edge22_packages as $name => $version ) {
			$name = str_replace( '@edge22/', '', $name );
			$path = GENERATEBLOCKS_PRO_DIR . "dist/{$name}-imported.asset.php";

			if ( ! file_exists( $path ) ) {
				continue;
			}

			$package_info = require $path;

			wp_register_script(
				"generateblocks-pro-$name",
				GENERATEBLOCKS_PRO_DIR_URL . 'dist/' . $name . '.js',
				$package_info['dependencies'],
				$version,
				true
			);

			wp_register_style(
				"generateblocks-pro-$name",
				GENERATEBLOCKS_PRO_DIR_URL . 'dist/' . $name . '.css',
				[],
				$version
			);
		}
	}
}

add_action( 'enqueue_block_editor_assets', 'generateblocks_pro_do_block_editor_assets', 9 );
/**
 * Enqueue Gutenberg block assets for both frontend + backend.
 *
 * Assets enqueued:
 * 1. blocks.style.build.css - Frontend + Backend.
 * 2. blocks.build.js - Backend.
 * 3. blocks.editor.build.css - Backend.
 *
 * @uses {wp-blocks} for block type registration & related functions.
 * @uses {wp-element} for WP Element abstraction — structure of blocks.
 * @uses {wp-i18n} to internationalize the block's text.
 * @uses {wp-editor} for WP editor styles.
 * @since 1.0.0
 */
function generateblocks_pro_do_block_editor_assets() {
	global $pagenow;

	$deps = array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-html-entities' );

	if ( 'widgets.php' === $pagenow ) {
		unset( $deps[3] );
	}

	$assets = generateblocks_pro_get_enqueue_assets(
		'blocks',
		[
			'dependencies' => $deps,
			'version' => filemtime( GENERATEBLOCKS_PRO_DIR . 'dist/blocks.js' ),
		]
	);

	wp_enqueue_script(
		'generateblocks-pro',
		GENERATEBLOCKS_PRO_DIR_URL . 'dist/blocks.js',
		$assets['dependencies'],
		$assets['version'],
		true
	);

	wp_set_script_translations( 'generateblocks-pro', 'generateblocks-pro', GENERATEBLOCKS_PRO_DIR . 'languages' );

	$admin_settings = wp_parse_args(
		get_option( 'generateblocks_admin', array() ),
		generateblocks_pro_get_admin_option_defaults()
	);

	wp_localize_script(
		'generateblocks-pro',
		'generateBlocksPro',
		array(
			'templatesURL' => admin_url( 'edit.php?post_type=gblocks_templates' ),
			'svgIcons' => generateblocks_pro_editor_icon_list(),
			'isGlobalStyle' => 'gblocks_global_style' === get_post_type(),
			'globalStyleIds' => generateblocks_pro_get_global_style_ids(),
			'globalStyleAttrs' => generateblocks_pro_get_global_style_attrs(),
			'hasRgbaSupport' => version_compare( GENERATEBLOCKS_VERSION, '1.4.0-alpha.1', '>=' ),
			'hasColorGroups' => version_compare( GENERATEBLOCKS_VERSION, '1.5.0-alpha.1', '>=' ),
			'enableRemoteTemplates' => $admin_settings['enable_remote_templates'],
			'enableLocalTemplates' => $admin_settings['enable_local_templates'],
			'isACFActive' => class_exists( 'ACF' ),
			'generateblocksVersion' => GENERATEBLOCKS_VERSION,
			'generateblocksProVersion' => GENERATEBLOCKS_PRO_VERSION,
			'blockStyles' => function_exists( 'generateblocks_get_default_styles' ) ? generateblocks_get_default_styles() : array(),
			'showIncompatibleGlobalStyles' => apply_filters( 'generateblocks_show_incompatible_global_styles', false ),
			'useLegacyPatternLibrary' => generateblocks_pro_has_legacy_patterns(),
			'adminUrl' => admin_url(),
			'canManageStyles' => GenerateBlocks_Pro_Styles::can_manage_styles(),
			'mediaQueries' => function_exists( 'generateblocks_get_media_queries' )
				? generateblocks_get_media_queries()
				: [],
		)
	);

	generateblocks_pro_packages_scripts();

	wp_enqueue_style(
		'generateblocks-pro',
		GENERATEBLOCKS_PRO_DIR_URL . 'dist/blocks.css',
		array_merge( [ 'wp-edit-blocks' ], generateblocks_pro_get_package_style_deps() ),
		filemtime( GENERATEBLOCKS_PRO_DIR . 'dist/blocks.css' )
	);

	$editor_assets = generateblocks_pro_get_enqueue_assets( 'editor' );

	wp_enqueue_script(
		'generateblocks-pro-editor',
		GENERATEBLOCKS_PRO_DIR_URL . 'dist/editor.js',
		$editor_assets['dependencies'],
		$editor_assets['version'],
		true
	);

	wp_enqueue_style(
		'generateblocks-pro-editor',
		GENERATEBLOCKS_PRO_DIR_URL . 'dist/editor.css',
		array_merge( [ 'wp-edit-blocks' ], generateblocks_pro_get_package_style_deps() ),
		filemtime( GENERATEBLOCKS_PRO_DIR . 'dist/editor.css' )
	);

	wp_localize_script(
		'generateblocks-pro-classic-menu-editor-script',
		'generateblocksBlockClassicMenu',
		[
			'hasMenuSupport' => get_theme_support( 'menus' ),
			'menuAdminUrl' => admin_url( 'nav-menus.php' ),
			'navMenus' => wp_get_nav_menus(),
		]
	);
}

add_filter( 'generateblocks_attr_container', 'generateblocks_pro_set_container_attributes', 10, 2 );
/**
 * Set the attributes for our main Container wrapper.
 *
 * @since 1.0.0
 * @param array $attributes The existing attributes.
 * @param array $settings The settings for the block.
 */
function generateblocks_pro_set_container_attributes( $attributes, $settings ) {
	if ( '' !== $settings['url'] && 'wrapper' === $settings['linkType'] ) {
		$attributes['href'] = esc_url( $settings['url'] );

		$rel_attributes = array();

		if ( $settings['relNoFollow'] ) {
			$rel_attributes[] = 'nofollow';
		}

		if ( $settings['target'] ) {
			$rel_attributes[] = 'noopener';
			$rel_attributes[] = 'noreferrer';
			$attributes['target'] = '_blank';
		}

		if ( $settings['relSponsored'] ) {
			$rel_attributes[] = 'sponsored';
		}

		if ( ! empty( $rel_attributes ) ) {
			$attributes['rel'] = implode( ' ', $rel_attributes );
		}

		if ( $settings['hiddenLinkAriaLabel'] ) {
			$attributes['aria-label'] = esc_attr( $settings['hiddenLinkAriaLabel'] );
		}
	}

	$attributes = generateblocks_pro_with_custom_attributes( $attributes, $settings );

	if ( ! empty( $settings['useGlobalStyle'] ) && ! empty( $settings['globalStyleId'] ) ) {
		$attributes['class'] .= ' gb-container-' . esc_attr( $settings['globalStyleId'] );
	}

	return $attributes;
}

add_filter( 'generateblocks_attr_grid-item', 'generateblocks_pro_set_grid_container_attributes', 10, 2 );
/**
 * Set the attributes for our grid item wrapper.
 *
 * @since 1.1.0
 * @param array $attributes The existing attributes.
 * @param array $settings The settings for the block.
 */
function generateblocks_pro_set_grid_container_attributes( $attributes, $settings ) {
	if ( ! empty( $settings['useGlobalStyle'] ) && ! empty( $settings['globalStyleId'] ) ) {
		$attributes['class'] .= ' gb-grid-column-' . esc_attr( $settings['globalStyleId'] );
	}

	return $attributes;
}

add_filter( 'generateblocks_attr_grid-wrapper', 'generateblocks_pro_set_grid_attributes', 10, 2 );
/**
 * Set the attributes for our grid wrapper.
 *
 * @since 1.0.0
 * @param array $attributes The existing attributes.
 * @param array $settings The settings for the block.
 */
function generateblocks_pro_set_grid_attributes( $attributes, $settings ) {
	if ( ! empty( $settings['useGlobalStyle'] ) && ! empty( $settings['globalStyleId'] ) ) {
		$attributes['class'] .= ' gb-grid-wrapper-' . esc_attr( $settings['globalStyleId'] );
	}

	$attributes = generateblocks_pro_with_custom_attributes( $attributes, $settings );

	return $attributes;
}

add_filter( 'generateblocks_attr_button-container', 'generateblocks_pro_set_button_container_attributes', 10, 2 );
/**
 * Set the attributes for our grid wrapper.
 *
 * @since 1.0.0
 * @param array $attributes The existing attributes.
 * @param array $settings The settings for the block.
 */
function generateblocks_pro_set_button_container_attributes( $attributes, $settings ) {
	if ( ! empty( $settings['useGlobalStyle'] ) && ! empty( $settings['globalStyleId'] ) ) {
		$attributes['class'] .= ' gb-button-wrapper-' . esc_attr( $settings['globalStyleId'] );
	}

	$attributes = generateblocks_pro_with_custom_attributes( $attributes, $settings );

	return $attributes;
}

add_filter( 'generateblocks_attr_image', 'generateblocks_pro_set_image_attributes', 10, 2 );
/**
 * Set the attributes for our dynamic image block.
 *
 * @since 1.2.0
 * @param array $attributes The existing attributes.
 * @param array $settings The settings for the block.
 */
function generateblocks_pro_set_image_attributes( $attributes, $settings ) {
	if ( ! empty( $settings['useGlobalStyle'] ) && ! empty( $settings['globalStyleId'] ) ) {
		$attributes['class'] .= ' gb-image-' . esc_attr( $settings['globalStyleId'] );
	}

	$attributes = generateblocks_pro_with_custom_attributes( $attributes, $settings );

	return $attributes;
}

add_filter( 'generateblocks_attr_dynamic-headline', 'generateblocks_pro_set_headline_attributes', 10, 2 );
/**
 * Set the attributes for our dynamic headlines.
 *
 * @since 1.2.0
 * @param array $attributes The existing attributes.
 * @param array $settings The settings for the block.
 */
function generateblocks_pro_set_headline_attributes( $attributes, $settings ) {
	if ( ! empty( $settings['useGlobalStyle'] ) && ! empty( $settings['globalStyleId'] ) ) {
		$attributes['class'] .= ' gb-headline-' . esc_attr( $settings['globalStyleId'] );
	}

	$attributes = generateblocks_pro_with_custom_attributes( $attributes, $settings );

	return $attributes;
}

add_filter( 'generateblocks_attr_dynamic-button', 'generateblocks_pro_set_button_attributes', 10, 2 );
/**
 * Set the attributes for our dynamic buttons.
 *
 * @since 1.2.0
 * @param array $attributes The existing attributes.
 * @param array $settings The settings for the block.
 */
function generateblocks_pro_set_button_attributes( $attributes, $settings ) {
	if ( ! empty( $settings['useGlobalStyle'] ) && ! empty( $settings['globalStyleId'] ) ) {
		$attributes['class'] .= ' gb-button-' . esc_attr( $settings['globalStyleId'] );
	}

	$attributes = generateblocks_pro_with_custom_attributes( $attributes, $settings );

	return $attributes;
}

add_filter( 'generateblocks_after_container_open', 'generateblocks_pro_add_container_link', 10, 2 );
/**
 * Add a hidden container link to the Container.
 *
 * @since 1.0.0
 * @param string $output Block output.
 * @param array  $attributes The block attributes.
 */
function generateblocks_pro_add_container_link( $output, $attributes ) {
	$defaults = generateblocks_get_block_defaults();

	$settings = wp_parse_args(
		$attributes,
		$defaults['container']
	);

	if ( '' !== $settings['url'] && 'hidden-link' === $settings['linkType'] ) {
		$rel_attributes = array();

		if ( $settings['relNoFollow'] ) {
			$rel_attributes[] = 'nofollow';
		}

		if ( $settings['relSponsored'] ) {
			$rel_attributes[] = 'sponsored';
		}

		if ( ! empty( $rel_attributes ) ) {
			$attributes['rel'] = implode( ' ', $rel_attributes );
		}

		if ( $settings['target'] ) {
			$rel_attributes[] = 'noopener';
			$rel_attributes[] = 'noreferrer';
		}

		$output .= sprintf(
			'<a %s></a>',
			generateblocks_attr(
				'container-link',
				array(
					'class' => 'gb-container-link',
					'href' => '' !== $settings['url'] ? esc_url( $settings['url'] ) : '',
					'aria-label' => $settings['hiddenLinkAriaLabel'] ? esc_attr( $settings['hiddenLinkAriaLabel'] ) : '',
					'rel' => ! empty( $rel_attributes ) ? implode( ' ', $rel_attributes ) : '',
					'target' => $settings['target'] ? '_blank' : '',
				),
				$settings
			)
		);
	}

	return $output;
}

add_filter( 'generateblocks_svg_shapes', 'generateblocks_pro_add_custom_svg_shapes' );
/**
 * Add custom SVG shapes from our library.
 *
 * @since 1.0.0
 * @param array $shapes Existing shapes.
 */
function generateblocks_pro_add_custom_svg_shapes( $shapes ) {
	$custom_shapes = get_option( 'generateblocks_svg_shapes', array() );
	$new_shapes = array();

	if ( ! empty( $custom_shapes ) ) {
		// Format our custom shapes to fit our shapes structure.
		foreach ( $custom_shapes as $index => $data ) {
			$new_shapes[ esc_attr( $data['group_id'] ) ] = array(
				'group' => esc_html( $data['group'] ),
				'svgs' => array(),
			);

			if ( isset( $data['shapes'] ) ) {
				foreach ( (array) $data['shapes'] as $shape_index => $shape ) {
					$new_shapes[ esc_attr( $data['group_id'] ) ]['svgs'][ $shape['id'] ] = array(
						'label' => $shape['name'],
						'icon' => $shape['shape'],
					);
				}
			}
		}
	}

	$shapes = array_merge( $new_shapes, $shapes );

	return $shapes;
}

add_filter( 'generateblocks_container_tagname', 'generateblocks_pro_set_container_tagname', 10, 2 );
/**
 * Set the Container block tag name.
 *
 * @since 1.0.0
 *
 * @param string $tagname Current tag name.
 * @param array  $attributes Current attributes.
 */
function generateblocks_pro_set_container_tagname( $tagname, $attributes ) {
	$defaults = generateblocks_get_block_defaults();

	$settings = wp_parse_args(
		$attributes,
		$defaults['container']
	);

	if ( ! empty( $settings['url'] ) && 'wrapper' === $settings['linkType'] ) {
		$tagname = 'a';
	}

	return $tagname;
}

add_filter( 'register_block_type_args', 'generateblocks_pro_filter_block_type_args', 10, 2 );
/**
 * Filter our register_block_type() args.
 *
 * @param array  $args Existing args.
 * @param string $name The block name.
 */
function generateblocks_pro_filter_block_type_args( $args, $name ) {
	if ( 'generateblocks/container' === $name ) {
		if ( ! empty( $args['editor_script_handles'] ) ) {
			$args['editor_script_handles'] = array_merge( $args['editor_script_handles'], [ 'generateblocks-pro' ] );
			$args['editor_style_handles'] = array_merge( $args['editor_style_handles'], [ 'generateblocks-pro' ] );
		}
	}

	return $args;
}

add_filter( 'generateblocks_modify_block_data', 'generateblocks_pro_add_block_data', 10, 2 );
/**
 * Add our blocks to the dataset.
 *
 * @param array $data The block data.
 * @param array $block The block.
 */
function generateblocks_pro_add_block_data( $data, $block ) {
	$block_name = $block['blockName'] ?? '';

	if ( ! $block_name ) {
		return $data;
	}

	if ( 'generateblocks-pro/accordion' === $block_name ) {
		$data['accordion'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/accordion-item' === $block_name ) {
		$data['accordion-item'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/accordion-toggle' === $block_name ) {
		$data['accordion-toggle'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/accordion-toggle-icon' === $block_name ) {
		$data['accordion-toggle-icon'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/tabs' === $block_name ) {
		$data['tabs'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/tabs-menu' === $block_name ) {
		$data['tabs-menu'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/tab-menu-item' === $block_name ) {
		$data['tab-menu-item'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/tab-items' === $block_name ) {
		$data['tab-items'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/tab-item' === $block_name ) {
		$data['tab-item'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/navigation' === $block_name ) {
		$data['navigation'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/classic-menu' === $block_name ) {
		$data['classic-menu'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/classic-menu-item' === $block_name ) {
		$data['classic-menu-item'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/classic-sub-menu' === $block_name ) {
		$data['classic-sub-menu'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/menu-container' === $block_name ) {
		$data['menu-container'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/menu-toggle' === $block_name ) {
		$data['menu-toggle'][] = $block['attrs'] ?? [];
	}

	if ( 'generateblocks-pro/site-header' === $block_name ) {
		$data['site-header'][] = $block['attrs'] ?? [];
	}

	return $data;
}

add_filter( 'generateblocks_dynamic_css_blocks', 'generateblocks_pro_add_dynamic_css_blocks' );
/**
 * Add our dynamic CSS blocks.
 *
 * @param array $blocks Existing blocks.
 */
function generateblocks_pro_add_dynamic_css_blocks( $blocks ) {
	$blocks['accordion']             = 'GenerateBlocks_Block_Accordion';
	$blocks['accordion-item']        = 'GenerateBlocks_Block_Accordion_Item';
	$blocks['accordion-toggle']      = 'GenerateBlocks_Block_Accordion_Toggle';
	$blocks['accordion-toggle-icon'] = 'GenerateBlocks_Block_Accordion_Toggle_Icon';
	$blocks['tabs']                  = 'GenerateBlocks_Block_Tabs';
	$blocks['tabs-menu']             = 'GenerateBlocks_Block_Tabs_Menu';
	$blocks['tab-menu-item']         = 'GenerateBlocks_Block_Tab_Menu_Item';
	$blocks['tab-items']             = 'GenerateBlocks_Block_Tab_Items';
	$blocks['tab-item']              = 'GenerateBlocks_Block_Tab_Item';
	$blocks['navigation']            = 'GenerateBlocks_Block_Navigation';
	$blocks['classic-menu']          = 'GenerateBlocks_Block_Classic_Menu';
	$blocks['classic-menu-item']     = 'GenerateBlocks_Block_Classic_Menu_Item';
	$blocks['classic-sub-menu']      = 'GenerateBlocks_Block_Classic_Sub_Menu';
	$blocks['menu-container']        = 'GenerateBlocks_Block_Menu_Container';
	$blocks['menu-toggle']           = 'GenerateBlocks_Block_Menu_Toggle';
	$blocks['site-header']           = 'GenerateBlocks_Block_Site_Header';

	return $blocks;
}

add_filter( 'generateblocks_dynamic_tags_allowed_blocks', 'generateblocks_pro_add_dynamic_tags_allowed_blocks' );
/**
 * Add our dynamic tags allowed blocks.
 *
 * @param array $blocks Existing blocks.
 */
function generateblocks_pro_add_dynamic_tags_allowed_blocks( $blocks ) {
	$pro_blocks = [
		'generateblocks-pro/accordion',
		'generateblocks-pro/accordion-content',
		'generateblocks-pro/accordion-item',
		'generateblocks-pro/accordion-toggle',
		'generateblocks-pro/accordion-toggle-icon',
		'generateblocks-pro/tabs',
		'generateblocks-pro/tabs-menu',
		'generateblocks-pro/tab-menu-item',
		'generateblocks-pro/tab-items',
		'generateblocks-pro/tab-item',
	];

	return array_merge( $blocks, $pro_blocks );
}

add_action( 'enqueue_block_editor_assets', 'generateblocks_pro_set_global_styles_permission', 0 );
add_action( 'admin_enqueue_scripts', 'generateblocks_pro_set_global_styles_permission', 0 );
/**
 * Output permissions for use in the editor.
 *
 * @return void
 */
function generateblocks_pro_set_global_styles_permission() {
	if ( 'admin_enqueue_scripts' === current_filter() ) {
		$screen = get_current_screen();

		if ( 'generateblocks_page_generateblocks-styles' !== $screen->id ) {
			return;
		}
	}

	$permissions = [
		'canManageStyles' => GenerateBlocks_Pro_Styles::can_manage_styles(),
	];

	$permission_object = wp_json_encode( $permissions );
	wp_register_script( 'generateblocks-global-styles-permissions', '', [], '1.0', false );
	wp_enqueue_script( 'generateblocks-global-styles-permissions' );
	$script = sprintf(
		'const gbGlobalStylePermissions = %s;
		Object.freeze( gbGlobalStylePermissions );',
		$permission_object
	);
	wp_add_inline_script( 'generateblocks-global-styles-permissions', $script );
}

add_action( 'init', 'generateblocks_pro_register_menu_support' );
/**
 * Register menu support for GenerateBlocks Pro.
 *
 * @return void
 */
function generateblocks_pro_register_menu_support() {
	register_setting(
		'general',
		'generateblocks_pro_classic_menu_support',
		array(
			'type' => 'boolean',
			'default' => false,
			'show_in_rest' => true,
		)
	);
}

add_action( 'after_setup_theme', 'generateblocks_pro_add_menu_support' );
/**
 * Add menu support for GenerateBlocks Pro.
 *
 * @return void
 */
function generateblocks_pro_add_menu_support() {
	if ( get_theme_support( 'menus' ) ) {
		return;
	}

	$menu_support = get_option( 'generateblocks_pro_classic_menu_support', false );

	if ( $menu_support ) {
		add_theme_support( 'menus' );
	}
}
