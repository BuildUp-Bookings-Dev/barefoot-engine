<?php

namespace BarefootEngine\Properties;

use BarefootEngine\Services\Property_Sync_Service;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Metaboxes
{
    private const CURATED_FIELDS = [
        'PropertyID',
        'name',
        'extdescription',
        'description',
        'status',
        'PropertyType',
        'Longitude',
        'Latitude',
        'PropertyTitle',
        'SleepsBeds',
        'NumberFloors',
        'UnitType',
        'propAddress',
        'propAddressNew',
        'street',
        'street2',
        'city',
        'state',
        'zip',
        'country',
        'a259',
        'a261',
        'a267',
    ];

    private const DISPLAY_GROUPS = [
        'Overview' => [
            'PropertyID',
            'name',
            'status',
            'PropertyType',
            'PropertyTitle',
            'UnitType',
            'a259',
            'a261',
            'a267',
            'SleepsBeds',
            'NumberFloors',
        ],
        'Location' => [
            'propAddress',
            'propAddressNew',
            'street',
            'street2',
            'city',
            'state',
            'zip',
            'country',
            'Latitude',
            'Longitude',
        ],
        'Descriptions' => [
            'extdescription',
            'description',
        ],
    ];

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
            'be-property-photos',
            __('Barefoot Photos', 'barefoot-engine'),
            [$this, 'render_property_photos'],
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
    }

    public function render_property_data(\WP_Post $post): void
    {
        $fields = get_post_meta($post->ID, '_be_property_fields', true);
        $sync_state = get_option(Property_Sync_Service::SYNC_STATE_OPTION_KEY, []);
        $amenity_labels = is_array($sync_state) && isset($sync_state['amenity_labels']) && is_array($sync_state['amenity_labels'])
            ? $sync_state['amenity_labels']
            : [];
        $amenity_types = is_array($sync_state) && isset($sync_state['amenity_types']) && is_array($sync_state['amenity_types'])
            ? $sync_state['amenity_types']
            : [];

        if (!is_array($fields) || empty($fields)) {
            echo '<p>' . esc_html__('No imported field data is stored for this property yet.', 'barefoot-engine') . '</p>';
            return;
        }

        $amenities = $this->resolve_amenities($fields, $amenity_labels, $amenity_types);

        echo '<div style="display:flex;flex-direction:column;gap:20px;">';

        foreach (self::DISPLAY_GROUPS as $group_title => $keys) {
            $is_description_group = $group_title === 'Descriptions';

            echo '<section style="display:flex;flex-direction:column;gap:12px;">';
            echo '<h3 style="margin:0;font-size:14px;font-weight:700;color:#1d2327;">' . esc_html__($group_title, 'barefoot-engine') . '</h3>';
            echo $is_description_group
                ? '<div style="display:flex;flex-direction:column;gap:12px;">'
                : '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">';

            foreach ($keys as $key) {
                $value = array_key_exists($key, $fields) ? $fields[$key] : null;
                $this->render_property_field_card($key, $value, $amenity_labels);
            }

            echo '</div>';
            echo '</section>';
        }

        echo '<section style="display:flex;flex-direction:column;gap:12px;">';
        echo '<div style="display:flex;flex-direction:column;gap:2px;">';
        echo '<h3 style="margin:0;font-size:14px;font-weight:700;color:#1d2327;">' . esc_html__('Amenities', 'barefoot-engine') . '</h3>';
        echo '<code style="font-size:11px;color:#646970;background:none;padding:0;">a75-a258</code>';
        echo '</div>';
        echo '<div style="border:1px solid #dcdcde;border-radius:12px;background:#fff;padding:16px;">';

        if ($amenities === []) {
            echo '<span class="description">' . esc_html__('No amenities captured.', 'barefoot-engine') . '</span>';
        } else {
            echo '<ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:6px;">';

            foreach ($amenities as $amenity) {
                $display = isset($amenity['display']) && is_string($amenity['display']) ? $amenity['display'] : '';
                if ($display === '') {
                    continue;
                }

                echo '<li style="margin:0;">' . esc_html($display) . '</li>';
            }

            echo '</ul>';
        }

        echo '</div>';
        echo '</section>';
        echo '</div>';
    }

    public function render_sync_details(\WP_Post $post): void
    {
        $property_id = (string) get_post_meta($post->ID, '_be_property_id', true);
        $last_synced = (int) get_post_meta($post->ID, '_be_property_last_synced_at', true);

        echo '<table class="widefat striped" style="margin:0">';
        echo '<tbody>';
        $this->render_sync_row(__('Property ID', 'barefoot-engine'), $property_id !== '' ? $property_id : '—');
        $this->render_sync_row(
            __('Last Synced', 'barefoot-engine'),
            $last_synced > 0
                ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_synced)
                : __('Not synced yet', 'barefoot-engine')
        );
        echo '</tbody></table>';

        echo '<div style="margin-top:12px;">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="barefoot_engine_sync_property" />';
        echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $post->ID) . '" />';
        wp_nonce_field('be_sync_single_property_' . $post->ID);
        submit_button(__('Sync This Property', 'barefoot-engine'), 'secondary', 'submit', false);
        echo '</form>';
        echo '</div>';
    }

    public function render_property_photos(\WP_Post $post): void
    {
        $images = get_post_meta($post->ID, '_be_property_images', true);
        if (!is_array($images)) {
            $images = [];
        }

        $images = array_values(
            array_filter(
                array_unique(
                    array_map(
                        static fn (mixed $image): string => is_scalar($image) ? esc_url_raw(trim((string) $image)) : '',
                        $images
                    )
                ),
                static fn (string $image): bool => $image !== ''
            )
        );

        if ($images === []) {
            echo '<p>' . esc_html__('No synced Barefoot photos are stored for this property yet.', 'barefoot-engine') . '</p>';
            return;
        }

        echo '<p class="description" style="margin-top:0;">';
        printf(
            /* translators: %d: number of synced property images */
            esc_html(_n('%d photo pulled from Barefoot.', '%d photos pulled from Barefoot.', count($images), 'barefoot-engine')),
            count($images)
        );
        echo '</p>';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">';

        foreach ($images as $index => $image_url) {
            $label = sprintf(
                /* translators: %d: 1-based property image number */
                __('Photo %d', 'barefoot-engine'),
                $index + 1
            );

            echo '<a href="' . esc_url($image_url) . '" target="_blank" rel="noopener noreferrer" style="display:flex;flex-direction:column;gap:8px;text-decoration:none;">';
            echo '<span style="display:block;aspect-ratio:4 / 3;border:1px solid #dcdcde;border-radius:12px;overflow:hidden;background:#f6f7f7;">';
            echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($label) . '" loading="lazy" style="display:block;width:100%;height:100%;object-fit:cover;" />';
            echo '</span>';
            echo '<span style="font-size:12px;line-height:1.4;color:#1d2327;word-break:break-word;">' . esc_html($label) . '</span>';
            echo '</a>';
        }

        echo '</div>';
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

    /**
     * @param array<string, string> $amenity_labels
     */
    private function render_property_field_card(string $key, mixed $value, array $amenity_labels): void
    {
        $label = $this->resolve_field_label($key, $amenity_labels);
        $normalized_value = is_scalar($value) ? trim((string) $value) : '';
        $is_empty = $normalized_value === '';
        $is_long_text = !$is_empty && $this->should_render_large_value($normalized_value);

        echo '<article style="border:1px solid #dcdcde;border-radius:12px;background:#fff;padding:14px 16px;display:flex;flex-direction:column;gap:10px;min-width:0;">';
        echo '<div style="display:flex;flex-direction:column;gap:2px;">';
        echo '<strong style="font-size:13px;line-height:1.4;color:#1d2327;">' . esc_html($label) . '</strong>';
        echo '<code style="font-size:11px;color:#646970;background:none;padding:0;">' . esc_html($key) . '</code>';
        echo '</div>';
        echo '<div>';

        if ($is_empty) {
            echo '<span class="description">' . esc_html__('Empty', 'barefoot-engine') . '</span>';
        } elseif ($is_long_text) {
            printf(
                '<textarea readonly="readonly" class="widefat code" rows="%1$d">%2$s</textarea>',
                esc_attr((string) $this->estimate_rows($normalized_value)),
                esc_textarea($normalized_value)
            );
        } else {
            echo '<span style="display:block;font-size:13px;line-height:1.5;color:#1d2327;word-break:break-word;">' . esc_html($normalized_value) . '</span>';
        }

        echo '</div>';
        echo '</article>';
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, string> $amenity_labels
     * @param array<string, string> $amenity_types
     * @return array<int, array<string, string>>
     */
    private function resolve_amenities(array $fields, array $amenity_labels, array $amenity_types): array
    {
        $stored = $fields['amenities'] ?? null;
        if (is_array($stored)) {
            $normalized = [];

            foreach ($stored as $amenity) {
                if (!is_array($amenity)) {
                    continue;
                }

                $key = isset($amenity['key']) && is_string($amenity['key']) ? trim($amenity['key']) : '';
                $label = isset($amenity['label']) && is_string($amenity['label']) ? trim($amenity['label']) : '';
                $value = isset($amenity['value']) && is_scalar($amenity['value']) ? trim((string) $amenity['value']) : '';
                $type = isset($amenity['type']) && is_string($amenity['type']) ? strtolower(trim($amenity['type'])) : $this->resolve_amenity_type($key, $amenity_types);

                if ($key === '' || $label === '') {
                    continue;
                }

                $entry = $this->build_amenity_entry($key, $value, $amenity_labels, $amenity_types);
                if ($entry !== null) {
                    $normalized[] = $entry;
                }
            }

            if ($normalized !== []) {
                return $normalized;
            }
        }

        $derived = [];

        foreach ($fields as $key => $value) {
            if (!is_string($key) || !$this->is_amenity_key($key)) {
                continue;
            }

            $amenity = $this->build_amenity_entry($key, $value, $amenity_labels, $amenity_types);
            if ($amenity !== null) {
                $derived[] = $amenity;
            }
        }

        return $derived;
    }

    private function is_amenity_key(string $key): bool
    {
        if (preg_match('/^a(\d+)$/i', $key, $matches) !== 1) {
            return false;
        }

        $number = (int) $matches[1];

        return $number >= 75 && $number <= 258;
    }

    /**
     * @param array<string, string> $amenity_labels
     * @param array<string, string> $amenity_types
     * @return array<string, string>|null
     */
    private function build_amenity_entry(string $key, mixed $value, array $amenity_labels, array $amenity_types): ?array
    {
        $normalized_value = is_scalar($value) ? trim((string) $value) : '';
        $amenity_type = $this->resolve_amenity_type($key, $amenity_types);

        if (!$this->should_include_amenity_value($normalized_value, $amenity_type)) {
            return null;
        }

        $label = $this->resolve_field_label($key, $amenity_labels);

        return [
            'key' => $key,
            'label' => $label,
            'value' => $normalized_value,
            'type' => $amenity_type,
            'display' => $this->build_amenity_display($label),
        ];
    }

    private function should_include_amenity_value(string $value, string $amenity_type): bool
    {
        if ($value === '') {
            return false;
        }

        $normalized = strtolower($value);
        if ($amenity_type === 'check box' || $amenity_type === 'radio button') {
            return $this->is_checked_amenity_value($normalized);
        }

        if ($amenity_type === 'list box') {
            return !in_array($normalized, ['0', '-1'], true);
        }

        if ($amenity_type === 'text box') {
            return true;
        }

        return !in_array($normalized, ['0', 'false', 'no', 'n'], true);
    }

    private function build_amenity_display(string $label): string
    {
        return $label;
    }

    private function is_checked_amenity_value(string $value): bool
    {
        return in_array(strtolower($value), ['-1', '1', 'true', 'yes', 'y'], true);
    }

    /**
     * @param array<string, string> $amenity_types
     */
    private function resolve_amenity_type(string $key, array $amenity_types): string
    {
        if (!isset($amenity_types[$key]) || !is_string($amenity_types[$key])) {
            return '';
        }

        return strtolower(trim($amenity_types[$key]));
    }

    /**
     * @param array<string, string> $amenity_labels
     */
    private function resolve_field_label(string $key, array $amenity_labels = []): string
    {
        $normalized_key = trim($key);
        if ($normalized_key === '') {
            return '';
        }

        if (isset($amenity_labels[$normalized_key]) && is_string($amenity_labels[$normalized_key])) {
            $amenity_label = trim($amenity_labels[$normalized_key]);
            if ($amenity_label !== '') {
                return $amenity_label;
            }
        }

        return $this->humanize_key($normalized_key);
    }

    private function humanize_key(string $key): string
    {
        if (preg_match('/^a\d+$/', $key) === 1) {
            return $key;
        }

        $lower = strtolower($key);
        $special_cases = [
            'propertyid' => 'Property ID',
            'keyboardid' => 'Keyboard ID',
            'addressid' => 'Address ID',
            'propaddressnew' => 'Prop Address New',
            'propaddress' => 'Prop Address',
            'mindays' => 'Min Days',
            'minprice' => 'Min Price',
            'maxprice' => 'Max Price',
            'imagepath' => 'Image Path',
            'extdescription' => 'Ext Description',
            'unitcomments' => 'Unit Comments',
            'internetdescription' => 'Internet Description',
            'videolink' => 'Video Link',
        ];

        if (isset($special_cases[$lower])) {
            return $special_cases[$lower];
        }

        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        if (!is_string($label)) {
            return $key;
        }

        $label = str_replace(['_', '-'], ' ', $label);
        $label = preg_replace('/\s+/', ' ', trim($label));

        if (!is_string($label) || $label === '') {
            return $key;
        }

        return ucwords($label);
    }
}
