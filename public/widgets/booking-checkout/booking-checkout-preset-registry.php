<?php

namespace BarefootEngine\Widgets\BookingCheckout;

if (!defined('ABSPATH')) {
    exit;
}

class Booking_Checkout_Preset_Registry
{
    private const DEFAULT_PRESET_KEY = 'default';

    /**
     * @var array<string, mixed>
     */
    private const FALLBACK_PRESET = [
        'currency' => '$',
        'reztypeid' => 26,
        'paymentMode' => 'ON',
        'portalId' => '',
        'sourceOfBusiness' => '',
        'calendarOptions' => [
            'monthsToShow' => 2,
            'datepickerPlacement' => 'auto',
            'defaultMinDays' => 1,
            'tooltipLabel' => 'Nights',
            'showTooltip' => true,
            'showClearButton' => true,
        ],
        'guests' => [
            'label' => 'Guests',
            'placeholder' => 'Select guests',
            'defaultValue' => '2',
            'options' => ['1', '2', '3', '4', '5', '6', '7', '8+'],
        ],
        'links' => [
            'termsUrl' => '',
            'rentalAgreementUrl' => '',
        ],
        'labels' => [
            'title' => 'Complete Your Booking',
            'summaryTitle' => 'Payable Amount',
            'guestStepTitle' => 'Primary Guest Details',
            'paymentStepTitle' => 'Payment Options',
            'proceedToPay' => 'Proceed to Pay',
            'paySecurely' => 'Pay Securely',
            'goBack' => 'Go Back',
            'listingDetails' => 'See listing details',
            'dates' => 'Dates',
            'guests' => 'Guests',
            'changeDates' => 'Change dates',
            'changeGuests' => 'Change guests',
            'changeDatesModalTitle' => 'Change dates',
            'modalApply' => 'Apply',
            'modalCancel' => 'Cancel',
            'modalClear' => 'Clear',
            'guestIncrement' => 'Increase guests',
            'guestDecrement' => 'Decrease guests',
            'applyGuestChange' => 'Apply',
            'closeEditor' => 'Close',
            'checkIn' => 'Check-In',
            'checkOut' => 'Check-Out',
            'guestCount' => 'Guest',
            'noDatesSelected' => 'No dates selected',
            'adultSingular' => 'adult',
            'adultPlural' => 'adults',
            'rent' => 'Rent',
            'tax' => 'Tax',
            'total' => 'Total',
            'depositAmount' => 'Deposit Amount',
            'payableAmount' => 'Payable Amount',
            'checking' => 'Checking live availability...',
            'available' => 'Selected dates are available.',
            'unavailable' => 'Selected dates are unavailable.',
            'error' => 'We could not update this booking quote right now. Please try again.',
            'idle' => 'No booking information is active.',
            'sessionReady' => 'Your booking details are ready for payment.',
            'processingPayment' => 'Processing booking...',
            'paymentSuccessTitle' => 'Booking Confirmed',
            'paymentSuccessBody' => 'Your reservation was created successfully.',
            'missingContext' => 'Property context is required to load checkout.',
            'ageConfirmation' => 'I confirm the primary guest checking in is 25 years of age or older.',
            'termsPrefix' => 'By submitting this form, you agree to this listing\'s',
            'termsLinkText' => 'terms and conditions',
            'rentalAgreementPrefix' => 'By submitting this form, you agree to abide by the terms and conditions in the rental agreement.',
            'rentalAgreementLinkText' => 'View rental agreement.',
        ],
    ];

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $resolved_presets = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function get_all(): array
    {
        if ($this->resolved_presets !== null) {
            return $this->resolved_presets;
        }

        $presets = $this->load_plugin_presets();
        $filtered_presets = apply_filters('barefoot_engine_booking_checkout_presets', $presets);

        if (!is_array($filtered_presets)) {
            $filtered_presets = $presets;
        }

        $resolved_presets = [];

        foreach ($filtered_presets as $preset_key => $preset) {
            $normalized_key = $this->sanitize_preset_key($preset_key);
            if ($normalized_key === '' || !is_array($preset)) {
                continue;
            }

            $resolved_presets[$normalized_key] = $this->merge_presets(
                self::FALLBACK_PRESET,
                $this->normalize_preset($preset)
            );
        }

        $this->resolved_presets = $resolved_presets;

        return $this->resolved_presets;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $widget_id): array
    {
        $presets = $this->get_all();
        $preset_key = $this->sanitize_preset_key($widget_id);

        if ($preset_key !== '' && isset($presets[$preset_key])) {
            return $presets[$preset_key];
        }

        if (isset($presets[self::DEFAULT_PRESET_KEY])) {
            return $presets[self::DEFAULT_PRESET_KEY];
        }

        return self::FALLBACK_PRESET;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load_plugin_presets(): array
    {
        $preset_file = BAREFOOT_ENGINE_PLUGIN_DIR . 'config/booking-checkout/presets.php';
        if (!is_readable($preset_file)) {
            return [
                self::DEFAULT_PRESET_KEY => self::FALLBACK_PRESET,
            ];
        }

        $presets = require $preset_file;
        if (!is_array($presets)) {
            return [
                self::DEFAULT_PRESET_KEY => self::FALLBACK_PRESET,
            ];
        }

        return $presets;
    }

    private function sanitize_preset_key($preset_key): string
    {
        if (!is_string($preset_key)) {
            return '';
        }

        return sanitize_title_with_dashes(trim($preset_key));
    }

    /**
     * @param array<string, mixed> $preset
     * @return array<string, mixed>
     */
    private function normalize_preset(array $preset): array
    {
        $normalized = [];

        if (array_key_exists('currency', $preset)) {
            $currency = trim(sanitize_text_field((string) $preset['currency']));
            $normalized['currency'] = $currency !== '' ? $currency : '$';
        }

        if (array_key_exists('reztypeid', $preset)) {
            $normalized['reztypeid'] = $this->normalize_positive_int($preset['reztypeid'], 26);
        }

        if (array_key_exists('paymentMode', $preset)) {
            $normalized['paymentMode'] = $this->normalize_payment_mode($preset['paymentMode']);
        }

        if (array_key_exists('portalId', $preset)) {
            $normalized['portalId'] = sanitize_text_field((string) $preset['portalId']);
        }

        if (array_key_exists('sourceOfBusiness', $preset)) {
            $normalized['sourceOfBusiness'] = sanitize_text_field((string) $preset['sourceOfBusiness']);
        }

        if (isset($preset['calendarOptions']) && is_array($preset['calendarOptions'])) {
            $calendar_options = [];

            if (array_key_exists('monthsToShow', $preset['calendarOptions'])) {
                $calendar_options['monthsToShow'] = max(1, min(6, (int) $preset['calendarOptions']['monthsToShow']));
            }

            if (array_key_exists('datepickerPlacement', $preset['calendarOptions'])) {
                $calendar_options['datepickerPlacement'] = sanitize_text_field((string) $preset['calendarOptions']['datepickerPlacement']);
            }

            if (array_key_exists('defaultMinDays', $preset['calendarOptions'])) {
                $calendar_options['defaultMinDays'] = max(1, (int) $preset['calendarOptions']['defaultMinDays']);
            }

            if (array_key_exists('tooltipLabel', $preset['calendarOptions'])) {
                $calendar_options['tooltipLabel'] = sanitize_text_field((string) $preset['calendarOptions']['tooltipLabel']);
            }

            if (array_key_exists('showTooltip', $preset['calendarOptions'])) {
                $calendar_options['showTooltip'] = $this->normalize_boolean($preset['calendarOptions']['showTooltip'], true);
            }

            if (array_key_exists('showClearButton', $preset['calendarOptions'])) {
                $calendar_options['showClearButton'] = $this->normalize_boolean($preset['calendarOptions']['showClearButton'], true);
            }

            if (!empty($calendar_options)) {
                $normalized['calendarOptions'] = $calendar_options;
            }
        }

        if (isset($preset['guests']) && is_array($preset['guests'])) {
            $guests = [];

            if (array_key_exists('label', $preset['guests'])) {
                $guests['label'] = sanitize_text_field((string) $preset['guests']['label']);
            }

            if (array_key_exists('placeholder', $preset['guests'])) {
                $guests['placeholder'] = sanitize_text_field((string) $preset['guests']['placeholder']);
            }

            if (array_key_exists('defaultValue', $preset['guests'])) {
                $guests['defaultValue'] = sanitize_text_field((string) $preset['guests']['defaultValue']);
            }

            if (array_key_exists('options', $preset['guests']) && is_array($preset['guests']['options'])) {
                $options = [];
                foreach ($preset['guests']['options'] as $option) {
                    if (!is_scalar($option)) {
                        continue;
                    }

                    $value = trim(sanitize_text_field((string) $option));
                    if ($value !== '') {
                        $options[] = $value;
                    }
                }

                $guests['options'] = array_values(array_unique($options));
            }

            if (!empty($guests)) {
                $normalized['guests'] = $guests;
            }
        }

        if (isset($preset['links']) && is_array($preset['links'])) {
            $links = [];
            foreach (['termsUrl', 'rentalAgreementUrl'] as $allowed_key) {
                if (!array_key_exists($allowed_key, $preset['links'])) {
                    continue;
                }

                $links[$allowed_key] = esc_url_raw((string) $preset['links'][$allowed_key]);
            }

            if (!empty($links)) {
                $normalized['links'] = $links;
            }
        }

        if (isset($preset['labels']) && is_array($preset['labels'])) {
            $labels = [];
            foreach (array_keys(self::FALLBACK_PRESET['labels']) as $allowed_key) {
                if (!array_key_exists($allowed_key, $preset['labels'])) {
                    continue;
                }

                $labels[$allowed_key] = sanitize_text_field((string) $preset['labels'][$allowed_key]);
            }

            if (!empty($labels)) {
                $normalized['labels'] = $labels;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function merge_presets(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && $this->is_associative_array($base[$key])
                && $this->is_associative_array($value)
            ) {
                /** @var array<string, mixed> $base_value */
                $base_value = $base[$key];
                /** @var array<string, mixed> $override_value */
                $override_value = $value;
                $base[$key] = $this->merge_presets($base_value, $override_value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param mixed $value
     */
    private function normalize_boolean($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        if (!is_string($value)) {
            return $default;
        }

        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    /**
     * @param mixed $value
     */
    private function normalize_positive_int($value, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : $default;
    }

    /**
     * @param mixed $value
     */
    private function normalize_payment_mode($value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return in_array($normalized, ['ON', 'TRUE', 'FALSE'], true) ? $normalized : 'ON';
    }

    /**
     * @param array<mixed> $array
     */
    private function is_associative_array(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
