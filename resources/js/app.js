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
        // the ?rest_route= form. Swagger UI drops the query from the server
        // URL and prepends only its path (cfg.strip), which we strip back off
        // to recover the route. The schema fetch and cross-origin requests
        // (e.g. the validator) are left alone.
        const cfg = swagger_ui_app.rest_route;
        if (!cfg || !cfg.enabled) {
            return req;
        }
        const base = new URL(cfg.restRoot);
        const u = new URL(req.url);
        if (u.origin !== base.origin || u.searchParams.has('swagger_api')) {
            return req;
        }
        // Only rewrite calls under the REST base path, so same-origin non-REST
        // requests (e.g. an OAuth2 tokenUrl) are left untouched. When cfg.strip
        // is just '/' (rare bare-root installs) we can't distinguish, so fall
        // back to rewriting; the schema fetch stays safe via the guard above.
        let route = u.pathname;
        if (cfg.strip && cfg.strip !== '/') {
            if (route.indexOf(cfg.strip) !== 0) {
                return req;
            }
            route = route.slice(cfg.strip.length);
        }
        const query = u.search ? u.search.slice(1) : '';
        req.url = base.origin + base.pathname + '?rest_route=' + route + (query ? '&' + query : '');
        return req;
    },
});