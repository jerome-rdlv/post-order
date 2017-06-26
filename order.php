<?php

add_action('wp_loaded', array(RdlvOrder::getInstance(), 'init'));

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

    public function init()
    {
        add_action('pre_get_posts', array($this, 'postsOrder'));
        add_action('pre_get_terms', array($this, 'termsOrder'));
        add_action('wp_ajax_update_order', array($this, 'updateOrder'));
        add_filter('edit_posts_per_page', array($this, 'adminPostPerPage'), 10, 2);

        wp_register_script('rdlv-order-main', plugin_dir_url(__FILE__) . '/order.js', array('jquery', 'jquery-ui-sortable'), false, true);
        add_action('admin_enqueue_scripts', array($this, 'adminEnqueueScripts'));

        add_action('admin_notices', array($this, 'adminNotices'));
    }

    public function adminNotices()
    {
        global $pagenow;
        $notice = '<div class="notice notice-info is-dismissible"><p>Vous pouvez modifier l’ordre des éléments par glisser-déposer.</p></div>';

        if ($pagenow === 'edit-tags.php') {
            global $taxonomy;
            if (array_search($taxonomy, apply_filters('ordered_taxos', array())) !== false) {
                echo $notice;
            }
        }
        if ($pagenow === 'edit.php') {
            global $post_type;
            if (array_search($post_type , apply_filters('ordered_types', array())) !== false) {
                echo $notice;
            }
        }
    }

    public function adminEnqueueScripts()
    {
        wp_enqueue_script('rdlv-order-main');

        $types = array_merge(
            array_map(function ($item) {
                return 'post-type-'. $item;
            }, apply_filters('ordered_types', array())),
            array_map(function ($item) {
                return 'taxonomy-'. $item;
            }, apply_filters('ordered_taxos', array()))
        );

        wp_localize_script('rdlv-order-main', 'rdlv_order', array(
            'update_order_url' => admin_url('admin-ajax.php'),
            'update_order_nonce' => wp_create_nonce('update_order_nonce'),
            'types' => $types
        ));
    }

    public function updateOrder()
    {
        if (wp_verify_nonce($_REQUEST['nonce'], 'update_order_nonce') === false) {
            exit;
        }

        if (!is_array($_REQUEST['order'])) {
            exit;
        }

        global $wpdb;

        if (!empty($_REQUEST['taxonomy'])) {
            foreach ($_REQUEST['order'] as $order => $term_id) {
                update_term_meta($term_id, 'term_order', $order);
            }
        }
        elseif (!empty($_REQUEST['post_type'])) {
            $query = "UPDATE $wpdb->posts SET menu_order = %d WHERE ID = %d AND post_type = '%s'";
            foreach ($_REQUEST['order'] as $order => $postId) {
                $wpdb->query($wpdb->prepare($query, $order, $postId, $_REQUEST['post_type']));
            }
        }
    }

    public function adminPostPerPage($posts_per_page, $post_type)
    {
        if (in_array($post_type, apply_filters('ordered_types', array()))) {
            return -1;
        }
        return $posts_per_page;
    }

    public function postsOrder(WP_Query $query)
    {
        if ($query->is_post_type_archive(apply_filters('ordered_types', array()))) {
            $query->set('orderby', 'menu_order');
            $query->set('order', 'ASC');
        }
    }

    public function termsOrder(WP_Term_Query $query)
    {
        if (array_intersect(apply_filters('ordered_taxos', array()), $query->query_vars['taxonomy'])) {
            $query->query_vars['orderby'] = 'meta_value_num';
            $query->query_vars['order'] = 'ASC';
            $query->query_vars['meta_key'] = 'term_order';
            $query->meta_query = new WP_Meta_Query(array(
                array(
                    'key' => 'term_order',
                    'value' => 0,
                    'compare' => 'GREATER'
                )
            ));
//            $query->query_vars['meta_value'] = '1';
        }
    }
}