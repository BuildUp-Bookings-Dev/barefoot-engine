<?php

namespace BarefootEngine\Properties;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Booking_Records
{
    public const POST_TYPE = 'be_booking';

    public const STATUS_STARTED = 'started';
    public const STATUS_READY_FOR_PAYMENT = 'ready_for_payment';
    public const STATUS_BOOKING_SUCCESS = 'booking_success';
    public const STATUS_BOOKING_FAILED = 'booking_failed';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_UNAVAILABLE = 'unavailable';

    private const META_STATUS = '_be_booking_status';
    private const META_PROPERTY_ID = '_be_booking_property_id';
    private const META_PROPERTY_POST_ID = '_be_booking_property_post_id';
    private const META_CHECK_IN = '_be_booking_check_in';
    private const META_CHECK_OUT = '_be_booking_check_out';
    private const META_GUESTS = '_be_booking_guests';
    private const META_REZTYPEID = '_be_booking_reztypeid';
    private const META_PAYMENT_MODE = '_be_booking_payment_mode';
    private const META_PORTAL_ID = '_be_booking_portal_id';
    private const META_PROPERTY_SUMMARY = '_be_booking_property_summary';
    private const META_GUEST_DETAILS = '_be_booking_guest_details';
    private const META_TOTALS = '_be_booking_totals';
    private const META_PAYMENT_SCHEDULE = '_be_booking_payment_schedule';
    private const META_PAYABLE_AMOUNT = '_be_booking_payable_amount';
    private const META_LEASE_ID = '_be_booking_lease_id';
    private const META_TENANT_ID = '_be_booking_tenant_id';
    private const META_FOLIO_ID = '_be_booking_folio_id';
    private const META_AMOUNT = '_be_booking_amount';
    private const META_PAYMENT_SUMMARY = '_be_booking_payment_summary';
    private const META_DIAGNOSTICS = '_be_booking_diagnostics';
    private const META_EVENTS = '_be_booking_events';
    private const META_SESSION_TOKEN_HASH = '_be_booking_session_token_hash';
    private static bool $admin_styles_printed = false;

    /**
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return [
            self::STATUS_STARTED,
            self::STATUS_READY_FOR_PAYMENT,
            self::STATUS_BOOKING_SUCCESS,
            self::STATUS_BOOKING_FAILED,
            self::STATUS_SUPERSEDED,
            self::STATUS_EXPIRED,
            self::STATUS_UNAVAILABLE,
        ];
    }

    public function register(): void
    {
        register_post_type(
            self::POST_TYPE,
            [
                'labels' => [
                    'name' => __('Bookings', 'barefoot-engine'),
                    'singular_name' => __('Booking', 'barefoot-engine'),
                    'menu_name' => __('Bookings', 'barefoot-engine'),
                    'all_items' => __('All Bookings', 'barefoot-engine'),
                    'edit_item' => __('View Booking', 'barefoot-engine'),
                    'view_item' => __('View Booking', 'barefoot-engine'),
                    'search_items' => __('Search Bookings', 'barefoot-engine'),
                    'not_found' => __('No bookings found.', 'barefoot-engine'),
                    'not_found_in_trash' => __('No bookings found in Trash.', 'barefoot-engine'),
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => 'edit.php?post_type=' . Property_Post_Type::POST_TYPE,
                'show_in_admin_bar' => false,
                'show_in_rest' => false,
                'exclude_from_search' => true,
                'publicly_queryable' => false,
                'has_archive' => false,
                'hierarchical' => false,
                'supports' => ['title'],
                'map_meta_cap' => true,
                'capabilities' => [
                    'create_posts' => 'do_not_allow',
                ],
            ]
        );
    }

    /**
     * @param array<string, mixed> $record
     */
    public function create_record(array $record): int
    {
        $status = $this->sanitize_status((string) ($record['status'] ?? self::STATUS_STARTED));
        $property_id = $this->clean_string($record['property_id'] ?? '');
        $check_in = $this->clean_string($record['check_in'] ?? '');
        $check_out = $this->clean_string($record['check_out'] ?? '');
        $folio_id = $this->clean_string($record['folio_id'] ?? '');

        $post_id = wp_insert_post(
            [
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => $this->build_post_title($property_id, $check_in, $check_out, $folio_id),
            ],
            true
        );

        if (is_wp_error($post_id) || !is_numeric($post_id) || (int) $post_id <= 0) {
            return 0;
        }

        $event_label = $this->clean_string($record['event_label'] ?? '');
        $event_context = isset($record['event_context']) && is_array($record['event_context'])
            ? $record['event_context']
            : [];

        $this->update_record((int) $post_id, $status, $record, $event_label, $event_context);

        return (int) $post_id;
    }

