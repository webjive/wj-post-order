# CLAUDE.md — WJ Post Order

## Project Overview

**WJ Post Order (Drag & Drop)** is a single-file WordPress plugin that provides drag-and-drop post ordering. It stores order in WordPress's built-in `menu_order` field and applies it on the frontend and in admin list tables.

- **Version**: 1.3.0
- **Author**: WebJIVE (Eric Caldwell)
- **License**: GPLv2 or later
- **WordPress requirement**: 5.0+
- **PHP requirement**: 7.4+
- **Text Domain**: `wj-post-order`

---

## Repository Structure

```
wj-post-order/
├── wj-post-order.php   # Entire plugin — single file, 513 lines
├── README.md           # GitHub-style documentation
├── readme.txt          # WordPress.org plugin registry format
└── LICENSE             # GPLv2
```

There are **no build tools, no package managers, no asset pipeline, no test suite, and no external dependencies**. All CSS and JavaScript are inlined via `wp_add_inline_style()` / `wp_add_inline_script()`.

---

## Architecture

The entire plugin is one PHP class instantiated at the bottom of the file:

```php
final class WJ_Post_Order { ... }
new WJ_Post_Order();
```

### Class Constants

| Constant | Value | Purpose |
|---|---|---|
| `OPTION_KEY` | `wjpo_options` | WordPress options table key |
| `NONCE_SAVE` | `wjpo_save_order` | Nonce for reorder page saves |
| `NONCE_LIST` | `wjpo_list_nonce` | Nonce for list table saves |

### Settings Schema

Stored under `wjpo_options` in `wp_options`:

```php
[
    'post_types'     => ['post'],  // array of enabled post type slugs
    'apply_frontend' => 1,         // 0 or 1
]
```

`post` is always included in `enabled_types()` even if absent from the saved array.

### Data Storage

- **Post order**: WordPress's native `menu_order` column in `wp_posts` — no custom tables.
- **Nav menu item flag**: `_wjpo_apply_order` post meta on `nav_menu_item` posts (value `'1'` or `'0'`).

---

## WordPress Hooks

### Registered in `__construct()`

| Hook | Method | Notes |
|---|---|---|
| `init` | `init_support()` | Adds `page-attributes` support to enabled post types (enables `menu_order`) |
| `admin_init` | `admin_init()` | Registers settings, columns |
| `admin_menu` | `register_admin_pages()` | Adds reorder screen and settings page |
| `admin_enqueue_scripts` | `enqueue_admin()` | Inline CSS/JS for drag-drop UI |
| `wp_ajax_wjpo_save_order` | `ajax_save_order()` | AJAX endpoint (authenticated users only) |
| `wp_nav_menu_item_custom_fields` | `menu_item_field()` | Checkbox on menu item edit |
| `wp_update_nav_menu_item` | `save_menu_item_field()` | Saves menu item checkbox |
| `pre_get_posts` (priority 999) | `apply_all_frontend_order()` | Frontend ordering |
| `pre_get_posts` (priority 999) | `apply_admin_list_order()` | Admin list ordering |

---

## Admin Screens

### Reorder Screen — Posts > Reorder (`?page=wjpo-reorder-posts`)

- Full-page jQuery UI Sortable list of all posts for the selected post type.
- Supports optional taxonomy term filtering via `?term_id=`.
- Save button triggers AJAX to `wjpo_save_order`.
- Requires `edit_posts` capability.

### List Table Inline Ordering — All Posts / All CPTs (`edit.php`)

- Adds an **Order** column (`wjpo_order`) with a drag handle (`⋮⋮`).
- Rows are jQuery UI Sortable; order saves automatically on drop.
- Fallback: if the column is hidden, injects a handle into the Title column.
- Requires `edit_posts` capability.

### Settings Page — Settings > WJ Post Order (`?page=wjpo-settings`)

- Checkbox list of all public post types.
- Toggle for "Auto-apply on Frontend".
- Requires `manage_options` capability.

---

## Frontend Query Ordering Logic (`apply_all_frontend_order`)

The hook fires at `pre_get_posts` priority 999 on all frontend `WP_Query` instances.

**The hook does nothing if any of these are true:**
1. The query is in admin context (`is_admin()`).
2. The query sets `'wjpo_no_sort' => 1` (per-query opt-out).
3. The query already has an explicit `orderby` set.

**The hook applies `menu_order ASC, date DESC` if either path is active:**
- **Path 1 (global)**: "Auto-apply on Frontend" setting is on AND the query's post type is in `enabled_types()`.
- **Path 2 (menu)**: A nav menu item for the post type archive has "Apply Custom Post Order" checked (uses `get_menu_ordered_types()`, cached per-request with a static variable).

**Per-query opt-out pattern:**
```php
$query = new WP_Query(['post_type' => 'post', 'wjpo_no_sort' => 1, ...]);
```

---

## AJAX Handler (`ajax_save_order`)

