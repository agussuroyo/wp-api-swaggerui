<?php

class TestSwaggerAuth extends WP_UnitTestCase {

	/**
	 * True when the given hook has any callback bound to a SwaggerAuth instance.
	 * Avoids relying on the $basic global, which is not exposed in the test env.
	 */
	private function hook_has_swaggerauth( $hook ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) ) {
			return false;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $priority ) {
			foreach ( $priority as $cb ) {
				if ( is_array( $cb['function'] ) && $cb['function'][0] instanceof SwaggerAuth ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * The test environment runs a modern WP (>= 5.6), so the Basic Auth
	 * handler that collides with native Application Passwords (#16) must NOT
	 * be registered.
	 */
	public function test_handler_not_registered_on_modern_wp() {
		global $wp_version;

		$this->assertTrue( version_compare( $wp_version, '5.6', '>=' ), 'Expected modern WP test env' );

		$this->assertFalse( $this->hook_has_swaggerauth( 'determine_current_user' ) );
		$this->assertFalse( $this->hook_has_swaggerauth( 'rest_authentication_errors' ) );
	}

	/**
	 * The Woo key auth and Swagger security-definition filters are always
	 * registered, regardless of WP version.
	 */
	public function test_always_on_filters_registered() {
		$this->assertTrue( $this->hook_has_swaggerauth( 'authenticate' ) );
		$this->assertTrue( $this->hook_has_swaggerauth( 'swagger_api_security_definitions' ) );
	}

}