    /**
     * @param array<string, mixed> $updates
     * @param array<string, mixed> $event_context
     */
    public function update_record(
        int $post_id,
        string $status,
        array $updates = [],
        string $event_label = '',
        array $event_context = []
    ): void {
        if ($post_id <= 0) {
            return;
        }

        $normalized_status = $this->sanitize_status($status);
        update_post_meta($post_id, self::META_STATUS, $normalized_status);

        if (array_key_exists('property_id', $updates)) {
            update_post_meta($post_id, self::META_PROPERTY_ID, $this->clean_string($updates['property_id']));
        }

        if (array_key_exists('property_summary', $updates)) {
            $summary = is_array($updates['property_summary']) ? $updates['property_summary'] : [];
            update_post_meta($post_id, self::META_PROPERTY_SUMMARY, $summary);

            $post_ref = isset($summary['postId']) && is_numeric($summary['postId']) ? (int) $summary['postId'] : 0;
            if ($post_ref > 0) {
                update_post_meta($post_id, self::META_PROPERTY_POST_ID, $post_ref);
            }
        }

        if (array_key_exists('check_in', $updates)) {
            update_post_meta($post_id, self::META_CHECK_IN, $this->clean_string($updates['check_in']));
        }

        if (array_key_exists('check_out', $updates)) {
            update_post_meta($post_id, self::META_CHECK_OUT, $this->clean_string($updates['check_out']));
        }

        if (array_key_exists('guests', $updates)) {
            $guests = is_numeric($updates['guests']) ? max(1, (int) $updates['guests']) : 1;
            update_post_meta($post_id, self::META_GUESTS, $guests);
        }

        if (array_key_exists('reztypeid', $updates)) {
            $reztypeid = is_numeric($updates['reztypeid']) ? max(1, (int) $updates['reztypeid']) : 0;
            if ($reztypeid > 0) {
                update_post_meta($post_id, self::META_REZTYPEID, $reztypeid);
            }
        }

        if (array_key_exists('payment_mode', $updates)) {
            update_post_meta($post_id, self::META_PAYMENT_MODE, $this->clean_string($updates['payment_mode']));
        }

        if (array_key_exists('portal_id', $updates)) {
            update_post_meta($post_id, self::META_PORTAL_ID, $this->clean_string($updates['portal_id']));
        }

        if (array_key_exists('guest_details', $updates)) {
            update_post_meta(
                $post_id,
                self::META_GUEST_DETAILS,
                is_array($updates['guest_details']) ? $updates['guest_details'] : []
            );
        }

        if (array_key_exists('totals', $updates)) {
            update_post_meta($post_id, self::META_TOTALS, is_array($updates['totals']) ? $updates['totals'] : []);
        }

        if (array_key_exists('payment_schedule', $updates)) {
            update_post_meta(
                $post_id,
                self::META_PAYMENT_SCHEDULE,
                is_array($updates['payment_schedule']) ? $updates['payment_schedule'] : []
            );
        }

        if (array_key_exists('payable_amount', $updates) && is_numeric($updates['payable_amount'])) {
            update_post_meta($post_id, self::META_PAYABLE_AMOUNT, $this->normalize_money((float) $updates['payable_amount']));
        }

        if (array_key_exists('lease_id', $updates) && is_numeric($updates['lease_id'])) {
            update_post_meta($post_id, self::META_LEASE_ID, max(0, (int) $updates['lease_id']));
        }

        if (array_key_exists('tenant_id', $updates) && is_numeric($updates['tenant_id'])) {
            update_post_meta($post_id, self::META_TENANT_ID, max(0, (int) $updates['tenant_id']));
        }

        if (array_key_exists('folio_id', $updates)) {
            update_post_meta($post_id, self::META_FOLIO_ID, $this->clean_string($updates['folio_id']));
        }

        if (array_key_exists('amount', $updates) && is_numeric($updates['amount'])) {
            update_post_meta($post_id, self::META_AMOUNT, $this->normalize_money((float) $updates['amount']));
        }

        if (array_key_exists('payment_summary', $updates)) {
            update_post_meta(
                $post_id,
                self::META_PAYMENT_SUMMARY,
                is_array($updates['payment_summary']) ? $updates['payment_summary'] : []
            );
        }

        if (array_key_exists('diagnostics', $updates)) {
            update_post_meta(
                $post_id,
                self::META_DIAGNOSTICS,
                is_array($updates['diagnostics']) ? $updates['diagnostics'] : []
            );
        }

        if (array_key_exists('session_token_hash', $updates)) {
            update_post_meta($post_id, self::META_SESSION_TOKEN_HASH, $this->clean_string($updates['session_token_hash']));
        }

        if ($event_label !== '') {
            $this->append_event($post_id, $event_label, $normalized_status, $event_context);
        }

        $property_id = $this->get_meta_string($post_id, self::META_PROPERTY_ID);
        $check_in = $this->get_meta_string($post_id, self::META_CHECK_IN);
        $check_out = $this->get_meta_string($post_id, self::META_CHECK_OUT);
        $folio_id = $this->get_meta_string($post_id, self::META_FOLIO_ID);

        wp_update_post(
            [
                'ID' => $post_id,
                'post_title' => $this->build_post_title($property_id, $check_in, $check_out, $folio_id),
            ]
        );
    }

