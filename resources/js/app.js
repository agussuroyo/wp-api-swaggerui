import { SwaggerUIBundle, SwaggerUIStandalonePreset } from "swagger-ui-dist";

SwaggerUIBundle({
    url: swagger_ui_app.schema_url,
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [
        SwaggerUIBundle.presets.apis,
        SwaggerUIStandalonePreset
    ],
    plugins: [
        SwaggerUIBundle.plugins.DownloadUrl
    ],
    layout: "StandaloneLayout",
    validatorUrl: "https://validator.swagger.io/validator",
});