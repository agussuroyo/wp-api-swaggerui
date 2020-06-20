<?php

class SwaggerSetting {

	public function menu() {
		add_submenu_page( 'options-general.php', 'Swagger Setting', 'Swagger', 'manage_options', 'swagger-ui', [ $this, 'display' ] );
	}

	public function saveSetting() {

		if ( isset( $_POST['_wpnonce'] ) && current_user_can( 'manage_options' ) && wp_verify_nonce( $_POST['_wpnonce'], 'swagger_api_setting' ) ) {

			if ( isset( $_POST['swagger_api_basepath'] ) ) {
				update_option( 'swagger_api_basepath', sanitize_text_field( $_POST['swagger_api_basepath'] ) );
			}

			add_action( 'admin_notices', [ $this, 'notices' ] );
		}
	}

	public function notices() {
		echo self::template( 'notice' );
	}

	public function display() {

		$data							 = [];
		$data['page_title']				 = get_admin_page_title();
		$data['swagger_api_basepath']	 = WP_API_SwaggerUI::getCLeanNameSpace();
		$data['namespaces']				 = rest_get_server()->get_namespaces();
		$data['docs_url']				 = home_url( untrailingslashit( WP_API_SwaggerUI::rewriteBaseApi() ) . '/docs' );

		echo self::template( 'setting', $data );
	}

	public static function template( $file, $data = [] ) {
		ob_start();

		$__file = __DIR__ . DIRECTORY_SEPARATOR . 'template/' . $file . '.php';
		if ( is_readable( $__file ) ) {
			extract( $data, EXTR_SKIP );
			include $__file;
		}

		return ob_get_clean();
	}

}

$swaggerSetting = new SwaggerSetting();

add_action( 'admin_menu', [ $swaggerSetting, 'menu' ] );
add_action( 'init', [ $swaggerSetting, 'saveSetting' ] );