    public function register_metaboxes(): void
    {
        add_meta_box(
            'be-booking-summary',
            __('Booking Summary', 'barefoot-engine'),
            [$this, 'render_summary_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'be-booking-details',
            __('Booking Details', 'barefoot-engine'),
            [$this, 'render_details_metabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function filter_columns(array $columns): array
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox" />',
            'title' => __('Booking', 'barefoot-engine'),
            'status' => __('Status', 'barefoot-engine'),
            'property' => __('Property', 'barefoot-engine'),
            'stay' => __('Stay', 'barefoot-engine'),
            'guests' => __('Guests', 'barefoot-engine'),
            'folio' => __('Folio', 'barefoot-engine'),
            'payable' => __('Payable', 'barefoot-engine'),
            'modified' => __('Updated', 'barefoot-engine'),
        ];
    }

    public function render_column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'status':
                echo esc_html($this->humanize_status($this->get_meta_string($post_id, self::META_STATUS)));
                break;
            case 'property':
                $summary = get_post_meta($post_id, self::META_PROPERTY_SUMMARY, true);
                $title = is_array($summary) && isset($summary['title']) ? $this->clean_string($summary['title']) : '';
                $property_id = $this->get_meta_string($post_id, self::META_PROPERTY_ID);
                $content = $title !== '' ? $title : ('Property #' . $property_id);
                echo esc_html($content);
                break;
            case 'stay':
                $check_in = $this->get_meta_string($post_id, self::META_CHECK_IN);
                $check_out = $this->get_meta_string($post_id, self::META_CHECK_OUT);
                echo esc_html(($check_in !== '' && $check_out !== '') ? ($check_in . ' - ' . $check_out) : '—');
                break;
            case 'guests':
                $guests = (int) get_post_meta($post_id, self::META_GUESTS, true);
                echo esc_html($guests > 0 ? (string) $guests : '—');
                break;
            case 'folio':
                $folio = $this->get_meta_string($post_id, self::META_FOLIO_ID);
                echo esc_html($folio !== '' ? $folio : '—');
                break;
            case 'payable':
                $payable = get_post_meta($post_id, self::META_PAYABLE_AMOUNT, true);
                if (!is_numeric($payable)) {
                    echo esc_html('—');
                    break;
                }

                echo esc_html('$' . number_format((float) $payable, 2, '.', ','));
                break;
            case 'modified':
                $modified = get_post_modified_time('Y-m-d H:i:s', false, $post_id);
                echo esc_html($modified ?: '—');
                break;
        }
    }

