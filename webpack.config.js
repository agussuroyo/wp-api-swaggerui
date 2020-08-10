const path = require('path');
const config = require('@wordpress/scripts/config/webpack.config');
const iewp = require('ignore-emit-webpack-plugin');
const ocawp = require('optimize-css-assets-webpack-plugin');

module.exports = {
    ...config,
    entry: {
        'js/app': './resources/js/app.js',
        'css/app': './resources/sass/app.scss',
    },
    output: {
        ...config.output,
        path: path.resolve(__dirname, 'assets'),
    },
    plugins: [
        ...config.plugins,
        new iewp(['css/app.js'])
    ],
    optimization: {
        ...config.optimization,
        minimizer: [
            ...config.optimization.minimizer,
            new ocawp({
                cssProcessorPluginOptions: {
                    preset: ['default', {discardComments: {removeAll: true}}],
                }
            })
        ]
    }
};

