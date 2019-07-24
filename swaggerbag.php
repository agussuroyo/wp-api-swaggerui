<?php

class SwaggerBag {

	public $items = [];

	public function __construct( $items = [] ) {
		$this->replace( $items );
	}

	public function replace( $items = [] ) {
		$this->items = $items;
	}

	public function set( $name, $value ) {
		$this->items[$name] = $value;
	}

	public function get( $name ) {
		return isset( $this->items[$name] ) ? $this->items[$name] : null;
	}

	public function has( $name ) {
		return array_key_exists( $name, $this->items );
	}

	public function all() {
		return $this->items;
	}

	public function keys() {
		return array_keys( $this->items );
	}

	public function only( $name ) {
		$look = is_array( $name ) ? $name : func_get_args();

		$all		 = $this->all();
		$filtered	 = [];

		foreach ( $look as $key ) {
			if ( isset( $all[$key] ) ) {
				$filtered[$key] = $all[$key];
			}
		}

		return $filtered;
	}

}
