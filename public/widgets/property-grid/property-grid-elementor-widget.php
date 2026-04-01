<?php

namespace BarefootEngine\Widgets\PropertyGrid;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Grid_Elementor_Widget extends Widget_Base
{
    public function get_name()
    {
        return 'barefoot-property-grid';
    }

    public function get_title()
    {
        return __('Property Grid', 'barefoot-engine');
    }

    public function get_icon()
    {
        return 'eicon-posts-grid';
    }

    public function get_categories()
    {
        return [Property_Grid_Elementor::CATEGORY];
    }

    public function get_keywords()
    {
        return ['barefoot', 'properties', 'grid', 'rentals', 'vacation'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'barefoot-engine'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label' => __('Columns', 'barefoot-engine'),
                'type' => Controls_Manager::NUMBER,
                'default' => 3,
                'tablet_default' => 2,
                'mobile_default' => 1,
                'min' => 1,
                'max' => 6,
                'step' => 1,
            ]
        );

        $this->add_control(
            'limit',
            [
                'label' => __('Limit', 'barefoot-engine'),
                'type' => Controls_Manager::NUMBER,
                'default' => 9,
                'min' => 1,
                'max' => 100,
                'step' => 1,
            ]
        );

        $this->add_control(
            'paginated',
            [
                'label' => __('Pagination', 'barefoot-engine'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('On', 'barefoot-engine'),
                'label_off' => __('Off', 'barefoot-engine'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_filter',
            [
                'label' => __('Show Filter', 'barefoot-engine'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'barefoot-engine'),
                'label_off' => __('Hide', 'barefoot-engine'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'prefilter_heading',
            [
                'type' => Controls_Manager::HEADING,
                'label' => __('Pre-Filters', 'barefoot-engine'),
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'prefilter_type',
            [
                'label' => __('Type', 'barefoot-engine'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Example: Condo', 'barefoot-engine'),
            ]
        );

        $this->add_control(
            'prefilter_bedrooms',
            [
                'label' => __('Bedrooms', 'barefoot-engine'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => $this->get_bedroom_prefilter_options(),
            ]
        );

        $this->add_control(
            'prefilter_bathrooms',
            [
                'label' => __('Bathrooms', 'barefoot-engine'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => $this->get_bathroom_prefilter_options(),
            ]
        );

        $this->add_control(
            'prefilter_guests',
            [
                'label' => __('Minimum Guests', 'barefoot-engine'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => $this->get_guest_prefilter_options(),
            ]
        );

        $this->add_control(
            'prefilter_rating',
            [
                'label' => __('Grade Letter', 'barefoot-engine'),
                'type' => Controls_Manager::SELECT,
                'default' => '',
                'options' => $this->get_rating_prefilter_options(),
            ]
        );

        $this->add_control(
            'empty_text',
            [
                'label' => __('Empty State Text', 'barefoot-engine'),
                'type' => Controls_Manager::TEXT,
                'default' => __('No properties matched your filters.', 'barefoot-engine'),
            ]
        );

        $this->add_control(
            'extra_class',
            [
                'label' => __('Extra CSS Class', 'barefoot-engine'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $columns_desktop = $this->resolve_responsive_int($settings, 'columns', 'desktop', 3, 1, 6);
        $columns_tablet = $this->resolve_responsive_int($settings, 'columns', 'tablet', 2, 1, 6);
        $columns_mobile = $this->resolve_responsive_int($settings, 'columns', 'mobile', 1, 1, 6);

        $shortcode = new Property_Grid_Shortcode();
        $output = $shortcode->render(
            [
                'columns_desktop' => (string) $columns_desktop,
                'columns_tablet' => (string) $columns_tablet,
                'columns_mobile' => (string) $columns_mobile,
                'limit' => (string) $this->normalize_int($settings['limit'] ?? 9, 9, 1, 100),
                'paginated' => $this->to_shortcode_boolean($settings['paginated'] ?? 'yes'),
                'show_filter' => $this->to_shortcode_boolean($settings['show_filter'] ?? 'yes'),
                'prefilter_type' => isset($settings['prefilter_type']) ? sanitize_text_field((string) $settings['prefilter_type']) : '',
                'prefilter_bedrooms' => isset($settings['prefilter_bedrooms']) ? sanitize_text_field((string) $settings['prefilter_bedrooms']) : '',
                'prefilter_bathrooms' => isset($settings['prefilter_bathrooms']) ? sanitize_text_field((string) $settings['prefilter_bathrooms']) : '',
                'prefilter_guests' => isset($settings['prefilter_guests']) ? sanitize_text_field((string) $settings['prefilter_guests']) : '',
                'prefilter_rating' => isset($settings['prefilter_rating']) ? sanitize_text_field((string) $settings['prefilter_rating']) : '',
                'empty_text' => isset($settings['empty_text']) ? (string) $settings['empty_text'] : '',
                'class' => isset($settings['extra_class']) ? sanitize_text_field((string) $settings['extra_class']) : '',
            ]
        );

        $this->add_render_attribute('wrapper', 'class', 'barefoot-engine-elementor-property-grid');

        echo '<div ' . $this->get_render_attribute_string('wrapper') . '>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode renderer is trusted and escapes dynamic values.
        echo $output;
        echo '</div>';
    }

    /**
     * @param mixed $value
     */
    private function normalize_int($value, int $fallback, int $min, int $max): int
    {
        if (!is_numeric($value)) {
            return $fallback;
        }

        $normalized = (int) $value;

        return max($min, min($max, $normalized));
    }

    /**
     * @param mixed $value
     */
    private function to_shortcode_boolean($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $normalized = strtolower(trim((string) $value));
        $truthy = ['1', 'true', 'yes', 'on'];

        return in_array($normalized, $truthy, true) ? 'true' : 'false';
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function resolve_responsive_int(
        array $settings,
        string $base_key,
        string $device,
        int $fallback,
        int $min,
        int $max
    ): int {
        $desktop = $this->normalize_int($settings[$base_key] ?? $fallback, $fallback, $min, $max);
        $tablet = $this->normalize_int($settings[$base_key . '_tablet'] ?? $desktop, $desktop, $min, $max);
        $mobile = $this->normalize_int($settings[$base_key . '_mobile'] ?? $tablet, $tablet, $min, $max);

        if ($device === 'desktop') {
            return $desktop;
        }

        if ($device === 'tablet') {
            return $tablet;
        }

        return $mobile;
    }

    /**
     * @return array<string, string>
     */
    private function get_bedroom_prefilter_options(): array
    {
        return [
            '' => __('None', 'barefoot-engine'),
            'studio' => __('Studio', 'barefoot-engine'),
            '1' => '1',
            '2' => '2',
            '3' => '3',
            '4' => '4',
            '5' => '5',
            '6' => '6',
            '7' => '7',
            '8+' => '8+',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function get_bathroom_prefilter_options(): array
    {
        return [
            '' => __('None', 'barefoot-engine'),
            '1' => '1',
            '1.5' => '1.5',
            '2' => '2',
            '2.5' => '2.5',
            '3' => '3',
            '3.5' => '3.5',
            '4' => '4',
            '4.5' => '4.5',
            '5' => '5',
            '5.5' => '5.5',
            '6+' => '6+',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function get_guest_prefilter_options(): array
    {
        return [
            '' => __('None', 'barefoot-engine'),
            '1' => '1',
            '2' => '2',
            '3' => '3',
            '4' => '4',
            '5' => '5',
            '6' => '6',
            '7' => '7',
            '8+' => '8+',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function get_rating_prefilter_options(): array
    {
        return [
            '' => __('None', 'barefoot-engine'),
            'A+' => 'A+',
            'A' => 'A',
            'B+' => 'B+',
            'B' => 'B',
            'C+' => 'C+',
            'C' => 'C',
            'D' => 'D',
            'F' => 'F',
        ];
    }
}
