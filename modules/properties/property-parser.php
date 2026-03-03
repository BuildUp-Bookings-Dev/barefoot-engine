<?php

namespace BarefootEngine\Properties;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Parser
{
    /**
     * @return array<string, mixed>|WP_Error
     */
    public function parse_property_list(string $xml): array|WP_Error
    {
        $document = $this->load_document($xml);
        if ($document === null) {
            return new WP_Error(
                'barefoot_engine_property_invalid_xml',
                __('Barefoot returned invalid property XML.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $root = $document->documentElement;
        if (!$root instanceof \DOMElement) {
            return new WP_Error(
                'barefoot_engine_property_invalid_xml',
                __('Barefoot returned invalid property XML.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $property_list = strtolower($root->localName) === 'propertylist'
            ? $root
            : null;

        if ($property_list === null) {
            $xpath = new \DOMXPath($document);
            $node = $xpath->query('//*[local-name()="PropertyList"]')->item(0);
            $property_list = $node instanceof \DOMElement ? $node : null;
        }

        if ($property_list === null) {
            return new WP_Error(
                'barefoot_engine_property_invalid_xml',
                __('Barefoot property XML did not include a PropertyList root node.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $properties = [];
        $field_keys = [];

        foreach ($property_list->childNodes as $property_node) {
            if (!$property_node instanceof \DOMElement || $property_node->localName !== 'Property') {
                continue;
            }

            $fields = [];
            $field_order = [];

            foreach ($property_node->childNodes as $field_node) {
                if (!$field_node instanceof \DOMElement) {
                    continue;
                }

                $key = $field_node->localName;
                $value = trim($field_node->textContent);

                $fields[$key] = $value;
                $field_order[] = $key;

                if (!in_array($key, $field_keys, true)) {
                    $field_keys[] = $key;
                }
            }

            $property_id = isset($fields['PropertyID']) ? trim((string) $fields['PropertyID']) : '';
            $keyboard_id = isset($fields['keyboardid']) ? trim((string) $fields['keyboardid']) : '';
            $name = isset($fields['name']) ? trim((string) $fields['name']) : '';
            $title = $name !== ''
                ? $name
                : ($keyboard_id !== '' ? $keyboard_id : ($property_id !== '' ? 'Property ' . $property_id : 'Property'));

            $raw_xml = trim($document->saveXML($property_node));

            $properties[] = [
                'property_id' => $property_id,
                'keyboard_id' => $keyboard_id,
                'title' => $title,
                'fields' => $fields,
                'field_order' => $field_order,
                'raw_xml' => $raw_xml,
                'source_hash' => sha1($raw_xml),
            ];
        }

        return [
            'properties' => $properties,
            'field_keys' => $field_keys,
        ];
    }

    /**
     * @return array<int, string>|WP_Error
     */
    public function parse_last_updated_property_ids(string $payload): array|WP_Error
    {
        $normalized = trim($payload);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $normalized);
        if (!is_array($parts)) {
            return new WP_Error(
                'barefoot_engine_property_invalid_last_updated_ids',
                __('Barefoot returned invalid updated property IDs.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $property_ids = [];

        foreach ($parts as $part) {
            if (!is_scalar($part)) {
                continue;
            }

            $property_id = trim((string) $part);
            if ($property_id === '' || in_array($property_id, $property_ids, true)) {
                continue;
            }

            $property_ids[] = $property_id;
        }

        return $property_ids;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function parse_property_details(string $xml): array|WP_Error
    {
        $document = $this->load_document($xml);
        if ($document === null) {
            return new WP_Error(
                'barefoot_engine_property_invalid_details_xml',
                __('Barefoot returned invalid property details XML.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $xpath = new \DOMXPath($document);
        $property_node = $xpath->query('//*[local-name()="ProperTy"]')->item(0);
        if (!$property_node instanceof \DOMElement) {
            return new WP_Error(
                'barefoot_engine_property_missing_details',
                __('Barefoot property details XML did not include a property payload.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $basic_info_node = $xpath->query('./*[local-name()="Basicinfo"]', $property_node)->item(0);
        if (!$basic_info_node instanceof \DOMElement) {
            return new WP_Error(
                'barefoot_engine_property_missing_basic_info',
                __('Barefoot property details XML did not include Basicinfo.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $fields = [];

        foreach ($basic_info_node->childNodes as $field_node) {
            if (!$field_node instanceof \DOMElement) {
                continue;
            }

            if ($field_node->childNodes->length > 1 && $field_node->firstChild instanceof \DOMElement) {
                continue;
            }

            $key = $this->normalize_detail_field_key($field_node->localName);
            if ($key === '') {
                continue;
            }

            $fields[$key] = trim($field_node->textContent);
        }

        $property_id = trim($basic_info_node->getAttribute('PropertyID'));
        if ($property_id === '' && isset($fields['PropertyID']) && is_scalar($fields['PropertyID'])) {
            $property_id = trim((string) $fields['PropertyID']);
        }

        if ($property_id !== '') {
            $fields['PropertyID'] = $property_id;
        }

        $keyboard_id = isset($fields['keyboardid']) ? trim((string) $fields['keyboardid']) : '';
        $title_candidates = [
            isset($fields['PropertyTitle']) ? (string) $fields['PropertyTitle'] : '',
            isset($fields['name']) ? (string) $fields['name'] : '',
            $keyboard_id,
            $property_id !== '' ? 'Property ' . $property_id : '',
        ];
        $title = '';

        foreach ($title_candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                $title = $candidate;
                break;
            }
        }

        $raw_xml = trim($document->saveXML($property_node));

        return [
            'property_id' => $property_id,
            'keyboard_id' => $keyboard_id,
            'title' => $title === '' ? __('Property', 'barefoot-engine') : $title,
            'fields' => $fields,
            'raw_xml' => $raw_xml,
            'source_hash' => sha1($raw_xml),
            'normalized_amenities' => $this->extract_detail_amenities($property_node, $basic_info_node),
            'merge_existing_fields' => true,
            'preserve_existing_queryable_meta' => true,
            'preserve_existing_source_hash' => true,
            'force_update' => true,
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function parse_property_images(string $xml): array|WP_Error
    {
        $document = $this->load_document($xml);
        if ($document === null) {
            return new WP_Error(
                'barefoot_engine_property_invalid_images_xml',
                __('Barefoot returned invalid property image XML.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $records = [];
        $images = [];
        $xpath = new \DOMXPath($document);
        $row_nodes = $xpath->query('//*[local-name()="PropertyImg"]');
        if ($row_nodes === false) {
            $row_nodes = [];
        }

        foreach ($row_nodes as $row_node) {
            if (!$row_node instanceof \DOMElement) {
                continue;
            }

            $record = [];

            foreach ($row_node->childNodes as $field_node) {
                if (!$field_node instanceof \DOMElement) {
                    continue;
                }

                $record[$field_node->localName] = trim($field_node->textContent);
            }

            if ($record === []) {
                continue;
            }

            $records[] = $record;

            foreach ($this->extract_image_urls_from_record($record) as $url) {
                $images[] = $url;
            }
        }

        return [
            'records' => $records,
            'images' => array_values(array_filter(array_unique($images))),
            'raw_xml' => trim($xml),
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function parse_property_rates(string $xml): array|WP_Error
    {
        $document = $this->load_document($xml);
        if ($document === null) {
            return new WP_Error(
                'barefoot_engine_property_invalid_rates_xml',
                __('Barefoot returned invalid property rates XML.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $items = [];
        $by_type = [];
        $xpath = new \DOMXPath($document);
        $row_nodes = $xpath->query('//*[local-name()="PropertyRates"]');
        if ($row_nodes === false) {
            $row_nodes = [];
        }

        foreach ($row_nodes as $row_node) {
            if (!$row_node instanceof \DOMElement) {
                continue;
            }

            $record = [];

            foreach ($row_node->childNodes as $field_node) {
                if (!$field_node instanceof \DOMElement) {
                    continue;
                }

                $record[$field_node->localName] = trim($field_node->textContent);
            }

            if ($record === []) {
                continue;
            }

            $item = [
                'date1' => isset($record['date1']) ? trim((string) $record['date1']) : '',
                'date2' => isset($record['date2']) ? trim((string) $record['date2']) : '',
                'date_start' => $this->normalize_rate_date($record['date1'] ?? ''),
                'date_end' => $this->normalize_rate_date($record['date2'] ?? ''),
                'rent' => isset($record['rent']) ? trim((string) $record['rent']) : '',
                'amount' => $this->normalize_rate_amount($record['rent'] ?? ''),
                'pricetype' => isset($record['pricetype']) ? strtolower(trim((string) $record['pricetype'])) : '',
                'wk_b' => isset($record['wk_b']) ? trim((string) $record['wk_b']) : '',
                'wk_e' => isset($record['wk_e']) ? trim((string) $record['wk_e']) : '',
                'priceid' => isset($record['priceid']) ? trim((string) $record['priceid']) : '',
            ];

            $items[] = $item;

            $rate_type = $item['pricetype'];
            if ($rate_type !== '') {
                if (!isset($by_type[$rate_type]) || !is_array($by_type[$rate_type])) {
                    $by_type[$rate_type] = [];
                }

                $by_type[$rate_type][] = $item;
            }
        }

        return [
            'items' => $items,
            'by_type' => $by_type,
            'raw_xml' => trim($xml),
        ];
    }

    private function load_document(string $xml): ?\DOMDocument
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        return $document;
    }

    /**
     * @param array<string, string> $record
     * @return array<int, string>
     */
    private function extract_image_urls_from_record(array $record): array
    {
        $priority_keys = [
            'imageurl',
            'imgurl',
            'url',
            'fullurl',
            'largeurl',
            'originalurl',
            'imagepath',
            'imgpath',
            'path',
            'filename',
            'image',
            'photo',
        ];

        $images = [];

        foreach ($priority_keys as $priority_key) {
            foreach ($record as $field_name => $value) {
                $normalized_field_name = strtolower($field_name);
                if ($normalized_field_name !== $priority_key) {
                    continue;
                }

                foreach ($this->extract_urls_from_value($value) as $url) {
                    $images[] = $url;
                }
            }
        }

        if ($images !== []) {
            return array_values(array_filter(array_unique($images)));
        }

        foreach ($record as $value) {
            foreach ($this->extract_urls_from_value($value) as $url) {
                $images[] = $url;
            }
        }

        return array_values(array_filter(array_unique($images)));
    }

    /**
     * @return array<int, string>
     */
    private function extract_urls_from_value(string $value): array
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return [];
        }

        preg_match_all('~https?://[^\\s,"\']+~i', $normalized, $matches);

        if (empty($matches[0])) {
            return [];
        }

        return array_map('esc_url_raw', $matches[0]);
    }

    private function normalize_detail_field_key(string $key): string
    {
        $normalized = trim($key);
        if ($normalized === '') {
            return '';
        }

        $mapping = [
            'Name' => 'name',
            'Keyboardid' => 'keyboardid',
            'Extdescription' => 'extdescription',
            'Description' => 'description',
            'Status' => 'status',
            'PropertyType' => 'PropertyType',
            'Longitude' => 'Longitude',
            'Latitude' => 'Latitude',
            'PropertyTitle' => 'PropertyTitle',
            'Street' => 'street',
            'Street2' => 'street2',
            'City' => 'city',
            'State' => 'state',
            'Zip' => 'zip',
            'Country' => 'country',
            'Mindays' => 'mindays',
            'Occupancy' => 'SleepsBeds',
            'Imagepath' => 'imagepath',
            'InternetDescription' => 'internetdescription',
            'VideoLink' => 'videolink',
            'FloorPlanLink' => 'floorplanlink',
            'CheckInTime' => 'checkintime',
            'CheckOutTime' => 'checkouttime',
        ];

        return $mapping[$normalized] ?? $normalized;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function extract_detail_amenities(\DOMElement $property_node, \DOMElement $basic_info_node): array
    {
        $amenities = [];

        foreach ($this->collect_property_amenity_nodes($basic_info_node, 'PropertyAmenities', 'PropertyAmenity') as $node) {
            $label = $this->read_child_text($node, 'Name');
            $value = $this->read_child_text($node, 'Value');

            if ($label === '' || $this->is_empty_detail_amenity_value($value)) {
                continue;
            }

            $amenities[] = $this->build_detail_amenity_entry($label, $value);
        }

        foreach ($this->collect_property_amenity_nodes($property_node, 'CustomAmenities', 'CustomAmenity') as $node) {
            $label = $this->read_child_text($node, 'CustomName');
            if ($label === '') {
                $label = $this->read_child_text($node, 'Name');
            }
            $value = $this->read_child_text($node, 'Value');

            if ($label === '' || $this->is_empty_detail_amenity_value($value)) {
                continue;
            }

            $amenities[] = $this->build_detail_amenity_entry($label, $value);
        }

        foreach ($this->collect_property_amenity_nodes($property_node, 'Amenities', 'Amentity') as $node) {
            $label = $this->read_child_text($node, 'Description');
            if ($label === '') {
                $label = $this->read_child_text($node, 'Name');
            }

            if ($label === '') {
                continue;
            }

            $amenities[] = $this->build_detail_amenity_entry($label, '-1');
        }

        return $this->dedupe_detail_amenities($amenities);
    }

    /**
     * @return array<int, \DOMElement>
     */
    private function collect_property_amenity_nodes(\DOMElement $parent, string $collection_name, string $item_name): array
    {
        $nodes = [];

        foreach ($parent->childNodes as $child_node) {
            if (!$child_node instanceof \DOMElement || $child_node->localName !== $collection_name) {
                continue;
            }

            foreach ($child_node->childNodes as $item_node) {
                if ($item_node instanceof \DOMElement && $item_node->localName === $item_name) {
                    $nodes[] = $item_node;
                }
            }
        }

        return $nodes;
    }

    private function read_child_text(\DOMElement $parent, string $name): string
    {
        foreach ($parent->childNodes as $child_node) {
            if ($child_node instanceof \DOMElement && $child_node->localName === $name) {
                return trim($child_node->textContent);
            }
        }

        return '';
    }

    private function is_empty_detail_amenity_value(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return $normalized === '' || $normalized === '0';
    }

    /**
     * @return array<string, string>
     */
    private function build_detail_amenity_entry(string $label, string $value): array
    {
        $normalized_label = trim(preg_replace('/\s+/', ' ', $label) ?? '');

        return [
            'key' => sanitize_title($normalized_label),
            'label' => $normalized_label,
            'value' => trim($value),
            'type' => '',
            'display' => $normalized_label,
        ];
    }

    /**
     * @param array<int, array<string, string>> $amenities
     * @return array<int, array<string, string>>
     */
    private function dedupe_detail_amenities(array $amenities): array
    {
        $deduped = [];
        $seen = [];

        foreach ($amenities as $amenity) {
            $label = isset($amenity['label']) ? strtolower(trim((string) $amenity['label'])) : '';
            if ($label === '' || isset($seen[$label])) {
                continue;
            }

            $seen[$label] = true;
            $deduped[] = $amenity;
        }

        return $deduped;
    }

    private function normalize_rate_date(mixed $value): string
    {
        $normalized = is_scalar($value) ? trim((string) $value) : '';
        if ($normalized === '') {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('m/d/Y', $normalized);
        if ($date instanceof \DateTimeImmutable) {
            return $date->format('Y-m-d');
        }

        return $normalized;
    }

    private function normalize_rate_amount(mixed $value): ?float
    {
        $normalized = is_scalar($value) ? trim((string) $value) : '';
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized);
        if (!is_string($normalized) || $normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

}
