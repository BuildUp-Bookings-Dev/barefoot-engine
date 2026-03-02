<?php

namespace BarefootEngine\Properties;

use BarefootEngine\Services\Property_Alias_Settings;
use BarefootEngine\Services\Property_Sync_Service;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Metaboxes
{
    private Property_Alias_Settings $alias_settings;

    public function __construct(?Property_Alias_Settings $alias_settings = null)
    {
        $this->alias_settings = $alias_settings ?? new Property_Alias_Settings();
    }

    public function register(): void
    {
        add_meta_box(
            'be-property-data',
            __('Barefoot Property Data', 'barefoot-engine'),
            [$this, 'render_property_data'],
            Property_Post_Type::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'be-property-sync',
            __('Barefoot Sync', 'barefoot-engine'),
            [$this, 'render_sync_details'],
            Property_Post_Type::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'be-property-raw-xml',
            __('Raw Barefoot XML', 'barefoot-engine'),
            [$this, 'render_raw_xml'],
            Property_Post_Type::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function render_property_data(\WP_Post $post): void
    {
        $fields = get_post_meta($post->ID, '_be_property_fields', true);
        $field_order = get_post_meta($post->ID, '_be_property_field_order', true);
        $sync_state = get_option(Property_Sync_Service::SYNC_STATE_OPTION_KEY, []);
        $amenity_labels = is_array($sync_state) && isset($sync_state['amenity_labels']) && is_array($sync_state['amenity_labels'])
            ? $sync_state['amenity_labels']
            : [];

        if (!is_array($fields) || empty($fields)) {
            echo '<p>' . esc_html__('No imported field data is stored for this property yet.', 'barefoot-engine') . '</p>';
            return;
        }

        if (!is_array($field_order) || empty($field_order)) {
            $field_order = array_keys($fields);
        }

        echo '<table class="widefat striped" style="margin:0">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Key', 'barefoot-engine') . '</th>';
        echo '<th scope="col">' . esc_html__('Label', 'barefoot-engine') . '</th>';
        echo '<th scope="col">' . esc_html__('Value', 'barefoot-engine') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($field_order as $key) {
            if (!is_string($key) || !array_key_exists($key, $fields)) {
                continue;
            }

            $value = $fields[$key];
            $display_value = is_scalar($value) ? (string) $value : '';
            $label = $this->alias_settings->resolve_label($key, $amenity_labels);

            echo '<tr>';
            echo '<td><code>' . esc_html($key) . '</code></td>';
            echo '<td>' . esc_html($label) . '</td>';
            echo '<td>';

            if ($display_value === '') {
                echo '<span class="description">' . esc_html__('Empty', 'barefoot-engine') . '</span>';
            } elseif ($this->should_render_large_value($display_value)) {
                printf(
                    '<textarea readonly="readonly" class="widefat code" rows="%1$d">%2$s</textarea>',
                    esc_attr((string) $this->estimate_rows($display_value)),
                    esc_textarea($display_value)
                );
            } else {
                echo esc_html($display_value);
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    public function render_sync_details(\WP_Post $post): void
    {
        $property_id = (string) get_post_meta($post->ID, '_be_property_id', true);
        $keyboard_id = (string) get_post_meta($post->ID, '_be_property_keyboardid', true);
        $import_status = (string) get_post_meta($post->ID, '_be_property_import_status', true);
        $last_synced = (int) get_post_meta($post->ID, '_be_property_last_synced_at', true);

        echo '<table class="widefat striped" style="margin:0">';
        echo '<tbody>';
        $this->render_sync_row(__('Property ID', 'barefoot-engine'), $property_id !== '' ? $property_id : '—');
        $this->render_sync_row(__('Keyboard ID', 'barefoot-engine'), $keyboard_id !== '' ? $keyboard_id : '—');
        $this->render_sync_row(
            __('Import Status', 'barefoot-engine'),
            $import_status !== '' ? ucfirst($import_status) : __('Unknown', 'barefoot-engine')
        );
        $this->render_sync_row(
            __('Last Synced', 'barefoot-engine'),
            $last_synced > 0
                ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_synced)
                : __('Not synced yet', 'barefoot-engine')
        );
        echo '</tbody></table>';
    }

    public function render_raw_xml(\WP_Post $post): void
    {
        $raw_xml = (string) get_post_meta($post->ID, '_be_property_raw_xml', true);
        if ($raw_xml === '') {
            echo '<p>' . esc_html__('No raw XML is stored for this property yet.', 'barefoot-engine') . '</p>';
            return;
        }

        printf(
            '<textarea readonly="readonly" class="widefat code" rows="18">%s</textarea>',
            esc_textarea($raw_xml)
        );
    }

    private function render_sync_row(string $label, string $value): void
    {
        echo '<tr>';
        echo '<th scope="row">' . esc_html($label) . '</th>';
        echo '<td>' . esc_html($value) . '</td>';
        echo '</tr>';
    }

    private function should_render_large_value(string $value): bool
    {
        return str_contains($value, "\n") || strlen($value) > 120;
    }

    private function estimate_rows(string $value): int
    {
        $lines = substr_count($value, "\n") + 1;

        return max(3, min(12, $lines));
    }
}
