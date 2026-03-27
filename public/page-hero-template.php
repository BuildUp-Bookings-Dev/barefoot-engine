<?php

namespace BarefootEngine\Core;

use Elementor\Element_Base;
use Elementor\Plugin as Elementor_Plugin;

if (!defined('ABSPATH')) {
    exit;
}

class Page_Hero_Template
{
    private const TEMPLATE_ID = 915;
    private const HERO_CONTAINER_ID = 'tmplhero1';

    public function maybe_apply_featured_image(Element_Base $element): void
    {
        if (is_admin() || !is_page() || is_front_page()) {
            return;
        }

        if (!$this->is_target_hero($element)) {
            return;
        }

        $featured_image_url = get_the_post_thumbnail_url(get_queried_object_id(), 'full');
        if (!is_string($featured_image_url) || $featured_image_url === '') {
            return;
        }

        $element->add_render_attribute(
            '_wrapper',
            'style',
            sprintf('background-image:url("%s");', esc_url_raw($featured_image_url))
        );
    }

    private function is_target_hero(Element_Base $element): bool
    {
        if ($element->get_id() !== self::HERO_CONTAINER_ID) {
            return false;
        }

        $document = Elementor_Plugin::$instance->documents->get_current();
        if (!$document || !method_exists($document, 'get_main_id')) {
            return false;
        }

        return (int) $document->get_main_id() === self::TEMPLATE_ID;
    }
}
