<?php

namespace BarefootEngine\Widgets\FeaturedProperties;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
    exit;
}

class Featured_Properties_Elementor_Widget extends Widget_Base
{
    public function get_name()
    {
        return 'barefoot-featured-properties';
    }

    public function get_title()
    {
        return __('Featured Properties', 'barefoot-engine');
    }

    public function get_icon()
    {
        return 'eicon-post-slider';
    }

    public function get_categories()
    {
        return [Featured_Properties_Elementor::CATEGORY];
    }

    public function get_keywords()
    {
        return ['barefoot', 'properties', 'featured', 'slider', 'vacation'];
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

        $this->add_control(
            'title',
            [
                'label' => __('Heading', 'barefoot-engine'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Featured Properties', 'barefoot-engine'),
            ]
        );

        $this->add_control(
            'heading_position',
            [
                'label' => __('Heading Position', 'barefoot-engine'),
                'type' => Controls_Manager::SELECT,
                'default' => 'left',
                'options' => [
                    'left' => __('Left', 'barefoot-engine'),
                    'center' => __('Center', 'barefoot-engine'),
                    'right' => __('Right', 'barefoot-engine'),
                ],
            ]
        );

        $this->add_control(
            'empty_text',
            [
                'label' => __('Empty State Text', 'barefoot-engine'),
                'type' => Controls_Manager::TEXT,
                'default' => __('No featured properties available yet.', 'barefoot-engine'),
            ]
        );

        $this->add_control(
            'limit',
            [
                'label' => __('Limit', 'barefoot-engine'),
                'type' => Controls_Manager::NUMBER,
                'default' => 9,
                'min' => 1,
                'max' => 30,
                'step' => 1,
            ]
        );

        $this->add_control(
            'currency',
            [
                'label' => __('Currency Symbol', 'barefoot-engine'),
                'type' => Controls_Manager::TEXT,
                'default' => '$',
            ]
        );

        $this->add_control(
            'starts_at_prefix',
            [
                'label' => __('Starts At Label', 'barefoot-engine'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Starts at', 'barefoot-engine'),
            ]
        );

        $this->add_control(
            'meta_display',
            [
                'label' => __('Card Meta Items', 'barefoot-engine'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'default' => ['starts_at', 'property_type', 'view', 'sleeps', 'bedrooms', 'bathrooms'],
                'options' => [
                    'starts_at' => __('Starts at', 'barefoot-engine'),
                    'property_type' => __('Property type', 'barefoot-engine'),
                    'view' => __('View', 'barefoot-engine'),
                    'sleeps' => __('Sleeps', 'barefoot-engine'),
                    'bedrooms' => __('Bedrooms', 'barefoot-engine'),
                    'bathrooms' => __('Bathrooms', 'barefoot-engine'),
                ],
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

        $this->start_controls_section(
            'section_slider',
            [
                'label' => __('Slider Controls', 'barefoot-engine'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'outer_loop',
            [
                'label' => __('Outer Slider Loop', 'barefoot-engine'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'barefoot-engine'),
                'label_off' => __('No', 'barefoot-engine'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'slider_controls_position',
            [
                'label' => __('Slider Control Position', 'barefoot-engine'),
                'type' => Controls_Manager::SELECT,
                'default' => 'top-right',
                'options' => [
                    'side' => __('Side', 'barefoot-engine'),
                    'top-right' => __('Top-right', 'barefoot-engine'),
                    'bottom-center' => __('Bottom-center', 'barefoot-engine'),
                ],
            ]
        );

        $this->add_control(
            'outer_navigation',
            [
                'label' => __('Outer Navigation', 'barefoot-engine'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'barefoot-engine'),
                'label_off' => __('Hide', 'barefoot-engine'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'outer_autoplay',
            [
                'label' => __('Outer Autoplay', 'barefoot-engine'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('On', 'barefoot-engine'),
                'label_off' => __('Off', 'barefoot-engine'),
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $this->add_control(
            'outer_autoplay_delay',
            [
                'label' => __('Autoplay Delay (ms)', 'barefoot-engine'),
                'type' => Controls_Manager::NUMBER,
                'default' => 5000,
                'min' => 1000,
                'max' => 30000,
                'step' => 250,
                'condition' => [
                    'outer_autoplay' => 'yes',
                ],
            ]
        );

        $this->add_responsive_control(
            'slides_per_view',
            [
                'label' => __('Slides Per View', 'barefoot-engine'),
                'type' => Controls_Manager::NUMBER,
                'default' => 3,
                'tablet_default' => 2,
                'mobile_default' => 1,
                'min' => 1,
                'max' => 6,
                'step' => 1,
            ]
        );

        $this->add_responsive_control(
            'slides_gap',
            [
                'label' => __('Slide Gap', 'barefoot-engine'),
                'type' => Controls_Manager::NUMBER,
                'default' => 24,
                'tablet_default' => 20,
                'mobile_default' => 16,
                'min' => 0,
                'max' => 120,
                'step' => 1,
            ]
        );

        $this->add_control(
            'inner_loop',
            [
                'label' => __('Image Slider Loop', 'barefoot-engine'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'barefoot-engine'),
                'label_off' => __('No', 'barefoot-engine'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'inner_navigation',
            [
                'label' => __('Image Slider Arrows', 'barefoot-engine'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'barefoot-engine'),
                'label_off' => __('Hide', 'barefoot-engine'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_cards',
            [
                'label' => __('Cards', 'barefoot-engine'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_background_color',
            [
                'label' => __('Card Background', 'barefoot-engine'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__card' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .barefoot-engine-featured-properties__card',
            ]
        );

        $this->add_control(
            'card_outer_padding',
            [
                'label' => __('Card Padding', 'barefoot-engine'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; box-sizing: border-box;',
                ],
            ]
        );

        $this->add_control(
            'card_padding',
            [
                'label' => __('Card Content Padding', 'barefoot-engine'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'card_min_height',
            [
                'label' => __('Card Min Height', 'barefoot-engine'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 800,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__card' => 'min-height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'image_height',
            [
                'label' => __('Image Height', 'barefoot-engine'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 120,
                        'max' => 520,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__media' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_typography',
            [
                'label' => __('Typography', 'barefoot-engine'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'heading_typography',
                'label' => __('Heading', 'barefoot-engine'),
                'selector' => '{{WRAPPER}} .barefoot-engine-featured-properties__title',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'card_title_typography',
                'label' => __('Card Title', 'barefoot-engine'),
                'selector' => '{{WRAPPER}} .barefoot-engine-featured-properties__card-title-link',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'starts_at_typography',
                'label' => __('Starts At', 'barefoot-engine'),
                'selector' => '{{WRAPPER}} .barefoot-engine-featured-properties__starts-at',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'meta_typography',
                'label' => __('Type/View Row', 'barefoot-engine'),
                'selector' => '{{WRAPPER}} .barefoot-engine-featured-properties__meta-item',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'stats_typography',
                'label' => __('Stats Row', 'barefoot-engine'),
                'selector' => '{{WRAPPER}} .barefoot-engine-featured-properties__stat-item',
            ]
        );

        $this->add_control(
            'heading_color',
            [
                'label' => __('Heading Color', 'barefoot-engine'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'card_title_color',
            [
                'label' => __('Card Title Color', 'barefoot-engine'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__card-title-link' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'meta_color',
            [
                'label' => __('Meta Color', 'barefoot-engine'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__meta-item' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .barefoot-engine-featured-properties__meta-item i' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'stats_color',
            [
                'label' => __('Stats Color', 'barefoot-engine'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__stat-item' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .barefoot-engine-featured-properties__stat-item i' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .barefoot-engine-featured-properties__stat-item strong' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'starts_at_color',
            [
                'label' => __('Starts At Color', 'barefoot-engine'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__starts-at' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_navigation',
            [
                'label' => __('Navigation', 'barefoot-engine'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'nav_background_color',
            [
                'label' => __('Button Background', 'barefoot-engine'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__outer-nav-btn' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'nav_icon_color',
            [
                'label' => __('Button Icon Color', 'barefoot-engine'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__outer-nav-btn' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'nav_border',
                'selector' => '{{WRAPPER}} .barefoot-engine-featured-properties__outer-nav-btn',
            ]
        );

        $this->add_responsive_control(
            'nav_size',
            [
                'label' => __('Button Size', 'barefoot-engine'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 28,
                        'max' => 96,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .barefoot-engine-featured-properties__outer-nav-btn' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $meta_display = isset($settings['meta_display']) && is_array($settings['meta_display']) ? $settings['meta_display'] : [];
        $slides_desktop = $this->resolve_responsive_int($settings, 'slides_per_view', 'desktop', 3, 1, 6);
        $slides_tablet = $this->resolve_responsive_int($settings, 'slides_per_view', 'tablet', 2, 1, 6);
        $slides_mobile = $this->resolve_responsive_int($settings, 'slides_per_view', 'mobile', 1, 1, 6);
        $gap_desktop = $this->resolve_responsive_int($settings, 'slides_gap', 'desktop', 24, 0, 120);
        $gap_tablet = $this->resolve_responsive_int($settings, 'slides_gap', 'tablet', 20, 0, 120);
        $gap_mobile = $this->resolve_responsive_int($settings, 'slides_gap', 'mobile', 16, 0, 120);

        $shortcode = new Featured_Properties_Shortcode();
        $output = $shortcode->render(
            [
                'title' => isset($settings['title']) ? (string) $settings['title'] : '',
                'empty_text' => isset($settings['empty_text']) ? (string) $settings['empty_text'] : '',
                'limit' => (string) $this->normalize_int($settings['limit'] ?? 9, 9, 1, 30),
                'currency' => isset($settings['currency']) ? (string) $settings['currency'] : '$',
                'starts_at_prefix' => isset($settings['starts_at_prefix']) ? (string) $settings['starts_at_prefix'] : '',
                'meta_display' => implode(',', array_map('strval', $meta_display)),
                'heading_position' => isset($settings['heading_position']) ? (string) $settings['heading_position'] : 'left',
                'slider_controls_position' => isset($settings['slider_controls_position']) ? (string) $settings['slider_controls_position'] : 'top-right',
                'outer_loop' => $this->to_shortcode_boolean($settings['outer_loop'] ?? 'yes'),
                'outer_navigation' => $this->to_shortcode_boolean($settings['outer_navigation'] ?? 'yes'),
                'outer_autoplay' => $this->to_shortcode_boolean($settings['outer_autoplay'] ?? ''),
                'outer_autoplay_delay' => (string) $this->normalize_int($settings['outer_autoplay_delay'] ?? 5000, 5000, 1000, 30000),
                'slides_mobile' => (string) $slides_mobile,
                'slides_tablet' => (string) $slides_tablet,
                'slides_desktop' => (string) $slides_desktop,
                'gap_mobile' => (string) $gap_mobile,
                'gap_tablet' => (string) $gap_tablet,
                'gap_desktop' => (string) $gap_desktop,
                'inner_loop' => $this->to_shortcode_boolean($settings['inner_loop'] ?? 'yes'),
                'inner_navigation' => $this->to_shortcode_boolean($settings['inner_navigation'] ?? 'yes'),
                'class' => isset($settings['extra_class']) ? sanitize_text_field((string) $settings['extra_class']) : '',
            ]
        );

        $this->add_render_attribute('wrapper', 'class', 'barefoot-engine-elementor-featured-properties');

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
}
