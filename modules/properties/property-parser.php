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
     * @return array{
     *     newly_added_property_ids: array<int, string>,
     *     updated_properties: array<int, array{property_id: string, type: string, source_key: string}>,
     *     updated_property_ids: array<int, string>,
     *     cancelled_property_ids: array<int, string>,
     *     all_update_property_ids: array<int, string>,
     *     update_types_by_property_id: array<string, array<int, string>>
     * }|WP_Error
     */
    public function parse_last_updated_property_changes(string $payload): array|WP_Error
    {
        $normalized = trim($payload);
        $changes = [
            'newly_added_property_ids' => [],
            'updated_properties' => [],
            'updated_property_ids' => [],
            'cancelled_property_ids' => [],
            'all_update_property_ids' => [],
            'update_types_by_property_id' => [],
        ];

        if ($normalized === '') {
            return $changes;
        }

        $document = $this->load_document_with_wrapped_roots($normalized);

        if ($document === null) {
            $fallback_ids = $this->parse_property_ids_from_text($normalized);
            $changes['updated_property_ids'] = $fallback_ids;
            $changes['all_update_property_ids'] = $fallback_ids;

            return $changes;
        }

        $xpath = new \DOMXPath($document);

        $new_nodes = $xpath->query('//*[local-name()="NewlyAddedPropertyIDs"]//*[local-name()="PropertyID"]');
        if ($new_nodes !== false) {
            foreach ($new_nodes as $new_node) {
                if (!$new_node instanceof \DOMElement) {
                    continue;
                }

                $property_id = trim($new_node->textContent);
                if ($property_id === '' || in_array($property_id, $changes['newly_added_property_ids'], true)) {
                    continue;
                }

                $changes['newly_added_property_ids'][] = $property_id;
            }
        }

        $updated_nodes = $xpath->query('//*[local-name()="LastUpdatedPropertyIDs"]//*[local-name()="Property"]');
        if ($updated_nodes !== false) {
            foreach ($updated_nodes as $updated_node) {
                if (!$updated_node instanceof \DOMElement) {
                    continue;
                }

                $property_id = $this->read_first_matching_child_text($updated_node, ['PropertyID', 'propertyid', 'addressid', 'AddressID']);
                if ($property_id === '') {
                    continue;
                }

                $update_type = $this->read_first_matching_child_text($updated_node, ['type', 'Type']);
                $source_key = $this->read_first_matching_child_text($updated_node, ['PropertyID', 'propertyid', 'addressid', 'AddressID'], true);

                $changes['updated_properties'][] = [
                    'property_id' => $property_id,
                    'type' => $update_type,
                    'source_key' => $source_key,
                ];

                if (!in_array($property_id, $changes['updated_property_ids'], true)) {
                    $changes['updated_property_ids'][] = $property_id;
                }

                if ($update_type !== '') {
                    $existing_types = $changes['update_types_by_property_id'][$property_id] ?? [];
                    if (!in_array($update_type, $existing_types, true)) {
                        $existing_types[] = $update_type;
                    }

                    $changes['update_types_by_property_id'][$property_id] = $existing_types;
                }
            }
        }

        $cancelled_nodes = $xpath->query('//*[local-name()="CancelledPropertyIDs"]//*[local-name()="PropertyID"]');
        if ($cancelled_nodes !== false) {
            foreach ($cancelled_nodes as $cancelled_node) {
                if (!$cancelled_node instanceof \DOMElement) {
                    continue;
                }

                $property_id = trim($cancelled_node->textContent);
                if ($property_id === '' || in_array($property_id, $changes['cancelled_property_ids'], true)) {
                    continue;
                }

                $changes['cancelled_property_ids'][] = $property_id;
            }
        }

        if (
            $changes['newly_added_property_ids'] === []
            && $changes['updated_property_ids'] === []
            && $changes['cancelled_property_ids'] === []
        ) {
            $fallback_ids = $this->parse_property_ids_from_text($normalized);
            $changes['updated_property_ids'] = $fallback_ids;
            $changes['all_update_property_ids'] = $fallback_ids;

            return $changes;
        }

        $changes['all_update_property_ids'] = array_values(
            array_unique(
                array_merge(
                    $changes['newly_added_property_ids'],
                    $changes['updated_property_ids']
                )
            )
        );

        return $changes;
    }

    /**
     * @return array<int, string>|WP_Error
     */
    public function parse_last_updated_property_ids(string $payload): array|WP_Error
    {
        $changes = $this->parse_last_updated_property_changes($payload);
        if (is_wp_error($changes)) {
            return $changes;
        }

        return isset($changes['all_update_property_ids']) && is_array($changes['all_update_property_ids'])
            ? $changes['all_update_property_ids']
            : [];
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
     * @return array{properties: array<int, array<string, mixed>>, property_ids: array<int, string>}|WP_Error
     */
    public function parse_property_availability_by_date(string $xml): array|WP_Error
    {
        $document = $this->load_document($xml);
        if ($document === null) {
            return new WP_Error(
                'barefoot_engine_property_invalid_availability_xml',
                __('Barefoot returned invalid availability XML.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $xpath = new \DOMXPath($document);
        $row_nodes = $xpath->query('//*[local-name()="PropertyAvailability"]');
        if ($row_nodes === false) {
            $row_nodes = [];
        }

        $properties = [];
        $property_ids = [];

        foreach ($row_nodes as $row_node) {
            if (!$row_node instanceof \DOMElement) {
                continue;
            }

            $fields = [];

            foreach ($row_node->childNodes as $field_node) {
                if (!$field_node instanceof \DOMElement) {
                    continue;
                }

                $fields[$field_node->localName] = trim($field_node->textContent);
            }

            if ($fields === []) {
                continue;
            }

            $property_id = isset($fields['PropertyID']) ? trim((string) $fields['PropertyID']) : '';
            $keyboard_id = isset($fields['keyboardid']) ? trim((string) $fields['keyboardid']) : '';
            $title_candidates = [
                isset($fields['name']) ? (string) $fields['name'] : '',
                isset($fields['PropertyTitle']) ? (string) $fields['PropertyTitle'] : '',
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

            $raw_xml = trim($document->saveXML($row_node));

            $properties[] = [
                'property_id' => $property_id,
                'keyboard_id' => $keyboard_id,
                'title' => $title === '' ? __('Property', 'barefoot-engine') : $title,
                'fields' => $fields,
                'raw_xml' => $raw_xml,
            ];

            if ($property_id !== '' && !in_array($property_id, $property_ids, true)) {
                $property_ids[] = $property_id;
            }
        }

        return [
            'properties' => $properties,
            'property_ids' => $property_ids,
        ];
    }

    /**
     * @return array{property_ids: array<int, string>}|WP_Error
     */
    public function parse_last_avail_changed_properties(string $xml): array|WP_Error
    {
        $normalized = trim($xml);
        if ($normalized === '' || stripos($normalized, 'No Changed Data') !== false) {
            return [
                'property_ids' => [],
            ];
        }

        $document = $this->load_document_with_wrapped_roots($normalized);
        if ($document === null) {
            return [
                'property_ids' => $this->parse_property_ids_from_text($normalized),
            ];
        }

        $xpath = new \DOMXPath($document);
        $property_id_nodes = $xpath->query('//*[local-name()="PropertyID"]');
        if ($property_id_nodes === false) {
            $property_id_nodes = [];
        }

        $property_ids = [];

        foreach ($property_id_nodes as $property_id_node) {
            if (!$property_id_node instanceof \DOMElement) {
                continue;
            }

            $property_id = trim($property_id_node->textContent);
            if ($property_id === '' || in_array($property_id, $property_ids, true)) {
                continue;
            }

            $property_ids[] = $property_id;
        }

        return [
            'property_ids' => $property_ids,
        ];
    }

    /**
     * @return array{
     *     blocked_ranges: array<int, array{arrival_date: string, departure_date: string}>,
     *     raw_xml: string
     * }|WP_Error
     */
    public function parse_property_booking_dates(string $xml): array|WP_Error
    {
        $document = $this->load_document($xml);
        if ($document === null) {
            return new WP_Error(
                'barefoot_engine_property_invalid_booking_xml',
                __('Barefoot returned invalid blocked booking date XML.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $blocked_ranges = [];
        $xpath = new \DOMXPath($document);
        $row_nodes = $xpath->query('//*[local-name()="Table"]');
        if ($row_nodes === false) {
            $row_nodes = [];
        }

        foreach ($row_nodes as $row_node) {
            if (!$row_node instanceof \DOMElement) {
                continue;
            }

            $arrival_raw = $this->read_child_text($row_node, 'ArrivalDate');
            $departure_raw = $this->read_child_text($row_node, 'DepartureDate');
            $arrival_date = $this->normalize_booking_date($arrival_raw);
            $departure_date = $this->normalize_booking_date($departure_raw);

            if ($arrival_date === '' || $departure_date === '') {
                continue;
            }

            if ($departure_date <= $arrival_date) {
                continue;
            }

            $blocked_ranges[] = [
                'arrival_date' => $arrival_date,
                'departure_date' => $departure_date,
            ];
        }

        return [
            'blocked_ranges' => $blocked_ranges,
            'raw_xml' => trim($xml),
        ];
    }

    /**
     * @return array{
     *     items: array<int, array{name: string, value: string, amount: float|null, rate_id: string}>,
     *     raw_payload: string
     * }|WP_Error
     */
    public function parse_quote_rates_detail(string $payload): array|WP_Error
    {
        $normalized = trim($payload);
        if ($normalized === '') {
            return new WP_Error(
                'barefoot_engine_property_invalid_quote_payload',
                __('Barefoot returned an empty quote payload.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $document = $this->load_document_with_wrapped_roots($normalized);
        if ($document === null) {
            return new WP_Error(
                'barefoot_engine_property_invalid_quote_payload',
                __('Barefoot returned an invalid quote payload.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $items = [];
        $xpath = new \DOMXPath($document);
        $row_nodes = $xpath->query('//*[local-name()="propertyratesdetails"]');
        if ($row_nodes === false) {
            $row_nodes = [];
        }

        foreach ($row_nodes as $row_node) {
            if (!$row_node instanceof \DOMElement) {
                continue;
            }

            $name = '';
            $value = '';
            $rate_id = '';

            foreach ($row_node->childNodes as $child_node) {
                if (!$child_node instanceof \DOMElement) {
                    continue;
                }

                $key = strtolower(trim($child_node->localName));
                $text = trim($child_node->textContent);

                if ($key === 'ratesname') {
                    $name = $text;
                } elseif ($key === 'ratesvalue') {
                    $value = $text;
                } elseif ($key === 'ratesid') {
                    $rate_id = $text;
                }
            }

            if ($name === '' && $value === '') {
                continue;
            }

            $items[] = [
                'name' => $name,
                'value' => $value,
                'amount' => $this->normalize_rate_amount($value),
                'rate_id' => $rate_id,
            ];
        }

        return [
            'items' => $items,
            'raw_payload' => $normalized,
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

    private function load_document_with_wrapped_roots(string $xml): ?\DOMDocument
    {
        $document = $this->load_document($xml);
        if ($document instanceof \DOMDocument) {
            return $document;
        }

        $trimmed = trim($xml);
        if ($trimmed === '' || strpos($trimmed, '<') === false) {
            return null;
        }

        return $this->load_document('<BarefootPayload>' . $trimmed . '</BarefootPayload>');
    }

    /**
     * @param array<int, string> $keys
     */
    private function read_first_matching_child_text(\DOMElement $node, array $keys, bool $return_key = false): string
    {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (!in_array($child->localName, $keys, true)) {
                continue;
            }

            return $return_key ? $child->localName : trim($child->textContent);
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function parse_property_ids_from_text(string $payload): array
    {
        $normalized = trim($payload);
        if ($normalized === '' || stripos($normalized, 'No Changed Data') !== false) {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $normalized);
        if (!is_array($parts)) {
            return [];
        }

        $property_ids = [];

        foreach ($parts as $part) {
            if (!is_scalar($part)) {
                continue;
            }

            $property_id = trim((string) $part);
            if ($property_id === '' || strpos($property_id, '<') !== false || strpos($property_id, '>') !== false) {
                continue;
            }

            if (in_array($property_id, $property_ids, true)) {
                continue;
            }

            $property_ids[] = $property_id;
        }

        return $property_ids;
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

    private function normalize_booking_date(mixed $value): string
    {
        $normalized = is_scalar($value) ? trim((string) $value) : '';
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) === 1) {
            return $normalized;
        }

        $formats = ['m/d/Y', 'n/j/Y', 'm/j/Y', 'n/d/Y'];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $normalized, wp_timezone());
            if (!$date instanceof \DateTimeImmutable) {
                continue;
            }

            return $date->format('Y-m-d');
        }

        return '';
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
