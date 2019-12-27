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
			
			$user_pass = $server->get( 'REDIRECT_HTTP_AUTHORIZATION' );
			if ( $server->has( 'REDIRECT_HTTP_AUTHORIZATION' ) && ! empty( $user_pass )  ) {
				list($username, $password) = explode( ':', base64_decode( substr( $user_pass, 6 ) ) );
				$server->set( 'PHP_AUTH_USER', $username );
				$server->set( 'PHP_AUTH_PW', $password );
			} else {
				return $user_id;
			}
		}

		$username	 = $server->get( 'PHP_AUTH_USER' );
		$password	 = $server->get( 'PHP_AUTH_PW' );
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

		$auth['basic'] = array(
			'type' => 'basic'
		);

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
add_filter( 'authenticate', [ $basic, 'authenticate' ], 21, 3 );
add_filter( 'rest_authentication_errors', [ $basic, 'error' ] );
add_filter( 'swagger_api_security_definitions', [ $basic, 'appendSwaggerAuth' ] );