    public function render_summary_metabox(\WP_Post $post): void
    {
        $this->render_admin_styles_once();

        $post_id = (int) $post->ID;
        $status = $this->humanize_status($this->get_meta_string($post_id, self::META_STATUS));
        $stats = [
            [
                'label' => __('Status', 'barefoot-engine'),
                'value' => $status,
                'badge' => true,
            ],
            [
                'label' => __('Property ID', 'barefoot-engine'),
                'value' => $this->get_meta_string($post_id, self::META_PROPERTY_ID),
            ],
            [
                'label' => __('Stay', 'barefoot-engine'),
                'value' => $this->format_stay_range(
                    $this->get_meta_string($post_id, self::META_CHECK_IN),
                    $this->get_meta_string($post_id, self::META_CHECK_OUT)
                ),
            ],
            [
                'label' => __('Guests', 'barefoot-engine'),
                'value' => (string) ((int) get_post_meta($post_id, self::META_GUESTS, true)),
            ],
            [
                'label' => __('Folio ID', 'barefoot-engine'),
                'value' => $this->get_meta_string($post_id, self::META_FOLIO_ID),
            ],
            [
                'label' => __('Lease ID', 'barefoot-engine'),
                'value' => (string) ((int) get_post_meta($post_id, self::META_LEASE_ID, true)),
            ],
            [
                'label' => __('Tenant ID', 'barefoot-engine'),
                'value' => (string) ((int) get_post_meta($post_id, self::META_TENANT_ID, true)),
            ],
            [
                'label' => __('Payable Amount', 'barefoot-engine'),
                'value' => $this->format_money_meta($post_id, self::META_PAYABLE_AMOUNT),
            ],
            [
                'label' => __('Charged Amount', 'barefoot-engine'),
                'value' => $this->format_money_meta($post_id, self::META_AMOUNT),
            ],
        ];

        echo '<div class="be-booking-admin-grid">';
        foreach ($stats as $stat) {
            $value = $this->clean_string($stat['value'] ?? '');
            $label = $this->clean_string($stat['label'] ?? '');
            $is_badge = !empty($stat['badge']);

            echo '<article class="be-booking-admin-card">';
            echo '<p class="be-booking-admin-label">' . esc_html($label) . '</p>';
            if ($is_badge) {
                echo '<p><span class="be-booking-admin-badge">' . esc_html($value !== '' ? $value : '—') . '</span></p>';
            } else {
                echo '<p class="be-booking-admin-value">' . esc_html($value !== '' ? $value : '—') . '</p>';
            }
            echo '</article>';
        }
        echo '</div>';
    }

