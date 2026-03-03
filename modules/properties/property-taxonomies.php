<?php

namespace BarefootEngine\Properties;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Taxonomies
{
    public const AMENITY_TAXONOMY = 'be_property_amenity';
    public const TYPE_TAXONOMY = 'be_property_type';

    public function register(): void
    {
        register_taxonomy(
            self::AMENITY_TAXONOMY,
            [Property_Post_Type::POST_TYPE],
            [
                'labels' => [
                    'name' => __('Amenities', 'barefoot-engine'),
                    'singular_name' => __('Amenity', 'barefoot-engine'),
                    'menu_name' => __('Amenities', 'barefoot-engine'),
                    'all_items' => __('All Amenities', 'barefoot-engine'),
                    'edit_item' => __('Edit Amenity', 'barefoot-engine'),
                    'search_items' => __('Search Amenities', 'barefoot-engine'),
                ],
                'public' => false,
                'show_ui' => true,
                'show_admin_column' => true,
                'show_in_rest' => false,
                'show_tagcloud' => false,
                'hierarchical' => false,
                'meta_box_cb' => false,
                'show_in_quick_edit' => false,
            ]
        );

        register_taxonomy(
            self::TYPE_TAXONOMY,
            [Property_Post_Type::POST_TYPE],
            [
                'labels' => [
                    'name' => __('Types', 'barefoot-engine'),
                    'singular_name' => __('Type', 'barefoot-engine'),
                    'menu_name' => __('Types', 'barefoot-engine'),
                    'all_items' => __('All Types', 'barefoot-engine'),
                    'edit_item' => __('Edit Type', 'barefoot-engine'),
                    'search_items' => __('Search Types', 'barefoot-engine'),
                ],
                'public' => false,
                'show_ui' => true,
                'show_admin_column' => true,
                'show_in_rest' => false,
                'show_tagcloud' => false,
                'hierarchical' => false,
                'meta_box_cb' => false,
                'show_in_quick_edit' => false,
            ]
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<int, array<string, mixed>> $amenities
     */
    public function sync_terms_for_property(int $post_id, array $fields, array $amenities): void
    {
        $amenity_term_ids = [];

        foreach ($amenities as $amenity) {
            if (!is_array($amenity)) {
                continue;
            }

            $label = isset($amenity['label']) && is_scalar($amenity['label']) ? (string) $amenity['label'] : '';
            $term_id = $this->resolve_term_id(self::AMENITY_TAXONOMY, $label);
            if ($term_id !== 0) {
                $amenity_term_ids[] = $term_id;
            }
        }

        $amenity_term_ids = array_values(array_unique($amenity_term_ids));
        wp_set_object_terms($post_id, $amenity_term_ids, self::AMENITY_TAXONOMY, false);

        $property_type = isset($fields['PropertyType']) && is_scalar($fields['PropertyType'])
            ? (string) $fields['PropertyType']
            : '';
        $type_term_id = $this->resolve_term_id(self::TYPE_TAXONOMY, $property_type);

        if ($type_term_id === 0) {
            wp_set_object_terms($post_id, [], self::TYPE_TAXONOMY, false);
        } else {
            wp_set_object_terms($post_id, [$type_term_id], self::TYPE_TAXONOMY, false);
        }
    }

    public function clear_terms_for_property(int $post_id): void
    {
        wp_set_object_terms($post_id, [], self::AMENITY_TAXONOMY, false);
        wp_set_object_terms($post_id, [], self::TYPE_TAXONOMY, false);
    }

    private function resolve_term_id(string $taxonomy, string $label): int
    {
        $normalized_label = $this->normalize_label($label);
        if ($normalized_label === '') {
            return 0;
        }

        $slug = sanitize_title($normalized_label);
        if ($slug === '') {
            return 0;
        }

        $existing = get_term_by('slug', $slug, $taxonomy);
        if ($existing instanceof \WP_Term) {
            return (int) $existing->term_id;
        }

        $inserted = wp_insert_term(
            $normalized_label,
            $taxonomy,
            [
                'slug' => $slug,
            ]
        );

        if (is_wp_error($inserted)) {
            $existing_id = $inserted->get_error_data('term_exists');
            if (is_numeric($existing_id)) {
                return (int) $existing_id;
            }

            return 0;
        }

        return isset($inserted['term_id']) ? (int) $inserted['term_id'] : 0;
    }

    private function normalize_label(string $label): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $label) ?? '');

        return $normalized;
    }
}
