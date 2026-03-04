=== WJ Post Order (Drag & Drop) ===
Contributors: webjive
Tags: post order, drag and drop, menu order, custom post type, reorder
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drag-and-drop post ordering for WordPress. Saves to menu_order and auto-applies on the frontend — including Divi and secondary queries.

== Description ==

WJ Post Order adds drag-and-drop reordering to the WordPress admin for Posts and any public Custom Post Type. Order is saved to the native `menu_order` database field and automatically applied on the frontend — no shortcodes or template changes required.

**Features:**

* Dedicated *Posts > Reorder* drag-and-drop screen
* Inline drag-and-drop on the standard All Posts list table
* Auto-applies `menu_order` on frontend archives and loops
* Works with Divi modules and secondary WP_Query instances
* Enable ordering for any public Custom Post Type
* Per-query opt-out via `wjpo_no_sort` query var
* Single PHP file — no external dependencies

== Installation ==

1. Upload the `wj-post-order` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins > Installed Plugins**
3. Go to **Settings > WJ Post Order** to configure

== Frequently Asked Questions ==

= Does this work with Custom Post Types? =

Yes. Go to **Settings > WJ Post Order** and check any public post type to enable drag-and-drop ordering for it.

= Will this affect my existing Divi loops? =

Yes — if *Auto-apply on Frontend* is enabled, all queries for enabled post types will respect `menu_order`, including Divi Blog and Portfolio modules. Use `'wjpo_no_sort' => 1` to opt out of ordering on a specific query.

= Does it use custom database tables? =

No. Order is stored in WordPress's built-in `menu_order` column in `wp_posts`.

= How do I stop auto-ordering on a specific query? =

Add `'wjpo_no_sort' => 1` to your `WP_Query` arguments.

== Changelog ==

= 1.3.1 =
* Fix: frontend ordering now works with Divi Blog/Portfolio modules and other page builder queries that explicitly set orderby

= 1.3.0 =
* Add "Apply Custom Post Order" checkbox to nav menu items (Appearance > Menus)
* Nav menu item checkbox enables ordering per post type archive, independent of the global toggle

= 1.2.4 =
* Initial public release

== Upgrade Notice ==

= 1.3.1 =
Fix for sites using Divi: custom post order now applies correctly in Blog and Portfolio modules.

= 1.3.0 =
New: per-menu-item checkbox to enable custom post ordering on post type archives.

= 1.2.4 =
Initial public release.
