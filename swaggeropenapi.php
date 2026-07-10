<?php

interface SwaggerSpecFormatter {
	public function version(): string;
	public function format(array $spec): array;
}

class Spec20Formatter implements SwaggerSpecFormatter {

	public function version(): string {
		return '2.0';
	}

	public function format(array $spec): array {
		return $spec; // intermediate is already 2.0-shaped
	}
}

class Spec30Formatter implements SwaggerSpecFormatter {

	public function version(): string {
		return '3.0.3';
	}

	public function format(array $spec): array {
		$spec['openapi'] = '3.0.3';
		$spec['servers'] = $this->mapServers( $spec );
		unset( $spec['swagger'], $spec['host'], $spec['basePath'], $spec['schemes'] );

		if ( isset( $spec['securityDefinitions'] ) ) {
			$schemes = $this->mapSecuritySchemes( $spec['securityDefinitions'] );
			if ( ! empty( $schemes ) ) {
				$spec['components'] = array( 'securitySchemes' => $schemes );
			}
			unset( $spec['securityDefinitions'] );
		}

		if ( isset( $spec['paths'] ) ) {
			$spec['paths'] = $this->mapPaths( $spec['paths'] );
		}

		return $spec;
	}

	private function mapPaths(array $paths): array {
		$out = array();
		foreach ( $paths as $path => $methods ) {
			$out[ $path ] = array();
			foreach ( $methods as $method => $operation ) {
				$out[ $path ][ $method ] = $this->mapOperation( $operation );
			}
		}

		return $out;
	}

	private function mapOperation(array $op): array {
		unset( $op['consumes'], $op['produces'] );

		if ( ! isset( $op['parameters'] ) ) {
			return $op;
		}

		$parameters = array();
		foreach ( $op['parameters'] as $param ) {
			$in = isset( $param['in'] ) ? $param['in'] : 'query';
			if ( 'formData' === $in || 'body' === $in ) {
				continue; // handled in Task 5
			}
			$parameters[] = $this->mapParameter( $param );
		}

		if ( empty( $parameters ) ) {
			unset( $op['parameters'] );
		} else {
			$op['parameters'] = $parameters;
		}

		return $op;
	}

	private function mapParameter(array $param): array {
		$schema = ( isset( $param['schema'] ) && is_array( $param['schema'] ) ) ? $param['schema'] : array();

		foreach ( array( 'type', 'format', 'items', 'minimum', 'maximum' ) as $key ) {
			if ( isset( $param[ $key ] ) && ! isset( $schema[ $key ] ) ) {
				$schema[ $key ] = $param[ $key ];
			}
			unset( $param[ $key ] );
		}

		if ( isset( $param['collectionFormat'] ) ) {
			if ( 'multi' === $param['collectionFormat'] ) {
				$param['style']   = 'form';
				$param['explode'] = true;
			}
			unset( $param['collectionFormat'] );
		}

		if ( ! empty( $schema ) ) {
			$param['schema'] = $schema;
		}

		return $param;
	}

	private function mapSecuritySchemes(array $defs): array {
		$out = array();
		foreach ( $defs as $key => $def ) {
			$type = isset( $def['type'] ) ? $def['type'] : '';
			if ( 'basic' === $type ) {
				$out[ $key ] = array( 'type' => 'http', 'scheme' => 'basic' );
			} elseif ( 'bearer' === $key ) {
				$scheme = array( 'type' => 'http', 'scheme' => 'bearer' );
				if ( isset( $def['description'] ) ) {
					$scheme['description'] = $def['description'];
				}
				$out[ $key ] = $scheme;
			} else {
				$out[ $key ] = $def; // already valid 3.0 (e.g. apiKey)
			}
		}

		return $out;
	}

	private function mapServers(array $spec): array {
		$host     = isset( $spec['host'] ) ? $spec['host'] : '';
		$basePath = isset( $spec['basePath'] ) ? $spec['basePath'] : '';
		$schemes  = ! empty( $spec['schemes'] ) ? $spec['schemes'] : array( 'https' );

		$servers = array();
		foreach ( $schemes as $scheme ) {
			$servers[] = array( 'url' => $scheme . '://' . $host . $basePath );
		}

		return $servers;
	}
}

class SwaggerSpecRegistry {

	private static function map(): array {
		return array(
			'2.0'   => Spec20Formatter::class,
			'3.0.3' => Spec30Formatter::class,
		);
	}

	public static function versions(): array {
		return array_keys( self::map() );
	}

	public static function forVersion( string $version ): SwaggerSpecFormatter {
		$map   = self::map();
		$class = isset( $map[ $version ] ) ? $map[ $version ] : Spec20Formatter::class;
		return new $class();
	}
}
