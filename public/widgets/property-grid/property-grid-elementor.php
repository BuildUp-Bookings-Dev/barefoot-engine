<?php

namespace BarefootEngine\Widgets\PropertyGrid;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Grid_Elementor
{
    public const CATEGORY = 'barefoot-engine';

    public function register(): void
    {
        if (!did_action('elementor/loaded')) {
            return;
        }

        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
    }

    /**
     * @param mixed $elements_manager
     */
    public function register_category($elements_manager): void
    {
        if (!is_object($elements_manager) || !method_exists($elements_manager, 'add_category')) {
            return;
        }

        $elements_manager->add_category(
            self::CATEGORY,
            [
                'title' => __('Barefoot Engine', 'barefoot-engine'),
                'icon' => 'fa fa-plug',
            ]
        );
    }

    /**
     * @param mixed $widgets_manager
     */
    public function register_widgets($widgets_manager): void
    {
        if (!class_exists('\Elementor\Widget_Base')) {
            return;
        }

        if (!is_object($widgets_manager) || !method_exists($widgets_manager, 'register')) {
            return;
        }

        $widgets_manager->register(new Property_Grid_Elementor_Widget());
    }
}
