<?php
/**
 * Our Tabs block.
 *
 * @package GenerateBlocks/Extend/Interactions/Tabs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * GenerateBlocks Pro Tabs.
 *
 * @since 1.4.0
 */
class GenerateBlocks_Pro_Tabs_Variation extends GenerateBlocks_Pro_Singleton {

	/**
	 * The class constructor.
	 */
	protected function __construct() {
		parent::__construct();

		if ( ! version_compare( GENERATEBLOCKS_VERSION, '1.7.0-alpha.1', '>=' ) ) {
			return;
		}

		add_filter( 'generateblocks_defaults', [ $this, 'set_defaults' ] );
		add_action( 'generateblocks_block_one_time_css_data', [ $this, 'generate_css' ], 10, 3 );
		add_action( 'generateblocks_block_css_data', [ $this, 'do_dynamic_css' ], 10, 3 );
		add_filter( 'generateblocks_attr_container', [ $this, 'set_container_attributes' ], 10, 2 );
		add_filter( 'generateblocks_attr_dynamic-button', [ $this, 'set_button_attributes' ], 10, 2 );
		add_filter( 'generateblocks_after_container_open', [ $this, 'enqueue_scripts' ], 10, 2 );
		add_filter( 'generateblocks_onboarding_user_meta_properties', [ $this, 'define_add_tab_item_onboarding_property' ], 10, 1 );
	}

	/**
	 * Set our attribute defaults.
	 *
	 * @param array $defaults Existing defaults.
	 */
	public function set_defaults( $defaults ) {
		$defaults['container']['defaultOpenedTab'] = '';
		$defaults['container']['tabItemOpen'] = false;
		$defaults['container']['tabTransition'] = '';
		$defaults['container']['borderColorCurrent'] = false;
		$defaults['container']['backgroundColorCurrent'] = '';
		$defaults['container']['textColorCurrent'] = '';
		$defaults['button']['tabItemOpen'] = false;

		return $defaults;
	}

	/**
	 * Enqueue our tabs script.
	 *
	 * @param string $content Block content.
	 * @param array  $attributes Block attributes.
	 */
	public function enqueue_scripts( $content, $attributes ) {
		if ( ! empty( $attributes['variantRole'] ) && 'tabs' === $attributes['variantRole'] ) {
			wp_enqueue_script(
				'generateblocks-tabs',
				GENERATEBLOCKS_PRO_DIR_URL . 'dist/tabs.js',
				array(),
				GENERATEBLOCKS_PRO_VERSION,
				true
			);
		}

		return $content;
	}

	/**
	 * Generate our one-time tabs CSS.
	 *
	 * @param string $name The block name.
	 * @param array  $settings Block settings.
	 * @param object $css The CSS object.
	 */
	public function generate_css( $name, $settings, $css ) {
		if ( 'container' === $name ) {
			$css->set_selector( '.gb-container.gb-tabs__item:not(.gb-tabs__item-open)' );
			$css->add_property( 'display', 'none' );
		}
	}

	/**
	 * Generate our CSS for our options.
	 *
	 * @since 1.0
	 * @param string $name Name of the block.
	 * @param array  $settings Our available settings.
	 * @param object $css Current desktop CSS object.
	 */
	public function do_dynamic_css( $name, $settings, $css ) {
		if ( 'container' === $name && isset( $settings['variantRole'] ) ) {
			$selector = function_exists( 'generateblocks_get_css_selector' )
				? generateblocks_get_css_selector( 'container', $settings )
				: '.gb-container-' . $settings['uniqueId'];

			if ( 'tab-button' === $settings['variantRole'] ) {
				$css->set_selector( $selector . ':not(.gb-block-is-current)' );
				$css->add_property( 'cursor', 'pointer' );
			}

			if ( 'tab-items' === $settings['variantRole'] && 'fade' === $settings['tabTransition'] ) {
				$css->set_selector( $selector . ' > .gb-tabs__item-open' );
				$css->add_property( 'opacity', 1 );

				$css->set_selector( $selector . ' > .gb-tabs__item-transition' );
				$css->add_property( 'opacity', 0 );
			}
		}
	}

	/**
	 * Set our Container block HTML attributes.
	 *
	 * @param array $attributes HTML attributes.
	 * @param array $settings Block settings.
	 */
	public function set_container_attributes( $attributes, $settings ) {
		if ( isset( $settings['variantRole'] ) ) {
			if ( 'tabs' === $settings['variantRole'] ) {
				$attributes['class'] .= ' gb-tabs';
				$attributes['data-opened-tab'] = $settings['defaultOpenedTab'];
			}

			if ( 'tab-items' === $settings['variantRole'] ) {
				$attributes['class'] .= ' gb-tabs__items';
			}

			if ( 'tab-buttons' === $settings['variantRole'] ) {
				$attributes['class'] .= ' gb-tabs__buttons';
			}

			if ( 'tab-button' === $settings['variantRole'] ) {
				$attributes['class'] .= ' gb-tabs__button';

				if ( true === $settings['tabItemOpen'] ) {
					$attributes['class'] .= ' gb-block-is-current';
				}

				$attributes['role'] = 'button';
				$attributes['tabindex'] = '0';
			}

			if ( 'tab-item' === $settings['variantRole'] ) {
				$attributes['class'] .= ' gb-tabs__item';

				if ( $settings['tabItemOpen'] ) {
					$attributes['class'] .= ' gb-tabs__item-open';
				}
			}
		}

		return $attributes;
	}

	/**
	 * Set our Button block HTML attributes.
	 *
	 * @param array $attributes HTML attributes.
	 * @param array $settings Block settings.
	 */
	public function set_button_attributes( $attributes, $settings ) {
		if ( isset( $settings['variantRole'] ) ) {
			if ( 'tab-button' === $settings['variantRole'] ) {
				$attributes['class'] .= ' gb-tabs__button';

				if ( $settings['tabItemOpen'] ) {
					$attributes['class'] .= ' gb-block-is-current';
				}
			}
		}

		return $attributes;
	}

	/**
	 * Define the onboarding key for adding tab items.
	 *
	 * @param array $properties The registered keys.
	 *
	 * @return array
	 */
	public function define_add_tab_item_onboarding_property( $properties ) {
		$properties['add_tab_item'] = array( 'type' => 'boolean' );

		return $properties;
	}
}

GenerateBlocks_Pro_Tabs_Variation::get_instance();
