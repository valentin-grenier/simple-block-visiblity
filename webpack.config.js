const defaultConfig = require('@wordpress/scripts/config/webpack.config');

const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
	...defaultConfig,
	entry: {
		editor: './src/js/editor.js',
		admin: './src/scss/admin.scss',
	},
	plugins: defaultConfig.plugins.filter(
		(plugin) => plugin.constructor.name !== 'RtlCssPlugin'
	),
	devtool: isProduction ? false : 'source-map',
};
