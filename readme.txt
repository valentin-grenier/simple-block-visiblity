===  Simple Block Visibility ===
Contributors: valentingrenier
Tags: gutenberg, blocks, visibility, responsive, breakpoints
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show or hide Gutenberg blocks based on custom breakpoints — no coding required.

== Description ==

Simple Block Visibility lets you control the visibility of any core Gutenberg block on a per-breakpoint basis. Define your own Mobile, Tablet, Laptop, and Desktop breakpoints in the settings page, then toggle visibility for each block directly in the editor.

**Features:**

* Hide any core Gutenberg block on Mobile, Tablet, Laptop, or Desktop
* Define your own breakpoint pixel values in Settings > Block Visibility
* Breakpoint labels update live in the editor to reflect your settings
* Pure CSS approach — no JavaScript on the frontend, no layout shifts
* Works with all core/ Gutenberg blocks

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/simple-block-visibility/`
2. Activate the plugin through the **Plugins** screen in WordPress
3. Go to **Settings > Block Visibility** to configure your breakpoints
4. In the block editor, open any block's settings panel and find the **Visibility** section

== Frequently Asked Questions ==

= Does this work with third-party blocks? =

Currently the plugin supports all `core/` Gutenberg blocks. Third-party block support may be added in a future release.

= How are blocks hidden? =

Blocks are hidden using CSS `display: none` via media queries generated from your breakpoint settings. There is no JavaScript involved on the frontend.

= Will hidden blocks affect SEO? =

Hidden blocks are still present in the HTML source. If SEO is a concern, consider whether the content should be conditionally rendered server-side instead.

= Can I use this alongside Simple Block Animations? =

Yes, both plugins are fully compatible and can be used together.

== Screenshots ==

1. The Visibility panel in the block editor
2. The Block Visibility settings page

== Changelog ==

= 1.0.0 =
* Initial release
* Hide blocks on Mobile, Tablet, Laptop, and Desktop breakpoints
* Configurable breakpoints via Settings > Block Visibility
