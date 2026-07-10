<?php

class TestSwaggerOpenApi extends WP_UnitTestCase {

	public function test_spec20_passthrough() {
		$spec = array( 'paths' => array( '/x' => array() ) );
		$f    = new Spec20Formatter();
		$out  = $f->format( $spec );
		$this->assertEquals( '2.0', $out['swagger'] );
		$this->assertEquals( array( '/x' => array() ), $out['paths'] );
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

	public function test_spec30_openapi_is_first_key() {
		$spec = array( 'info' => array( 'title' => 'T' ), 'paths' => array() );
		$out  = ( new Spec30Formatter() )->format( $spec );

		$this->assertEquals( 'openapi', array_key_first( $out ) );
	}

	public function test_spec30_top_level() {
		$spec = array(
			'info'     => array( 'title' => 'T' ),
			'host'     => 'example.com',
			'basePath' => '/wp-json',
			'tags'     => array(),
			'schemes'  => array( 'https', 'http' ),
			'paths'    => array(),
		);
		$out = ( new Spec30Formatter() )->format( $spec );

		$this->assertEquals( '3.0.3', $out['openapi'] );
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
			array(
				'type'        => 'http',
				'scheme'      => 'bearer',
				'description' => 'Enter your token; the "Bearer" prefix is added automatically.',
			),
			$out['components']['securitySchemes']['bearer']
		);
	}

	public function test_spec30_no_security_definitions() {
		$spec = array( 'host' => 'e.com', 'basePath' => '/wp-json', 'schemes' => array( 'https' ) );
		$out  = ( new Spec30Formatter() )->format( $spec );
		$this->assertArrayNotHasKey( 'components', $out );
	}

	public function test_spec30_non_array_security_definitions_no_crash() {
		$spec = array(
			'host'                => 'e.com',
			'basePath'            => '/wp-json',
			'schemes'             => array( 'https' ),
			'securityDefinitions' => 'x',
		);
		$out = ( new Spec30Formatter() )->format( $spec );

		$this->assertArrayNotHasKey( 'components', $out );
	}

