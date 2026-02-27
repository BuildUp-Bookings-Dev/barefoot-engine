<?php

namespace BarefootEngine\Services;

if (!defined('ABSPATH')) {
    exit;
}

class Api_Integration_Settings
{
    public const OPTION_KEY = 'barefoot_engine_api_integration';

    /**
     * @return array<string, array<string, string>>
     */
    public function get_settings(): array
    {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $defaults = $this->get_defaults();
        $api = isset($raw['api']) && is_array($raw['api']) ? $raw['api'] : [];

        return [
            'api' => [
                'username' => $this->sanitize_scalar($api['username'] ?? $defaults['api']['username']),
                'password' => $this->sanitize_scalar($api['password'] ?? $defaults['api']['password']),
                'company_id' => $this->sanitize_scalar($api['company_id'] ?? $defaults['api']['company_id']),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, array<string, string>>
     */
    public function save(array $payload): array
    {
        $current = $this->get_settings();
        $api_payload = [];

        if (isset($payload['api']) && is_array($payload['api'])) {
            $api_payload = $payload['api'];
        }

        $username = $this->sanitize_scalar($api_payload['username'] ?? $current['api']['username']);
        $company_id = $this->sanitize_scalar($api_payload['company_id'] ?? $current['api']['company_id']);
        $password_input = '';

        if (isset($api_payload['password']) && is_scalar($api_payload['password'])) {
            $password_input = trim((string) $api_payload['password']);
        }

        $password = $current['api']['password'];
        if ($password_input !== '') {
            $password = $this->sanitize_scalar($password_input);
        }

        $settings = [
            'api' => [
                'username' => $username,
                'password' => $password,
                'company_id' => $company_id,
            ],
        ];

        update_option(self::OPTION_KEY, $settings, false);

        return $settings;
    }

    /**
     * @return array<string, array<string, bool|string>>
     */
    public function get_public_settings(): array
    {
        return $this->to_public_settings($this->get_settings());
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return array<string, array<string, bool|string>>
     */
    public function to_public_settings(array $settings): array
    {
        $api = isset($settings['api']) && is_array($settings['api']) ? $settings['api'] : [];
        $username = isset($api['username']) && is_string($api['username']) ? $api['username'] : '';
        $company_id = isset($api['company_id']) && is_string($api['company_id']) ? $api['company_id'] : '';
        $password = isset($api['password']) && is_string($api['password']) ? $api['password'] : '';

        return [
            'api' => [
                'username' => $username,
                'company_id' => $company_id,
                'has_password' => $password !== '',
            ],
        ];
    }

    /**
     * @param array<string, array<string, string>>|null $settings
     */
    public function has_required_credentials(?array $settings = null): bool
    {
        $resolved = $settings ?? $this->get_settings();
        $api = isset($resolved['api']) && is_array($resolved['api']) ? $resolved['api'] : [];

        $username = isset($api['username']) && is_string($api['username']) ? trim($api['username']) : '';
        $company_id = isset($api['company_id']) && is_string($api['company_id']) ? trim($api['company_id']) : '';
        $password = isset($api['password']) && is_string($api['password']) ? trim($api['password']) : '';

        return $username !== '' && $company_id !== '' && $password !== '';
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function get_defaults(): array
    {
        return [
            'api' => [
                'username' => '',
                'password' => '',
                'company_id' => '',
            ],
        ];
    }

    private function sanitize_scalar(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field(trim((string) $value));
    }
}