    public function render_details_metabox(\WP_Post $post): void
    {
        $this->render_admin_styles_once();

        $post_id = (int) $post->ID;
        $property_snapshot = get_post_meta($post_id, self::META_PROPERTY_SUMMARY, true);
        $guest_details = get_post_meta($post_id, self::META_GUEST_DETAILS, true);
        $totals = get_post_meta($post_id, self::META_TOTALS, true);
        $payment_schedule = get_post_meta($post_id, self::META_PAYMENT_SCHEDULE, true);
        $payment_summary = get_post_meta($post_id, self::META_PAYMENT_SUMMARY, true);
        $diagnostics = get_post_meta($post_id, self::META_DIAGNOSTICS, true);

        $property_stats = is_array($property_snapshot) && isset($property_snapshot['stats']) && is_array($property_snapshot['stats'])
            ? $property_snapshot['stats']
            : [];
        $property_rows = [
            __('Title', 'barefoot-engine') => is_array($property_snapshot) ? ($property_snapshot['title'] ?? '') : '',
            __('Property ID', 'barefoot-engine') => is_array($property_snapshot) ? ($property_snapshot['propertyId'] ?? '') : '',
            __('Post ID', 'barefoot-engine') => is_array($property_snapshot) ? ($property_snapshot['postId'] ?? '') : '',
            __('Address', 'barefoot-engine') => is_array($property_snapshot) ? ($property_snapshot['address'] ?? '') : '',
            __('Sleeps', 'barefoot-engine') => is_array($property_stats) ? ($property_stats['sleeps'] ?? '') : '',
            __('Bedrooms', 'barefoot-engine') => is_array($property_stats) ? ($property_stats['bedrooms'] ?? '') : '',
            __('Bathrooms', 'barefoot-engine') => is_array($property_stats) ? ($property_stats['bathrooms'] ?? '') : '',
            __('Listing URL', 'barefoot-engine') => is_array($property_snapshot) ? ($property_snapshot['permalink'] ?? '') : '',
        ];

        $guest_rows = [
            __('First Name', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['first_name'] ?? '') : '',
            __('Last Name', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['last_name'] ?? '') : '',
            __('Email', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['email'] ?? '') : '',
            __('Cell Phone', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['cell_phone'] ?? '') : '',
            __('Address 1', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['address_1'] ?? '') : '',
            __('Address 2', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['address_2'] ?? '') : '',
            __('City', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['city'] ?? '') : '',
            __('State', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['state'] ?? '') : '',
            __('Country', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['country'] ?? '') : '',
            __('Postal Code', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['postal_code'] ?? '') : '',
            __('Age Confirmed', 'barefoot-engine') => is_array($guest_details) ? ($guest_details['age_confirmed'] ?? '') : '',
        ];

        $totals_rows = [
            __('Rent', 'barefoot-engine') => is_array($totals) ? ($totals['daily_price'] ?? '') : '',
            __('Subtotal', 'barefoot-engine') => is_array($totals) ? ($totals['subtotal'] ?? '') : '',
            __('Tax', 'barefoot-engine') => is_array($totals) ? ($totals['tax_total'] ?? '') : '',
            __('Total', 'barefoot-engine') => is_array($totals) ? ($totals['grand_total'] ?? '') : '',
            __('Nights', 'barefoot-engine') => is_array($totals) ? ($totals['nights'] ?? '') : '',
        ];

        $payment_rows = [
            __('Card Type', 'barefoot-engine') => is_array($payment_summary) ? ($payment_summary['card_type'] ?? '') : '',
            __('Last 4 Digits', 'barefoot-engine') => is_array($payment_summary) ? ($payment_summary['last4'] ?? '') : '',
            __('Masked Card', 'barefoot-engine') => is_array($payment_summary) ? ($payment_summary['masked_card'] ?? '') : '',
            __('Name On Card', 'barefoot-engine') => is_array($payment_summary) ? ($payment_summary['name_on_card'] ?? '') : '',
        ];

        $diagnostic_rows = [];
        if (is_array($diagnostics)) {
            foreach ($diagnostics as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                $diagnostic_rows[$this->humanize_data_key($key)] = $value;
            }
        }

        echo '<div class="be-booking-admin-sections">';
        $this->render_key_value_section(__('Property Snapshot', 'barefoot-engine'), $property_rows);
        $this->render_key_value_section(__('Primary Guest', 'barefoot-engine'), $guest_rows);
        $this->render_key_value_section(__('Quote Totals', 'barefoot-engine'), $totals_rows, true);
        $this->render_schedule_section(__('Payment Schedule', 'barefoot-engine'), is_array($payment_schedule) ? $payment_schedule : []);
        $this->render_key_value_section(__('Payment Summary', 'barefoot-engine'), $payment_rows);
        $this->render_key_value_section(__('Diagnostics', 'barefoot-engine'), $diagnostic_rows);
        echo '</div>';
    }

