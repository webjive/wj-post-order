# WJ Post Order (Drag & Drop)

A lightweight WordPress plugin that adds drag-and-drop post ordering to the admin. Saves order to the built-in `menu_order` field and automatically applies it on the frontend — including Divi and other secondary queries.

## Features

- **Drag & Drop Reorder Page**: Dedicated *Posts > Reorder* screen with a clean drag-and-drop list
- **Inline List Table Ordering**: Drag rows directly on the standard *All Posts* / *All CPTs* admin screen — no separate page required
- **Frontend Auto-Apply**: Archives and loops respect `menu_order` automatically, including Divi modules and secondary `WP_Query` instances
- **Custom Post Type Support**: Enable ordering for any public CPT from the settings page
- **menu_order Native Storage**: Uses WordPress's built-in `menu_order` column — no custom tables, no extra metadata
- **Per-Query Opt-Out**: Set `'wjpo_no_sort' => 1` on any query to skip automatic ordering
- **Zero Dependencies**: Ships as a single PHP file with no external libraries

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- User role: `edit_posts` (for reordering), `manage_options` (for settings)

## Installation

1. Upload the `wj-post-order` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins > Installed Plugins**
3. Go to **Settings > WJ Post Order** to choose which post types are orderable

## Usage

### Reorder Screen

Navigate to **Posts > Reorder** (or the equivalent for any enabled CPT). Drag items into the desired order and click **Save Order**.

### Inline List Table

On the standard **All Posts** screen, drag the `⋮⋮` handle next to any post. Order saves automatically on drop.

### Frontend Ordering

With **Auto-apply on Frontend** enabled (default), all archives and loops for enabled post types will be ordered by `menu_order` ascending, with `date` descending as a tiebreaker.

To skip auto-ordering on a specific query:

```php
$query = new WP_Query([
    'post_type'    => 'post',
    'wjpo_no_sort' => 1,
    // ... your own orderby
]);
```

### Settings

Go to **Settings > WJ Post Order** to:

- Choose which public post types support drag-and-drop ordering
- Toggle frontend auto-apply on or off

## File Structure

```
wj-post-order/
├── wj-post-order.php   # Main plugin file
├── README.md
└── LICENSE
```

## How It Works

The plugin hooks into `pre_get_posts` at priority 999. On the frontend it sets `orderby => ['menu_order' => 'ASC', 'date' => 'DESC']` on any query that:

- Belongs to an enabled post type
- Has not opted out via `wjpo_no_sort`

Page builders like Divi pass `orderby => 'date'` in their module queries (the WordPress default). The plugin overrides this so that saved ordering is respected in Divi loops, Blog modules, and other secondary queries. Use `wjpo_no_sort => 1` on any query that genuinely needs a different sort order.

Order is persisted via `wp_update_post()` with a direct database fallback (`$wpdb->update`) for environments that skip standard WP hooks (e.g., custom table prefixes).

## Security

- All AJAX endpoints are nonce-verified and capability-checked
- All output is escaped with `esc_html()` / `esc_attr()`
- Post type and term inputs are sanitized via `sanitize_key()` / `absint()`
- IDs are cast to integers before any database interaction

## License

This project is licensed under the GNU General Public License v2.0 or later — see the [LICENSE](LICENSE) file for details.

## Author

WebJIVE — [https://www.web-jive.com](https://www.web-jive.com)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Changelog

### Version 1.3.2
- Fix: frontend ordering now applies inside Divi's AJAX-filtered grids/modules (previously `is_admin()` returned `true` for `admin-ajax.php` requests, causing the hook to skip Divi's filterable module queries entirely)

### Version 1.3.1
- Fix: frontend ordering now applies to Divi Blog/Portfolio modules and other page builder queries that pass an explicit `orderby` parameter (previously the plugin skipped these queries)

### Version 1.3.0
- Add "Apply Custom Post Order" checkbox to nav menu items (`Appearance > Menus`)
- Checkbox enables `menu_order` ordering per post type archive, independent of the global toggle

### Version 1.2.4
- Initial public release
- Drag-and-drop reorder screen for Posts and CPTs
- Inline list table ordering with auto-save on drop
- Frontend auto-apply via `pre_get_posts`
- Settings page for post type selection and frontend toggle
- Direct DB fallback for `menu_order` updates