	private function specWithParams( array $params, string $method = 'get', array $consumes = array( 'application/x-www-form-urlencoded' ) ): array {
		return array(
			'host'     => 'e.com',
			'basePath' => '/wp-json',
			'schemes'  => array( 'https' ),
			'paths'    => array(
				'/x' => array(
					$method => array(
						'tags'       => array( 'x' ),
						'consumes'   => $consumes,
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

	public function test_spec30_non_array_responses_no_crash() {
		$spec = array(
			'host'     => 'e.com',
			'basePath' => '/wp-json',
			'schemes'  => array( 'https' ),
			'paths'    => array(
				'/x' => array(
					'get' => array(
						'tags'      => array( 'x' ),
						'responses' => 'foo',
					),
				),
			),
		);
		$op = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertEquals( 'foo', $op['responses'] );
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

	public function test_spec30_csv_array_param_explode_false() {
		$spec  = $this->specWithParams( array(
			array( 'name' => 'author', 'in' => 'query', 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertEquals( 'form', $param['style'] );
		$this->assertFalse( $param['explode'] );
		$this->assertEquals( 'array', $param['schema']['type'] );
		$this->assertArrayNotHasKey( 'collectionFormat', $param );
	}

	public function test_spec30_array_without_items_gets_default() {
		$spec  = $this->specWithParams( array(
			array( 'name' => 'status', 'in' => 'query', 'required' => false, 'type' => 'array' ),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertEquals( array( 'type' => 'string' ), $param['schema']['items'] );
	}

	public function test_spec30_scalar_param_no_style() {
		$spec  = $this->specWithParams( array(
			array( 'name' => 'search', 'in' => 'query', 'required' => false, 'type' => 'string' ),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertArrayNotHasKey( 'style', $param );
		$this->assertArrayNotHasKey( 'explode', $param );
	}

	public function test_spec30_unknown_param_keyword_goes_to_schema() {
		$spec  = $this->specWithParams( array(
			array( 'name' => 'search', 'in' => 'query', 'required' => false, 'type' => 'string', 'pattern' => '^x' ),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertArrayNotHasKey( 'pattern', $param );
		$this->assertEquals( '^x', $param['schema']['pattern'] );
	}

	public function test_spec30_preexisting_schema_not_clobbered() {
		$spec  = $this->specWithParams( array(
			array( 'name' => 'id', 'in' => 'path', 'required' => true, 'type' => 'string', 'schema' => array( 'type' => 'integer', 'format' => 'int64' ) ),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertEquals( array( 'type' => 'integer', 'format' => 'int64' ), $param['schema'] );
	}

	public function test_spec30_formdata_to_requestbody() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'title', 'in' => 'formData', 'required' => true, 'description' => 'Post title', 'type' => 'string' ),
			array( 'name' => 'excerpt', 'in' => 'formData', 'required' => false, 'type' => 'string' ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertArrayNotHasKey( 'parameters', $op );
		$schema = $op['requestBody']['content']['application/x-www-form-urlencoded']['schema'];
		$this->assertEquals( 'object', $schema['type'] );
		$this->assertEquals( array( 'description' => 'Post title', 'type' => 'string' ), $schema['properties']['title'] );
		$this->assertEquals( array( 'type' => 'string' ), $schema['properties']['excerpt'] );
		$this->assertEquals( array( 'title' ), $schema['required'] );
	}

	public function test_spec30_formdata_nameless_param_skipped() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'title', 'in' => 'formData', 'required' => false, 'type' => 'string' ),
			array( 'in' => 'formData', 'required' => false, 'type' => 'string' ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$properties = $op['requestBody']['content']['application/x-www-form-urlencoded']['schema']['properties'];
		$this->assertEquals( array( 'title' ), array_keys( $properties ) );
		$this->assertArrayNotHasKey( '', $properties );
	}

	public function test_spec30_formdata_drops_collectionformat() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'status', 'in' => 'formData', 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ), 'collectionFormat' => 'multi' ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$property = $op['requestBody']['content']['application/x-www-form-urlencoded']['schema']['properties']['status'];
		$this->assertEquals(
			array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'open', 'closed' ) ) ),
			$property
		);
		$this->assertArrayNotHasKey( 'collectionFormat', $property );
	}

	public function test_spec30_formdata_csv_array_encoding() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'categories', 'in' => 'formData', 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$entry = $op['requestBody']['content']['application/x-www-form-urlencoded'];
		$this->assertEquals(
			array( 'style' => 'form', 'explode' => false ),
			$entry['encoding']['categories']
		);
		$this->assertEquals(
			array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			$entry['schema']['properties']['categories']
		);
	}

	public function test_spec30_formdata_multi_array_encoding() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'categories', 'in' => 'formData', 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'collectionFormat' => 'multi' ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$entry = $op['requestBody']['content']['application/x-www-form-urlencoded'];
		$this->assertEquals(
			array( 'style' => 'form', 'explode' => true ),
			$entry['encoding']['categories']
		);
	}

	public function test_spec30_formdata_scalar_no_encoding() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'title', 'in' => 'formData', 'required' => true, 'type' => 'string' ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$entry = $op['requestBody']['content']['application/x-www-form-urlencoded'];
		$this->assertArrayNotHasKey( 'encoding', $entry );
	}

	public function test_spec30_formdata_required_body() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'title', 'in' => 'formData', 'required' => true, 'type' => 'string' ),
			array( 'name' => 'excerpt', 'in' => 'formData', 'required' => false, 'type' => 'string' ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertTrue( $op['requestBody']['required'] );
		$this->assertEquals(
			array( 'title' ),
			$op['requestBody']['content']['application/x-www-form-urlencoded']['schema']['required']
		);
	}

	public function test_spec30_formdata_optional_body_no_required() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'excerpt', 'in' => 'formData', 'required' => false, 'type' => 'string' ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertArrayNotHasKey( 'required', $op['requestBody'] );
	}

	public function test_spec30_body_param_to_json_requestbody() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'payload', 'in' => 'body', 'required' => true, 'schema' => array( 'type' => 'object' ) ),
		), 'post', array( 'application/json' ) );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertArrayNotHasKey( 'parameters', $op );
		$this->assertEquals(
			array( 'type' => 'object' ),
			$op['requestBody']['content']['application/json']['schema']
		);
	}

	public function test_spec30_response_schema_to_content() {
		$spec = array(
			'host'     => 'e.com',
			'basePath' => '/wp-json',
			'schemes'  => array( 'https' ),
			'paths'    => array(
				'/x' => array(
					'get' => array(
						'produces'  => array( 'application/json' ),
						'responses' => array(
							'200' => array( 'description' => 'OK', 'schema' => array( 'type' => 'object' ) ),
						),
					),
				),
			),
		);
		$op = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertArrayNotHasKey( 'schema', $op['responses']['200'] );
		$this->assertEquals( array( 'type' => 'object' ), $op['responses']['200']['content']['application/json']['schema'] );
		$this->assertEquals( 'OK', $op['responses']['200']['description'] );
	}

	public function test_spec30_response_examples_to_content() {
		$spec = array(
			'host'     => 'e.com',
			'basePath' => '/wp-json',
			'schemes'  => array( 'https' ),
			'paths'    => array(
				'/x' => array(
					'get' => array(
						'produces'  => array( 'application/json' ),
						'responses' => array(
							'200' => array(
								'description' => 'OK',
								'schema'      => array( 'type' => 'object' ),
								'examples'    => array( 'application/json' => array( 'a' => 1 ) ),
							),
						),
					),
				),
			),
		);
		$op = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertArrayNotHasKey( 'schema', $op['responses']['200'] );
		$this->assertArrayNotHasKey( 'examples', $op['responses']['200'] );
		$this->assertEquals( array( 'type' => 'object' ), $op['responses']['200']['content']['application/json']['schema'] );
		$this->assertEquals( array( 'a' => 1 ), $op['responses']['200']['content']['application/json']['example'] );
	}

	public function test_spec30_description_only_response_unchanged() {
		$spec = array(
			'host'     => 'e.com',
			'basePath' => '/wp-json',
			'schemes'  => array( 'https' ),
			'paths'    => array(
				'/x' => array(
					'get' => array(
						'responses' => array( '200' => array( 'description' => 'OK' ) ),
					),
				),
			),
		);
		$op = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertEquals( array( '200' => array( 'description' => 'OK' ) ), $op['responses'] );
	}

	public function test_spec30_body_required_on_requestbody() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'payload', 'in' => 'body', 'required' => true, 'schema' => array( 'type' => 'object' ) ),
		), 'post', array( 'application/json' ) );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertTrue( $op['requestBody']['required'] );
		$this->assertEquals(
			array( 'type' => 'object' ),
			$op['requestBody']['content']['application/json']['schema']
		);
	}

	public function test_spec30_body_not_required_omits_requestbody_required() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'payload', 'in' => 'body', 'required' => false, 'schema' => array( 'type' => 'object' ) ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertArrayNotHasKey( 'required', $op['requestBody'] );
	}

	public function test_spec30_body_description_carried() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'payload', 'in' => 'body', 'required' => true, 'description' => 'Created user object', 'schema' => array( 'type' => 'object' ) ),
		), 'post', array( 'application/json' ) );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertEquals( 'Created user object', $op['requestBody']['description'] );
		$this->assertEquals(
			array( 'type' => 'object' ),
			$op['requestBody']['content']['application/json']['schema']
		);
	}

	public function test_spec30_body_no_description_omitted() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'payload', 'in' => 'body', 'required' => true, 'schema' => array( 'type' => 'object' ) ),
		), 'post' );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertArrayNotHasKey( 'description', $op['requestBody'] );
	}

	public function test_spec30_formdata_consumes_override() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'title', 'in' => 'formData', 'required' => true, 'type' => 'string' ),
		), 'post', array( 'application/xml' ) );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertArrayHasKey( 'application/xml', $op['requestBody']['content'] );
		$this->assertArrayNotHasKey( 'application/x-www-form-urlencoded', $op['requestBody']['content'] );
		$this->assertEquals(
			array( 'type' => 'string' ),
			$op['requestBody']['content']['application/xml']['schema']['properties']['title']
		);
	}

	public function test_spec30_formdata_encoding_only_on_form_media() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'categories', 'in' => 'formData', 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		), 'post', array( 'application/x-www-form-urlencoded', 'application/json' ) );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$content = $op['requestBody']['content'];
		$this->assertArrayHasKey( 'encoding', $content['application/x-www-form-urlencoded'] );
		$this->assertArrayNotHasKey( 'encoding', $content['application/json'] );
	}

	public function test_spec30_formdata_multipart_gets_encoding() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'categories', 'in' => 'formData', 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		), 'post', array( 'multipart/form-data' ) );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertArrayHasKey( 'encoding', $op['requestBody']['content']['multipart/form-data'] );
	}

	public function test_spec30_formdata_multiple_consumes() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'title', 'in' => 'formData', 'required' => true, 'type' => 'string' ),
		), 'post', array( 'application/x-www-form-urlencoded', 'multipart/form-data' ) );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$content = $op['requestBody']['content'];
		$this->assertArrayHasKey( 'application/x-www-form-urlencoded', $content );
		$this->assertArrayHasKey( 'multipart/form-data', $content );
		$this->assertEquals(
			$content['application/x-www-form-urlencoded']['schema'],
			$content['multipart/form-data']['schema']
		);
	}

	public function test_spec30_body_consumes_override() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'payload', 'in' => 'body', 'required' => true, 'schema' => array( 'type' => 'object' ) ),
		), 'post', array( 'application/xml' ) );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertEquals(
			array( 'type' => 'object' ),
			$op['requestBody']['content']['application/xml']['schema']
		);
	}

	public function test_spec30_file_param_to_binary() {
		$spec = $this->specWithParams( array(
			array( 'name' => 'file', 'in' => 'formData', 'required' => true, 'type' => 'file' ),
		), 'post', array( 'multipart/form-data' ) );
		$op   = $this->firstOperation( ( new Spec30Formatter() )->format( $spec ), 'post' );

		$this->assertEquals(
			array( 'type' => 'string', 'format' => 'binary' ),
			$op['requestBody']['content']['multipart/form-data']['schema']['properties']['file']
		);
	}

	public function test_spec30_path_array_param_simple_style() {
		$spec  = $this->specWithParams( array(
			array( 'name' => 'ids', 'in' => 'path', 'required' => true, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertEquals( 'simple', $param['style'] );
		$this->assertFalse( $param['explode'] );

		$spec  = $this->specWithParams( array(
			array( 'name' => 'ids', 'in' => 'header', 'required' => true, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertEquals( 'simple', $param['style'] );
		$this->assertFalse( $param['explode'] );
	}

	public function test_spec30_query_array_still_form() {
		$spec  = $this->specWithParams( array(
			array( 'name' => 'author', 'in' => 'query', 'required' => false, 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
		) );
		$param = $this->firstParam( ( new Spec30Formatter() )->format( $spec ) );

		$this->assertEquals( 'form', $param['style'] );
		$this->assertFalse( $param['explode'] );
	}

	public function test_spec30_oauth2_scheme_converted() {
		$spec = array(
			'host'                => 'e.com',
			'basePath'            => '/wp-json',
			'schemes'             => array( 'https' ),
			'securityDefinitions' => array(
				'oauth' => array(
					'type'             => 'oauth2',
					'flow'             => 'accessCode',
					'authorizationUrl' => 'https://ex/auth',
					'tokenUrl'         => 'https://ex/token',
					'scopes'           => array( 'read' => 'Read' ),
				),
			),
		);
		$out = ( new Spec30Formatter() )->format( $spec );

		$this->assertEquals(
			array(
				'type'  => 'oauth2',
				'flows' => array(
					'authorizationCode' => array(
						'authorizationUrl' => 'https://ex/auth',
						'tokenUrl'         => 'https://ex/token',
						'scopes'           => array( 'read' => 'Read' ),
					),
				),
			),
			$out['components']['securitySchemes']['oauth']
		);
	}

	public function test_spec30_oauth2_empty_scopes_is_object() {
		$spec = array(
			'host'                => 'e.com',
			'basePath'            => '/wp-json',
			'schemes'             => array( 'https' ),
			'securityDefinitions' => array(
				'oauth' => array(
					'type'             => 'oauth2',
					'flow'             => 'implicit',
					'authorizationUrl' => 'https://ex/auth',
				),
			),
		);
		$out = ( new Spec30Formatter() )->format( $spec );

		$scopes = $out['components']['securitySchemes']['oauth']['flows']['implicit']['scopes'];
		$this->assertInstanceOf( \stdClass::class, $scopes );
		$this->assertStringContainsString( '"scopes":{}', wp_json_encode( $out['components']['securitySchemes']['oauth'] ) );
	}

	public function test_spec30_bearer_keyed_oauth2_converts() {
		$spec = array(
			'host'                => 'e.com',
			'basePath'            => '/wp-json',
			'schemes'             => array( 'https' ),
			'securityDefinitions' => array(
				'bearer' => array(
					'type'             => 'oauth2',
					'flow'             => 'implicit',
					'authorizationUrl' => 'https://x/a',
				),
			),
		);
		$out = ( new Spec30Formatter() )->format( $spec );

		$scheme = $out['components']['securitySchemes']['bearer'];
		$this->assertEquals( 'oauth2', $scheme['type'] );
		$this->assertEquals( 'https://x/a', $scheme['flows']['implicit']['authorizationUrl'] );
		$this->assertInstanceOf( \stdClass::class, $scheme['flows']['implicit']['scopes'] );
	}

	public function test_spec30_unknown_apikey_scheme_passthrough() {
		$spec = array(
			'host'                => 'e.com',
			'basePath'            => '/wp-json',
			'schemes'             => array( 'https' ),
			'securityDefinitions' => array(
				'x' => array( 'type' => 'apiKey', 'in' => 'header', 'name' => 'X-Key' ),
			),
		);
		$out = ( new Spec30Formatter() )->format( $spec );

		$this->assertEquals(
			array( 'type' => 'apiKey', 'in' => 'header', 'name' => 'X-Key' ),
			$out['components']['securitySchemes']['x']
		);
	}

	public function test_setting_save_whitelists_version() {
		if ( ! class_exists( 'SwaggerSetting' ) ) {
			require_once dirname( __DIR__ ) . '/swaggersetting.php';
		}

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$setting = new SwaggerSetting();

		$_POST['_wpnonce']                 = wp_create_nonce( 'swagger_api_setting' );
		$_POST['swagger_api_spec_version'] = '3.0.3';
		$setting->saveSetting();
		$this->assertEquals( '3.0.3', get_option( 'swagger_api_spec_version' ) );

		$_POST['swagger_api_spec_version'] = 'bogus';
		$setting->saveSetting();
		$this->assertEquals( '3.0.3', get_option( 'swagger_api_spec_version' ), 'invalid value must be rejected' );

		unset( $_POST['_wpnonce'], $_POST['swagger_api_spec_version'] );
	}
}
