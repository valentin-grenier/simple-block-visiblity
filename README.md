# Simple Block Visibility

Show or hide Gutenberg blocks based on custom breakpoints — no coding required.

![WordPress Plugin Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress Compatibility](https://img.shields.io/badge/wordpress-5.8%2B-blue.svg)
![PHP Version](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPLv2%2B-green.svg)

## Features

- Hide any core Gutenberg block on **Mobile**, **Tablet**, **Laptop**, or **Desktop**
- Define your own breakpoint pixel values in **Settings > Block Visibility**
- Breakpoint labels update live in the editor to reflect your settings
- Pure CSS approach — no JavaScript on the frontend, no layout shifts
- Works with all core and custom Gutenberg blocks

## How It Works

1. The plugin registers `hideOnMobile`, `hideOnTablet`, `hideOnLaptop`, and `hideOnDesktop` boolean attributes on every core Gutenberg block
2. A **Visibility** panel appears in the block's Inspector Controls (Settings tab)
3. On the frontend, PHP injects a small `<style>` tag with 4 media query rules based on your admin-defined breakpoints
4. When a block has a hide attribute set, PHP adds the matching CSS class (`sblv-hide-mobile`, etc.) to the block's opening tag via the `render_block` filter

## Installation

### From source

```bash
cd wp-content/plugins/simple-block-visibility
npm install
npm run build
```

Then activate the plugin in the WordPress admin.

### Development

```bash
npm run dev   # watch mode with source maps
```

## Settings

Go to **Settings > Block Visibility** and configure:

| Field | Default | Description |
|---|---|---|
| Mobile max-width | 550px | Screens ≤ this width are "Mobile" |
| Tablet max-width | 768px | Screens up to this width are "Tablet" |
| Laptop max-width | 1440px | Screens up to this width are "Laptop". Desktop starts after. |

## Available npm Scripts

| Script | Description |
|---|---|
| `npm run build` | Production build |
| `npm run dev` | Development build with watch |
| `npm run lint:js` | Lint JavaScript |
| `npm run lint:css` | Lint SCSS |
| `npm run format` | Auto-format source files |
| `npm run i18n` | Generate `.pot` translation file |

## Deployment

Use the included deploy script to publish to WordPress.org SVN:

```bash
# Test run (no commits)
bash bin/deploy-to-svn.sh --test

# Real deployment
bash bin/deploy-to-svn.sh
```

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Node.js (for development)

## License

This project is licensed under the GPLv2 or later - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by [Valentin Grenier](https://github.com/valentin-grenier) - [Studio Val](https://studio-val.fr)

## Support

For issues, questions, or suggestions, please [open an issue](https://github.com/valentin-grenier/simple-block-visibility/issues) on GitHub.
