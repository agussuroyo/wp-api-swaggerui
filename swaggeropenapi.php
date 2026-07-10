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
		return array( 'swagger' => $this->version() ) + $spec;
	}
}

class Spec30Formatter implements SwaggerSpecFormatter {

	public function version(): string {
		return '3.0.3';
	}

	public function format(array $spec): array {
		$spec['servers'] = $this->mapServers( $spec );
		unset( $spec['host'], $spec['basePath'], $spec['schemes'] );
		$spec = array( 'openapi' => $this->version() ) + $spec;

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
		$produces = ! empty( $op['produces'] ) ? (array) $op['produces'] : array( 'application/json' );
		$consumes = ! empty( $op['consumes'] ) ? (array) $op['consumes'] : array();
		unset( $op['consumes'], $op['produces'] );

		if ( isset( $op['responses'] ) ) {
			$op['responses'] = $this->mapResponses( $op['responses'], $produces );
		}

		if ( ! isset( $op['parameters'] ) ) {
			return $op;
		}

		$parameters    = array();
		$form_data     = array();
		$body          = null;
		$body_required = false;

		foreach ( $op['parameters'] as $param ) {
			$in = isset( $param['in'] ) ? $param['in'] : 'query';
			if ( 'formData' === $in ) {
				$form_data[] = $param;
			} elseif ( 'body' === $in ) {
				$body          = isset( $param['schema'] ) ? $param['schema'] : array( 'type' => 'object' );
				$body_required = ! empty( $param['required'] );
			} else {
				$parameters[] = $this->mapParameter( $param );
			}
		}

		if ( empty( $parameters ) ) {
			unset( $op['parameters'] );
		} else {
			$op['parameters'] = $parameters;
		}

		if ( ! empty( $form_data ) ) {
			$media             = ! empty( $consumes ) ? $consumes : array( 'application/x-www-form-urlencoded' );
			$op['requestBody'] = $this->mapFormDataBody( $form_data, $media );
		} elseif ( null !== $body ) {
			$media   = ! empty( $consumes ) ? $consumes : array( 'application/json' );
			$content = array();
			foreach ( $media as $m ) {
				$content[ $m ] = array( 'schema' => $body );
			}
			$request_body = array( 'content' => $content );
			if ( $body_required ) {
				$request_body['required'] = true;
			}
			$op['requestBody'] = $request_body;
		}

		return $op;
	}

	private function mapResponses(array $responses, array $produces): array {
		foreach ( $responses as $code => $response ) {
			if ( is_array( $response ) && isset( $response['schema'] ) ) {
				$schema = $response['schema'];
				unset( $response['schema'] );
				$content = array();
				foreach ( $produces as $media ) {
					$content[ $media ] = array( 'schema' => $schema );
				}
				$response['content'] = $content;
				$responses[ $code ]  = $response;
			}
		}
		return $responses;
	}

	private function mapParameter(array $param): array {
		if ( isset( $param['collectionFormat'] ) ) {
			if ( 'multi' === $param['collectionFormat'] ) {
				$param['style']   = 'form';
				$param['explode'] = true;
			}
			unset( $param['collectionFormat'] );
		}

		$keep   = array( 'name', 'in', 'description', 'required', 'deprecated', 'allowEmptyValue', 'style', 'explode', 'schema' );
		$schema = $this->extractSchema( $param, $keep );

		foreach ( array_keys( $param ) as $key ) {
			if ( ! in_array( $key, $keep, true ) ) {
				unset( $param[ $key ] );
			}
		}

		if ( ! empty( $schema ) ) {
			$param['schema'] = $schema;
		}

		return $param;
	}

	private function extractSchema(array $param, array $keep): array {
		$schema = ( isset( $param['schema'] ) && is_array( $param['schema'] ) ) ? $param['schema'] : array();
		foreach ( array_keys( $param ) as $key ) {
			if ( in_array( $key, $keep, true ) ) {
				continue;
			}
			if ( ! isset( $schema[ $key ] ) ) {
				$schema[ $key ] = $param[ $key ];
			}
		}
		return $schema;
	}

	private function mapFormDataBody(array $form_data, array $media): array {
		$properties = array();
		$required   = array();

		foreach ( $form_data as $param ) {
			$name   = $param['name'];
			$schema = $this->extractSchema( $param, array( 'name', 'in', 'required', 'schema', 'collectionFormat' ) );
			$schema = $this->normalizeFileSchema( $schema );
			if ( empty( $schema ) ) {
				$schema = array( 'type' => 'string' );
			}
			$properties[ $name ] = $schema;
			if ( ! empty( $param['required'] ) ) {
				$required[] = $name;
			}
		}

		$body = array( 'type' => 'object', 'properties' => $properties );
		if ( ! empty( $required ) ) {
			$body['required'] = $required;
		}

		$content = array();
		foreach ( $media as $m ) {
			$content[ $m ] = array( 'schema' => $body );
		}

		return array( 'content' => $content );
	}

	private function normalizeFileSchema(array $schema): array {
		if ( isset( $schema['type'] ) && 'file' === $schema['type'] ) {
			$schema['type']   = 'string';
			$schema['format'] = 'binary';
		}
		return $schema;
	}

	private function mapSecuritySchemes(array $defs): array {
		$out = array();
		foreach ( $defs as $key => $def ) {
			$type = isset( $def['type'] ) ? $def['type'] : '';
			if ( 'basic' === $type ) {
				$out[ $key ] = array( 'type' => 'http', 'scheme' => 'basic' );
			} elseif ( 'bearer' === $key ) {
				$out[ $key ] = array(
					'type'        => 'http',
					'scheme'      => 'bearer',
					'description' => 'Enter your token; the "Bearer" prefix is added automatically.',
				);
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
