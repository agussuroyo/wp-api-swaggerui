<?php
/**
 * WP API SwaggerUI
 *
 * @package     PluginPackage
 * @author      Your Name
 * @copyright   2019 Your Name or Company Name
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: WP API SwaggerUI
 * Description: WordPress REST API with Swagger UI.
 * Version:     1.0.0
 * Author:      Agus Suroyo
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */
global $wp_version;

if ( version_compare( PHP_VERSION, '5.4', '<' ) || version_compare( $wp_version, '4.7', '<' ) ) {
	return;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggerbag.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggerauth.php';

if ( is_admin() ) {
	require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggersetting.php';
}

class WP_API_SwaggerUI {

	public function routes() {
		$base = self::rewriteBaseApi();
		add_rewrite_tag( '%swagger_api%', '([^&]+)' );
		add_rewrite_rule( '^' . $base . '/docs/?', 'index.php?swagger_api=docs', 'top' );
		add_rewrite_rule( '^' . $base . '/schema/?', 'index.php?swagger_api=schema', 'top' );
	}

	public function view( $template ) {
		if ( get_query_var( 'swagger_api' ) === 'docs' ) {
			$template = __DIR__ . DIRECTORY_SEPARATOR . 'template/swagger/index.php';
		}
		return $template;
	}

	public static function rewriteBaseApi() {
		return apply_filters( 'swagger_api_rewrite_api_base', 'rest-api' );
	}

	public static function pluginUrl( $path = null ) {
		return plugin_dir_url( __FILE__ ) . $path;
	}

	public function swagger() {
		if ( get_query_var( 'swagger_api' ) !== 'schema' ) {
			return;
		}

		global $wp_version;

		$response = array(
			'swagger'				 => '2.0',
			'info'					 => array(
				'title'			 => get_option( 'blogname' ) . ' API',
				'description'	 => get_option( 'blogdescription' ),
				'version'		 => $wp_version,
				'contact'		 => array(
					'email' => get_option( 'admin_email' )
				)
			),
			'host'					 => $this->getHost(),
			'basePath'				 => '/' . ltrim( rest_get_url_prefix(), '/' ),
			'tags'					 => [ [ 'name' => 'endpoint', 'description' => '' ] ],
			'schemes'				 => $this->getSchemes(),
			'paths'					 => $this->getPaths(),
			'securityDefinitions'	 => $this->securityDefinitions()
		);

		wp_send_json( $response );
	}

	public function getHost() {
		return str_replace( [ 'http://', 'https://' ], '', site_url() );
	}

	public function getSchemes() {
		$schemes = [];
		if ( is_ssl() ) {
			$schemes[] = 'https';
		}
		$schemes[] = 'http';
		return $schemes;
	}

	public static function getNameSpace() {
		return '/' . trim( get_option( 'swagger_api_basepath', '/wp/v2' ), '/' );
	}

	public static function getCLeanNameSpace() {
		return trim( self::getNameSpace(), '/' );
	}

	public function getRawPaths() {
		$routes		 = rest_get_server()->get_routes();
		$basepath	 = self::getNameSpace();
		$length		 = strlen( $basepath );

		$raw_paths = [];
		foreach ( $routes as $route => $value ) {
			if ( mb_substr( $route, 0, $length ) === $basepath && ($basepath !== $route) ) {
				$raw_paths[$route] = $value;
			}
		}

		return $raw_paths;
	}

	public function getPaths() {
		$raw = $this->getRawPaths();

		$paths = [];

		foreach ( $raw as $endpoint => $args ) {
			$ep			 = $this->convertEndpoint( $endpoint );
			$paths[$ep]	 = $this->getMethodsFromArgs( $ep, $args );
		}

		return $paths;
	}

	public function convertEndpoint( $endpoint ) {

		if ( mb_strpos( $endpoint, '(?P<' ) !== false ) {
			$endpoint = preg_replace_callback( '/\(\?P\<(.*?)>(.*?)\)/', function($match) use ($endpoint) {
				return '{' . $match[1] . '}';
			}, $endpoint );
		}

		return $endpoint;
	}

	public function getMethodsFromArgs( $endpoint, $args ) {

		$methods = [];

		foreach ( $args as $arg ) {

			$parameters = $this->buildParameters( $endpoint, $arg );

			foreach ( $arg['methods'] as $method => $bool ) {
				$mtd			 = mb_strtolower( $method );
				$methodEndpoint	 = $mtd . str_replace( '/', '_', $endpoint );
				$conf			 = array(
					'tags'			 => array( 'endpoint' ),
					'summary'		 => '',
					'description'	 => '',
					'consumes'		 => [
						'application/x-www-form-urlencoded',
						'multipart/form-data',
						'application/json'
					],
					'produces'		 => array(
						'application/json'
					),
					'parameters'	 => isset( $parameters[$mtd] ) ? $parameters[$mtd] : [],
					'security'		 => $this->getSecurity(),
					'responses'		 => $this->getResponses( $methodEndpoint )
				);
				if ( $arg['accept_json'] ) {
					$conf['consumes'][] = [ 'application/json' ];
				}
				$methods[$mtd] = $conf;
			}
		}

		return $methods;
	}

	public function detectIn( $param, $mtd, $endpoint, $detail ) {

		switch ( $mtd ) {
			case strpos( $endpoint, '{' . $param . '}' ) !== false:
				$in	 = 'path';
				break;
			case 'get':
				$in	 = 'query';
				break;
			case 'post':
				$in	 = 'formData';
				break;
			default:
				$in	 = 'query';
				break;
		}

		return $in;
	}

	public function buildParams( $param, $mtd, $endpoint, $detail ) {

		$type = $detail['type'] === 'object' ? 'string' : $detail['type'];

		if ( empty( $type ) ) {

			if ( strpos( $param, '_id' ) !== false ) {
				$type = 'integer';
			} elseif ( strtolower( $param ) === 'id' ) {
				$type = 'integer';
			} else {
				$type = 'string';
			}
		}

		$in			 = $this->detectIn( $param, $mtd, $endpoint, $detail );
		$required	 = ! empty( $detail['required'] );

		if ( 'path' === $in ) {
			$required = true;
		}

		$params = array(
			'name'			 => $param,
			'in'			 => $in,
			'description'	 => isset( $detail['description'] ) ? $detail['description'] : '',
			'required'		 => $required,
			'type'			 => $type
		);

		if ( isset( $detail['items'] ) ) {
			$params['items'] = array(
				'type' => $detail['items']['type']
			);
		} elseif ( isset( $detail['enum'] ) ) {
			$params['type']	 = 'array';
			$items			 = array(
				'type'	 => $detail['type'],
				'enum'	 => $detail['enum']
			);
			if ( isset( $detail['default'] ) ) {
				$items['default'] = $detail['default'];
			}
			$params['items']			 = $items;
			$params['collectionFormat']	 = 'multi';
		}

		if ( isset( $detail['maximum'] ) ) {
			$params['maximum'] = $detail['maximum'];
		}

		if ( isset( $detail['minimum'] ) ) {
			$params['minimum'] = $detail['minimum'];
		}

		if ( isset( $detail['format'] ) ) {
			$params['format'] = $detail['format'];
		} elseif ( $detail['type'] === 'integer' ) {
			$params['format'] = 'int64';
		}

		return $params;
	}

	public function buildParameters( $endpoint, $arg ) {
		$parameters = [];

		if ( isset( $arg['args'] ) ) {
			foreach ( $arg['args'] as $param => $detail ) {
				foreach ( $arg['methods'] as $method => $bool ) {
					$mtd = mb_strtolower( $method );

					if ( ! isset( $parameters[$mtd] ) ) {
						$parameters[$mtd] = [];
					}

					$parameters[$mtd][] = $this->buildParams( $param, $mtd, $endpoint, $detail );
				}
			}
		}

		return $parameters;
	}

	public function getSecurity() {
		$raw = $this->securityDefinitions();
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		$securities = [];
		foreach ( $raw as $key => $name ) {
			$securities[] = array(
				$key => []
			);
		}

		return $securities;
	}

	public function getResponses( $methodEndpoint ) {
		return apply_filters( 'swagger_api_responses_' . $methodEndpoint, array(
			'200'	 => [ 'description' => 'OK' ],
			'404'	 => [ 'description' => 'Not Found' ],
			'400'	 => [ 'description' => 'Bad Request' ]
				) );
	}

	public function securityDefinitions() {
		return apply_filters( 'swagger_api_security_definitions', null );
	}

	public function flushActivate() {
		$this->routes();
		flush_rewrite_rules();
	}

	public function flushDeactivate() {
		flush_rewrite_rules();
	}

	public static function debug( $params = null ) {
		echo '<pre>';
		print_r( $params );
		echo '</pre>';
		die();
	}

}

$swagerui = new WP_API_SwaggerUI();

register_activation_hook( __FILE__, [ $swagerui, 'flushActivate' ] );
register_deactivation_hook( __FILE__, [ $swagerui, 'flushDeactivate' ] );
add_action( 'init', [ $swagerui, 'routes' ] );
add_action( 'template_include', [ $swagerui, 'view' ] );
add_action( 'wp', [ $swagerui, 'swagger' ] );
