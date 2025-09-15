const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    // Add custom entry points
    entry: {
        frontend: path.resolve(process.cwd(), 'js/frontend.js'),
        editor: path.resolve(process.cwd(), 'js/editor.js'),
        css: path.resolve(process.cwd(), 'js/css.js'),
    },
};
