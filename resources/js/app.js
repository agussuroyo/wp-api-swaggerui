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
    requestInterceptor: (req) => {
        // Plain permalinks have no /wp-json route, so rewrite REST calls to
        // the ?rest_route= form. Only touch requests under the REST base path;
        // the schema fetch (?swagger_api=schema) is left alone.
        const cfg = swagger_ui_app.rest_route;
        if (cfg && cfg.enabled) {
            const u = new URL(req.url);
            if (cfg.basePath && u.pathname.indexOf(cfg.basePath) === 0) {
                const route = u.pathname.slice(cfg.basePath.length);
                const query = u.search ? u.search.slice(1) : '';
                req.url = cfg.restRoot + '?rest_route=' + route + (query ? '&' + query : '');
            }
        }
        return req;
    },
});