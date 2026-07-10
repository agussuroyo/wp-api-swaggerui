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

	public function test_registry_has_303() {
		$this->assertContains( '3.0.3', SwaggerSpecRegistry::versions() );
		$this->assertInstanceOf( Spec30Formatter::class, SwaggerSpecRegistry::forVersion( '3.0.3' ) );
	}

	public function test_spec30_top_level() {
		$spec = array(
			'swagger'  => '2.0',
			'info'     => array( 'title' => 'T' ),
			'host'     => 'example.com',
			'basePath' => '/wp-json',
			'tags'     => array(),
			'schemes'  => array( 'https', 'http' ),
			'paths'    => array(),
		);
		$out = ( new Spec30Formatter() )->format( $spec );

		$this->assertEquals( '3.0.3', $out['openapi'] );
		$this->assertArrayNotHasKey( 'swagger', $out );
		$this->assertArrayNotHasKey( 'host', $out );
		$this->assertArrayNotHasKey( 'basePath', $out );
		$this->assertArrayNotHasKey( 'schemes', $out );
		$this->assertEquals( 'https://example.com/wp-json', $out['servers'][0]['url'] );
		$this->assertEquals( 'http://example.com/wp-json', $out['servers'][1]['url'] );
		$this->assertSame( array(), $out['tags'] );
		$this->assertEquals( array( 'title' => 'T' ), $out['info'] );
	}

	public function test_spec30_security_schemes() {
		$spec = array(
			'swagger'             => '2.0',
			'host'                => 'e.com',
			'basePath'            => '/wp-json',
			'schemes'             => array( 'https' ),
			'securityDefinitions' => array(
				'basic'  => array( 'type' => 'basic' ),
				'bearer' => array(
					'type'        => 'apiKey',
					'in'          => 'header',
					'name'        => 'Authorization',
					'description' => 'x',
				),
			),
		);
		$out = ( new Spec30Formatter() )->format( $spec );

		$this->assertArrayNotHasKey( 'securityDefinitions', $out );
		$this->assertEquals(
			array( 'type' => 'http', 'scheme' => 'basic' ),
			$out['components']['securitySchemes']['basic']
		);
		$this->assertEquals(
			array( 'type' => 'http', 'scheme' => 'bearer', 'description' => 'x' ),
			$out['components']['securitySchemes']['bearer']
		);
	}

	public function test_spec30_no_security_definitions() {
		$spec = array( 'swagger' => '2.0', 'host' => 'e.com', 'basePath' => '/wp-json', 'schemes' => array( 'https' ) );
		$out  = ( new Spec30Formatter() )->format( $spec );
		$this->assertArrayNotHasKey( 'components', $out );
	}
}
