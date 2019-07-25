<?php

class TestSwaggerBag extends WP_UnitTestCase {

	public $bag = null;

	public function setUp() {

		$this->bag = new SwaggerBag( array(
			'sample' => 'yes'
				) );

		parent::setUp();
	}

	public function test_all() {
		$all = $this->bag->all();

		$this->assertEquals( array(
			'sample' => 'yes'
				), $all );
	}

	public function test_replace() {
		$items = [];
		$this->bag->replace( $items );

		$this->assertEquals( [], $this->bag->all() );
	}

	public function test_set() {
		$this->bag->set( 'other', 'no' );

		$this->assertArrayHasKey( 'other', $this->bag->all() );
	}

	public function test_get() {

		$this->bag->set( 'pure', 'yes' );

		$this->assertEquals( 'yes', $this->bag->get( 'pure' ) );
	}

	public function test_has() {
		$this->bag->set( 'clone', 'king' );

		$this->assertArrayHasKey( 'clone', $this->bag->all() );
	}

	public function test_keys() {
		$this->assertTrue( true );
	}

	public function tets_only() {
		$this->assertTrue( true );
	}

}
