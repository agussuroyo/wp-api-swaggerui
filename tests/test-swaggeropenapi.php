<?php

class TestSwaggerOpenApi extends WP_UnitTestCase {

	public function test_spec20_passthrough() {
		$spec = array( 'swagger' => '2.0', 'paths' => array( '/x' => array() ) );
		$f    = new Spec20Formatter();
		$this->assertSame( $spec, $f->format( $spec ) );
		$this->assertEquals( '2.0', $f->version() );
	}

	public function test_registry_versions_contains_20() {
		$this->assertContains( '2.0', SwaggerSpecRegistry::versions() );
	}

	public function test_registry_forVersion_known_and_unknown() {
		$this->assertInstanceOf( Spec20Formatter::class, SwaggerSpecRegistry::forVersion( '2.0' ) );
		$this->assertInstanceOf( Spec20Formatter::class, SwaggerSpecRegistry::forVersion( 'bogus' ) );
	}
}
