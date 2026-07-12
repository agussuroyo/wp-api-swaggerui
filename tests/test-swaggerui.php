<?php

class TestSwaggerUI extends WP_UnitTestCase {

	public $ui = null;

	public function set_up() {

		$this->ui = new WP_API_SwaggerUI();

		parent::set_up();
	}

	public function test_rewriteBaseApi() {
		$this->assertEquals( 'rest-api', WP_API_SwaggerUI::rewriteBaseApi() );
	}

	public function test_getHost() {

		$host = $this->ui->getHost();

		$this->assertStringNotContainsString( 'http://', $host );
		$this->assertStringNotContainsString( 'https://', $host );
	}

	public function test_getSchemes() {
		$schemes = $this->ui->getSchemes();

		$this->assertTrue(  ! empty( $schemes ) );
		$this->assertTrue( is_array( $schemes ) );

		$this->assertContains( 'http', $schemes );
	}

	public function test_getNameSpace() {
		$namespace = WP_API_SwaggerUI::getNameSpace();

		$this->assertEquals( '/wp/v2', $namespace );
	}

	public function test_getRawPaths() {
		$this->assertTrue( is_array( $this->ui->getRawPaths() ) );
	}

	public function test_getPaths() {
		$this->assertTrue( is_array( $this->ui->getPaths() ) );
	}

	public function test_convertEndpoint() {
		$this->assertEquals( '/sample/endpoint/{sample_id}', $this->ui->convertEndpoint( '/sample/endpoint/(?P<sample_id>)' ) );
        $this->assertEquals( '/other/{other_id}/edit', $this->ui->convertEndpoint( '/other/(?P<other_id>[^.\/]+(?:\/[^.\/]+)?)/edit' ) );
        // Multiple params in one route must each convert, not collapse into the first.
        $this->assertEquals( '/parent/{parent_id}/child/{child_id}', $this->ui->convertEndpoint( '/parent/(?P<parent_id>[\d]+)/child/(?P<child_id>[\d]+)' ) );
        // A parameter pattern may nest parens to any depth.
        $this->assertEquals( '/x/{slug}', $this->ui->convertEndpoint( '/x/(?P<slug>[a-z]+(?:-[a-z]+(?:-[a-z]+)?)?)' ) );
        // A literal ')' inside a character class must not end the group early.
        $this->assertEquals( '/x/{slug}/y', $this->ui->convertEndpoint( '/x/(?P<slug>[^/)]+)/y' ) );
	}

	public function test_getDefaultTagsFromEndpoint() {
		$this->assertEquals( [ 'posts' ], $this->ui->getDefaultTagsFromEndpoint( '/wp/v2/posts' ) );
		// A leading named param must not become the tag.
		$this->assertEquals( [ 'revisions' ], $this->ui->getDefaultTagsFromEndpoint( '/wp/v2/(?P<parent>[\d]+)/revisions' ) );
	}

	public function test_getParametersFromEndpoint() {
		$params = $this->ui->getParametersFromEndpoint( '/parent/(?P<parent_id>[\d]+)/child/(?P<child_slug>[\w]+)' );

		$this->assertCount( 2, $params );
		$this->assertArrayHasKey( 'parent_id', $params );
		$this->assertArrayHasKey( 'child_slug', $params );
		$this->assertEquals( 'integer', $params['parent_id']['type'] );
		$this->assertEquals( 'string', $params['child_slug']['type'] );
	}

    public function test_detectIn() {
		$this->assertEquals( 'path', $this->ui->detectIn( 'id', 'get', '/sample/{id}', null ) );
		$this->assertEquals( 'query', $this->ui->detectIn( 'other_id', 'get', '/sample/{id}', null ) );
		$this->assertEquals( 'formData', $this->ui->detectIn( 'firstname', 'post', '/sample/{id}', null ) );
	}

	public function test_buildParams() {
		$params = $this->ui->buildParams( 'name', 'get', '/sample/{id}', array(
			'type'			 => 'string',
			'required'		 => true,
			'description'	 => 'Sample Description'
				) );

		$this->assertArrayHasKey( 'name', $params );
		$this->assertArrayHasKey( 'in', $params );
		$this->assertArrayHasKey( 'description', $params );
		$this->assertArrayHasKey( 'required', $params );
		$this->assertArrayHasKey( 'type', $params );
	}

