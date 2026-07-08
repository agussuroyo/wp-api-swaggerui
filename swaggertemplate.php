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
        if (!is_string($src) || $src === '') {
            return false;
        }

        $bases = [plugins_url()];
        if (defined('WPMU_PLUGIN_URL')) {
            $bases[] = WPMU_PLUGIN_URL;
        }

        foreach ($bases as $base) {
            if ($base && strpos($src, $base) === 0) {
                return true;
            }
        }

        return false;
    }

    public function enqueueScritps()
    {
        if (get_query_var('swagger_api') === 'docs') {

            $info_css = $this->getAssetInfo('assets/css/app');
            wp_enqueue_style('swagger-ui', WP_API_SwaggerUI::pluginUrl('assets/css/app.css'), [], $info_css['version']);

            $info_js = $this->getAssetInfo('assets/js/app');
            wp_enqueue_script('swagger-ui', WP_API_SwaggerUI::pluginUrl('assets/js/app.js'), $info_js['dependencies'], $info_js['version'], true);

            $l10n = array(
                'schema_url' => home_url(WP_API_SwaggerUI::rewriteBaseApi() . '/schema')
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
