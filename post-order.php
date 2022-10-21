<?php

namespace Rdlv\WordPress\PostOrder;

use WP_Meta_Query;
use WP_Post;
use WP_Posts_List_Table;
use WP_Query;
use WP_Term_Query;
use WP_Terms_List_Table;

new PostOrder();

class PostOrder
{
    const AJAX_ACTION = 'rdlv_update_order';

    public function __construct()
    {
        add_action('wp_loaded', [$this, 'init']);
    }

    public function init()
    {
        add_action('pre_get_posts', [$this, 'postsOrder']);
        add_action('pre_get_terms', [$this, 'termsOrder']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'updateOrder']);
        add_filter('edit_posts_per_page', [$this, 'adminPostPerPage'], 10, 2);

        add_filter('get_previous_post_where', [$this, 'adjacentPostWhere'], 9, 5);
        add_filter('get_next_post_where', [$this, 'adjacentPostWhere'], 9, 5);
        add_filter('get_previous_post_sort', [$this, 'adjacentPostSort'], 10, 3);
        add_filter('get_next_post_sort', [$this, 'adjacentPostSort'], 10, 3);

        $pluginUrl = plugin_dir_url(__FILE__);
        wp_register_script('rdlv-order', $pluginUrl . '/order.js', ['jquery', 'jquery-ui-sortable'], false, true);
        wp_register_style('rdlv-order', $pluginUrl . '/order.css');
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        // useless since sort handle has been added
//        add_action('admin_notices', [$this, 'adminNotices']);

        // add meta to be able to order
        $taxonomies = get_taxonomies();
        foreach ($taxonomies as $taxonomy) {
            if (apply_filters('is_taxonomy_ordered', false, $taxonomy)) {
                add_action('created_' . $taxonomy, function ($term_id) {
                    add_term_meta($term_id, 'term_order', 0);
                });
            }
        }
    }

    public function adminNotices()
    {
        global $pagenow;
        $notice = '<div class="notice notice-info is-dismissible"><p>Vous pouvez modifier l’ordre des éléments par glisser-déposer.</p></div>';

        if ($pagenow === 'edit-tags.php') {
            global $taxonomy;
            if (apply_filters('is_taxonomy_ordered', false, $taxonomy)) {
                echo $notice;
            }
        }
        if ($pagenow === 'edit.php') {
            global $post_type;
            if (apply_filters('is_post_type_ordered', false, $post_type)) {
                echo $notice;
            }
        }
    }

    public function adminEnqueueScripts()
    {
        global $current_screen, $wp_list_table;
       
        $kinds = [
            WP_Posts_List_Table::class => 'post_type',
            WP_Terms_List_Table::class => 'taxonomy',
        ];

        if (!($kind = $kinds[get_class($wp_list_table)] ?? null)) {
            return;
        }

        if (!($slug = $current_screen->{$kind})) {
            return;
        }

        if ($type = apply_filters(sprintf('is_%s_ordered', $kind), false, $slug) ? $slug : null) {
            wp_enqueue_script('rdlv-order');
            wp_enqueue_style('rdlv-order');

            wp_localize_script('rdlv-order', 'rdlv_order', [
                'action' => self::AJAX_ACTION,
                'update_order_url' => admin_url('admin-ajax.php'),
                'update_order_nonce' => wp_create_nonce('update_order_nonce'),
                'type' => $type,
            ]);
        }
    }

    public function updateOrder()
    {
        if (wp_verify_nonce($_REQUEST['nonce'], 'update_order_nonce') === false) {
            exit;
        }

        if (!is_array($_REQUEST['order'])) {
            exit;
        }

        if (!empty($_REQUEST['taxonomy'])) {
            if (apply_filters('is_taxonomy_ordered', false, $_REQUEST['taxonomy'])) {
                foreach ($_REQUEST['order'] as $order => $term_id) {
                    update_term_meta($term_id, 'term_order', $order);
                }
            }
        } elseif (!empty($_REQUEST['post_type'])) {
            if (apply_filters('is_post_type_ordered', false, $_REQUEST['post_type'])) {
                global $wpdb;
                $query = "UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d AND post_type = '%s'";
                foreach ($_REQUEST['order'] as $order => $postId) {
                    $wpdb->query($wpdb->prepare($query, $order, $postId, $_REQUEST['post_type']));
                }
            }
        }
    }

    public function adminPostPerPage($posts_per_page, $post_type)
    {
        if (apply_filters('is_post_type_ordered', false, $post_type)) {
            return -1;
        }
        return $posts_per_page;
    }

    public function postsOrder(WP_Query $query)
    {
        if (!$query->is_main_query()) {
            return;
        }
        if (!apply_filters('is_post_type_ordered', false, $query->get('post_type'))) {
            return;
        }
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }

    public function adjacentPostSort(string $orderBy, WP_Post $post, string $order): string
    {
        if (!apply_filters('is_post_type_ordered', false, $post->post_type)) {
            return $orderBy;
        }
        return "ORDER BY p.menu_order $order LIMIT 1";
    }

    public function adjacentPostWhere(string $where, $sameTerm, $excluded, $taxo, WP_Post $post): string
    {
        if (!apply_filters('is_post_type_ordered', false, $post->post_type)) {
            return $where;
        }
        if (!preg_match('/ p.post_date (<|>) /', $where, $matches)) {
            return $where;
        }

        return sprintf(
            'WHERE p.post_type = "%s" AND p.menu_order %s %s',
            $post->post_type,
            $matches[1],
            $post->menu_order
        );
    }

    public function termsOrder(WP_Term_Query $query)
    {
        $ordered = false;
        foreach ($query->query_vars['taxonomy'] as $taxo) {
            if (apply_filters('is_taxonomy_ordered', false, $taxo)) {
                $ordered = true;
                break;
            }
        }
        if (!$ordered) {
            return;
        }

        $query->query_vars['orderby'] = 'meta_value_num';
        $query->query_vars['order'] = 'ASC';
        $query->meta_query = new WP_Meta_Query(
            [
                'relation' => 'OR',
                [
                    'key' => 'term_order',
                    'type' => 'NUMERIC',
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => 'term_order',
                    'type' => 'NUMERIC',
                    'compare' => 'NOT EXISTS',
                ],
            ]
        );
    }
}