<?php
/**
 * Handles the Content block.
 *
 * @package GenerateBlocksPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Content block class.
 */
class GenerateBlocks_Block_Classic_Menu extends GenerateBlocks_Block {
	/**
	 * Keep track of all blocks of this type on the page.
	 *
	 * @var array $block_ids The current block id.
	 */
	protected static $block_ids = [];

	/**
	 * Store our block name.
	 *
	 * @var string $block_name The block name.
	 */
	public static $block_name = 'generateblocks-pro/classic-menu';

	/**
	 * Render the Element block.
	 *
	 * @param array  $attributes    The block attributes.
	 * @param string $block_content The block content.
	 * @param array  $block         The block.
	 */
	public static function render_block( $attributes, $block_content, $block ) {
		// Add styles to this block if needed.
		$block_content = generateblocks_maybe_add_block_css(
			$block_content,
			[
				'class_name' => __CLASS__,
				'attributes' => $attributes,
				'block_ids' => self::$block_ids,
			]
		);

		$selected_menu = $attributes['menu'] ?? '';
		$unique_id = $attributes['uniqueId'] ?? '';

		if ( ! $selected_menu || ! $unique_id ) {
			return;
		}

		if ( isset( $block->context['generateblocks-pro/subMenuType'] ) ) {
			$sub_menu_type = $block->context['generateblocks-pro/subMenuType'];
		} elseif ( isset( $_GET['subMenuType'] ) ) { // phpcs:ignore -- No processing of data.
			$sub_menu_type = esc_attr( $_GET['subMenuType'] ); // phpcs:ignore -- No processing of data.
		} else {
			$sub_menu_type = 'hover';
		}

		$classes = [
			'gb-menu',
			'gb-menu--base',
			'gb-menu-' . $unique_id,
			'gb-menu--' . $sub_menu_type,
		];

		if ( ! empty( $attributes['globalClasses'] ) ) {
			$classes = array_merge( $classes, $attributes['globalClasses'] );
		}

		if ( ! empty( $attributes['className'] ) ) {
			$classes[] = $attributes['className'];
		}

		$class = implode( ' ', $classes );

		$add_menu_item_classes = function( $classes, $menu_item ) use ( $sub_menu_type, $unique_id ) {
			/**
			 * $mega_menu = get_post_meta( $menu_item->ID, '_gb_mega_menu', true );
			 */
			$mega_menu = false;

			if ( $mega_menu && 'click' === $sub_menu_type ) {
				$classes[] = 'menu-item-has-gb-mega-menu';
				$classes[] = 'menu-item-has-children';
			}

			$classes[] = 'gb-menu-item';
			$new_unique_id = substr_replace( $unique_id, 'mi', 0, 2 );
			$classes[] = 'gb-menu-item-' . $new_unique_id;

			// Escape classes.
			$classes = array_map( 'esc_attr', $classes );

			return $classes;
		};

		$add_dropdown_icon = function( $title, $menu_item ) use ( $sub_menu_type ) {
			/**
			 * $mega_menu = get_post_meta( $menu_item->ID, '_gb_mega_menu', true );
			 * $show_mega_menu = $mega_menu && 'click' === $sub_menu_type;
			 */
			$show_mega_menu = false;
			$has_children = in_array( 'menu-item-has-children', $menu_item->classes, true );

			if ( $show_mega_menu || $has_children ) {
				$arrow_icon     = '<svg class="gb-submenu-toggle-icon" viewBox="0 0 330 512" aria-hidden="true" width="1em" height="1em" fill="currentColor"><path d="M305.913 197.085c0 2.266-1.133 4.815-2.833 6.514L171.087 335.593c-1.7 1.7-4.249 2.832-6.515 2.832s-4.815-1.133-6.515-2.832L26.064 203.599c-1.7-1.7-2.832-4.248-2.832-6.514s1.132-4.816 2.832-6.515l14.162-14.163c1.7-1.699 3.966-2.832 6.515-2.832 2.266 0 4.815 1.133 6.515 2.832l111.316 111.317 111.316-111.317c1.7-1.699 4.249-2.832 6.515-2.832s4.815 1.133 6.515 2.832l14.162 14.163c1.7 1.7 2.833 4.249 2.833 6.515z"></path></svg>';
				$submenu_button = sprintf(
					'<span class="gb-submenu-toggle" role="button" aria-expanded="false" aria-haspopup="menu" tabindex="0">%s</span>',
					$arrow_icon
				);

				if ( 'click' === $sub_menu_type ) {
					return $title . $arrow_icon;
				}

				return $title . $submenu_button;
			}

			return $title;
		};

		$add_link_atts = function ( $atts, $menu_item ) use ( $sub_menu_type ) {
			$class         = $atts['class'] ?? '';
			$class         .= ' gb-menu-link';
			$class         = trim( $class );
			$atts['class'] = esc_attr( $class );
			$disable_links = isset( $_GET['disableLinks'] ); // phpcs:ignore -- No processing of data.

			if ( $disable_links ) {
				$atts['onClick'] = 'event.preventDefault();';
			}

			if ( 'click' === $sub_menu_type ) {
				/**
				 * $mega_menu = get_post_meta( $menu_item->ID, '_gb_mega_menu', true );
				 */
				$mega_menu = false;

				if ( $mega_menu ) {
					$modal_id = '#gb-mega-menu-' . $menu_item->ID;

					// Add directive and modal element target.
					$atts['data-gb-toggle']        = $modal_id;
					$atts['data-gb-modal-options'] = '{showBackdrop: false, placement: "bottom", allowMultiple: false}';
					$atts['aria-controls']         = str_replace( '#', '', $modal_id );
				}

				if ( $mega_menu || in_array( 'menu-item-has-children', $menu_item->classes, true ) ) {
					$atts['role']          = 'button';
					$atts['aria-expanded'] = 'false';
					$atts['aria-haspopup'] = 'menu';
				}
			}

			return $atts;
		};

		$add_sub_menu_attributes = function( $atts, $args ) use ( $unique_id ) {
			$new_unique_id = substr_replace( $unique_id, 'sm', 0, 2 );
			$atts['class'] = 'sub-menu gb-sub-menu gb-sub-menu-' . $new_unique_id;

			return $atts;
		};

		add_filter( 'nav_menu_css_class', $add_menu_item_classes, 10, 2 );
		add_filter( 'nav_menu_submenu_attributes', $add_sub_menu_attributes, 10, 2 );
		add_filter( 'nav_menu_item_title', $add_dropdown_icon, 10, 2 );
		add_filter( 'nav_menu_link_attributes', $add_link_atts, 10, 2 );

		ob_start();

		wp_nav_menu(
			[
				'menu'            => $selected_menu,
				'container'       => '',
				'container_class' => '',
				'menu_class'      => $class,
				'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
				'fallback_cb'     => false,
			]
		);

		remove_filter( 'nav_menu_css_class', $add_menu_item_classes, 10, 2 );
		remove_filter( 'nav_menu_submenu_attributes', $add_sub_menu_attributes, 10, 2 );
		remove_filter( 'nav_menu_item_title', $add_dropdown_icon, 10, 2 );
		remove_filter( 'nav_menu_link_attributes', $add_link_atts, 10, 2 );

		do_action( 'generateblocks_pro_after_menu_block', $attributes, is_admin() );

		$block_content .= ob_get_clean();

		if ( ! wp_style_is( 'generateblocks-classic-menu', 'enqueued' ) ) {
			self::enqueue_style();
		}

		if ( ! wp_script_is( 'generateblocks-classic-menu', 'enqueued' ) ) {
			self::enqueue_scripts();
		}

		return $block_content;
	}

	/**
	 * Enqueue block styles.
	 */
	private static function enqueue_style() {
		wp_enqueue_style(
			'generateblocks-classic-menu',
			GENERATEBLOCKS_PRO_DIR_URL . 'dist/classic-menu-style.css',
			[],
			GENERATEBLOCKS_PRO_VERSION
		);
	}

	/**
	 * Enqueue block scripts.
	 */
	private static function enqueue_scripts() {
		wp_enqueue_script(
			'generateblocks-classic-menu',
			GENERATEBLOCKS_PRO_DIR_URL . 'dist/classic-menu.js',
			[],
			GENERATEBLOCKS_PRO_VERSION,
			true
		);
	}

	/**
	 * Enqueue block assets.
	 */
	public static function enqueue_assets() {
		self::enqueue_scripts();
		self::enqueue_style();
	}
}
