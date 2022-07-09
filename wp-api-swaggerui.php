<?php
/**
 * WP API SwaggerUI
 *
 * @package     WP API SwaggerUI
 * @author      Agus Suroyo
 * @copyright   2019 Agus Suroyo
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: WP API SwaggerUI
 * Description: WordPress REST API with Swagger UI.
 * Version:     1.1.2
 * Author:      Agus Suroyo
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */
global $wp_version;

if (version_compare(PHP_VERSION, '5.4', '<') || version_compare($wp_version, '4.7', '<')) {
    return;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggerbag.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggerauth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggertemplate.php';

if (is_admin()) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggersetting.php';
}

class WP_API_SwaggerUI
{

    public function routes()
    {
        $base = self::rewriteBaseApi();
        add_rewrite_tag('%swagger_api%', '([^&]+)');
        add_rewrite_rule('^' . $base . '/docs/?', 'index.php?swagger_api=docs', 'top');
        add_rewrite_rule('^' . $base . '/schema/?', 'index.php?swagger_api=schema', 'top');
    }

    public static function rewriteBaseApi()
    {
        return apply_filters('swagger_api_rewrite_api_base', 'rest-api');
    }

    public static function pluginUrl($path = null)
    {
        return plugin_dir_url(__FILE__) . $path;
    }

    public static function pluginPath($path)
    {
        return plugin_dir_path(__FILE__) . $path;
    }

    public function swagger()
    {
        if (get_query_var('swagger_api') !== 'schema') {
            return;
        }

        global $wp_version;

        $response = array(
            'swagger' => '2.0',
            'info' => array(
                'title' => get_option('blogname') . ' API',
                'description' => get_option('blogdescription'),
                'version' => $wp_version,
                'contact' => array(
                    'email' => get_option('admin_email')
                )
            ),
            'host' => $this->getHost(),
            'basePath' => $this->getBasePath(),
            'tags' => [],
            'schemes' => $this->getSchemes(),
            'paths' => $this->getPaths(),
            'securityDefinitions' => $this->securityDefinitions()
        );

        wp_send_json($response);
    }

    public function getHost()
    {
        $host = parse_url(home_url(), PHP_URL_HOST);
        $port = parse_url(home_url(), PHP_URL_PORT);

        if ($port) {
            if ($port != 80 && $port != 443) {
                $host = $host . ':' . $port;
            }
        }

        return $host;
    }

    public function getBasePath()
    {
        $path = parse_url(home_url(), PHP_URL_PATH);
        return rtrim($path, '/') . '/' . ltrim(rest_get_url_prefix(), '/');
    }

    public function getSchemes()
    {
        $schemes = [];
        if (is_ssl()) {
            $schemes[] = 'https';
        }
        $schemes[] = 'http';
        return $schemes;
    }

    public static function getNameSpace()
    {
        return '/' . trim(get_option('swagger_api_basepath', '/wp/v2'), '/');
    }

    public static function getCLeanNameSpace()
    {
        return trim(self::getNameSpace(), '/');
    }

    public function getRawPaths()
    {
        $routes = rest_get_server()->get_routes();
        $basepath = self::getNameSpace();

        $raw_paths = [];
        foreach ($routes as $route => $value) {
            if (mb_strpos($route, $basepath) === 0 && ($basepath !== $route)) {
                $raw_paths[$route] = $value;
            }
        }

        return $raw_paths;
    }

    public function getPaths()
    {
        $raw = $this->getRawPaths();

        $paths = [];

        foreach ($raw as $endpoint => $args) {
            $ep = $this->convertEndpoint($endpoint);
            $paths[$ep] = $this->getMethodsFromArgs($ep, $endpoint, $args);
        }

        return $paths;
    }