	public function test_buildParams_preserves_nested_array_object_schema() {
		$params = $this->ui->buildParams( 'data', 'post', '/sample', array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'action' => array( 'type' => 'enum', 'enum' => array( 'add', 'update' ), 'required' => true ),
					'dates'  => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'format' => 'date-time' ) ),
				),
			),
		) );

		$this->assertEquals( 'object', $params['items']['type'] );
		$this->assertEquals( 'string', $params['items']['properties']['action']['type'] );
		$this->assertEquals( array( 'add', 'update' ), $params['items']['properties']['action']['enum'] );
		$this->assertEquals( array( 'action' ), $params['items']['required'] );
		$this->assertEquals( 'date-time', $params['items']['properties']['dates']['items']['format'] );
		$this->assertArrayNotHasKey( 'required', $params['items']['properties']['action'] );
	}

	public function test_json_route_combines_registered_arguments_into_body_schema() {
		$args = array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array(
				'title' => array( 'type' => 'string', 'required' => true ),
				'data'  => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'action'             => array( 'type' => 'string', 'enum' => array( 'add', 'update' ) ),
							'date_created'       => array( 'type' => 'string' ),
							'date_last_modified' => array( 'type' => 'string' ),
						),
					),
				),
			),
		));

		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', $args )['post'];
		$this->assertCount( 1, $operation['parameters'] );
		$this->assertEquals( 'body', $operation['parameters'][0]['in'] );
		$this->assertEquals( array( 'title' ), $operation['parameters'][0]['schema']['required'] );
		$this->assertArrayHasKey( 'action', $operation['parameters'][0]['schema']['properties']['data']['items']['properties'] );

		$openapi = ( new Spec30Formatter() )->format(array(
			'host'     => 'example.org',
			'basePath' => '/wp-json',
			'schemes'  => array( 'https' ),
			'paths'    => array( '/sample' => array( 'post' => $operation ) ),
		));
		$request_schema = $openapi['paths']['/sample']['post']['requestBody']['content']['application/json']['schema'];
		$this->assertArrayHasKey( 'date_created', $request_schema['properties']['data']['items']['properties'] );
		$this->assertArrayHasKey( 'date_last_modified', $request_schema['properties']['data']['items']['properties'] );
	}

	public function test_non_json_scalar_parameters_remain_form_data() {
		$params = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => false,
			'args'        => array( 'title' => array( 'type' => 'string' ) ),
		)) )['post']['parameters'];

		$this->assertEquals( 'formData', $params[0]['in'] );
		$this->assertEquals( 'string', $params[0]['type'] );
	}

	public function test_object_body_param_keeps_required_subproperties() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array(
				'address' => array(
					'type'       => 'object',
					'properties' => array(
						'street' => array( 'type' => 'string', 'required' => true ),
						'zip'    => array( 'type' => 'string' ),
					),
				),
			),
		)) )['post'];

		$schema = $operation['parameters'][0]['schema'];
		$this->assertEquals( array( 'street' ), $schema['properties']['address']['required'] );
		$this->assertArrayNotHasKey( 'required', $schema['properties']['address']['properties']['street'] );
		$this->assertArrayNotHasKey( 'required', $schema );
	}

	public function test_required_object_param_stays_in_body_required() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array(
				'address' => array(
					'type'       => 'object',
					'required'   => true,
					'properties' => array(
						'street' => array( 'type' => 'string', 'required' => true ),
					),
				),
			),
		)) )['post'];

		$schema = $operation['parameters'][0]['schema'];
		$this->assertEquals( array( 'address' ), $schema['required'] );
		$this->assertEquals( array( 'street' ), $schema['properties']['address']['required'] );
		$this->assertArrayNotHasKey( 'objectRequired', $schema['properties']['address'] );
	}

	public function test_array_enum_places_choices_in_items() {
		$params = $this->ui->buildParams( 'kinds', 'get', '/sample', array(
			'type' => 'array',
			'enum' => array( 'a', 'b', 'c' ),
		) );

		$this->assertEquals( 'array', $params['type'] );
		$this->assertEquals( array( 'a', 'b', 'c' ), $params['items']['enum'] );
		$this->assertArrayNotHasKey( 'enum', $params );
		$this->assertEquals( 'multi', $params['collectionFormat'] );
	}

	public function test_typeless_properties_infer_object_schema() {
		$schema = $this->ui->normalizeSchema( array(
			'properties' => array( 'a' => array( 'type' => 'string' ) ),
		) );

		$this->assertEquals( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'a', $schema['properties'] );
	}

	public function test_normalizeSchema_preserves_additional_properties() {
		$schema = $this->ui->normalizeSchema( array(
			'type'                 => 'object',
			'additionalProperties' => array( 'type' => 'string' ),
		) );

		$this->assertEquals( array( 'type' => 'string' ), $schema['additionalProperties'] );
	}

	public function test_typeless_properties_survive_route_generation() {
		$params = $this->ui->getParametersFromArgs( '/sample', array(
			'config' => array( 'properties' => array( 'a' => array( 'type' => 'string' ) ) ),
		), array( 'POST' => true ) )['post'];

		$this->assertEquals( 'object', $params[0]['type'] );
		$this->assertArrayHasKey( 'a', $params[0]['properties'] );
	}

	public function test_json_put_route_builds_body_parameter() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'PUT' => true ),
			'accept_json' => true,
			'args'        => array( 'title' => array( 'type' => 'string', 'required' => true ) ),
		)) )['put'];

		$this->assertEquals( 'body', $operation['parameters'][0]['in'] );
		$this->assertArrayHasKey( 'title', $operation['parameters'][0]['schema']['properties'] );
		$this->assertEquals( array( 'application/json' ), $operation['consumes'] );
	}

	public function test_object_level_required_array_is_preserved() {
		$schema = $this->ui->normalizeSchema( array(
			'type'       => 'object',
			'required'   => array( 'street' ),
			'properties' => array(
				'street' => array( 'type' => 'string' ),
				'zip'    => array( 'type' => 'string' ),
			),
		) );

		$this->assertEquals( array( 'street' ), $schema['required'] );
	}

	public function test_object_level_required_array_not_treated_as_field_required() {
		$params = $this->ui->buildParams( 'address', 'post', '/sample', array(
			'type'       => 'object',
			'required'   => array( 'street' ),
			'properties' => array( 'street' => array( 'type' => 'string' ) ),
		) );

		$this->assertFalse( $params['required'] );
		$this->assertEquals( array( 'street' ), $params['objectRequired'] );
	}

	public function test_additional_properties_schema_is_normalized() {
		$schema = $this->ui->normalizeSchema( array(
			'type'                 => 'object',
			'additionalProperties' => array( 'type' => 'enum', 'enum' => array( 'a', 'b' ) ),
		) );

		$this->assertEquals( 'string', $schema['additionalProperties']['type'] );
		$this->assertEquals( array( 'a', 'b' ), $schema['additionalProperties']['enum'] );
	}

	public function test_swagger2_invalid_keywords_are_dropped() {
		$schema = $this->ui->normalizeSchema( array(
			'type'  => 'string',
			'oneOf' => array( array( 'type' => 'string' ) ),
			'const' => 'x',
		) );

		$this->assertArrayNotHasKey( 'oneOf', $schema );
		$this->assertArrayNotHasKey( 'const', $schema );
	}

	public function test_non_body_array_of_object_strips_incompatible_keywords() {
		$params = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'GET' => true ),
			'accept_json' => false,
			'args'        => array( 'filters' => array(
				'type'     => 'array',
				'minItems' => 1,
				'items'    => array( 'type' => 'object', 'properties' => array( 'k' => array( 'type' => 'string' ) ) ),
			) ),
		)) )['get']['parameters'];

		$this->assertEquals( 'string', $params[0]['type'] );
		$this->assertArrayNotHasKey( 'items', $params[0] );
		$this->assertArrayNotHasKey( 'minItems', $params[0] );
	}

	public function test_json_get_object_query_param_is_downgraded() {
		$params = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'GET' => true ),
			'accept_json' => true,
			'args'        => array( 'filter' => array( 'type' => 'object', 'properties' => array( 'a' => array( 'type' => 'string' ) ) ) ),
		)) )['get']['parameters'];

		$this->assertEquals( 'query', $params[0]['in'] );
		$this->assertEquals( 'string', $params[0]['type'] );
		$this->assertArrayNotHasKey( 'properties', $params[0] );
	}

	public function test_explicit_and_inferred_body_merge_into_one() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array(
				'payload' => array( 'in' => 'body', 'schema' => array( 'type' => 'object', 'properties' => array( 'x' => array( 'type' => 'string' ) ) ) ),
				'title'   => array( 'type' => 'string', 'required' => true ),
			),
		)) )['post'];

		$bodies = array_values( array_filter( $operation['parameters'], function ( $p ) {
			return isset( $p['in'] ) && 'body' === $p['in'];
		} ) );
		$this->assertCount( 1, $bodies );
		$this->assertArrayHasKey( 'x', $bodies[0]['schema']['properties'] );
		$this->assertArrayHasKey( 'title', $bodies[0]['schema']['properties'] );
		$this->assertEquals( array( 'title' ), $bodies[0]['schema']['required'] );
	}

	public function test_explicit_body_param_carries_only_schema() {
		$params = $this->ui->buildParams( 'payload', 'post', '/sample', array(
			'in'     => 'body',
			'schema' => array( 'type' => 'object', 'properties' => array( 'x' => array( 'type' => 'enum', 'enum' => array( 'a' ) ) ) ),
		) );

		$this->assertEquals( 'body', $params['in'] );
		$this->assertArrayHasKey( 'schema', $params );
		$this->assertArrayNotHasKey( 'type', $params );
		$this->assertArrayNotHasKey( 'items', $params );
		$this->assertEquals( 'string', $params['schema']['properties']['x']['type'] );
	}

	public function test_json_route_with_file_keeps_form_data() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array( 'file' => array( 'type' => 'file' ) ),
		)) )['post'];

		$this->assertEquals( 'formData', $operation['parameters'][0]['in'] );
		$this->assertEquals( 'file', $operation['parameters'][0]['type'] );
		$this->assertContains( 'multipart/form-data', $operation['consumes'] );
	}

	public function test_ref_schema_is_preserved() {
		$this->assertEquals(
			array( '$ref' => '#/definitions/Thing' ),
			$this->ui->normalizeSchema( array( '$ref' => '#/definitions/Thing' ) )
		);
		$nested = $this->ui->normalizeSchema( array( 'type' => 'array', 'items' => array( '$ref' => '#/definitions/Thing' ) ) );
		$this->assertEquals( '#/definitions/Thing', $nested['items']['$ref'] );
	}

	public function test_composition_only_schema_has_no_string_type() {
		$schema = $this->ui->normalizeSchema( array(
			'allOf' => array( array( 'type' => 'object', 'properties' => array( 'a' => array( 'type' => 'string' ) ) ) ),
		) );

		$this->assertArrayNotHasKey( 'type', $schema );
		$this->assertArrayHasKey( 'allOf', $schema );
	}

	public function test_union_type_with_null_selects_real_type() {
		$object = $this->ui->normalizeSchema( array( 'type' => array( 'null', 'object' ), 'properties' => array( 'a' => array( 'type' => 'string' ) ) ) );
		$this->assertEquals( 'object', $object['type'] );

		$string = $this->ui->normalizeSchema( array( 'type' => array( 'string', 'null' ) ) );
		$this->assertEquals( 'string', $string['type'] );
	}

	public function test_empty_additional_properties_stays_open() {
		$schema = $this->ui->normalizeSchema( array( 'type' => 'object', 'additionalProperties' => array() ) );
		$this->assertTrue( $schema['additionalProperties'] );
	}

	public function test_required_preserved_without_direct_properties() {
		$schema = $this->ui->normalizeSchema( array(
			'type'     => 'object',
			'required' => array( 'street' ),
			'allOf'    => array( array( 'properties' => array( 'street' => array( 'type' => 'string' ) ) ) ),
		) );

		$this->assertEquals( array( 'street' ), $schema['required'] );
	}

	public function test_typeless_id_argument_infers_integer() {
		$params = $this->ui->getParametersFromArgs( '/sample', array(
			'id'      => array(),
			'post_id' => array(),
			'title'   => array(),
		), array( 'GET' => true ) )['get'];

		$by_name = array();
		foreach ( $params as $param ) {
			$by_name[ $param['name'] ] = $param;
		}
		$this->assertEquals( 'integer', $by_name['id']['type'] );
		$this->assertEquals( 'integer', $by_name['post_id']['type'] );
		$this->assertEquals( 'string', $by_name['title']['type'] );
	}

	public function test_non_array_detail_does_not_error() {
		$params = $this->ui->buildParams( 'title', 'get', '/sample', 'garbage' );
		$this->assertEquals( 'string', $params['type'] );
	}

	public function test_required_only_schema_infers_object() {
		$schema = $this->ui->normalizeSchema( array( 'required' => array( 'a' ) ) );
		$this->assertEquals( 'object', $schema['type'] );
		$this->assertEquals( array( 'a' ), $schema['required'] );
	}

	public function test_array_enum_default_moves_into_items() {
		$params = $this->ui->buildParams( 'kind', 'get', '/sample', array(
			'type'    => 'array',
			'enum'    => array( 'a', 'b' ),
			'default' => 'a',
		) );

		$this->assertEquals( 'a', $params['items']['default'] );
		$this->assertArrayNotHasKey( 'default', $params );
	}

	public function test_nested_array_object_query_param_is_downgraded() {
		$params = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'GET' => true ),
			'accept_json' => false,
			'args'        => array( 'grid' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array( 'k' => array( 'type' => 'string' ) ) ) ),
			) ),
		)) )['get']['parameters'];

		$this->assertEquals( 'string', $params[0]['type'] );
		$this->assertArrayNotHasKey( 'items', $params[0] );
	}

	public function test_merged_required_field_makes_body_required() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array(
				'payload' => array( 'in' => 'body', 'schema' => array( 'type' => 'object', 'properties' => array( 'x' => array( 'type' => 'string' ) ) ) ),
				'title'   => array( 'type' => 'string', 'required' => true ),
			),
		)) )['post'];

		$bodies = array_values( array_filter( $operation['parameters'], function ( $p ) {
			return isset( $p['in'] ) && 'body' === $p['in'];
		} ) );
		$this->assertCount( 1, $bodies );
		$this->assertTrue( $bodies[0]['required'] );
	}

	public function test_non_object_explicit_body_is_not_merged() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array(
				'items' => array( 'in' => 'body', 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ),
				'title' => array( 'type' => 'string' ),
			),
		)) )['post'];

		$bodies = array_values( array_filter( $operation['parameters'], function ( $p ) {
			return isset( $p['in'] ) && 'body' === $p['in'];
		} ) );
		$this->assertCount( 1, $bodies );
		$this->assertEquals( 'array', $bodies[0]['schema']['type'] );
		$this->assertArrayNotHasKey( 'properties', $bodies[0]['schema'] );
	}

	public function test_two_explicit_bodies_collapse_to_one() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => false,
			'args'        => array(
				'a' => array( 'in' => 'body', 'schema' => array( 'type' => 'object', 'properties' => array( 'x' => array( 'type' => 'string' ) ) ) ),
				'b' => array( 'in' => 'body', 'schema' => array( 'type' => 'object', 'properties' => array( 'y' => array( 'type' => 'string' ) ) ) ),
			),
		)) )['post'];

		$bodies = array_values( array_filter( $operation['parameters'], function ( $p ) {
			return isset( $p['in'] ) && 'body' === $p['in'];
		} ) );
		$this->assertCount( 1, $bodies );
		$this->assertArrayHasKey( 'x', $bodies[0]['schema']['properties'] );
		$this->assertArrayHasKey( 'y', $bodies[0]['schema']['properties'] );
	}

	public function test_non_json_body_absorbs_form_fields() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => false,
			'args'        => array(
				'payload' => array( 'in' => 'body', 'schema' => array( 'type' => 'object', 'properties' => array( 'x' => array( 'type' => 'string' ) ) ) ),
				'title'   => array( 'type' => 'string' ),
			),
		)) )['post'];

		$ins = array_map( function ( $p ) { return $p['in']; }, $operation['parameters'] );
		$this->assertNotContains( 'formData', $ins );
		$this->assertContains( 'body', $ins );
		$this->assertContains( 'application/json', $operation['consumes'] );
	}

	public function test_file_route_drops_json_consume() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array( 'file' => array( 'type' => 'file' ) ),
		)) )['post'];

		$this->assertNotContains( 'application/json', $operation['consumes'] );
		$this->assertContains( 'multipart/form-data', $operation['consumes'] );
		$this->assertEquals( 'file', $operation['parameters'][0]['type'] );
	}

	public function test_nested_file_field_prevents_json_body() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array( 'upload' => array(
				'type'       => 'object',
				'properties' => array( 'doc' => array( 'type' => 'file' ) ),
			) ),
		)) )['post'];

		$this->assertNotContains( 'application/json', $operation['consumes'] );
		$ins = array_map( function ( $p ) { return $p['in']; }, $operation['parameters'] );
		$this->assertNotContains( 'body', $ins );
	}

	public function test_file_inside_composition_prevents_json_body() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array( 'doc' => array(
				'allOf' => array( array( 'type' => 'object', 'properties' => array( 'f' => array( 'type' => 'file' ) ) ) ),
			) ),
		)) )['post'];

		$this->assertNotContains( 'application/json', $operation['consumes'] );
	}

	public function test_file_route_consumes_multipart_only() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array( 'file' => array( 'type' => 'file' ) ),
		)) )['post'];

		$this->assertEquals( array( 'multipart/form-data' ), $operation['consumes'] );
	}

	public function test_xml_only_body_not_broadened_to_json() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => false,
			'consumes'    => array( 'application/xml' ),
			'args'        => array( 'payload' => array( 'in' => 'body', 'schema' => array( 'type' => 'object', 'properties' => array( 'x' => array( 'type' => 'string' ) ) ) ) ),
		)) )['post'];

		$this->assertContains( 'application/xml', $operation['consumes'] );
		$this->assertNotContains( 'application/json', $operation['consumes'] );
	}

	public function test_openapi3_moves_definitions_and_rewrites_refs() {
		$openapi = ( new Spec30Formatter() )->format(array(
			'host'        => 'example.org',
			'basePath'    => '/wp-json',
			'schemes'     => array( 'https' ),
			'definitions' => array( 'Thing' => array( 'type' => 'object', 'properties' => array( 'a' => array( 'type' => 'string' ) ) ) ),
			'paths'       => array( '/sample' => array( 'post' => array(
				'consumes'   => array( 'application/json' ),
				'parameters' => array( array( 'name' => 'body', 'in' => 'body', 'schema' => array( '$ref' => '#/definitions/Thing' ) ) ),
				'responses'  => array( '200' => array( 'description' => 'OK' ) ),
			) ) ),
		));

		$this->assertArrayHasKey( 'Thing', $openapi['components']['schemas'] );
		$this->assertArrayNotHasKey( 'definitions', $openapi );
		$ref = $openapi['paths']['/sample']['post']['requestBody']['content']['application/json']['schema']['$ref'];
		$this->assertEquals( '#/components/schemas/Thing', $ref );
	}

	public function test_openapi3_normalizes_nested_file_to_binary() {
		$openapi = ( new Spec30Formatter() )->format(array(
			'host'     => 'example.org',
			'basePath' => '/wp-json',
			'schemes'  => array( 'https' ),
			'paths'    => array( '/sample' => array( 'post' => array(
				'consumes'   => array( 'multipart/form-data' ),
				'parameters' => array(
					array( 'name' => 'upload', 'in' => 'formData', 'type' => 'object', 'properties' => array(
						'doc' => array( 'type' => 'file', 'description' => 'the file' ),
					) ),
				),
				'responses'  => array( '200' => array( 'description' => 'OK' ) ),
			) ) ),
		));

		$doc = $openapi['paths']['/sample']['post']['requestBody']['content']['multipart/form-data']['schema']['properties']['upload']['properties']['doc'];
		$this->assertEquals( 'string', $doc['type'] );
		$this->assertEquals( 'binary', $doc['format'] );
		$this->assertEquals( 'the file', $doc['description'] );
	}

	public function test_query_param_with_raw_object_schema_is_downgraded() {
		$params = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'GET' => true ),
			'accept_json' => false,
			'args'        => array( 'filter' => array( 'schema' => array( 'type' => 'object', 'properties' => array( 'a' => array( 'type' => 'string' ) ) ) ) ),
		)) )['get']['parameters'];

		$this->assertEquals( 'string', $params[0]['type'] );
		$this->assertArrayNotHasKey( 'schema', $params[0] );
	}

	public function test_parseTypeObjectToString_backward_compatible() {
		$this->assertEquals( 'string', $this->ui->parseTypeObjectToString( 'object' ) );
		$this->assertEquals( 'integer', $this->ui->parseTypeObjectToString( 'integer' ) );
		$this->assertEquals( 'string', $this->ui->parseTypeObjectToString( array( 'object', 'null' ) ) );
	}

	public function test_ref_body_property_has_no_string_type() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array( 'thing' => array( '$ref' => '#/definitions/Thing' ) ),
		)) )['post'];

		$bodies = array_values( array_filter( $operation['parameters'], function ( $p ) {
			return isset( $p['in'] ) && 'body' === $p['in'];
		} ) );
		$prop = $bodies[0]['schema']['properties']['thing'];
		$this->assertEquals( '#/definitions/Thing', $prop['$ref'] );
		$this->assertArrayNotHasKey( 'type', $prop );
	}

	public function test_composition_body_property_has_no_string_type() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array( 'thing' => array( 'allOf' => array( array( 'type' => 'object', 'properties' => array( 'a' => array( 'type' => 'string' ) ) ) ) ) ),
		)) )['post'];

		$bodies = array_values( array_filter( $operation['parameters'], function ( $p ) {
			return isset( $p['in'] ) && 'body' === $p['in'];
		} ) );
		$prop = $bodies[0]['schema']['properties']['thing'];
		$this->assertArrayHasKey( 'allOf', $prop );
		$this->assertArrayNotHasKey( 'type', $prop );
	}

	public function test_file_plus_explicit_body_flattens_into_multipart() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => true,
			'args'        => array(
				'file' => array( 'type' => 'file' ),
				'meta' => array( 'in' => 'body', 'schema' => array( 'type' => 'object', 'properties' => array( 'x' => array( 'type' => 'string' ) ), 'required' => array( 'x' ) ) ),
			),
		)) )['post'];

		$ins = array_map( function ( $p ) { return $p['in']; }, $operation['parameters'] );
		$this->assertNotContains( 'body', $ins );
		$this->assertEquals( array( 'multipart/form-data' ), $operation['consumes'] );

		$by_name = array();
		foreach ( $operation['parameters'] as $p ) {
			$by_name[ $p['name'] ] = $p;
		}
		$this->assertArrayHasKey( 'file', $by_name );
		$this->assertArrayHasKey( 'x', $by_name );
		$this->assertEquals( 'formData', $by_name['x']['in'] );
		$this->assertTrue( $by_name['x']['required'] );
	}

	public function test_openapi3_rewrites_schema_refs_but_not_example_refs() {
		$openapi = ( new Spec30Formatter() )->format(array(
			'host'     => 'example.org',
			'basePath' => '/wp-json',
			'schemes'  => array( 'https' ),
			'paths'    => array( '/sample' => array( 'get' => array(
				'produces'  => array( 'application/json' ),
				'responses' => array( '200' => array(
					'description' => 'OK',
					'schema'      => array( '$ref' => '#/definitions/Thing' ),
					'examples'    => array( 'application/json' => array( '$ref' => '#/definitions/Thing' ) ),
				) ),
			) ) ),
		));

		$entry = $openapi['paths']['/sample']['get']['responses']['200']['content']['application/json'];
		// $ref in a schema position is rewritten for OpenAPI 3...
		$this->assertEquals( '#/components/schemas/Thing', $entry['schema']['$ref'] );
		// ...but an identical $ref sitting in literal example data is left alone.
		$this->assertEquals( '#/definitions/Thing', $entry['example']['$ref'] );
	}

	public function test_non_body_param_drops_schema_only_keys() {
		$params = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'GET' => true ),
			'accept_json' => false,
			'args'        => array( 'q' => array( 'type' => 'string', 'title' => 'Q', 'example' => 'hi', 'xml' => array( 'name' => 'Q' ), 'description' => 'search' ) ),
		)) )['get']['parameters'];

		$this->assertArrayNotHasKey( 'title', $params[0] );
		$this->assertArrayNotHasKey( 'example', $params[0] );
		$this->assertArrayNotHasKey( 'xml', $params[0] );
		$this->assertEquals( 'search', $params[0]['description'] );
		$this->assertEquals( 'string', $params[0]['type'] );
	}

	public function test_body_schema_preserves_xml_metadata() {
		$operation = $this->ui->getMethodsFromArgs( '/sample', '/wp/v2/sample', array(array(
			'methods'     => array( 'POST' => true ),
			'accept_json' => false,
			'consumes'    => array( 'application/xml' ),
			'args'        => array( 'payload' => array( 'in' => 'body', 'schema' => array(
				'type'       => 'object',
				'xml'        => array( 'name' => 'Payload' ),
				'properties' => array( 'x' => array( 'type' => 'string', 'xml' => array( 'attribute' => true ) ) ),
			) ) ),
		)) )['post'];

		$bodies = array_values( array_filter( $operation['parameters'], function ( $p ) {
			return isset( $p['in'] ) && 'body' === $p['in'];
		} ) );
		$this->assertEquals( array( 'name' => 'Payload' ), $bodies[0]['schema']['xml'] );
		$this->assertEquals( array( 'attribute' => true ), $bodies[0]['schema']['properties']['x']['xml'] );
	}

	public function test_buildParameters() {
		$this->assertTrue( is_array( $this->ui->getParametersFromArgs( '/sample/{id}', [], [] ) ) );
	}

	public function test_securityDefinitions() {
		$definitions = $this->ui->securityDefinitions();
		$this->assertTrue( is_array( $definitions ) );

		$this->assertArrayHasKey( 'basic', $definitions );
	}

	public function test_getSecurity() {
		$this->assertTrue( is_array( $this->ui->getSecurity() ) );
	}

	public function test_getResponses() {
		$responses = $this->ui->getResponses( '/sample/{id}' );

		$this->assertTrue( is_array( $responses ) );

		$this->assertArrayHasKey( '200', $responses );
		$this->assertArrayHasKey( '400', $responses );
		$this->assertArrayHasKey( '404', $responses );
	}

	public function test_getMethodsFromArgs_custom_tags() {
		$args = [
			[
				'methods'      => [ 'GET' => true ],
				'tags'         => [ 'Pets' ],
				'accept_json'  => false,
			],
		];
		$methods = $this->ui->getMethodsFromArgs( '/pets', '/wp/v2/pets', $args );
		$this->assertEquals( [ 'Pets' ], $methods['get']['tags'] );
	}

	public function test_getMethodsFromArgs_default_tags() {
		$args = [
			[
				'methods'      => [ 'GET' => true ],
				'accept_json'  => false,
			],
		];
		$methods = $this->ui->getMethodsFromArgs( '/pets', '/wp/v2/pets', $args );
		$this->assertEquals( [ 'pets' ], $methods['get']['tags'] );
	}

	public function test_getMethodsFromArgs_consumes_are_flat_strings() {
		$args = [
			[
				'methods'      => [ 'POST' => true ],
				'accept_json'  => true,
			],
		];
		$methods = $this->ui->getMethodsFromArgs( '/pets', '/wp/v2/pets', $args );
		$consumes = $methods['post']['consumes'];

		foreach ( $consumes as $item ) {
			$this->assertTrue( is_string( $item ) );
		}
		$this->assertTrue( in_array( 'application/json', $consumes, true ) );
	}

	public function test_endpointUrl_emits_query_string_form()
	{
		$url = WP_API_SwaggerUI::endpointUrl('docs');

		$this->assertStringContainsString('swagger_api=docs', $url);
		$this->assertStringStartsWith(home_url('/'), $url);

		parse_str((string) parse_url($url, PHP_URL_QUERY), $qs);
		$this->assertSame('docs', $qs['swagger_api']);
	}

	public function test_endpointUrl_works_when_permalinks_are_plain()
	{
		update_option('permalink_structure', '');

		$url = WP_API_SwaggerUI::endpointUrl('schema');

		$this->assertStringContainsString('swagger_api=schema', $url);
	}

}
