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
 * Version:     2.2.0
 * Author:      Agus Suroyo
 * Requires PHP: 7.4
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */
global $wp_version;

if (version_compare(PHP_VERSION, '7.4', '<') || version_compare($wp_version, '4.7', '<')) {
    return;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggerbag.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggerauth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggertemplate.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'swaggeropenapi.php';

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

        $expose_email  = '1' === get_option('swagger_api_expose_contact_email', '1');
        $contact_email = apply_filters('swagger_api_contact_email', $expose_email ? get_option('admin_email') : '');

        $info = array(
            'title' => get_option('blogname') . ' API',
            'description' => get_option('blogdescription'),
            'version' => apply_filters('swagger_api_info_version', $wp_version),
        );
        if (!empty($contact_email)) {
            $info['contact'] = array('email' => $contact_email);
        }

        // Canonical Swagger 2.0 pivot document. Each formatter stamps its own
        // version marker (swagger/openapi) and reshapes from here.
        $response = array(
            'info' => $info,
            'host' => $this->getHost(),
            'basePath' => $this->getBasePath(),
            'tags' => [],
            'schemes' => $this->getSchemes(),
            'paths' => $this->getPaths(),
        );

        // Only advertise securityDefinitions when a scheme is enabled. Omitting
        // the key (rather than emitting an empty map) keeps /schema valid Swagger
        // 2.0 and stops Swagger UI rendering a stray, empty Authorize dialog.
        $securityDefinitions = $this->securityDefinitions();
        if (is_array($securityDefinitions) && !empty($securityDefinitions)) {
            $response['securityDefinitions'] = $securityDefinitions;
        }

        $formatter = SwaggerSpecRegistry::forVersion(get_option('swagger_api_spec_version', '2.0'));
        $output    = $formatter->format($response);
        if (empty($output['paths'])) {
            $output['paths'] = new \stdClass();
        }
        wp_send_json($output);
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
        $path = parse_url(home_url(), PHP_URL_PATH) ?? '';
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
            // Match each named group separately (multi-param routes). Atoms:
            // escaped char, char class [...], or a (?3)-recursive balanced group,
            // so a param regex may nest parens to any depth and may contain a
            // literal ')' inside a class or escape without ending the group early.
            $endpoint = preg_replace_callback('/\(\?P<([^>]+)>((?:\\\\.|\[[^\]]*\]|(\((?:\\\\.|\[[^\]]*\]|[^()]|(?3))*\))|[^()])*)\)/', function ($match) {
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
        // Strip named parameters so the tag comes from a real path segment,
        // not a raw (?P<...>) regex. Same recursive match as convertEndpoint.
        $ep = preg_replace_callback('/\(\?P<([^>]+)>((?:\\\\.|\[[^\]]*\]|(\((?:\\\\.|\[[^\]]*\]|[^()]|(?3))*\))|[^()])*)\)/', function () {
            return '';
        }, $ep);
        $parts = array_values(array_filter(explode('/', trim($ep, '/'))));
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
                    $consumes[] = 'application/json';
                }

                $has_file = false;
                $has_explicit_body = false;
                foreach ($parameters as $parameter) {
                    if ($this->schemaContainsFile($parameter)) {
                        $has_file = true;
                    }
                    if (isset($parameter['in']) && 'body' === $parameter['in']) {
                        $has_explicit_body = true;
                    }
                }
                $wants_json = in_array('application/json', $consumes, true);

                if ($has_file) {
                    // Swagger 2 file parameters are valid only under multipart/form-data.
                    $consumes = ['multipart/form-data'];
                    $parameters = array_map([$this, 'normalizeNonBodyParameter'], $parameters);
                } elseif ($wants_json || $has_explicit_body) {
                    // Consolidate form fields into the single body parameter Swagger 2 allows.
                    $parameters = $this->buildJsonBodyParameter($parameters);
                    $has_body = false;
                    foreach ($parameters as $index => $parameter) {
                        if (isset($parameter['in']) && 'body' === $parameter['in']) {
                            $has_body = true;
                        } else {
                            // Query/header params still cannot carry object schemas in Swagger 2.
                            $parameters[$index] = $this->normalizeNonBodyParameter($parameter);
                        }
                    }
                    // A body parameter cannot coexist with form media types. Drop those, but keep
                    // any declared body-compatible type (e.g. XML); only default to JSON if none remain.
                    if ($has_body) {
                        $consumes = array_values(array_diff($consumes, ['application/x-www-form-urlencoded', 'multipart/form-data']));
                        if (empty($consumes)) {
                            $consumes = ['application/json'];
                        }
                    }
                } else {
                    $parameters = array_map([$this, 'normalizeNonBodyParameter'], $parameters);
                }

                $responses =$this->getResponses($methodEndpoint);
                if (isset($arg['responses'])) {
                    $responses = $arg['responses'];
                }

                $conf = array(
                    'tags' => isset($arg['tags']) ? (array) $arg['tags'] : $tags,
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

        if (mb_strpos($endpoint, '(?P<') !== false && (preg_match_all('/\(\?P<([^>]+)>((?:\\\\.|\[[^\]]*\]|(\((?:\\\\.|\[[^\]]*\]|[^()]|(?3))*\))|[^()])*)\)/', $endpoint, $matches))) {
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
            case 'put':
            case 'patch':
                $in = 'formData';
                break;
            default:
                $in = 'query';
                break;
        }

        return $in;
    }

    public function buildParams($param, $mtd, $endpoint, $detail)
    {
        if (!is_array($detail)) {
            $detail = array();
        }
        $schema = $this->normalizeSchema($detail);
        $type = isset($schema['type']) ? $schema['type'] : 'string';

        $in = $this->detectIn($param, $mtd, $endpoint, $detail);
        // A JSON-Schema `required` array is the object's schema-level list, not field requiredness.
        $required = !empty($detail['required']) && !is_array($detail['required']);

        // Swagger 2 body parameters carry their schema under `schema`, not primitive fields.
        if ('body' === $in) {
            return array(
                'name' => $param,
                'in' => 'body',
                'description' => isset($detail['description']) ? $detail['description'] : '',
                'required' => $required,
                'schema' => isset($detail['schema']) ? $this->normalizeSchema($detail['schema']) : $schema,
            );
        }

        // Typeless `id` / `*_id` arguments are conventionally integers.
        if (!isset($detail['type']) && 'string' === $type
            && ('id' === strtolower($param) || false !== strpos($param, '_id'))) {
            $type = 'integer';
        }

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

        foreach ($schema as $key => $value) {
            if ('description' !== $key && 'required' !== $key && 'type' !== $key) {
                $params[$key] = $value;
            }
        }

        // Object sub-property requirements are schema-level; carry them separately so the
        // param's own boolean requiredness ($params['required']) is preserved.
        if ('object' === $type && isset($schema['required'])) {
            $params['objectRequired'] = $schema['required'];
        }

        if ('array' === $type && isset($detail['enum']) && !isset($detail['items'])) {
            $params['collectionFormat'] = 'multi';
        }

        if ('integer' === $type && !isset($params['format'])) {
            $params['format'] = 'int64';
        }

        return $params;
    }

    /**
     * @deprecated Use normalizeSchema() instead. Retained for backward compatibility.
     */
    public function parseTypeObjectToString($types)
    {
        if (is_array($types)) {
            foreach ($types as $type) {
                return $this->parseTypeObjectToString($type);
            }
        }
        return 'object' === $types ? 'string' : $types;
    }

    /** Normalize a WordPress REST argument into a recursively complete schema. */
    public function normalizeSchema($detail)
    {
        if (!is_array($detail)) {
            return array('type' => 'string');
        }

        // A $ref replaces the schema; it carries no sibling keywords.
        if (isset($detail['$ref'])) {
            return array('$ref' => $detail['$ref']);
        }

        $schema = array();
        $type = null;
        if (isset($detail['type'])) {
            $type = $detail['type'];
            if (is_array($type)) {
                // Union types: drop the non-representable "null" and keep the first real type.
                $type = array_values(array_filter($type, function ($member) {
                    return 'null' !== $member;
                }));
                $type = !empty($type) ? reset($type) : 'string';
            }
            // Some routes use the non-standard type "enum". Keep their intent valid.
            $type = ('enum' === $type) ? 'string' : ($type ?: 'string');
        } elseif (isset($detail['properties'])) {
            $type = 'object';
        } elseif (isset($detail['items'])) {
            $type = 'array';
        } elseif (isset($detail['allOf'])) {
            $type = null; // composition-only schema has no primitive type
        } elseif ((isset($detail['required']) && is_array($detail['required'])) || isset($detail['additionalProperties']) || isset($detail['minProperties']) || isset($detail['maxProperties'])) {
            $type = 'object'; // object-only keywords imply an object
        } else {
            $type = 'string';
        }
        if (null !== $type) {
            $schema['type'] = $type;
        }

        // Keep only keywords valid in Swagger 2.0 (the base spec this plugin emits).
        // oneOf/anyOf/const/patternProperties are not; dropping beats emitting invalid output.
        $keys = array('description', 'format', 'enum', 'default', 'example', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'minLength', 'maxLength', 'pattern', 'minItems', 'maxItems', 'uniqueItems', 'minProperties', 'maxProperties', 'title');
        foreach ($keys as $key) {
            if (array_key_exists($key, $detail)) {
                $schema[$key] = $detail[$key];
            }
        }

        if (isset($detail['additionalProperties'])) {
            $additional = $detail['additionalProperties'];
            if (is_array($additional)) {
                // Empty schema {} means "any value"; keep it open rather than narrowing to string.
                $schema['additionalProperties'] = empty($additional) ? true : $this->normalizeSchema($additional);
            } else {
                $schema['additionalProperties'] = $additional;
            }
        }
        if (isset($detail['allOf']) && is_array($detail['allOf'])) {
            $schema['allOf'] = array_map(array($this, 'normalizeSchema'), $detail['allOf']);
        }

        if (isset($detail['items'])) {
            $schema['items'] = $this->normalizeSchema($detail['items']);
        } elseif ('array' === $type) {
            $items = array('type' => 'string');
            if (isset($schema['enum'])) {
                $items['enum'] = $schema['enum'];
                unset($schema['enum']);
                // The scalar default constrains items, not the array itself.
                if (isset($schema['default']) && !is_array($schema['default'])) {
                    $items['default'] = $schema['default'];
                    unset($schema['default']);
                }
            }
            $schema['items'] = $items;
        }

        // Object-level required (JSON Schema array) applies even when properties arrive via allOf.
        $required = (isset($detail['required']) && is_array($detail['required'])) ? $detail['required'] : array();
        if (isset($detail['properties']) && is_array($detail['properties'])) {
            $schema['properties'] = array();
            foreach ($detail['properties'] as $name => $property) {
                $schema['properties'][$name] = $this->normalizeSchema($property);
                if (is_array($property) && !empty($property['required']) && !is_array($property['required'])) {
                    $required[] = $name;
                }
            }
        }
        $required = array_values(array_unique($required));
        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /** Combine inferred form fields into the one body parameter Swagger 2 supports. */
    public function buildJsonBodyParameter($parameters)
    {
        $ordinary = array();
        $properties = array();
        $required = array();

        foreach ($parameters as $parameter) {
            if (!isset($parameter['in']) || 'formData' !== $parameter['in']) {
                $ordinary[] = $parameter;
                continue;
            }
            $name = $parameter['name'];
            $field_required = !empty($parameter['required']);
            $schema = $parameter;
            foreach (array('name', 'in', 'required', 'collectionFormat', 'objectRequired') as $key) {
                unset($schema[$key]);
            }
            if (isset($parameter['objectRequired'])) {
                $schema['required'] = $parameter['objectRequired'];
            }
            $properties[$name] = $schema;
            if ($field_required) {
                $required[] = $name;
            }
        }

        // Swagger 2 permits a single body parameter: collapse multiple explicit ones into the first.
        $bodyIndex = null;
        foreach ($ordinary as $index => $parameter) {
            if (!isset($parameter['in']) || 'body' !== $parameter['in']) {
                continue;
            }
            if (null === $bodyIndex) {
                $bodyIndex = $index;
                continue;
            }
            if ($this->bodySchemaIsObjectCompatible($this->bodySchema($ordinary[$bodyIndex]))
                && $this->bodySchemaIsObjectCompatible($this->bodySchema($parameter))) {
                $ordinary[$bodyIndex]['schema'] = $this->mergeObjectSchema($this->bodySchema($ordinary[$bodyIndex]), $this->bodySchema($parameter));
                if (!empty($parameter['required'])) {
                    $ordinary[$bodyIndex]['required'] = true;
                }
            }
            unset($ordinary[$index]);
        }
        $ordinary = array_values($ordinary);

        if (empty($properties)) {
            return $ordinary;
        }

        // Merge inferred form fields into an existing body when it is object-compatible.
        foreach ($ordinary as $index => $parameter) {
            if (!isset($parameter['in']) || 'body' !== $parameter['in']) {
                continue;
            }
            if (!$this->bodySchemaIsObjectCompatible($this->bodySchema($parameter))) {
                return $ordinary; // cannot fold form fields into a non-object body
            }
            $ordinary[$index]['schema'] = $this->mergeObjectSchema($this->bodySchema($parameter), array('properties' => $properties, 'required' => $required));
            if (!empty($required)) {
                $ordinary[$index]['required'] = true;
            }
            return $ordinary;
        }

        $schema = array('type' => 'object', 'properties' => $properties);
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        $ordinary[] = array(
            'name' => 'body',
            'in' => 'body',
            'description' => '',
            'required' => !empty($required),
            'schema' => $schema,
        );

        return $ordinary;
    }

    private function bodySchema($parameter)
    {
        return isset($parameter['schema']) && is_array($parameter['schema']) ? $parameter['schema'] : array();
    }

    /** An object body can absorb inferred form fields; a $ref, allOf, or scalar/array body cannot. */
    private function bodySchemaIsObjectCompatible($schema)
    {
        if (isset($schema['$ref']) || isset($schema['allOf'])) {
            return false;
        }
        return !isset($schema['type']) || 'object' === $schema['type'];
    }

    /** Merge one object body schema's properties and required list into another. */
    private function mergeObjectSchema($target, $source)
    {
        $target['type'] = 'object';
        if (!isset($target['properties']) || !is_array($target['properties'])) {
            $target['properties'] = array();
        }
        if (isset($source['properties']) && is_array($source['properties'])) {
            $target['properties'] += $source['properties'];
        }
        $required = isset($target['required']) && is_array($target['required']) ? $target['required'] : array();
        if (isset($source['required']) && is_array($source['required'])) {
            $required = array_merge($required, $source['required']);
        }
        if (!empty($required)) {
            $target['required'] = array_values(array_unique($required));
        }
        return $target;
    }

    /** Swagger 2 query/form parameters cannot carry object or reference schemas at any depth. */
    public function normalizeNonBodyParameter($parameter)
    {
        if ($this->schemaIsStructured($parameter)) {
            $parameter['type'] = 'string';
            foreach (array('properties', 'objectRequired', 'additionalProperties', 'allOf', '$ref', 'items', 'schema', 'collectionFormat', 'minProperties', 'maxProperties', 'minItems', 'maxItems', 'uniqueItems') as $key) {
                unset($parameter[$key]);
            }
        }
        // A string parameter cannot carry an array default left over from the downgrade.
        if (isset($parameter['type']) && 'string' === $parameter['type'] && isset($parameter['default']) && is_array($parameter['default'])) {
            unset($parameter['default']);
        }
        return $parameter;
    }

    /** A query/form schema is unrepresentable in Swagger 2 if it nests an object or $ref. */
    private function schemaIsStructured($schema)
    {
        if (!is_array($schema)) {
            return false;
        }
        if (isset($schema['$ref']) || isset($schema['properties']) || isset($schema['additionalProperties']) || isset($schema['allOf'])) {
            return true;
        }
        if (isset($schema['type']) && 'object' === $schema['type']) {
            return true;
        }
        if (isset($schema['schema']) && $this->schemaIsStructured($schema['schema'])) {
            return true;
        }
        if (isset($schema['items'])) {
            return $this->schemaIsStructured($schema['items']);
        }
        return false;
    }

    /** Detect a Swagger 2 `file` type anywhere in a built parameter tree. */
    private function schemaContainsFile($schema)
    {
        if (!is_array($schema)) {
            return false;
        }
        if (isset($schema['type']) && 'file' === $schema['type']) {
            return true;
        }
        foreach (array('items', 'schema', 'additionalProperties') as $key) {
            if (isset($schema[$key]) && $this->schemaContainsFile($schema[$key])) {
                return true;
            }
        }
        foreach (array('properties', 'allOf') as $key) {
            if (isset($schema[$key]) && is_array($schema[$key])) {
                foreach ($schema[$key] as $sub) {
                    if ($this->schemaContainsFile($sub)) {
                        return true;
                    }
                }
            }
        }
        return false;
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

                $parameters[$mtd][] = $this->buildParams($param, $mtd, $endpoint, $detail);
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
