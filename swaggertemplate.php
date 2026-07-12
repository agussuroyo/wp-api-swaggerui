<?php

class SwaggerTemplate
{

    public function view($template)
    {
        if (get_query_var('swagger_api') === 'docs') {
            $template = WP_API_SwaggerUI::pluginPath('template/single.php');
        }
        return $template;
    }

    public function removeQueuedScritps()
    {
        if (get_query_var('swagger_api') === 'docs') {
            // Only keep plugin assets when the admin bar is showing: that is when
            // admin bar tools (e.g. Query Monitor) need them. Without the bar
            // (public docs) strip everything so no front-end plugin CSS bleeds in.
            $keep_plugin_assets = is_admin_bar_showing();

            // Strip theme/front-end styles that clash with Swagger UI, but keep
            // admin bar essentials and plugin assets. Dequeue only (no
            // deregister) so a kept style's dependencies stay registered and
            // WordPress can still resolve and print it.
            global $wp_styles;
            $style_whitelist = ['admin-bar', 'dashicons'];

            if (isset($wp_styles->registered)) {
                foreach ($wp_styles->registered as $handle => $data) {
                    if (in_array($handle, $style_whitelist) || ($keep_plugin_assets && $this->isPluginAsset($data->src))) {
                        continue;
                    }
                    wp_dequeue_style($handle);
                }
            }

            // Same for scripts: keep admin bar and plugin assets.
            global $wp_scripts;
            $script_whitelist = ['admin-bar'];

            if (isset($wp_scripts->registered)) {
                foreach ($wp_scripts->registered as $handle => $data) {
                    if (in_array($handle, $script_whitelist) || ($keep_plugin_assets && $this->isPluginAsset($data->src))) {
                        continue;
                    }
                    wp_dequeue_script($handle);
                }
            }
        }
    }

    private function isPluginAsset($src)
    {
        // Trailing slash so 'wp-content/plugins' does not prefix-match siblings
        // like 'wp-content/plugins-cache'. Cover must-use plugins too, since
        // admin bar tools are often dropped in there. src is false for
        // alias-only handles.
        return is_string($src) && (
            strpos($src, plugins_url('/')) === 0
            || (defined('WPMU_PLUGIN_URL') && strpos($src, WPMU_PLUGIN_URL . '/') === 0)
        );
    }

    public function enqueueScritps()
    {
        if (get_query_var('swagger_api') === 'docs') {

            // Deregister our own handles first so our assets win even if
            // another plugin already registered the 'swagger-ui' handle
            // (dequeue no longer clears the registration).
            wp_deregister_style('swagger-ui');
            wp_deregister_script('swagger-ui');

            $info_css = $this->getAssetInfo('assets/css/app');
            wp_enqueue_style('swagger-ui', WP_API_SwaggerUI::pluginUrl('assets/css/app.css'), [], $info_css['version']);

            $info_js = $this->getAssetInfo('assets/js/app');
            wp_enqueue_script('swagger-ui', WP_API_SwaggerUI::pluginUrl('assets/js/app.js'), $info_js['dependencies'], $info_js['version'], true);

            $l10n = array(
                'schema_url' => WP_API_SwaggerUI::endpointUrl('schema')
            );
            wp_localize_script('swagger-ui', 'swagger_ui_app', $l10n);
        }
    }

    public function getAssetInfo($name = '')
    {
        global $wp_version;
        $info = ['dependencies' => [], 'version' => $wp_version];

        $file = WP_API_SwaggerUI::pluginPath($name . '.asset.php');
        if (is_readable($file)) {
            $info = include $file;
        }

        return $info;
    }

}

$swaggerTemplate = new SwaggerTemplate();

add_action('template_include', [$swaggerTemplate, 'view'], 99);
add_action('wp_enqueue_scripts', [$swaggerTemplate, 'removeQueuedScritps'], 99);
add_action('wp_enqueue_scripts', [$swaggerTemplate, 'enqueueScritps'], 99);
