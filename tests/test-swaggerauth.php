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
	 * The auth handler, Woo key auth and Swagger security-definition filters are
	 * always registered, regardless of WP version.
	 */
	public function test_core_filters_registered() {
		$this->assertTrue( $this->hook_has_swaggerauth( 'determine_current_user' ) );
		$this->assertTrue( $this->hook_has_swaggerauth( 'authenticate' ) );
		$this->assertTrue( $this->hook_has_swaggerauth( 'swagger_api_security_definitions' ) );
	}

	/**
	 * On WP 5.6+ (the test env), the rest_authentication_errors reporter must NOT
	 * be registered: it would hard-block the REST API and shadow native
	 * Application Passwords. See #16.
	 */
	public function test_error_filter_not_registered_on_modern_wp() {
		global $wp_version;

		$this->assertTrue( version_compare( $wp_version, '5.6', '>=' ), 'Expected modern WP test env' );
		$this->assertFalse( $this->hook_has_swaggerauth( 'rest_authentication_errors' ) );
	}

	public function test_parse_basic_header_rejects_bearer() {
		$this->assertNull( SwaggerAuth::parseBasicHeader( 'Bearer eyJhbGciOi.payload.sig' ) );
	}

	public function test_parse_basic_header_rejects_non_string() {
		$this->assertNull( SwaggerAuth::parseBasicHeader( null ) );
	}

	public function test_parse_basic_header_parses_basic() {
		$value = 'Basic ' . base64_encode( 'alice:s3cr3t' );
		$this->assertSame( array( 'alice', 's3cr3t' ), SwaggerAuth::parseBasicHeader( $value ) );
	}

	public function test_parse_basic_header_rejects_malformed_base64() {
		$this->assertNull( SwaggerAuth::parseBasicHeader( 'Basic not-base64-no-colon' ) );
	}

}
