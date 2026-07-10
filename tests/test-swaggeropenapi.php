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

	private function specWithParams( array $params, string $method = 'get' ): array {
		return array(
			'swagger'  => '2.0',
			'host'     => 'e.com',
			'basePath' => '/wp-json',
			'schemes'  => array( 'https' ),
			'paths'    => array(
				'/x' => array(
					$method => array(
						'tags'       => array( 'x' ),
						'consumes'   => array( 'application/x-www-form-urlencoded' ),
						'produces'   => array( 'application/json' ),
						'parameters' => $params,
						'responses'  => array( '200' => array( 'description' => 'OK' ) ),
					),
				),
			),
		);
	}

	private function firstOperation( array $out, string $method = 'get' ): array {
		return $out['paths']['/x'][ $method ];
	}

	private function firstParam( array $out ): array {
		return $this->firstOperation( $out )['parameters'][0];
	}

	public function test_spec30_drops_consumes_produces() {
		$op = $this->firstOperation( ( new Spec30Formatter() )->format( $this->specWithParams( array() ) ) );
		$this->assertArrayNotHasKey( 'consumes', $op );
		$this->assertArrayNotHasKey( 'produces', $op );
		$this->assertEquals( array( '200' => array( 'description' => 'OK' ) ), $op['responses'] );
	}

	public function test_spec30_scalar_param_to_schema() {
		$spec  = $this->specWithParams( array(
			array( 'name' => 'search', 'in' => 'query', 'description' => '', 'required' => false, 'type' => 'string', 'format' => 'x' ),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertArrayNotHasKey( 'type', $param );
		$this->assertArrayNotHasKey( 'format', $param );
		$this->assertEquals( array( 'type' => 'string', 'format' => 'x' ), $param['schema'] );
		$this->assertEquals( 'query', $param['in'] );
	}

	public function test_spec30_enum_array_param() {
		$spec  = $this->specWithParams( array(
			array(
				'name'             => 'status',
				'in'               => 'query',
				'required'         => false,
				'type'             => 'array',
				'items'            => array( 'type' => 'string', 'enum' => array( 'a', 'b' ), 'default' => 'a' ),
				'collectionFormat' => 'multi',
			),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertArrayNotHasKey( 'collectionFormat', $param );
		$this->assertEquals( 'form', $param['style'] );
		$this->assertTrue( $param['explode'] );
		$this->assertEquals( 'array', $param['schema']['type'] );
		$this->assertEquals(
			array( 'type' => 'string', 'enum' => array( 'a', 'b' ), 'default' => 'a' ),
			$param['schema']['items']
		);
	}

	public function test_spec30_preexisting_schema_not_clobbered() {
		$spec  = $this->specWithParams( array(
			array( 'name' => 'id', 'in' => 'path', 'required' => true, 'type' => 'integer', 'schema' => array( 'type' => 'integer', 'format' => 'int64' ) ),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertEquals( array( 'type' => 'integer', 'format' => 'int64' ), $param['schema'] );
	}

	public function test_spec30_formdata_to_requestbody() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'title', 'in' => 'formData', 'required' => true, 'type' => 'string' ),
			array( 'name' => 'excerpt', 'in' => 'formData', 'required' => false, 'type' => 'string' ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertArrayNotHasKey( 'parameters', $op );
		$schema = $op['requestBody']['content']['application/x-www-form-urlencoded']['schema'];
		$this->assertEquals( 'object', $schema['type'] );
		$this->assertEquals( array( 'type' => 'string' ), $schema['properties']['title'] );
		$this->assertEquals( array( 'type' => 'string' ), $schema['properties']['excerpt'] );
		$this->assertEquals( array( 'title' ), $schema['required'] );
	}

	public function test_spec30_body_param_to_json_requestbody() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'payload', 'in' => 'body', 'required' => true, 'schema' => array( 'type' => 'object' ) ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertArrayNotHasKey( 'parameters', $op );
		$this->assertEquals(
			array( 'type' => 'object' ),
			$op['requestBody']['content']['application/json']['schema']
		);
	}
}
