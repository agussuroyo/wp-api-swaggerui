<?php

class SwaggerTemplate {

    public function view( $template ) {
	if ( get_query_var( 'swagger_api' ) === 'docs' ) {
	    $template = __DIR__ . DIRECTORY_SEPARATOR . 'template/swagger/index.php';
	}
	return $template;
    }

    public function removeQueuedScritps() {
	if ( get_query_var( 'swagger_api' ) === 'docs' ) {
	    // Remove all default styles.
	    global $wp_styles;
	    $style_whitelist = [ 'admin-bar', 'dashicons' ];

	    if ( isset( $wp_styles->registered ) ) {
		foreach ( $wp_styles->registered as $handle => $data ) {
		    if ( ! in_array( $handle, $style_whitelist ) ) {
			wp_deregister_style( $handle );
			wp_dequeue_style( $handle );
		    }
		}
	    }

	    // Remove all default scripts;
	    global $wp_scripts;
	    $script_whitelist = [ 'admin-bar' ];

	    if ( isset( $wp_scripts->registered ) ) {
		foreach ( $wp_scripts->registered as $handle => $data ) {
		    if ( ! in_array( $handle, $script_whitelist ) ) {
			wp_deregister_script( $handle );
			wp_dequeue_style( $handle );
		    }
		}
	    }
	}
    }

    public function enqueueScritps() {
	if ( get_query_var( 'swagger_api' ) === 'docs' ) {
	    wp_enqueue_style( 'swagger-ui', WP_API_SwaggerUI::pluginUrl( 'template/swagger/swagger-ui.css' ) );
	    wp_enqueue_style( 'style', WP_API_SwaggerUI::pluginUrl( 'template/swagger/style.css' ) );

	    wp_enqueue_script( 'swagger-ui-bundle', WP_API_SwaggerUI::pluginUrl( 'template/swagger/swagger-ui-bundle.js' ), [], null, true );
	    wp_enqueue_script( 'swagger-ui-standalone-preset', WP_API_SwaggerUI::pluginUrl( 'template/swagger/swagger-ui-standalone-preset.js' ), [], null, true );
	    wp_enqueue_script( 'swagger-ui-app', WP_API_SwaggerUI::pluginUrl( 'template/swagger/app.js' ), [ 'swagger-ui-bundle', 'swagger-ui-standalone-preset' ], null, true );

	    $l10n = array(
		'schema_url' => site_url( WP_API_SwaggerUI::rewriteBaseApi() . '/schema' )
	    );
	    wp_localize_script( 'swagger-ui-app', 'swagger_ui_app', $l10n );
	}
    }

}

$swaggerTemplate = new SwaggerTemplate();

add_action( 'template_include', [ $swaggerTemplate, 'view' ], 99 );
add_action( 'wp_enqueue_scripts', [ $swaggerTemplate, 'removeQueuedScritps' ], 99 );
add_action( 'wp_enqueue_scripts', [ $swaggerTemplate, 'enqueueScritps' ], 99 );
