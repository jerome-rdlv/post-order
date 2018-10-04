<?php

add_action('wp_loaded', [RdlvOrder::getInstance(), 'init']);

class RdlvOrder
{
    private static $instance = null;

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    const AJAX_ACTION = 'rdlv_update_order';

    public function init()
    {
        add_action('pre_get_posts', [$this, 'postsOrder']);
        add_action('pre_get_terms', [$this, 'termsOrder']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'updateOrder']);
        add_filter('edit_posts_per_page', [$this, 'adminPostPerPage'], 10, 2);

        wp_register_script('rdlv-order', plugin_dir_url(__FILE__) . '/order.js', ['jquery', 'jquery-ui-sortable'], false, true);
        wp_register_style('rdlv-order', plugin_dir_url(__FILE__) . '/order.css');
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
        $type = null;
        
        if (is_post_type_archive() && apply_filters('is_post_type_ordered', false, get_post_type())) {
            $type = 'post-type-'. get_post_type();
        }
        else {
            global $wp_list_table;
            if (isset($wp_list_table->screen->taxonomy)) {
                $taxonomy = $wp_list_table->screen->taxonomy;
                if (apply_filters('is_taxonomy_ordered', false, $taxonomy)) {
                    $type = 'taxonomy-'. $taxonomy;
                }
            }
        }

        if ($type) {
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
        }
        elseif (!empty($_REQUEST['post_type'])) {
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
        $query->meta_query = new WP_Meta_Query([
            'relation' => 'OR',
            [
                'key' => 'term_order',
                'type' => 'NUMERIC',
                'compare' => 'EXISTS'
            ],
            [
                'key' => 'term_order',
                'type' => 'NUMERIC',
                'compare' => 'NOT EXISTS'
            ],
        ]);
    }
}