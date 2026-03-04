<?php
/**
 * Plugin Name: WJ Post Order (Drag & Drop)
 * Description: Drag-and-drop ordering for Posts (and selected CPTs). Saves to menu_order and applies that order on the frontend—including Divi/secondary queries—unless a query explicitly sets its own order.
 * Version: 1.2.4
 * Author: WebJIVE
 * License: GPLv2 or later
 * Text Domain: wj-post-order
 */

if (!defined('ABSPATH')) exit;

final class WJ_Post_Order {
    const OPTION_KEY = 'wjpo_options';
    const NONCE_SAVE = 'wjpo_save_order';
    const NONCE_LIST = 'wjpo_list_nonce';

    public function __construct() {
        add_action('init',                     [$this, 'init_support']);
        add_action('admin_init',               [$this, 'admin_init']);
        add_action('admin_menu',               [$this, 'register_admin_pages']);
        add_action('admin_enqueue_scripts',    [$this, 'enqueue_admin']);
        add_action('wp_ajax_wjpo_save_order',  [$this, 'ajax_save_order']);

        // Frontend: respect menu_order for ALL queries (main + secondary like Divi), unless orderby is explicitly set.
        add_action('pre_get_posts',            [$this, 'apply_all_frontend_order'], 999);

        // Admin list: show posts in saved order on edit.php for enabled post types
        add_action('pre_get_posts',            [$this, 'apply_admin_list_order'], 999);
    }

    /* -------------------- Options -------------------- */

    private function defaults() {
        return [
            'post_types'     => ['post'],
            'apply_frontend' => 1,
        ];
    }

    private function opts() {
        return wp_parse_args(get_option(self::OPTION_KEY, []), $this->defaults());
    }

    private function enabled_types() {
        $o   = $this->opts();
        $pts = array_values(array_unique((array)($o['post_types'] ?? ['post'])));
        if (!in_array('post', $pts, true)) array_unshift($pts, 'post');
        return $pts;
    }

    /* -------------------- Setup -------------------- */

    public function init_support() {
        foreach ($this->enabled_types() as $pt) {
            add_post_type_support($pt, 'page-attributes'); // enables menu_order
        }
    }

