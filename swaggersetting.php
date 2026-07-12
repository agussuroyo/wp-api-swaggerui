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

			$schemes = (array) ( isset( $_POST['swagger_api_auth_schemes'] ) ? $_POST['swagger_api_auth_schemes'] : array() );
			update_option( 'swagger_api_auth_schemes', array_values( array_intersect( array( 'basic', 'bearer' ), $schemes ) ) );

			if ( isset( $_POST['swagger_api_spec_version'] ) ) {
				$version = sanitize_text_field( $_POST['swagger_api_spec_version'] );
				if ( in_array( $version, SwaggerSpecRegistry::versions(), true ) ) {
					update_option( 'swagger_api_spec_version', $version );
				}
			}

			update_option( 'swagger_api_expose_contact_email', isset( $_POST['swagger_api_expose_contact_email'] ) ? '1' : '0' );

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
		$data['docs_url']				 = WP_API_SwaggerUI::endpointUrl( 'docs' );
		$data['swagger_api_auth_schemes'] = (array) get_option( 'swagger_api_auth_schemes', array( 'basic' ) );
		$data['spec_versions']			 = SwaggerSpecRegistry::versions();
		$data['swagger_api_spec_version'] = get_option( 'swagger_api_spec_version', '2.0' );
		$data['swagger_api_expose_contact_email'] = get_option( 'swagger_api_expose_contact_email', '1' );

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
