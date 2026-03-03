<?php

namespace BarefootEngine\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Manifest
{
    private const MANIFEST_PATH = BAREFOOT_ENGINE_PLUGIN_DIR . 'assets/dist/.vite/manifest.json';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $manifest = null;

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (!file_exists(self::MANIFEST_PATH)) {
            $this->manifest = [];
            return $this->manifest;
        }

        $raw = file_get_contents(self::MANIFEST_PATH);
        if ($raw === false) {
            $this->manifest = [];
            return $this->manifest;
        }

        $decoded = json_decode($raw, true);
        $this->manifest = is_array($decoded) ? $decoded : [];

        return $this->manifest;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_entry_by_name(string $entry_name): ?array
    {
        foreach ($this->get() as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['name'] ?? '') === $entry_name && !empty($entry['isEntry'])) {
                return $entry;
            }
        }

        // Vite omits the "name" key for CSS-only entries, so fall back to
        // matching the emitted file prefix against the configured input alias.
        foreach ($this->get() as $entry) {
            if (!is_array($entry) || empty($entry['isEntry']) || empty($entry['file'])) {
                continue;
            }

            $file = (string) $entry['file'];
            $basename = pathinfo($file, PATHINFO_BASENAME);

            if ($basename === $entry_name || str_starts_with($basename, $entry_name . '-')) {
                return $entry;
            }
        }

        return null;
    }
}
