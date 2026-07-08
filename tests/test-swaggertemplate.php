<?php

class TestSwaggerTemplate extends WP_UnitTestCase
{

    public $template = null;

    public function set_up()
    {

        $this->template = new SwaggerTemplate();

        parent::set_up();
    }

    public function test_instance()
    {
        $this->assertTrue($this->template instanceof SwaggerTemplate);
    }

    public function test_info()
    {
        $info = $this->template->getAssetInfo('assets/js/app');
        $this->assertTrue(is_array($info));

        $this->assertArrayHasKey('dependencies', $info);
        $this->assertArrayHasKey('version', $info);
    }

    public function test_removeQueuedScritps_keeps_plugin_assets_removes_theme()
    {
        global $wp_query;
        $wp_query->set('swagger_api', 'docs');

        wp_enqueue_style('fake-plugin', plugins_url('fake.css', __FILE__));
        wp_enqueue_style('fake-theme', 'http://example.org/wp-content/themes/foo/style.css');
        wp_enqueue_script('fake-plugin-js', plugins_url('fake.js', __FILE__));
        wp_enqueue_script('fake-theme-js', 'http://example.org/wp-content/themes/foo/app.js');

        $this->template->removeQueuedScritps();

        $this->assertTrue(wp_style_is('fake-plugin', 'registered'));
        $this->assertFalse(wp_style_is('fake-theme', 'registered'));
        $this->assertTrue(wp_script_is('fake-plugin-js', 'enqueued'));
        $this->assertFalse(wp_script_is('fake-theme-js', 'enqueued'));
    }
}