<?php

namespace BarefootEngine\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Updates_Service
{
    private const RELEASES_TRANSIENT_KEY = 'barefoot_engine_github_releases';
    private const RELEASES_CACHE_TTL = 600;
    private const RELEASES_FETCH_LIMIT = 8;

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_status(): array|WP_Error
    {
        $releases = $this->get_recent_releases();
        if (is_wp_error($releases)) {
            return $releases;
        }

        $latest_version = $this->get_latest_version_from_releases($releases);

        $current_version = $this->normalize_version(BAREFOOT_ENGINE_VERSION);
        if ($latest_version === '') {
            $latest_version = $current_version;
        }

        $has_update = version_compare($latest_version, $current_version, '>');
        $last_checked = $this->get_last_checked_at();

        return [
            'current_version' => $current_version,
            'latest_version' => $latest_version !== '' ? $latest_version : $current_version,
            'has_update' => $has_update,
            'is_latest' => !$has_update,
            'last_checked_at' => $last_checked,
            'last_checked_human' => $this->format_last_checked($last_checked),
            'summary' => $has_update
                ? __('A newer version is available. Run update from WordPress Plugins screen.', 'barefoot-engine')
                : __('You are running the latest version of Barefoot Engine.', 'barefoot-engine'),
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function check_now(): array|WP_Error
    {
        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . 'wp-includes/update.php';
        }

        delete_site_transient('update_plugins');
        wp_update_plugins();

        $this->clear_release_cache();
        $releases = $this->get_recent_releases(true);
        if (is_wp_error($releases)) {
            return $releases;
        }

        $latest_version = $this->get_latest_version_from_releases($releases);
        $this->purge_stale_plugin_update_offer($latest_version);

        $status = $this->get_status();
        if (is_wp_error($status)) {
            return $status;
        }

        return [
            'status' => $status,
            'releases' => $releases,
            'checked_at' => time(),
        ];
    }

    public function clear_release_cache(): void
    {
        delete_transient(self::RELEASES_TRANSIENT_KEY);
    }

    /**
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public function get_recent_releases(bool $force_refresh = false): array|WP_Error
    {
        if (!$force_refresh) {
            $cached = get_transient(self::RELEASES_TRANSIENT_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $api_url = $this->build_releases_api_url();
        if (is_wp_error($api_url)) {
            return $api_url;
        }

        $token = (string) apply_filters('barefoot_engine_github_api_token', '');

        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'barefoot-engine/' . BAREFOOT_ENGINE_VERSION,
        ];

        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get(
            $api_url,
            [
                'timeout' => 20,
                'headers' => $headers,
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'barefoot_engine_releases_request_failed',
                __('Unable to fetch releases from GitHub.', 'barefoot-engine'),
                [
                    'status' => 502,
                    'details' => $response->get_error_message(),
                ]
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            $message = wp_remote_retrieve_response_message($response);

            return new WP_Error(
                'barefoot_engine_releases_http_error',
                sprintf(
                    /* translators: 1: status code, 2: status message */
                    __('GitHub releases request failed (%1$d %2$s).', 'barefoot-engine'),
                    $status_code,
                    is_string($message) ? $message : ''
                ),
                ['status' => 502]
            );
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) {
            return new WP_Error(
                'barefoot_engine_releases_invalid_response',
                __('GitHub releases response was invalid JSON.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $releases = [];

        foreach ($decoded as $release) {
            if (!is_array($release)) {
                continue;
            }

            $is_draft = !empty($release['draft']);
            if ($is_draft) {
                continue;
            }

            $tag_name = isset($release['tag_name']) && is_string($release['tag_name']) ? trim($release['tag_name']) : '';
            if ($tag_name === '') {
                continue;
            }

            $published_at = isset($release['published_at']) && is_string($release['published_at']) ? trim($release['published_at']) : '';
            $body = isset($release['body']) && is_string($release['body']) ? trim($release['body']) : '';

            $releases[] = [
                'tag_name' => $tag_name,
                'name' => isset($release['name']) && is_string($release['name']) ? trim($release['name']) : $tag_name,
                'url' => isset($release['html_url']) && is_string($release['html_url']) ? esc_url_raw($release['html_url']) : '',
                'published_at' => $published_at,
                'published_human' => $this->format_release_date($published_at),
                'is_prerelease' => !empty($release['prerelease']),
                'body' => $body,
                'body_excerpt' => $this->make_release_excerpt($body),
            ];

            if (count($releases) >= self::RELEASES_FETCH_LIMIT) {
                break;
            }
        }

        set_transient(self::RELEASES_TRANSIENT_KEY, $releases, self::RELEASES_CACHE_TTL);

        return $releases;
    }

    private function get_last_checked_at(): int
    {
        $transient = get_site_transient('update_plugins');
        if (is_object($transient) && isset($transient->last_checked) && is_numeric($transient->last_checked)) {
            return (int) $transient->last_checked;
        }

        return 0;
    }

    private function format_last_checked(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return __('Not checked yet', 'barefoot-engine');
        }

        return wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp
        );
    }

    private function format_release_date(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return __('Unknown date', 'barefoot-engine');
        }

        return wp_date(get_option('date_format'), $timestamp);
    }

    private function normalize_version(string $version): string
    {
        $normalized = trim($version);
        if (str_starts_with($normalized, 'v')) {
            $normalized = substr($normalized, 1);
        }

        return preg_replace('/[^0-9A-Za-z.\-_]/', '', $normalized) ?? '';
    }

    /**
     * @param array<int, array<string, mixed>> $releases
     */
    private function get_latest_version_from_releases(array $releases): string
    {
        if (empty($releases)) {
            return '';
        }

        $first = $releases[0];
        if (!is_array($first)) {
            return '';
        }

        $tag_name = isset($first['tag_name']) && is_string($first['tag_name']) ? $first['tag_name'] : '';

        return $this->normalize_version($tag_name);
    }

    private function purge_stale_plugin_update_offer(string $latest_github_version): void
    {
        $transient = get_site_transient('update_plugins');
        if (!is_object($transient) || !isset($transient->response) || !is_array($transient->response)) {
            return;
        }

        $entry = $transient->response[BAREFOOT_ENGINE_PLUGIN_BASENAME] ?? null;
        if ($entry === null) {
            return;
        }

        if (is_object($entry)) {
            $entry = (array) $entry;
        }

        if (!is_array($entry)) {
            unset($transient->response[BAREFOOT_ENGINE_PLUGIN_BASENAME]);
            set_site_transient('update_plugins', $transient);
            return;
        }

        $entry_version = isset($entry['new_version']) && is_string($entry['new_version'])
            ? $this->normalize_version($entry['new_version'])
            : '';

        $should_purge = $latest_github_version === '' ||
            ($entry_version !== '' && version_compare($entry_version, $latest_github_version, '>'));

        if ($should_purge) {
            unset($transient->response[BAREFOOT_ENGINE_PLUGIN_BASENAME]);
            set_site_transient('update_plugins', $transient);
        }
    }

    /**
     * @return string|WP_Error
     */
    private function build_releases_api_url(): string|WP_Error
    {
        $repository = (string) apply_filters('barefoot_engine_updater_repository', BAREFOOT_ENGINE_GITHUB_REPOSITORY);
        $repository = trim($repository);

        if ($repository === '') {
            return new WP_Error(
                'barefoot_engine_repository_missing',
                __('GitHub repository URL is not configured.', 'barefoot-engine'),
                ['status' => 500]
            );
        }

        $path = parse_url($repository, PHP_URL_PATH);
        if (!is_string($path)) {
            return new WP_Error(
                'barefoot_engine_repository_invalid',
                __('GitHub repository URL is invalid.', 'barefoot-engine'),
                ['status' => 500]
            );
        }

        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        if (count($parts) < 2) {
            return new WP_Error(
                'barefoot_engine_repository_invalid',
                __('GitHub repository URL must include owner and repository.', 'barefoot-engine'),
                ['status' => 500]
            );
        }

        $owner = sanitize_text_field($parts[0]);
        $repo = sanitize_text_field(preg_replace('/\.git$/', '', $parts[1]) ?? $parts[1]);

        if ($owner === '' || $repo === '') {
            return new WP_Error(
                'barefoot_engine_repository_invalid',
                __('GitHub repository owner or name is empty.', 'barefoot-engine'),
                ['status' => 500]
            );
        }

        return sprintf('https://api.github.com/repos/%s/%s/releases?per_page=10', rawurlencode($owner), rawurlencode($repo));
    }

    private function make_release_excerpt(string $markdown): string
    {
        $normalized = trim(str_replace("\r\n", "\n", $markdown));
        if ($normalized === '') {
            return __('No release notes provided.', 'barefoot-engine');
        }

        $collapsed = preg_replace("/\n{3,}/", "\n\n", $normalized);
        if (!is_string($collapsed)) {
            $collapsed = $normalized;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($collapsed) : strlen($collapsed);
        if ($length <= 1200) {
            return $collapsed;
        }

        if (function_exists('mb_substr')) {
            return rtrim(mb_substr($collapsed, 0, 1197)) . '...';
        }

        return rtrim(substr($collapsed, 0, 1197)) . '...';
    }
}