    public function admin_init() {
        // Posts screen (generic hooks)
        add_filter('manage_posts_columns',       [$this, 'add_order_column_posts'], 999);
        add_filter('manage_edit-post_columns',   [$this, 'add_order_column_posts'], 999);
        add_action('manage_posts_custom_column', [$this, 'render_order_column'], 10, 2);

        // CPT-specific hooks
        foreach ($this->enabled_types() as $pt) {
            if ($pt === 'post') continue;
            add_filter("manage_edit-{$pt}_columns", function($cols){ return WJ_Post_Order::insert_order_column($cols); }, 999);
            add_action("manage_{$pt}_posts_custom_column", [$this, 'render_order_column'], 10, 2);
        }

        // Ensure our column isn't hidden by default on Posts
        add_filter('default_hidden_columns', function($hidden, $screen){
            if (!empty($screen->id) && $screen->id === 'edit-post') {
                $hidden = array_diff($hidden, ['wjpo_order']);
            }
            return $hidden;
        }, 10, 2);

        // Settings
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [$this, 'sanitize_options']);
        add_settings_section('wjpo_main', __('General', 'wj-post-order'), function(){
            echo '<p>' . esc_html__('Choose orderable post types and whether to auto-apply order on the frontend.', 'wj-post-order') . '</p>';
        }, 'wjpo-settings');
        add_settings_field('post_types', __('Orderable Post Types', 'wj-post-order'), [$this, 'field_post_types'], 'wjpo-settings', 'wjpo_main');
        add_settings_field('apply_frontend', __('Auto-apply on Frontend', 'wj-post-order'), [$this, 'field_apply_frontend'], 'wjpo-settings', 'wjpo_main');
    }

    public static function insert_order_column($cols){
        $new = [];
        foreach ($cols as $key => $label) {
            $new[$key] = $label;
            if ($key === 'cb' && !isset($cols['wjpo_order'])) {
                $new['wjpo_order'] = __('Order', 'wj-post-order');
            }
        }
        if (!isset($new['wjpo_order'])) {
            $new = ['wjpo_order' => __('Order', 'wj-post-order')] + $new;
        }
        return $new;
    }

    public function add_order_column_posts($cols){
        return self::insert_order_column($cols);
    }

    public function render_order_column($col, $post_id){
        if ($col !== 'wjpo_order') return;
        $order = (int) get_post_field('menu_order', $post_id);
        echo '<span class="wjpo-row-handle" title="' . esc_attr__('Drag to reorder', 'wj-post-order') . '">⋮⋮</span>';
        echo '<span class="wjpo-row-order">' . esc_html($order) . '</span>';
        echo '<input type="hidden" class="wjpo-row-id" value="' . esc_attr($post_id) . '" />';
    }

    public function sanitize_options($input){
        $out = $this->opts();
        $out['post_types'] = [];
        if (!empty($input['post_types']) && is_array($input['post_types'])) {
            foreach ($input['post_types'] as $pt) {
                $pt  = sanitize_key($pt);
                $obj = get_post_type_object($pt);
                if ($obj && $obj->public) $out['post_types'][] = $pt;
            }
        }
        $out['apply_frontend'] = !empty($input['apply_frontend']) ? 1 : 0;
        return $out;
    }

    public function field_post_types(){
        $public  = get_post_types(['public' => true], 'objects');
        $enabled = $this->enabled_types();
        echo '<fieldset class="wjpo-pts">';
        foreach ($public as $pt => $obj) {
            $checked = in_array($pt, $enabled, true) ? 'checked' : '';
            echo '<label style="display:block;margin:4px 0;">';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[post_types][]" value="' . esc_attr($pt) . '" ' . $checked . '> ' . esc_html($obj->labels->name) . ' (' . esc_html($pt) . ')';
            echo '</label>';
        }
        echo '</fieldset>';
    }

    public function field_apply_frontend(){
        $opts = $this->opts();
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[apply_frontend]" value="1" ' . checked(1, (int) $opts['apply_frontend'], false) . '> ' . esc_html__('Order archives by menu_order automatically', 'wj-post-order') . '</label>';
    }

    /* -------------------- Admin Pages -------------------- */

    public function register_admin_pages(){
        add_submenu_page('edit.php', __('Reorder Posts', 'wj-post-order'), __('Reorder', 'wj-post-order'), 'edit_posts', 'wjpo-reorder-posts', [$this, 'render_reorder_screen']);
        add_options_page(__('WJ Post Order Settings', 'wj-post-order'), __('WJ Post Order', 'wj-post-order'), 'manage_options', 'wjpo-settings', [$this, 'render_settings']);
    }

    public function render_settings(){
        if (!current_user_can('manage_options')) wp_die(__('You do not have permission.', 'wj-post-order'));
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('WJ Post Order Settings', 'wj-post-order') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION_KEY);
        do_settings_sections('wjpo-settings');
        submit_button();
        echo '</form>';
        echo '</div>';
        echo '<style>.wjpo-pts label{font-weight:500}</style>';
    }

    public function render_reorder_screen(){
        if (!current_user_can('edit_posts')) wp_die(__('You do not have permission.', 'wj-post-order'));

        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';
        if (!in_array($post_type, $this->enabled_types(), true)) $post_type = 'post';

        // Safe taxonomy detection
        if ($post_type === 'post') {
            $tax = 'category';
        } else {
            $taxes = get_object_taxonomies($post_type, 'names');
            $tax   = (is_array($taxes) && !empty($taxes)) ? reset($taxes) : '';
        }

        $term_id = isset($_GET['term_id']) ? absint($_GET['term_id']) : 0;

        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
            'order'          => 'ASC',
            'post_status'    => ['publish', 'future', 'draft', 'private'],
        ];
        if ($term_id && $tax) {
            $args['tax_query'] = [[
                'taxonomy' => $tax,
                'field'    => 'term_id',
                'terms'    => $term_id,
            ]];
        }

        $posts = get_posts($args);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Reorder Posts', 'wj-post-order') . '</h1>';
        echo '<p class="description">' . esc_html__('Drag posts to reorder. The order is saved to the built-in menu_order field.', 'wj-post-order') . '</p>';
        echo '<div id="wjpo-status" aria-live="polite"></div>';
        echo '<ul id="wjpo-sortable" class="wjpo-list">';
        foreach ($posts as $p){
            echo '<li class="wjpo-item" data-id="' . esc_attr($p->ID) . '">';
            echo '<span class="wjpo-handle" aria-hidden="true">⇅</span> ' . esc_html(get_the_title($p)) . ' <span class="wjpo-meta">#' . (int)$p->menu_order . '</span>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<button id="wjpo-save" class="button button-primary">' . esc_html__('Save Order','wj-post-order') . '</button>';
        echo '</div>';
    }

    /* -------------------- Assets -------------------- */

    public function enqueue_admin($hook){
        if (!current_user_can('edit_posts')) return;

        // Common deps
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');

        /* Reorder page assets */
        if (isset($_GET['page']) && $_GET['page'] === 'wjpo-reorder-posts') {
            $css = <<<'CSS'
.wjpo-list{margin-top:12px;max-width:920px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:0}
.wjpo-item{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid #eee;cursor:move}
.wjpo-item:last-child{border-bottom:none}
.wjpo-handle{font-weight:700}
.wjpo-meta{color:#666;margin-left:auto}
#wjpo-status{margin:8px 0}
CSS;
            wp_register_style('wjpo-reorder', false, [], '1.2.4');
            wp_enqueue_style('wjpo-reorder');
            wp_add_inline_style('wjpo-reorder', $css);

            $nonce = wp_create_nonce(self::NONCE_SAVE);
            $js = <<<JS
(function($){
  var \$list = $('#wjpo-sortable');
  if(!\$list.length) return;

  \$list.sortable({ handle: '.wjpo-item' });

  $('#wjpo-save').on('click', function(e){
    e.preventDefault();
    var ids = \$list.children('.wjpo-item').map(function(){ return $(this).data('id'); }).get();
    $('#wjpo-status').text('Saving…');
    $.post(ajaxurl, { action: 'wjpo_save_order', nonce: '{$nonce}', ids: ids }, function(resp){
      $('#wjpo-status').text(resp && resp.success ? 'Order saved' : 'Could not save order');
    }).fail(function(){
      $('#wjpo-status').text('Could not save order');
    });
  });
})(jQuery);
JS;
            wp_register_script('wjpo-reorder', false, ['jquery','jquery-ui-sortable'], '1.2.4', true);
            wp_enqueue_script('wjpo-reorder');
            wp_add_inline_script('wjpo-reorder', $js);
        }

        /* List table (All Posts/CPTs) assets with fallback handle injection */
        if ($hook === 'edit.php') {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            $pt_on_screen = $screen && !empty($screen->post_type) ? $screen->post_type : (isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post');
            if (!in_array($pt_on_screen, $this->enabled_types(), true)) return;

            $css = <<<'CSS'
.column-wjpo_order{width:72px}
.wjpo-row-handle{cursor:move;font-size:16px;margin-right:6px;display:inline-block;vertical-align:middle}
.wjpo-row-order{color:#666;font-size:11px;vertical-align:middle}
.wjpo-fallback-handle{cursor:move;margin-right:6px;opacity:.8;display:inline-block}
.ui-sortable-placeholder{background:#f6f7f7;height:48px;border:2px dashed #ccd0d4}
#wjpo-list-notice{margin:8px 0}
CSS;
            wp_register_style('wjpo-list', false, [], '1.2.4');
            wp_enqueue_style('wjpo-list');
            wp_add_inline_style('wjpo-list', $css);

            $nonce_list = wp_create_nonce(self::NONCE_LIST);
            $js = <<<JS
(function($){
  var \$tbody = $('#the-list');
  if(!\$tbody.length) return;

  function ensureHandle(){
    var hasColumn = $('th.column-wjpo_order, td.column-wjpo_order').length > 0;
    if(hasColumn) return '.wjpo-row-handle';

    \$tbody.children('tr').each(function(){
      var \$row = $(this);
      var \$titleCell = \$row.find('td.title, td.column-title').first();
      if(!\$titleCell.length) return;
      if(\$titleCell.find('.wjpo-fallback-handle').length) return;

      if(!\$row.find('.wjpo-row-id').length){
        var m = (\$row.attr('id')||'').match(/post-(\\d+)/);
        if(m) $('<input/>',{type:'hidden','class':'wjpo-row-id',value:m[1]}).appendTo(\$titleCell);
      }
      var \$target = \$titleCell.find('.row-title').first();
      if(!\$target.length) \$target = \$titleCell;
      $('<span class="wjpo-fallback-handle">⋮⋮</span>').prependTo(\$target);
    });
    return '.wjpo-fallback-handle';
  }

  function idsFromDOM(){
    return \$tbody.children('tr').map(function(){
      var \$r = $(this), id = \$r.find('.wjpo-row-id').val();
      if(!id){
        var m = (\$r.attr('id')||'').match(/post-(\\d+)/);
        if(m) id = m[1];
      }
      return id ? parseInt(id,10) : null;
    }).get().filter(Boolean);
  }

  var handleSel = ensureHandle();
  var \$notice = $('<div id="wjpo-list-notice" class="notice inline notice-info"><p>Drag rows to reorder. Affects only this page; use Screen Options to show more items.</p></div>');
  $('#posts-filter .tablenav.top').after(\$notice);

  \$tbody.sortable({
    items: '> tr',
    handle: handleSel,
    helper: function(e,ui){ ui.children().each(function(){ $(this).width($(this).width()); }); return ui; },
    placeholder: 'ui-sortable-placeholder',
    update: function(){
      var ids = idsFromDOM();
      if(!ids.length) return;
      var \$sp = $('<span class="spinner is-active" style="float:none;margin:0 6px;"></span>');
      \$notice.find('p').text('Saving order…').append(\$sp);
      $.post(ajaxurl, { action:'wjpo_save_order', nonce:'{$nonce_list}', ids: ids }, function(resp){
        \$sp.remove();
        if(resp && resp.success){
          \$notice.removeClass('notice-error').addClass('notice-success');
          \$notice.find('p').text('Order saved');
        } else {
          \$notice.removeClass('notice-success').addClass('notice-error');
          \$notice.find('p').text('Save failed');
        }
      }).fail(function(){
        \$sp.remove();
        \$notice.removeClass('notice-success').addClass('notice-error');
        \$notice.find('p').text('Save failed');
      });
    }
  });

  $(document).ajaxComplete(function(){ handleSel = ensureHandle(); });
})(jQuery);
JS;
            wp_register_script('wjpo-list', false, ['jquery','jquery-ui-sortable'], '1.2.4', true);
            wp_enqueue_script('wjpo-list');
            wp_add_inline_script('wjpo-list', $js);
        }
    }

    /* -------------------- AJAX -------------------- */

    public function ajax_save_order(){
        if (!current_user_can('edit_posts')) wp_send_json_error(['message'=>__('Permission denied','wj-post-order')], 403);

        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_SAVE) && !wp_verify_nonce($nonce, self::NONCE_LIST)) {
            wp_send_json_error(['message'=>__('Bad nonce','wj-post-order')], 403);
        }

        $ids = isset($_POST['ids']) ? array_map('absint', (array) $_POST['ids']) : [];
        if (!$ids) wp_send_json_error(['message'=>__('No IDs received','wj-post-order')], 400);

        global $wpdb;
        $ok = true;

        foreach ($ids as $i => $id) {
            $pt = get_post_type($id);
            if (!$pt || !in_array($pt, $this->enabled_types(), true)) continue;

            // Try standard WP update first
            $res = wp_update_post(['ID' => $id, 'menu_order' => $i], true);

            if (is_wp_error($res)) {
                // Fallback direct DB update (respects custom prefix, e.g., jzue_posts)
                $updated = $wpdb->update(
                    $wpdb->posts,
                    ['menu_order' => $i],
                    ['ID' => $id],
                    ['%d'],
                    ['%d']
                );
                if ($updated === false) {
                    $ok = false;
                    continue;
                }
                clean_post_cache($id);
            } else {
                clean_post_cache($id);
            }
        }

        if ($ok) {
            wp_send_json_success(['message'=>__('Saved','wj-post-order')]);
        } else {
            wp_send_json_error(['message'=>__('Update failed','wj-post-order')]);
        }
    }

    /* -------------------- Query Ordering -------------------- */

    /**
     * Apply menu_order to ALL frontend queries (main + secondary, e.g., Divi modules)
     * unless the query explicitly sets its own 'orderby' or the site owner disables it via 'wjpo_no_sort' => 1.
     */
    public function apply_all_frontend_order($q){
        if (is_admin() || !($q instanceof WP_Query)) return;

        $o = $this->opts();
        if (empty($o['apply_frontend'])) return;

        // Allow opt-out per query: set 'wjpo_no_sort' => 1
        if ((int) $q->get('wjpo_no_sort') === 1) return;

        // Respect explicit orderby set by the theme/module
        if ($q->get('orderby')) return;

        // Only target enabled post types
        $pt = $q->get('post_type');
        if (empty($pt)) {
            $pt = 'post';
        }
        // Normalize to array
        $pts = (array) $pt;
        $enabled = $this->enabled_types();
        $applies = count(array_intersect($pts, $enabled)) > 0;
        if (!$applies) return;

        $q->set('orderby', ['menu_order' => 'ASC', 'date' => 'DESC']);
        $q->set('order', 'ASC');
    }

    // Admin list: show saved order on edit.php for enabled post types
    public function apply_admin_list_order($q){
        if (!is_admin() || !$q->is_main_query()) return;
        global $pagenow;
        if ($pagenow !== 'edit.php') return;

        $post_type = $q->get('post_type') ?: 'post';
        if (!in_array($post_type, $this->enabled_types(), true)) return;

        // Respect explicit user sorting (e.g., clicking column headers)
        if (!empty($_GET['orderby'])) return;

        $q->set('orderby', ['menu_order'=>'ASC','date'=>'DESC']);
        $q->set('order', 'ASC');
    }
}

new WJ_Post_Order();