    public function render_events_metabox(\WP_Post $post): void
    {
        $events = get_post_meta((int) $post->ID, self::META_EVENTS, true);
        if (!is_array($events) || $events === []) {
            echo '<p>' . esc_html__('No events recorded.', 'barefoot-engine') . '</p>';
            return;
        }

        echo '<ol style="margin:0 0 0 18px">';
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $label = isset($event['label']) ? $this->clean_string($event['label']) : '';
            $status = isset($event['status']) ? $this->humanize_status((string) $event['status']) : '';
            $time = isset($event['time']) ? $this->clean_string($event['time']) : '';

            echo '<li style="margin-bottom:.6rem">';
            echo '<strong>' . esc_html($label !== '' ? $label : __('Event', 'barefoot-engine')) . '</strong><br />';
            echo '<small>' . esc_html(trim($status . ($time !== '' ? ' · ' . $time : ''))) . '</small>';
            echo '</li>';
        }
        echo '</ol>';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function append_event(int $post_id, string $label, string $status, array $context = []): void
    {
        $events = get_post_meta($post_id, self::META_EVENTS, true);
        if (!is_array($events)) {
            $events = [];
        }

        $events[] = [
            'label' => $this->clean_string($label),
            'status' => $this->sanitize_status($status),
            'time' => wp_date('Y-m-d H:i:s'),
            'context' => $context,
        ];

        update_post_meta($post_id, self::META_EVENTS, array_values($events));
    }

