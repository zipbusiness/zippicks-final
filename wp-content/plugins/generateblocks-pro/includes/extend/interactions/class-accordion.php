<?php
/**
 * This file handles the Accordion functions.
 *
 * @package GenerateBlocksPro/Extend/Interactions/Accordion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Accordion functions.
 *
 * @since 1.5.0
 */
class GenerateBlocks_Pro_Block_Variant_Accordion extends GenerateBlocks_Pro_Singleton {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		if ( ! version_compare( GENERATEBLOCKS_VERSION, '1.7.0-alpha.1', '>=' ) ) {
			return;
		}

		add_filter( 'generateblocks_defaults', [ $this, 'set_defaults' ] );
		add_filter( 'generateblocks_after_container_open', [ $this, 'enqueue_scripts' ], 10, 2 );
		add_filter( 'generateblocks_attr_container', [ $this, 'set_container_attributes' ], 10, 4 );
		add_filter( 'generateblocks_attr_dynamic-button', [ $this, 'set_button_attributes' ], 10, 2 );
		add_action( 'generateblocks_block_one_time_css_data', [ $this, 'generate_css' ], 10, 3 );
		add_action( 'generateblocks_block_css_data', [ $this, 'do_dynamic_css' ], 10, 3 );
		add_filter( 'generateblocks_before_container_open', [ $this, 'open_accordion_content_container' ], 1, 3 );
		add_filter( 'generateblocks_after_container_close', [ $this, 'close_accordion_content_container' ], 100, 3 );
		add_filter( 'generateblocks_onboarding_user_meta_properties', [ $this, 'define_add_accordion_item_onboarding_property' ], 10, 1 );
		add_filter( 'register_block_type_args', [ $this, 'block_type_args' ], 10, 2 );
		add_action( 'wp_footer', [ $this, 'faq_schema_script' ] );
		add_filter( 'render_block', [ $this, 'gather_schema_data' ], 10, 3 );
	}

	/**
	 * Set our attribute defaults.
	 *
	 * @param array $defaults Existing defaults.
	 */
	public function set_defaults( $defaults ) {
		$defaults['container']['accordionItemOpen'] = false;
		$defaults['container']['accordionMultipleOpen'] = false;
		$defaults['container']['accordionTransition'] = '';
		$defaults['button']['accordionItemOpen'] = false;

		return $defaults;
	}

	/**
	 * Enqueue our accordion script.
	 *
	 * @param string $content Block content.
	 * @param array  $attributes Block attributes.
	 */
	public function enqueue_scripts( $content, $attributes ) {
		if ( ! empty( $attributes['variantRole'] ) && 'accordion' === $attributes['variantRole'] ) {
			wp_enqueue_script(
				'generateblocks-accordion',
				GENERATEBLOCKS_PRO_DIR_URL . 'dist/accordion.js',
				array(),
				GENERATEBLOCKS_PRO_VERSION,
				true
			);
		}

		return $content;
	}

	/**
	 * Set our Container block HTML attributes.
	 *
	 * @param array    $attributes HTML attributes.
	 * @param array    $settings Block settings.
	 * @param string   $context Context of the filter.
	 * @param WP_Block $block      Block instance.
	 */
	public function set_container_attributes( $attributes, $settings, $context, $block ) {
		if ( isset( $settings['variantRole'] ) && 'accordion' === $settings['variantRole'] ) {
			$attributes['class'] .= ' gb-accordion';

			if ( $settings['accordionMultipleOpen'] ) {
				$attributes['data-accordion-multiple-open'] = true;
			}
		}

		if ( isset( $settings['variantRole'] ) && 'accordion-item' === $settings['variantRole'] ) {
			$attributes['class'] .= ' gb-accordion__item';

			if ( $settings['accordionItemOpen'] ) {
				$attributes['class'] .= ' gb-accordion__item-open';
			}

			if ( $settings['accordionTransition'] ) {
				$attributes['data-transition'] = $settings['accordionTransition'];
			}
		}

		if ( isset( $settings['variantRole'] ) && 'accordion-content' === $settings['variantRole'] ) {
			$transition = isset( $block->context['generateblocks-pro/accordionTransition'] )
				? $block->context['generateblocks-pro/accordionTransition']
				: '';

			if ( 'slide' !== $transition ) {
				$attributes['class'] .= ' gb-accordion__content';
			}

			// Unset the accordion content ID. This ID is added to the wrapping div
			// in the open_accordion_content_container function.
			$attributes['id'] = '';
		}

		if ( isset( $settings['variantRole'] ) && 'accordion-toggle' === $settings['variantRole'] ) {
			$attributes['class'] .= ' gb-accordion__toggle';

			if ( $settings['accordionItemOpen'] ) {
				$attributes['class'] .= ' gb-block-is-current';
			}

			$attributes['role'] = 'button';
			$attributes['tabindex'] = '0';
		}

		return $attributes;
	}

	/**
	 * Set our dynamic Button block HTML attributes.
	 *
	 * @param array $attributes HTML attributes.
	 * @param array $settings Block settings.
	 */
	public function set_button_attributes( $attributes, $settings ) {
		if ( isset( $settings['variantRole'] ) && 'accordion-toggle' === $settings['variantRole'] ) {
			$attributes['class'] .= ' gb-accordion__toggle';

			if ( $settings['accordionItemOpen'] ) {
				$attributes['class'] .= ' gb-block-is-current';
			}
		}

		return $attributes;
	}

	/**
	 * Generate our one-time accordion CSS.
	 *
	 * @param string $name The block name.
	 * @param array  $settings Block settings.
	 * @param object $css The CSS object.
	 */
	public function generate_css( $name, $settings, $css ) {
		if ( 'button' === $name ) {
			$css->set_selector( '.gb-accordion__item:not(.gb-accordion__item-open) > .gb-button .gb-accordion__icon-open' );
			$css->add_property( 'display', 'none' );

			$css->set_selector( '.gb-accordion__item.gb-accordion__item-open > .gb-button .gb-accordion__icon' );
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
			$accordion_transition = ! empty( $settings['accordionTransition'] ) ? $settings['accordionTransition'] : '';
			$selector = function_exists( 'generateblocks_get_css_selector' )
				? generateblocks_get_css_selector( 'container', $settings )
				: '.gb-container-' . $settings['uniqueId'];

			if ( 'accordion-item' === $settings['variantRole'] ) {
				if ( '' === $accordion_transition || 'fade' === $accordion_transition ) {
					$css->set_selector( $selector . ':not(.gb-accordion__item-open) > .gb-accordion__content' );
					$css->add_property( 'display', 'none' );

					if ( 'fade' === $accordion_transition ) {
						$css->set_selector( $selector . '.gb-accordion__item-open > .gb-accordion__content' );
						$css->add_property( 'opacity', 1 );

						$css->set_selector( $selector . '.gb-accordion__item-transition > .gb-accordion__content' );
						$css->add_property( 'opacity', 0 );
					}
				}

				if ( 'slide' === $accordion_transition ) {
					$css->set_selector( $selector . ' > .gb-accordion__content' );
					$css->add_property( 'will-change', 'max-height' );
					$css->add_property( 'max-height', 0 );
					$css->add_property( 'overflow', 'hidden' );
					$css->add_property( 'visibility', 'hidden' );

					$css->set_selector( $selector . '.gb-accordion__item-open > .gb-accordion__content' );
					$css->add_property( 'max-height', 'inherit' );
					$css->add_property( 'visibility', 'visible' );
				}
			}

			if ( 'accordion-toggle' === $settings['variantRole'] ) {
				$css->set_selector( $selector );
				$css->add_property( 'cursor', 'pointer' );
			}
		}
	}

	/**
	 * Inject our opening accordion content div.
	 *
	 * @param string   $content Block content.
	 * @param array    $attributes Block attributes.
	 * @param WP_Block $block Block instance.
	 */
	public function open_accordion_content_container( $content, $attributes, $block ) {
		$transition = isset( $block->context['generateblocks-pro/accordionTransition'] )
			? $block->context['generateblocks-pro/accordionTransition']
			: '';

		if ( isset( $attributes['variantRole'] ) && 'accordion-content' === $attributes['variantRole'] && 'slide' === $transition ) {
			if ( ! empty( $attributes['anchor'] ) ) {
				$content = '<div id="' . esc_attr( $attributes['anchor'] ) . '" class="gb-accordion__content">' . $content;
			} else {
				$content = '<div class="gb-accordion__content">' . $content;
			}
		}

		return $content;
	}

	/**
	 * Inject our closing accordion content div.
	 *
	 * @param string   $content Block content.
	 * @param array    $attributes Block attributes.
	 * @param WP_Block $block Block instance.
	 */
	public function close_accordion_content_container( $content, $attributes, $block ) {
		$transition = isset( $block->context['generateblocks-pro/accordionTransition'] )
			? $block->context['generateblocks-pro/accordionTransition']
			: '';

		if ( isset( $attributes['variantRole'] ) && 'accordion-content' === $attributes['variantRole'] && 'slide' === $transition ) {
			$content .= '</div>';
		}

		return $content;
	}

	/**
	 * Define the onboarding key for adding accordion items.
	 *
	 * @param array $properties The registered keys.
	 *
	 * @return array
	 */
	public function define_add_accordion_item_onboarding_property( $properties ) {
		$properties['add_accordion_item'] = array( 'type' => 'boolean' );

		return $properties;
	}

	/**
	 * Filter block args.
	 *
	 * @param array  $args Existing args.
	 * @param string $name The block name.
	 */
	public function block_type_args( $args, $name ) {
		if ( 'generateblocks/container' === $name ) {
			if ( ! isset( $args['provides_context'] ) || ! is_array( $args['provides_context'] ) ) {
				$args['provides_context'] = [];
			}

			$args['provides_context'] = array_merge(
				$args['provides_context'],
				[
					'generateblocks-pro/accordionTransition' => 'accordionTransition',
					'generateblocks-pro/faqSchema' => 'faqSchema',
				]
			);

			if ( ! isset( $args['uses_context'] ) || ! is_array( $args['uses_context'] ) ) {
				$args['uses_context'] = [];
			}

			$args['uses_context'] = array_merge(
				$args['uses_context'],
				[
					'generateblocks-pro/accordionTransition',
					'generateblocks-pro/faqSchema',
				]
			);
		}

		if ( 'generateblocks/button' === $name ) {
			if ( ! isset( $args['provides_context'] ) || ! is_array( $args['provides_context'] ) ) {
				$args['provides_context'] = [];
			}

			$args['provides_context'] = array_merge(
				$args['provides_context'],
				[
					'generateblocks-pro/faqSchema' => 'faqSchema',
				]
			);

			if ( ! isset( $args['uses_context'] ) || ! is_array( $args['uses_context'] ) ) {
				$args['uses_context'] = [];
			}

			$args['uses_context'] = array_merge(
				$args['uses_context'],
				[
					'generateblocks-pro/faqSchema',
				]
			);
		}

		return $args;
	}

	/**
	 * Remove all HTML and line-breaks/whitespace from the content.
	 *
	 * @param string $content The block content.
	 */
	public static function strip_html( $content ) {
		$allowed_tags = [ '<h1>', '<h2>', '<h3>', '<h4>', '<h5>', '<h6>', '<br>', '<ol>', '<ul>', '<li>', '<a>', '<p>', '<b>', '<strong>', '<i>', '<em>' ];
		$allowed_tags = implode( '', $allowed_tags );
		$block_text = strip_tags( $content, $allowed_tags );
		$block_text = preg_replace( "/\n/", '', $block_text ); // Remove new lines.

		return $block_text;
	}

	/**
	 * Gather FAQ schema from accordions.
	 *
	 * @since 1.6.0
	 * @param string   $block_content The block content.
	 * @param array    $block The block attributes/data.
	 * @param WP_Block $instance The block instance.
	 * @return array
	 */
	public function gather_schema_data( $block_content, $block, $instance ) {
		if (
			isset( $block['attrs']['variantRole'] ) &&
			isset( $instance->context ) &&
			! empty( $instance->context['generateblocks-pro/faqSchema'] )
		) {
			if ( 'accordion-toggle' === $block['attrs']['variantRole'] ) {
				add_filter(
					'generateblocks_faq_schema_data',
					function( $data ) use ( $block_content ) {
						$data[] = [ 'question' => self::strip_html( $block_content ) ];

						return $data;
					},
					1
				);
			}

			if ( 'accordion-content' === $block['attrs']['variantRole'] ) {
				add_filter(
					'generateblocks_faq_schema_data',
					function( $data ) use ( $block_content ) {
						foreach ( $data as $key => $info ) {
							// If we have a question but no answer, assume this is the answer.
							if ( ! empty( $info['question'] ) && empty( $info['answer'] ) ) {
								preg_match( '/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $block_content, $image );

								if ( ! empty( $image['src'] ) ) {
									$data[ $key ]['image'] = $image['src'];
								}

								$data[ $key ]['answer'] = self::strip_html( $block_content );
								break;
							}
						}

						return $data;
					},
					2
				);
			}
		}

		return $block_content;
	}

	/**
	 * Output the accordion FAQ schema to the footer.
	 *
	 * @since 1.6.0
	 */
	public function faq_schema_script() {
		$faq_schema_data = apply_filters(
			'generateblocks_faq_schema_data',
			[]
		);

		if ( empty( $faq_schema_data ) ) {
			return;
		}

		// Remove any duplicated questions/answers.
		// @see https://stackoverflow.com/a/308955.
		$serialized = array_map( 'json_encode', $faq_schema_data );
		$unique = array_unique( $serialized );
		$faq_schema_data = array_values( array_intersect_key( $faq_schema_data, $unique ) );

		$faq_schema = [
			'@context' => 'https://schema.org',
			'@type' => 'FAQPage',
			'name' => get_the_title(),
			'mainEntity' => [],
		];

		foreach ( $faq_schema_data as $data ) {
			$question = [
				'@type' => 'Question',
				'name' => $data['question'],
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text' => $data['answer'],
				],
			];

			if ( ! empty( $data['image'] ) ) {
				$question['acceptedAnswer']['image'] = [
					'@type' => 'ImageObject',
					'contentUrl' => esc_url_raw( $data['image'] ),
				];
			}

			$faq_schema['mainEntity'][] = $question;
		}

		if ( count( $faq_schema['mainEntity'] ) > 0 ) {
			printf( '<script type="application/ld+json">%s</script>', wp_json_encode( $faq_schema ) );
		}
	}
}

GenerateBlocks_Pro_Block_Variant_Accordion::get_instance();
