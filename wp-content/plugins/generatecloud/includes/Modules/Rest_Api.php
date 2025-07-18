<?php
/**
 * The generative REST API endpoints for the proxy server.
 *
 * @package GenerateCloud
 */

namespace GenerateCloud\Modules;

use GenerateCloud\Modules\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Public Keys post type.
 *
 * @since 1.0.0
 */
class Rest_Api extends Module {
	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'generatecloud/v';

	/**
	 * Version.
	 *
	 * @var string
	 */
	protected $version = '1';

	/**
	 * Load the module.
	 */
	public function load(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register rest routes.
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		// Get list of Google fonts from the API or cached resource.
		register_rest_route(
			$namespace,
			'/save-license-key/',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'save_license_key' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Save the license key.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function save_license_key( $request ) {
		$new_license_data = $request->get_param( 'license' );
		$license_settings = get_option( 'generatecloud_licensing', [] );
		$old_license      = $license_settings['key'] ?? '';
		$old_status       = $license_settings['status'] ?? '';
		$new_license      = trim( $new_license_data['key'] );

		if ( $new_license ) {
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => sanitize_key( $new_license ),
				'item_name'  => rawurlencode( 'GenerateCloud' ),
				'url'        => home_url(),
			);
		} elseif ( $old_license && 'valid' === $old_status ) {
			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => sanitize_key( $old_license ),
				'item_name'  => rawurlencode( 'GenerateCloud' ),
				'url'        => home_url(),
			);
		}

		if ( isset( $api_params ) ) {
			$response = wp_remote_post(
				'https://generatepress.com',
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params,
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				if ( is_wp_error( $response ) ) {
					return $this->failed( $response->get_error_message() );
				} else {
					$message = __( 'An error occurred, please try again.', 'generatecloud' );
				}
			} else {
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );

				if ( false === $license_data->success ) {
					switch ( $license_data->error ) {
						case 'expired':
							$message = sprintf(
								/* translators: License key expiration date. */
								__( 'Your license key expired on %s.', 'generatecloud' ),
								date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) ) // phpcs:ignore
							);
							break;

						case 'disabled':
						case 'revoked':
							$message = __( 'Your license key has been disabled.', 'generatecloud' );
							break;

						case 'missing':
							$message = __( 'Invalid license.', 'generatecloud' );
							break;

						case 'invalid':
						case 'site_inactive':
							$message = __( 'Your license is not active for this URL.', 'generatecloud' );
							break;

						case 'item_name_mismatch':
							$message = __( 'This appears to be an invalid license key for GenerateCloud.', 'generatecloud' );
							break;

						case 'no_activations_left':
							$message = __( 'Your license key has reached its activation limit.', 'generatecloud' );
							break;

						default:
							$message = __( 'An error occurred, please try again.', 'generatecloud' );
							break;
					}
				}
			}

			$new_settings['status'] = esc_attr( $license_data->license );
		}

		$new_settings['key'] = sanitize_key( $new_license );

		if ( is_array( $new_settings ) ) {
			update_option( 'generatecloud_licensing', array_merge( $license_settings, $new_settings ) );

			if ( ! isset( $api_params ) ) {
				return $this->success( $license_data );
			}
		}

		if ( ! empty( $message ) ) {
			return $this->failed( $message );
		}

		return $this->success( $license_data );
	}

	/**
	 * Success rest.
	 *
	 * @param mixed $response response data.
	 * @return mixed
	 */
	public function success( $response ) {
		return new \WP_REST_Response(
			array(
				'success'  => true,
				'response' => $response,
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
		return new \WP_REST_Response(
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
		return new \WP_REST_Response(
			array(
				'error'      => true,
				'success'    => false,
				'error_code' => $code,
				'response'   => $response,
			),
			401
		);
	}
}
