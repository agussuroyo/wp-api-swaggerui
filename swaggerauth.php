<?php

class SwaggerAuth {

	private $error = null;

	public function handler( $user_id ) {
		// Don't authenticate twice
		if ( ! empty( $user_id ) ) {
			return $user_id;
		}

		$server = new SwaggerBag( $_SERVER );

		// Check that we're trying to authenticate
		if ( ! $server->has( 'PHP_AUTH_USER' ) ) {

			$creds = self::parseBasicHeader( $server->get( 'REDIRECT_HTTP_AUTHORIZATION' ) );
			if ( $creds === null ) {
				return $user_id;
			}
			$server->set( 'PHP_AUTH_USER', $creds[0] );
			$server->set( 'PHP_AUTH_PW', $creds[1] );
		}

		$username	 = $server->get( 'PHP_AUTH_USER' );
		$password	 = $server->get( 'PHP_AUTH_PW' );

		// WP 5.6+ owns generic Basic Auth via Application Passwords; only handle
		// WooCommerce consumer keys here so we neither shadow it nor fail-auth
		// server Basic credentials (see #16).
		global $wp_version;
		if ( version_compare( $wp_version, '5.6', '>=' )
			&& ( ! class_exists( 'woocommerce' ) || strpos( (string) $username, 'ck_' ) !== 0 ) ) {
			return $user_id;
		}

		/**
		 * In multi-site, wp_authenticate_spam_check filter is run on authentication. This filter calls
		 * get_currentuserinfo which in turn calls the determine_current_user filter. This leads to infinite
		 * recursion and a stack overflow unless the current function is removed from the determine_current_user
		 * filter during authentication.
		 */
		remove_filter( 'determine_current_user', [ $this, 'handler' ], 14 );

		$user = wp_authenticate( $username, $password );

		add_filter( 'determine_current_user', [ $this, 'handler' ], 14 );

		if ( is_wp_error( $user ) ) {
			$this->error = $user;
			return null;
		}

		$this->error = true;

		return $user->ID;
	}

	/**
	 * Parse a Basic auth header into [username, password].
	 * Returns null for non-Basic values (e.g. Bearer), so a Bearer token in
	 * REDIRECT_HTTP_AUTHORIZATION is never mis-decoded into fake credentials.
	 * public + static so the suite can test it without reflection.
	 */
	public static function parseBasicHeader( $value ) {
		if ( ! is_string( $value ) || stripos( $value, 'Basic ' ) !== 0 ) {
			return null;
		}

		$decoded = base64_decode( substr( $value, 6 ), true );
		if ( $decoded === false || strpos( $decoded, ':' ) === false ) {
			return null;
		}

		return explode( ':', $decoded, 2 );
	}

	public function error( $error ) {

		if ( ! empty( $error ) ) {
			return $error;
		}

		return $this->error;
	}

	public function appendSwaggerAuth( $auth ) {
		if ( ! is_array( $auth ) ) {
			$auth = [];
		}

		$schemes = (array) get_option( 'swagger_api_auth_schemes', array( 'basic' ) );

		if ( in_array( 'basic', $schemes, true ) ) {
			$auth['basic'] = array(
				'type' => 'basic',
			);
		}

		if ( in_array( 'bearer', $schemes, true ) ) {
			// ponytail: Swagger 2.0 has no native bearer type — apiKey-in-header
			// named Authorization is the standard representation. We only emit
			// the definition so Swagger UI sends the header; validating the
			// token is the site's JWT plugin's job, not ours.
			$auth['bearer'] = array(
				'type'        => 'apiKey',
				'name'        => 'Authorization',
				'in'          => 'header',
				'description' => 'Enter your token as: `Bearer <token>`',
			);
		}

		return $auth;
	}

	private function getUserDataByConsumerKey( $consumer_key ) {
	    global $wpdb;

	    $consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );
	    return $wpdb->get_row( $wpdb->prepare( "SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s LIMIT 1", $consumer_key ) );
    }

    public function authenticate( $user, $username, $password ) {

	    if ( ! ( $user instanceof WP_User ) &&  class_exists( 'woocommerce' ) ) {
	        $u = $this->getUserDataByConsumerKey( $username );
	        if ( ! empty( $u ) && hash_equals( $u->consumer_secret, $password ) ) {
                $user = get_user_by( 'ID', $u->user_id );
            }
        }

	    return $user;
    }

}

$basic = new SwaggerAuth();

add_filter( 'determine_current_user', [ $basic, 'handler' ], 14 );
// Priority 19 runs before core's wp_authenticate_application_password (20) so a
// WooCommerce key resolves to a user first, preventing core from recording an
// invalid-application-password error for the ck_ username. See #16.
add_filter( 'authenticate', [ $basic, 'authenticate' ], 19, 3 );

// Pre-5.6 has no Application Passwords, so surface Basic Auth failures as REST
// errors. On 5.6+ this hard-blocks the REST API (breaks Elementor, App Passwords),
// so leave it to WordPress core. See #16.
global $wp_version;
if ( version_compare( $wp_version, '5.6', '<' ) ) {
	add_filter( 'rest_authentication_errors', [ $basic, 'error' ] );
}

add_filter( 'swagger_api_security_definitions', [ $basic, 'appendSwaggerAuth' ] );

