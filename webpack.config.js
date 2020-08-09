const path = require('path');
const config = require('@wordpress/scripts/config/webpack.config');
const iewp = require('ignore-emit-webpack-plugin');

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
    ]
};

