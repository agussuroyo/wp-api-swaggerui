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

}
