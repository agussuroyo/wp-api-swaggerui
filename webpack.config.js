const path = require('path');
const config = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...config,
    entry: {
        'js/app': './resources/js/app.js',
        'css/app': './resources/sass/app.scss',
    },
    output: {
        ...config.output,
        path: path.resolve(__dirname, 'assets'),
    }
};

