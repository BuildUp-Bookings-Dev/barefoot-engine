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
}
