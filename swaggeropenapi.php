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

		if ( isset( $spec['securityDefinitions'] ) && is_array( $spec['securityDefinitions'] ) ) {
			$schemes = $this->mapSecuritySchemes( $spec['securityDefinitions'] );
			if ( ! empty( $schemes ) ) {
				$spec['components']['securitySchemes'] = $schemes;
			}
			unset( $spec['securityDefinitions'] );
		}

		if ( isset( $spec['definitions'] ) && is_array( $spec['definitions'] ) ) {
			$existing_schemas              = isset( $spec['components']['schemas'] ) && is_array( $spec['components']['schemas'] ) ? $spec['components']['schemas'] : array();
			$spec['components']['schemas'] = $existing_schemas + $spec['definitions'];
			unset( $spec['definitions'] );
		}

		if ( isset( $spec['components']['schemas'] ) && is_array( $spec['components']['schemas'] ) ) {
			foreach ( $spec['components']['schemas'] as $name => $schema ) {
				$spec['components']['schemas'][ $name ] = $this->rewriteSchemaRefs( $schema );
			}
		}

		if ( isset( $spec['paths'] ) ) {
			$spec['paths'] = $this->mapPaths( $spec['paths'] );
		}

		return $spec;
	}

	/**
	 * Rewrite JSON Reference targets from Swagger 2 (#/definitions/) to OpenAPI 3
	 * (#/components/schemas/). Descends only through schema keywords, so a "$ref"
	 * key sitting in literal example/default data is never rewritten.
	 */
	private function rewriteSchemaRefs($schema) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}
		$prefix = '#/definitions/';
		if ( isset( $schema['$ref'] ) && is_string( $schema['$ref'] ) && 0 === strpos( $schema['$ref'], $prefix ) ) {
			$schema['$ref'] = '#/components/schemas/' . substr( $schema['$ref'], strlen( $prefix ) );
		}
		foreach ( array( 'items', 'additionalProperties' ) as $key ) {
			if ( isset( $schema[ $key ] ) && is_array( $schema[ $key ] ) ) {
				$schema[ $key ] = $this->rewriteSchemaRefs( $schema[ $key ] );
			}
		}
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $name => $sub ) {
				$schema['properties'][ $name ] = $this->rewriteSchemaRefs( $sub );
			}
		}
		foreach ( array( 'allOf', 'oneOf', 'anyOf' ) as $key ) {
			if ( isset( $schema[ $key ] ) && is_array( $schema[ $key ] ) ) {
				foreach ( $schema[ $key ] as $i => $branch ) {
					$schema[ $key ][ $i ] = $this->rewriteSchemaRefs( $branch );
				}
			}
		}
		return $schema;
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

		if ( isset( $op['responses'] ) && is_array( $op['responses'] ) ) {
			$op['responses'] = $this->mapResponses( $op['responses'], $produces );
		}

		if ( ! isset( $op['parameters'] ) ) {
			return $op;
		}

		$parameters       = array();
		$form_data        = array();
		$body             = null;
		$body_required    = false;
		$body_description = null;

		foreach ( $op['parameters'] as $param ) {
			$in = isset( $param['in'] ) ? $param['in'] : 'query';
			if ( 'formData' === $in ) {
				$form_data[] = $param;
			} elseif ( 'body' === $in ) {
				$body             = isset( $param['schema'] ) ? $param['schema'] : array( 'type' => 'object' );
				$body_required    = ! empty( $param['required'] );
				$body_description = ( isset( $param['description'] ) && '' !== $param['description'] ) ? $param['description'] : null;
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
			$media        = ! empty( $consumes ) ? $consumes : array( 'application/json' );
			$content      = $this->contentFor( $media, $body );
			$request_body = array( 'content' => $content );
			if ( null !== $body_description ) {
				$request_body['description'] = $body_description;
			}
			if ( $body_required ) {
				$request_body['required'] = true;
			}
			$op['requestBody'] = $request_body;
		}

		return $op;
	}

	private function mapResponses(array $responses, array $produces): array {
		foreach ( $responses as $code => $response ) {
			if ( ! is_array( $response ) ) {
				continue;
			}
			$schema   = isset( $response['schema'] ) ? $response['schema'] : null;
			$examples = ( isset( $response['examples'] ) && is_array( $response['examples'] ) ) ? $response['examples'] : array();
			if ( null === $schema && empty( $examples ) ) {
				continue;
			}
			unset( $response['schema'], $response['examples'] );
			$content = array();
			foreach ( $produces as $m ) {
				$entry = array();
				if ( null !== $schema ) {
					$entry['schema'] = $this->rewriteSchemaRefs( $schema );
				}
				if ( isset( $examples[ $m ] ) ) {
					$entry['example'] = $examples[ $m ];
				}
				if ( ! empty( $entry ) ) {
					$content[ $m ] = $entry;
				}
			}
			if ( ! empty( $content ) ) {
				$response['content'] = $content;
			}
			$responses[ $code ] = $response;
		}
		return $responses;
	}

	private function mapParameter(array $param): array {
		$collection_format = isset( $param['collectionFormat'] ) ? $param['collectionFormat'] : null;
		unset( $param['collectionFormat'] );

		$keep   = array( 'name', 'in', 'description', 'required', 'deprecated', 'allowEmptyValue', 'style', 'explode', 'schema' );
		$schema = $this->extractSchema( $param, $keep );

		foreach ( array_keys( $param ) as $key ) {
			if ( ! in_array( $key, $keep, true ) ) {
				unset( $param[ $key ] );
			}
		}

		if ( ! empty( $schema ) ) {
			$param['schema'] = $this->rewriteSchemaRefs( $schema );
		}

		if ( isset( $schema['type'] ) && 'array' === $schema['type'] ) {
			$in               = isset( $param['in'] ) ? $param['in'] : 'query';
			$serialization    = $this->arrayStyle( $collection_format, $in );
			$param['style']   = $serialization[0];
			$param['explode'] = $serialization[1];
		}

		return $param;
	}

	private function arrayStyle($collection_format, string $in): array {
		if ( 'path' === $in || 'header' === $in ) {
			// OAS3: path/header array parameters use 'simple' (comma-separated).
			return array( 'simple', false );
		}
		switch ( $collection_format ) {
			case 'multi':
				return array( 'form', true );
			case 'ssv':
				return array( 'spaceDelimited', false );
			case 'pipes':
				return array( 'pipeDelimited', false );
			case 'csv':
			case 'tsv': // no native OAS3 tsv; closest is comma (form/false)
			default:    // Swagger 2.0 default is csv
				return array( 'form', false );
		}
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
		if ( isset( $schema['type'] ) && 'array' === $schema['type'] && ! isset( $schema['items'] ) ) {
			$schema['items'] = array( 'type' => 'string' );
		}
		return $schema;
	}

	private function contentFor(array $media, array $schema): array {
		$schema  = $this->rewriteSchemaRefs( $schema );
		$content = array();
		foreach ( $media as $m ) {
			$content[ $m ] = array( 'schema' => $schema );
		}
		return $content;
	}

	private function mapFormDataBody(array $form_data, array $media): array {
		$properties = array();
		$required   = array();
		$encoding   = array();

		foreach ( $form_data as $param ) {
			if ( ! isset( $param['name'] ) || '' === $param['name'] ) {
				continue;
			}
			$name              = $param['name'];
			$collection_format = isset( $param['collectionFormat'] ) ? $param['collectionFormat'] : null;
			$schema            = $this->extractSchema( $param, array( 'name', 'in', 'required', 'schema', 'collectionFormat' ) );
			$schema            = $this->normalizeFileSchema( $schema );
			if ( empty( $schema ) ) {
				$schema = array( 'type' => 'string' );
			}
			$properties[ $name ] = $schema;
			if ( ! empty( $param['required'] ) ) {
				$required[] = $name;
			}
			if ( isset( $schema['type'] ) && 'array' === $schema['type'] ) {
				$style             = $this->arrayStyle( $collection_format, 'query' );
				$encoding[ $name ] = array( 'style' => $style[0], 'explode' => $style[1] );
			}
		}

		$body = array( 'type' => 'object', 'properties' => $properties );
		if ( ! empty( $required ) ) {
			$body['required'] = $required;
		}

		$form_media = array( 'application/x-www-form-urlencoded', 'multipart/form-data' );

		$content = $this->contentFor( $media, $body );
		if ( ! empty( $encoding ) ) {
			foreach ( $form_media as $fm ) {
				if ( isset( $content[ $fm ] ) ) {
					$content[ $fm ]['encoding'] = $encoding;
				}
			}
		}

		$request_body = array( 'content' => $content );
		if ( ! empty( $required ) ) {
			$request_body['required'] = true;
		}

		return $request_body;
	}

	private function normalizeFileSchema(array $schema): array {
		if ( isset( $schema['type'] ) && 'file' === $schema['type'] ) {
			$schema['type']   = 'string';
			$schema['format'] = 'binary';
		}

		foreach ( array( 'items', 'schema', 'additionalProperties' ) as $key ) {
			if ( isset( $schema[ $key ] ) && is_array( $schema[ $key ] ) ) {
				$schema[ $key ] = $this->normalizeFileSchema( $schema[ $key ] );
			}
		}

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $name => $property ) {
				if ( is_array( $property ) ) {
					$schema['properties'][ $name ] = $this->normalizeFileSchema( $property );
				}
			}
		}

		foreach ( array( 'allOf', 'oneOf', 'anyOf' ) as $combinator ) {
			if ( isset( $schema[ $combinator ] ) && is_array( $schema[ $combinator ] ) ) {
				foreach ( $schema[ $combinator ] as $index => $branch ) {
					if ( is_array( $branch ) ) {
						$schema[ $combinator ][ $index ] = $this->normalizeFileSchema( $branch );
					}
				}
			}
		}

		return $schema;
	}

	private function mapSecuritySchemes(array $defs): array {
		$out = array();
		foreach ( $defs as $key => $def ) {
			$type = isset( $def['type'] ) ? $def['type'] : '';
			if ( 'basic' === $type ) {
				$out[ $key ] = array( 'type' => 'http', 'scheme' => 'basic' );
			} elseif ( 'bearer' === $key && 'oauth2' !== $type ) {
				$out[ $key ] = array(
					'type'        => 'http',
					'scheme'      => 'bearer',
					'description' => 'Enter your token; the "Bearer" prefix is added automatically.',
				);
			} elseif ( 'oauth2' === $type ) {
				$out[ $key ] = $this->mapOauth2( $def );
			} else {
				$out[ $key ] = $def; // already valid 3.0 (e.g. apiKey)
			}
		}

		return $out;
	}

	private function mapOauth2(array $def): array {
		$flow_names = array(
			'implicit'    => 'implicit',
			'password'    => 'password',
			'application' => 'clientCredentials',
			'accessCode'  => 'authorizationCode',
		);
		$flow_key = ( isset( $def['flow'] ) && isset( $flow_names[ $def['flow'] ] ) )
			? $flow_names[ $def['flow'] ]
			: 'implicit';

		$flow = array();
		if ( isset( $def['authorizationUrl'] ) ) {
			$flow['authorizationUrl'] = $def['authorizationUrl'];
		}
		if ( isset( $def['tokenUrl'] ) ) {
			$flow['tokenUrl'] = $def['tokenUrl'];
		}
		$flow['scopes'] = ( isset( $def['scopes'] ) && ! empty( $def['scopes'] ) ) ? $def['scopes'] : new \stdClass();

		$scheme = array(
			'type'  => 'oauth2',
			'flows' => array( $flow_key => $flow ),
		);
		if ( isset( $def['description'] ) ) {
			$scheme['description'] = $def['description'];
		}
		return $scheme;
	}

	private function mapServers(array $spec): array {
		// Plain permalinks: advertise the honest ?rest_route= server so the
		// dropdown matches the URL Try-it-out actually calls.
		$cfg = WP_API_SwaggerUI::restRouteConfig();
		if ( $cfg['enabled'] && ! empty( $cfg['server'] ) ) {
			return array( array( 'url' => $cfg['server'] ) );
		}

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