**Endpoint**: `wp_ajax_wjpo_save_order`

**Request** (POST):
- `action` — `wjpo_save_order`
- `nonce` — created with either `NONCE_SAVE` or `NONCE_LIST`
- `ids[]` — ordered array of post IDs

**Security checks** (in order):
1. `current_user_can('edit_posts')` → 403 on fail
2. `wp_verify_nonce()` against both nonce keys → 403 on fail
3. Each ID cast via `absint()`
4. Each post's type validated against `enabled_types()`

**Update strategy**:
1. First attempt: `wp_update_post()`.
2. On `WP_Error`: fallback to direct `$wpdb->update()` on `$wpdb->posts`.
3. Always calls `clean_post_cache($id)` after update.

---

## Security Conventions

These patterns are already in use and must be maintained in any modifications:

- All nonces: `wp_verify_nonce()` before processing data.
- Integer IDs: always `absint()` or `(int)` cast.
- Post type slugs: `sanitize_key()`.
- User text: `sanitize_text_field()`.
- HTML output: `esc_html()`.
- Attribute output: `esc_attr()`.
- Capability gates: `current_user_can()` before any privileged action.
- AJAX responses: `wp_send_json_success()` / `wp_send_json_error()` only — never raw `echo`.

---

## Naming Conventions

| Type | Convention | Example |
|---|---|---|
| Class | `WJ_Post_Order` | — |
| Constants | `SCREAMING_SNAKE` | `OPTION_KEY` |
| Public methods | `snake_case` | `ajax_save_order()` |
| Private methods | `_underscore_prefix` | `_defaults()` → uses `defaults()` (no underscore in this file, but prefix `wjpo_` is used on hooks/options) |
| Hook/option names | `wjpo_` prefix | `wjpo_save_order`, `wjpo_options` |
| CSS classes | `wjpo-` prefix | `wjpo-item`, `wjpo-handle` |
| JS variables | `camelCase` | `$tbody`, `handleSel` |
| Post meta keys | `_wjpo_` prefix (private) | `_wjpo_apply_order` |

Code sections are delimited with banner comments:
```php
/* -------------------- Section Name -------------------- */
```

---

## Development Workflow

### No Build Step

There is nothing to compile, bundle, or transpile. Edit `wj-post-order.php` directly.

### Testing

There is no automated test suite. Manual testing requires a WordPress install with the plugin active.

**Key scenarios to test after changes:**
1. Reorder screen drag-and-drop saves correctly.
2. List table inline drag-and-drop saves correctly.
3. Frontend archive respects saved order.
4. Per-query opt-out (`wjpo_no_sort`) prevents ordering.
5. Nav menu item checkbox enables ordering via Path 2.
6. Settings page saves and reads post type selections correctly.
7. AJAX handler rejects bad nonces and insufficient capabilities.

### Deployment

This is a standard WordPress plugin. Deploy by copying `wj-post-order.php` (and optionally `readme.txt`/`README.md`/`LICENSE`) to the target `wp-content/plugins/wj-post-order/` directory.

---

## Common Modification Patterns

### Adding a new setting

1. Add a default in `defaults()`.
2. Add sanitization in `sanitize_options()`.
3. Add a settings field callback and register it in `admin_init()`.
4. Reference via `$this->opts()['your_key']`.

### Supporting a new admin screen feature

1. Add any new column filter/action registration inside `admin_init()`.
2. Add inline CSS to the `$css` heredoc in the appropriate `enqueue_admin()` branch.
3. Add inline JS to the `$js` heredoc in the same branch.

### Modifying query ordering behavior

- Edit `apply_all_frontend_order()` for frontend changes.
- Edit `apply_admin_list_order()` for admin list changes.
- Both use `$q->set('orderby', ...)` — never modify `$q->query_vars` directly.

### Adding a new post meta field to nav menu items

- Display callback: `wp_nav_menu_item_custom_fields` hook → add alongside `menu_item_field()`.
- Save callback: `wp_update_nav_menu_item` hook → add alongside `save_menu_item_field()`.
- Use `_wjpo_` prefix for private meta keys.

---

## Important Constraints

- **Single file**: Keep all code in `wj-post-order.php`. Do not introduce separate files, directories, or an `includes/` structure unless there is a compelling reason.
- **No external dependencies**: Do not add Composer packages, npm packages, or any external libraries. Use only WordPress core APIs and bundled jQuery/jQuery UI.
- **No custom tables**: All data storage must use existing WordPress tables (`wp_options`, `wp_posts`, `wp_postmeta`).
- **Backward compatibility**: The `menu_order` field is WordPress core — changes to how it is written or read can affect themes and other plugins. Be conservative.
- **`post` type is always enabled**: `enabled_types()` always prepends `post`; do not break this invariant.
- **Inline assets only**: CSS and JS are registered as empty handles (`false` src) and appended with `wp_add_inline_style()` / `wp_add_inline_script()`. Keep this pattern.