    public function convertEndpoint($endpoint)
    {

        if (mb_strpos($endpoint, '(?P<') !== false) {
            $endpoint = preg_replace_callback('/\(\?P\<(.*?)>(.*)\)+/', function ($match) use ($endpoint) {
                return '{' . $match[1] . '}';
            }, $endpoint);
        }

        return $endpoint;
    }

    public function getDefaultTagsFromEndpoint($endpoint)
    {
        $namespace = self::getNameSpace();
        $ep = preg_replace_callback('/^' . preg_quote($namespace, '/') . '/', function () {
            return '';
        }, $endpoint);
        $parts = explode('/', trim($ep, '/'));
        return isset($parts[0]) ? [$parts[0]] : [];
    }

    public function getMethodsFromArgs($ep, $endpoint, $args)
    {

        $path_parameters = $this->getParametersFromEndpoint($endpoint);
        $methods = [];

        $tags = $this->getDefaultTagsFromEndpoint($endpoint);

        foreach ($args as $arg) {

            $all_parameters = $this->getParametersFromArgs(
                $ep,
                isset($arg['args']) ? $arg['args'] : [],
                isset($arg['methods']) ? $arg['methods'] : []
            );

            foreach ($arg['methods'] as $method => $bool) {
                $mtd = mb_strtolower($method);
                $methodEndpoint = $mtd . str_replace('/', '_', $ep);
                $parameters = isset($all_parameters[$mtd]) ? $all_parameters[$mtd] : [];

                // Building parameters.
                $existing_names = array_map(function ($param) {
                    return $param['name'];
                }, $parameters);
                foreach ($path_parameters as $path_params) {
                    if (!in_array($path_params['name'], $existing_names, true)) {
                        $parameters[] = $path_params;
                    }
                }

                $produces = ['application/json'];
                if (isset($arg['produces'])) {
                    $produces = (array) $arg['produces'];
                }

                $consumes = [
                    'application/x-www-form-urlencoded',
                    'multipart/form-data',
                ];

                if (isset($arg['consumes'])) {
                    $consumes = (array) $arg['consumes'];
                }

                if ($arg['accept_json']) {
                    $consumes[] = ['application/json'];
                }

                if (isset($args['tags']) && is_array($args['tags'])) {
                    $tags = $args['tags'];
                }

                $responses =$this->getResponses($methodEndpoint);
                if (isset($arg['responses'])) {
                    $responses = $arg['responses'];
                }

                $conf = array(
                    'tags' => $tags,
                    'summary' => isset($arg['summary']) ? $arg['summary'] : '',
                    'description' => isset($arg['description']) ? $arg['description'] : '',
                    'consumes' => $consumes,
                    'produces' => $produces,
                    'parameters' => $parameters,
                    'security' => $this->getSecurity(),
                    'responses' => $responses
                );

                $methods[$mtd] = $conf;
            }
        }

        return $methods;
    }

    public function getParametersFromEndpoint($endpoint)
    {
        $path_params = [];

        if (mb_strpos($endpoint, '(?P<') !== false && (preg_match_all('/\(\?P\<(.*?)>(.*)\)/', $endpoint, $matches))) {
            foreach ($matches[1] as $order => $match) {
                $type = strpos(mb_strtolower($matches[2][$order]), '\d') !== false ? 'integer' : 'string';
                $params = array(
                    'name' => $match,
                    'in' => 'path',
                    'description' => '',
                    'required' => true,
                    'type' => $type,
                );
                if ($type === 'integer') {
                    $params['format'] = 'int64';
                }
                $path_params[$match] = $params;
            }
        }

        return $path_params;
    }

    public function detectIn($param, $mtd, $endpoint, $detail)
    {
        if (isset($detail['in'])) {
            return $detail['in'];
        }

        switch ($mtd) {
            case strpos($endpoint, '{' . $param . '}') !== false:
                $in = 'path';
                break;
            case 'post':
                $in = 'formData';
                break;
            default:
                $in = 'query';
                break;
        }

        return $in;
    }

