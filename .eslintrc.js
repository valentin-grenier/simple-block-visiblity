module.exports = {
	extends: [
		'plugin:@wordpress/eslint-plugin/recommended',
	],
	env: {
		browser: true,
		es2021: true,
		node: true,
	},
	parserOptions: {
		ecmaVersion: 'latest',
		sourceType: 'module',
		ecmaFeatures: {
			jsx: true,
		},
	},
	globals: {
		wp: 'readonly',
		jQuery: 'readonly',
		ajaxurl: 'readonly',
	},
	rules: {
		'no-console': 'warn',
		'no-unused-vars': 'warn',
		'prefer-const': 'error',
		'no-var': 'error',
		'@wordpress/i18n-text-domain': ['error', {
			allowedTextDomain: 'simple-block-visibility',
		}],
	},
	settings: {
		react: {
			version: 'detect',
		},
	},
};