    private function render_admin_styles_once(): void
    {
        if (self::$admin_styles_printed) {
            return;
        }

        self::$admin_styles_printed = true;

        echo '<style>
            .be-booking-admin-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
            .be-booking-admin-card { border:1px solid #dcdcde; border-radius:8px; padding:12px; background:#fff; }
            .be-booking-admin-label { margin:0 0 6px; color:#646970; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }
            .be-booking-admin-value { margin:0; color:#1d2327; font-size:16px; font-weight:600; line-height:1.35; word-break:break-word; }
            .be-booking-admin-badge { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-size:12px; font-weight:600; }
            .be-booking-admin-sections { display:grid; gap:14px; }
            .be-booking-admin-section { border:1px solid #dcdcde; border-radius:8px; background:#fff; overflow:hidden; }
            .be-booking-admin-section h4 { margin:0; padding:10px 12px; border-bottom:1px solid #dcdcde; background:#f6f7f7; font-size:13px; }
            .be-booking-admin-section table { margin:0; border:0; }
            .be-booking-admin-section td,.be-booking-admin-section th { padding:9px 12px; vertical-align:top; }
            .be-booking-admin-section th { width:220px; color:#50575e; font-weight:600; background:#fcfcfc; }
            .be-booking-admin-empty { margin:0; padding:12px; color:#646970; font-style:italic; }
            .be-booking-admin-cell { color:#1d2327; font-size:13px; line-height:1.45; word-break:break-word; }
        </style>';
    }

    /**
     * @param array<string, mixed> $rows
     */
    private function render_key_value_section(string $title, array $rows, bool $format_money_rows = false): void
    {
        echo '<section class="be-booking-admin-section">';
        echo '<h4>' . esc_html($title) . '</h4>';

        if ($rows === []) {
            echo '<p class="be-booking-admin-empty">' . esc_html__('No data captured.', 'barefoot-engine') . '</p>';
            echo '</section>';
            return;
        }

        echo '<table class="widefat striped"><tbody>';
        foreach ($rows as $label => $value) {
            if (!is_string($label) || $label === '') {
                continue;
            }

            $display_value = $this->format_detail_value($value, $format_money_rows);
            if ($label === __('Listing URL', 'barefoot-engine') && is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                $display_value = '<a href="' . esc_url($value) . '" target="_blank" rel="noopener noreferrer">' . esc_html($value) . '</a>';
            }

            echo '<tr>';
            echo '<th>' . esc_html($label) . '</th>';
            echo '<td class="be-booking-admin-cell">' . $display_value . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</section>';
    }

    /**
     * @param array<int, mixed> $schedule_rows
     */
    private function render_schedule_section(string $title, array $schedule_rows): void
    {
        echo '<section class="be-booking-admin-section">';
        echo '<h4>' . esc_html($title) . '</h4>';

        if ($schedule_rows === []) {
            echo '<p class="be-booking-admin-empty">' . esc_html__('No data captured.', 'barefoot-engine') . '</p>';
            echo '</section>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Due Date', 'barefoot-engine') . '</th>';
        echo '<th>' . esc_html__('Amount', 'barefoot-engine') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($schedule_rows as $item) {
            if (!is_array($item)) {
                continue;
            }

            $due_date = $this->clean_string($item['due_date'] ?? $item['dueDate'] ?? '');
            $amount = $item['amount'] ?? '';

            echo '<tr>';
            echo '<td>' . esc_html($due_date !== '' ? $due_date : '—') . '</td>';
            echo '<td>' . $this->format_detail_value($amount, true) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</section>';
    }

    private function format_stay_range(string $check_in, string $check_out): string
    {
        if ($check_in === '' || $check_out === '') {
            return '—';
        }

        return $check_in . ' - ' . $check_out;
    }

    private function humanize_data_key(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    private function format_detail_value(mixed $value, bool $format_money = false): string
    {
        if (is_bool($value)) {
            return esc_html($value ? __('Yes', 'barefoot-engine') : __('No', 'barefoot-engine'));
        }

        if (is_numeric($value)) {
            $number = (float) $value;
            if ($format_money) {
                return esc_html('$' . number_format($number, 2, '.', ','));
            }

            return esc_html((string) $value);
        }

        if (is_array($value)) {
            if ($value === []) {
                return esc_html('—');
            }

            return esc_html((string) wp_json_encode($value, JSON_UNESCAPED_SLASHES));
        }

        $text = $this->clean_string($value);
        if ($text === '') {
            return esc_html('—');
        }

        return esc_html($text);
    }

    private function sanitize_status(string $status): string
    {
        $normalized = sanitize_key($status);

        return in_array($normalized, $this->statuses(), true)
            ? $normalized
            : self::STATUS_STARTED;
    }

    private function humanize_status(string $status): string
    {
        $normalized = $this->sanitize_status($status);

        return ucwords(str_replace('_', ' ', $normalized));
    }

    private function clean_string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function normalize_money(float $value): float
    {
        return (float) number_format($value, 2, '.', '');
    }

    private function format_money_meta(int $post_id, string $meta_key): string
    {
        $value = get_post_meta($post_id, $meta_key, true);
        if (!is_numeric($value)) {
            return '—';
        }

        return '$' . number_format((float) $value, 2, '.', ',');
    }

    private function get_meta_string(int $post_id, string $meta_key): string
    {
        $value = get_post_meta($post_id, $meta_key, true);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function build_post_title(string $property_id, string $check_in, string $check_out, string $folio_id): string
    {
        $parts = [];

        if ($folio_id !== '') {
            $parts[] = 'Folio ' . $folio_id;
        }

        if ($property_id !== '') {
            $parts[] = 'Property ' . $property_id;
        }

        if ($check_in !== '' && $check_out !== '') {
            $parts[] = $check_in . ' - ' . $check_out;
        }

        if ($parts === []) {
            $parts[] = 'Booking';
        }

        return implode(' · ', $parts);
    }
}