    public function parseTypeObjectToString($types)
    {
        if (is_array($types)) {
            foreach ($types as $type) {
                return $this->parseTypeObjectToString($type);
            }
        }
        return $types === 'object' ? 'string' : $types;
    }

    public function buildParams($param, $mtd, $endpoint, $detail)
    {
        /**
         * When the type is object, SwaggerUI by default add empty `{}` to parameter value
         * It's annoying so need to convert to just `string`
         */
        $type = $this->parseTypeObjectToString($detail['type']);
        if (is_array($type) && isset($type[0])) {
            $type = $type[0];
        }

        if (empty($type)) {

            if (strpos($param, '_id') !== false) {
                $type = 'integer';
            } elseif (strtolower($param) === 'id') {
                $type = 'integer';
            } else {
                $type = 'string';
            }
        }

        $in = $this->detectIn($param, $mtd, $endpoint, $detail);
        $required = !empty($detail['required']);

        if ('path' === $in) {
            $required = true;
        }

        $params = array(
            'name' => $param,
            'in' => $in,
            'description' => isset($detail['description']) ? $detail['description'] : '',
            'required' => $required,
            'type' => $type
        );

        if (isset($detail['items'])) {
            $params['items'] = array(
                'type' => isset($detail['items']['type']) ? $detail['items']['type'] : 'string'
            );
        } elseif (isset($detail['enum'])) {
            $params['type'] = 'array';
            $items = array(
                'type' => $detail['type'],
                'enum' => $detail['enum']
            );
            if (isset($detail['default'])) {
                $items['default'] = $detail['default'];
            }
            $params['items'] = $items;
            $params['collectionFormat'] = 'multi';
        }

        if (isset($detail['maximum'])) {
            $params['maximum'] = $detail['maximum'];
        }

        if (isset($detail['minimum'])) {
            $params['minimum'] = $detail['minimum'];
        }

        if (isset($detail['format'])) {
            $params['format'] = $detail['format'];
        } elseif ($detail['type'] === 'integer') {
            $params['format'] = 'int64';
        }

        if (isset($detail['schema'])) {
            $params['schema'] = $detail['schema'];
        }

        return $params;
    }

    public function getParametersFromArgs($endpoint = '', $args = [], $methods = [])
    {
        $parameters = [];

        foreach ($args as $param => $detail) {
            foreach ($methods as $method => $bool) {
                $mtd = mb_strtolower($method);

                if (!isset($parameters[$mtd])) {
                    $parameters[$mtd] = [];
                }

                $parameters[$mtd][] = $this->buildParams($param, $mtd, $endpoint, $detail + ['type' => 'string']);
            }
        }

        return $parameters;
    }

    public function getSecurity()
    {
        $raw = $this->securityDefinitions();
        if (!is_array($raw)) {
            $raw = [];
        }

        $securities = [];
        foreach ($raw as $key => $name) {
            $securities[] = array(
                $key => []
            );
        }

        return $securities;
    }

    public function getResponses( $methodEndpoint ) {
        return apply_filters('swagger_api_responses_' . $methodEndpoint, array(
            '200' => ['description' => 'OK'],
            '404' => ['description' => 'Not Found'],
            '400' => ['description' => 'Bad Request']
        ));
    }

    public function securityDefinitions()
    {
        return apply_filters('swagger_api_security_definitions', null);
    }

    public function flushActivate()
    {
        $this->routes();
        flush_rewrite_rules();
    }

    public function flushDeactivate()
    {
        flush_rewrite_rules();
    }

    public static function debug($params = null)
    {
        echo '<pre>';
        print_r($params);
        echo '</pre>';
        die();
    }

}

$swagerui = new WP_API_SwaggerUI();

register_activation_hook(__FILE__, [$swagerui, 'flushActivate']);
register_deactivation_hook(__FILE__, [$swagerui, 'flushDeactivate']);
add_action('init', [$swagerui, 'routes']);
add_action('wp', [$swagerui, 'swagger']);
