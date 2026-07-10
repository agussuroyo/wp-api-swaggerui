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

		return $spec;
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
