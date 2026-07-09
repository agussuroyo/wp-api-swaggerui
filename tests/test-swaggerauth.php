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

	public function test_default_emits_only_basic() {
		delete_option( 'swagger_api_auth_schemes' );
		$defs = ( new SwaggerAuth() )->appendSwaggerAuth( array() );
		$this->assertArrayHasKey( 'basic', $defs );
		$this->assertSame( 'basic', $defs['basic']['type'] );
		$this->assertArrayNotHasKey( 'bearer', $defs );
	}

	public function test_both_schemes_emit_both() {
		update_option( 'swagger_api_auth_schemes', array( 'basic', 'bearer' ) );
		$defs = ( new SwaggerAuth() )->appendSwaggerAuth( array() );
		$this->assertSame( 'basic', $defs['basic']['type'] );
		$this->assertSame( 'apiKey', $defs['bearer']['type'] );
		$this->assertSame( 'Authorization', $defs['bearer']['name'] );
		$this->assertSame( 'header', $defs['bearer']['in'] );
	}

	public function test_bearer_only() {
		update_option( 'swagger_api_auth_schemes', array( 'bearer' ) );
		$defs = ( new SwaggerAuth() )->appendSwaggerAuth( array() );
		$this->assertArrayNotHasKey( 'basic', $defs );
		$this->assertArrayHasKey( 'bearer', $defs );
	}

}
