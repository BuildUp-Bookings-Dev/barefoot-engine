<?php

namespace BarefootEngine\Services;

if (!defined('ABSPATH')) {
    exit;
}

class Api_Integration_Settings
{
    public const OPTION_KEY = 'barefoot_engine_api_integration';

    /**
     * @return array<string, mixed>
     */
    public function get_settings(): array
    {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $defaults = $this->get_defaults();
        $api = isset($raw['api']) && is_array($raw['api']) ? $raw['api'] : [];
        $booking = isset($raw['booking']) && is_array($raw['booking']) ? $raw['booking'] : [];

        return [
            'api' => [
                'username' => $this->sanitize_identifier($api['username'] ?? $defaults['api']['username']),
                'password' => $this->sanitize_secret($api['password'] ?? $defaults['api']['password']),
                'company_id' => $this->sanitize_identifier($api['company_id'] ?? $defaults['api']['company_id']),
            ],
            'booking' => [
                'mock_mode' => $this->sanitize_boolean($booking['mock_mode'] ?? $defaults['booking']['mock_mode']),
                'payment_mode' => $this->sanitize_payment_mode($booking['payment_mode'] ?? $defaults['booking']['payment_mode']),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function save(array $payload): array
    {
        $current = $this->get_settings();
        $api_payload = [];
        $booking_payload = [];

        if (isset($payload['api']) && is_array($payload['api'])) {
            $api_payload = $payload['api'];
        }

        if (isset($payload['booking']) && is_array($payload['booking'])) {
            $booking_payload = $payload['booking'];
        }

        $username = $this->sanitize_identifier($api_payload['username'] ?? $current['api']['username']);
        $company_id = $this->sanitize_identifier($api_payload['company_id'] ?? $current['api']['company_id']);
        $password_input = '';

        if (isset($api_payload['password']) && is_scalar($api_payload['password'])) {
            $password_input = (string) $api_payload['password'];
        }

        $password = $current['api']['password'];
        if ($password_input !== '') {
            $password = $this->sanitize_secret($password_input);
        }

        $mock_mode = $this->sanitize_boolean($booking_payload['mock_mode'] ?? $current['booking']['mock_mode'] ?? false);
        $payment_mode = $this->sanitize_payment_mode($booking_payload['payment_mode'] ?? $current['booking']['payment_mode'] ?? $this->get_defaults()['booking']['payment_mode']);

        $settings = [
            'api' => [
                'username' => $username,
                'password' => $password,
                'company_id' => $company_id,
            ],
            'booking' => [
                'mock_mode' => $mock_mode,
                'payment_mode' => $payment_mode,
            ],
        ];

        update_option(self::OPTION_KEY, $settings, false);

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_public_settings(): array
    {
        return $this->to_public_settings($this->get_settings());
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function to_public_settings(array $settings): array
    {
        $api = isset($settings['api']) && is_array($settings['api']) ? $settings['api'] : [];
        $booking = isset($settings['booking']) && is_array($settings['booking']) ? $settings['booking'] : [];
        $username = isset($api['username']) && is_string($api['username']) ? $api['username'] : '';
        $company_id = isset($api['company_id']) && is_string($api['company_id']) ? $api['company_id'] : '';
        $password = isset($api['password']) && is_string($api['password']) ? $api['password'] : '';
        $mock_mode = isset($booking['mock_mode']) ? (bool) $booking['mock_mode'] : (bool) $this->get_defaults()['booking']['mock_mode'];
        $payment_mode = $this->sanitize_payment_mode($booking['payment_mode'] ?? $this->get_defaults()['booking']['payment_mode']);

        return [
            'api' => [
                'username' => $username,
                'company_id' => $company_id,
                'has_password' => $password !== '',
            ],
            'booking' => [
                'mock_mode' => $mock_mode,
                'payment_mode' => $payment_mode,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $settings
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
     * @param array<string, mixed>|null $settings
     */
    public function is_booking_mock_mode_enabled(?array $settings = null): bool
    {
        $resolved = $settings ?? $this->get_settings();
        $booking = isset($resolved['booking']) && is_array($resolved['booking']) ? $resolved['booking'] : [];

        return $this->sanitize_boolean($booking['mock_mode'] ?? $this->get_defaults()['booking']['mock_mode']);
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    public function get_booking_payment_mode(?array $settings = null): string
    {
        $resolved = $settings ?? $this->get_settings();
        $booking = isset($resolved['booking']) && is_array($resolved['booking']) ? $resolved['booking'] : [];

        return $this->sanitize_payment_mode($booking['payment_mode'] ?? $this->get_defaults()['booking']['payment_mode']);
    }

    /**
     * @return array<string, mixed>
     */
    private function get_defaults(): array
    {
        return [
            'api' => [
                'username' => '',
                'password' => '',
                'company_id' => '',
            ],
            'booking' => [
                'mock_mode' => false,
                'payment_mode' => 'TRUE',
            ],
        ];
    }

    private function sanitize_identifier(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field(trim((string) $value));
    }

    private function sanitize_secret(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $string = wp_check_invalid_utf8((string) $value);
        $string = str_replace("\0", '', $string);

        return preg_replace('/[\r\n]+/', '', $string) ?? '';
    }

    private function sanitize_boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        if (!is_scalar($value)) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function sanitize_payment_mode(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return in_array($normalized, ['ON', 'TRUE', 'FALSE'], true) ? $normalized : 'TRUE';
    }
}
