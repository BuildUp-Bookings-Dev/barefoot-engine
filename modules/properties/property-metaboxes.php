<?php

namespace BarefootEngine\Properties;

use BarefootEngine\Services\Property_Sync_Service;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Metaboxes
{
    private const FEATURED_NONCE_ACTION = 'be_save_featured_property';
    private const FEATURED_NONCE_NAME = 'be_featured_property_nonce';
    private const GUEST_COUNT_FIELD = 'be_guest_count';
    private const BEDROOM_COUNT_FIELD = 'be_bedroom_count';
    private const BATHROOM_COUNT_FIELD = 'be_bathroom_count';
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
        self::GUEST_COUNT_FIELD,
        self::BEDROOM_COUNT_FIELD,
        self::BATHROOM_COUNT_FIELD,
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
            self::GUEST_COUNT_FIELD,
            self::BEDROOM_COUNT_FIELD,
            self::BATHROOM_COUNT_FIELD,
            'UnitType',
            'a259',
            'a261',
            'a267',
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
            'be-property-featured',
            __('Featured Property', 'barefoot-engine'),
            [$this, 'render_featured_toggle'],
            Property_Post_Type::POST_TYPE,
            'side',
            'high'
        );

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

    public function render_featured_toggle(\WP_Post $post): void
    {
        $is_featured = get_post_meta($post->ID, Property_Post_Type::FEATURED_META_KEY, true) === '1';
        wp_nonce_field(self::FEATURED_NONCE_ACTION, self::FEATURED_NONCE_NAME);
        ?>
        <style>
            .be-featured-toggle{display:flex;align-items:center;justify-content:space-between;gap:12px}
            .be-featured-toggle__label{font-size:13px;font-weight:600;color:#1d2327}
            .be-featured-toggle__switch{position:relative;display:inline-flex;align-items:center;justify-content:center;width:52px;height:30px}
            .be-featured-toggle__checkbox{position:absolute;inset:0;margin:0;opacity:0;cursor:pointer}
            .be-featured-toggle__track{position:absolute;inset:0;border-radius:999px;background:#c3c4c7;transition:background .18s ease;pointer-events:none}
            .be-featured-toggle__track::after{content:'';position:absolute;top:3px;left:3px;width:24px;height:24px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:transform .18s ease}
            .be-featured-toggle__checkbox:checked + .be-featured-toggle__track{background:#2271b1}
            .be-featured-toggle__checkbox:checked + .be-featured-toggle__track::after{transform:translateX(22px)}
            .be-featured-toggle__checkbox:focus-visible + .be-featured-toggle__track{outline:2px solid #2271b1;outline-offset:2px}
            .be-featured-toggle__state{font-size:12px;font-weight:600;color:#2271b1}
        </style>
        <div class="be-featured-toggle">
            <input type="hidden" name="be_property_is_featured_present" value="1" />
            <span class="be-featured-toggle__label"><?php echo esc_html__('Set Featured', 'barefoot-engine'); ?></span>
            <label class="be-featured-toggle__switch">
                <input
                    id="be-property-featured-toggle"
                    type="checkbox"
                    class="be-featured-toggle__checkbox"
                    name="be_property_is_featured"
                    value="1"
                    <?php checked($is_featured); ?>
                />
                <span class="be-featured-toggle__track" aria-hidden="true"></span>
            </label>
        </div>
        <p class="be-featured-toggle__state" style="margin:10px 0 0;">
            <?php echo esc_html($is_featured ? __('Featured: On', 'barefoot-engine') : __('Featured: Off', 'barefoot-engine')); ?>
        </p>
        <?php
    }

    public function save_featured_flag(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== Property_Post_Type::POST_TYPE) {
            return;
        }

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $nonce = isset($_POST[self::FEATURED_NONCE_NAME]) && is_string($_POST[self::FEATURED_NONCE_NAME])
            ? wp_unslash($_POST[self::FEATURED_NONCE_NAME])
            : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, self::FEATURED_NONCE_ACTION)) {
            return;
        }

        if (!isset($_POST['be_property_is_featured_present'])) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $is_featured = isset($_POST['be_property_is_featured']) && is_scalar($_POST['be_property_is_featured']);

        update_post_meta($post_id, Property_Post_Type::FEATURED_META_KEY, $is_featured ? '1' : '0');
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

        $display_fields = $this->build_display_fields($post->ID, $fields);
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
                $value = array_key_exists($key, $display_fields) ? $display_fields[$key] : null;
                $this->render_property_field_card($key, $value, $amenity_labels);
            }

            echo '</div>';
            echo '</section>';
        }

        echo '<style>
            .be-property-amenities-list{
                margin:0;
                padding-left:0;
                list-style:disc inside;
                display:grid;
                grid-template-columns:repeat(3,minmax(0,1fr));
                gap:6px 18px;
            }
            .be-property-amenities-list li{
                margin:0;
                min-width:0;
            }
            @media (max-width:782px){
                .be-property-amenities-list{
                    grid-template-columns:1fr;
                }
            }
        </style>';
        echo '<section style="display:flex;flex-direction:column;gap:12px;">';
        echo '<div style="display:flex;flex-direction:column;gap:2px;">';
        echo '<h3 style="margin:0;font-size:14px;font-weight:700;color:#1d2327;">' . esc_html__('Amenities', 'barefoot-engine') . '</h3>';
        echo '</div>';
        echo '<div style="border:1px solid #dcdcde;border-radius:12px;background:#fff;padding:16px;">';

        if ($amenities === []) {
            echo '<span class="description">' . esc_html__('No amenities captured.', 'barefoot-engine') . '</span>';
        } else {
            echo '<ul class="be-property-amenities-list">';

            foreach ($amenities as $amenity) {
                $display = isset($amenity['display']) && is_string($amenity['display']) ? $amenity['display'] : '';
                if ($display === '') {
                    continue;
                }

                echo '<li>' . esc_html($display) . '</li>';
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
        $sync_url = add_query_arg(
            [
                'action' => 'barefoot_engine_sync_property',
                'post_id' => $post->ID,
            ],
            admin_url('admin-post.php')
        );
        $sync_url = wp_nonce_url($sync_url, 'be_sync_single_property_' . $post->ID);

        echo '<a class="button button-secondary" href="' . esc_url($sync_url) . '">' . esc_html__('Sync This Property', 'barefoot-engine') . '</a>';
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

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function build_display_fields(int $post_id, array $fields): array
    {
        $display_fields = $fields;
        $display_fields[self::GUEST_COUNT_FIELD] = $this->resolve_display_count(
            $post_id,
            Property_Sync_Service::GUEST_COUNT_META_KEY,
            [$fields['SleepsBeds'] ?? null, $fields['a53'] ?? null]
        );
        $display_fields[self::BEDROOM_COUNT_FIELD] = $this->resolve_display_count(
            $post_id,
            Property_Sync_Service::BEDROOM_COUNT_META_KEY,
            [$fields['a56'] ?? null, $this->parse_count_from_text($fields['a259'] ?? null, 'bed'), $this->parse_count_from_text($fields['PropertyTitle'] ?? null, 'bed')]
        );
        $display_fields[self::BATHROOM_COUNT_FIELD] = $this->resolve_display_count(
            $post_id,
            Property_Sync_Service::BATHROOM_COUNT_META_KEY,
            [
                $fields['a195'] ?? null,
                $this->resolve_amenity_value($fields, 'a195'),
                get_post_meta($post_id, '_be_property_api_a195', true),
                $this->parse_count_from_text($fields['a259'] ?? null, 'bath'),
                $this->parse_count_from_text($fields['PropertyTitle'] ?? null, 'bath'),
            ]
        );

        return $display_fields;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolve_amenity_value(array $fields, string $amenity_key): ?string
    {
        if (!isset($fields['amenities']) || !is_array($fields['amenities'])) {
            return null;
        }

        foreach ($fields['amenities'] as $amenity) {
            if (!is_array($amenity)) {
                continue;
            }

            $key = isset($amenity['key']) && is_scalar($amenity['key']) ? trim((string) $amenity['key']) : '';
            if ($key === '' || strcasecmp($key, $amenity_key) !== 0) {
                continue;
            }

            $value = isset($amenity['value']) && is_scalar($amenity['value']) ? trim((string) $amenity['value']) : '';

            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * @param array<int, mixed> $fallbacks
     */
    private function resolve_display_count(int $post_id, string $meta_key, array $fallbacks): string
    {
        $stored = get_post_meta($post_id, $meta_key, true);
        $normalized = $this->normalize_count_value($stored);
        if ($normalized !== '') {
            return $normalized;
        }

        foreach ($fallbacks as $fallback) {
            $normalized = $this->normalize_count_value($fallback);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function normalize_count_value(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || !is_numeric($normalized)) {
            return '';
        }

        $numeric = (float) $normalized;
        if ($numeric < 0) {
            return '';
        }

        if (abs($numeric - floor($numeric)) < 0.00001) {
            return (string) (int) round($numeric);
        }

        return rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.');
    }

    private function parse_count_from_text(mixed $value, string $kind): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $pattern = $kind === 'bath'
            ? '/(\d+(?:\.\d+)?)\s*(?:bathroom|bathrooms|bath|baths|ba)\b/i'
            : '/(\d+(?:\.\d+)?)\s*(?:bedroom|bedrooms|bed|beds)\b/i';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return '';
        }

        return $this->normalize_count_value($matches[1] ?? '');
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
        $secondary_label = $this->resolve_secondary_key_label($key);
        $normalized_value = is_scalar($value) ? trim((string) $value) : '';
        $is_empty = $normalized_value === '';
        $is_long_text = !$is_empty && $this->should_render_large_value($normalized_value);

        echo '<article style="border:1px solid #dcdcde;border-radius:12px;background:#fff;padding:14px 16px;display:flex;flex-direction:column;gap:10px;min-width:0;">';
        echo '<div style="display:flex;flex-direction:column;gap:2px;">';
        echo '<strong style="font-size:13px;line-height:1.4;color:#1d2327;">' . esc_html($label) . '</strong>';
        if ($secondary_label !== '') {
            echo '<code style="font-size:11px;color:#646970;background:none;padding:0;">' . esc_html($secondary_label) . '</code>';
        }
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

    private function resolve_secondary_key_label(string $key): string
    {
        return match ($key) {
            self::GUEST_COUNT_FIELD, self::BEDROOM_COUNT_FIELD, self::BATHROOM_COUNT_FIELD => '',
            default => $key,
        };
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

        if ($normalized_key === self::GUEST_COUNT_FIELD) {
            return __('Sleeps', 'barefoot-engine');
        }

        if ($normalized_key === self::BEDROOM_COUNT_FIELD) {
            return __('Bedrooms', 'barefoot-engine');
        }

        if ($normalized_key === self::BATHROOM_COUNT_FIELD) {
            return __('Bathrooms', 'barefoot-engine');
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
