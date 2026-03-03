<?php

namespace BarefootEngine\Properties;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Post_Type
{
    public const POST_TYPE = 'be_property';

    public function register(): void
    {
        register_post_type(
            self::POST_TYPE,
            [
                'labels' => [
                    'name' => __('Properties', 'barefoot-engine'),
                    'singular_name' => __('Property', 'barefoot-engine'),
                    'menu_name' => __('Properties', 'barefoot-engine'),
                    'all_items' => __('All Properties', 'barefoot-engine'),
                    'edit_item' => __('Edit Property', 'barefoot-engine'),
                    'view_item' => __('View Property', 'barefoot-engine'),
                    'search_items' => __('Search Properties', 'barefoot-engine'),
                    'not_found' => __('No properties found.', 'barefoot-engine'),
                    'not_found_in_trash' => __('No properties found in Trash.', 'barefoot-engine'),
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => true,
                'show_in_admin_bar' => false,
                'show_in_rest' => false,
                'publicly_queryable' => false,
                'exclude_from_search' => true,
                'has_archive' => false,
                'hierarchical' => false,
                'supports' => ['title'],
                'menu_icon' => 'dashicons-admin-home',
                'menu_position' => 57,
                'map_meta_cap' => true,
                'capabilities' => [
                    'create_posts' => 'do_not_allow',
                ],
            ]
        );
    }
}
