<?php

$context = get_query_var('barefoot_engine_booking_confirmation_context');
if (!is_array($context)) {
    $context = [
        'valid' => false,
        'title' => __('Booking confirmation unavailable', 'barefoot-engine'),
        'message' => __('We could not load this booking confirmation.', 'barefoot-engine'),
        'propertiesUrl' => home_url('/properties/'),
    ];
}

$is_valid = !empty($context['valid']);
$property = is_array($context['property'] ?? null) ? $context['property'] : [];
$stay = is_array($context['stay'] ?? null) ? $context['stay'] : [];
$payments = is_array($context['payments'] ?? null) ? $context['payments'] : [];
$map = is_array($context['map'] ?? null) ? $context['map'] : [];
$calendar = is_array($context['calendar'] ?? null) ? $context['calendar'] : [];
$folio_id = is_scalar($context['folioId'] ?? null) ? trim((string) $context['folioId']) : '';
$property_permalink = is_scalar($property['permalink'] ?? null) ? trim((string) $property['permalink']) : '';
$property_address = is_scalar($property['address'] ?? null) ? trim((string) $property['address']) : '';
$check_in_display = is_scalar($stay['checkInDisplay'] ?? null) ? trim((string) $stay['checkInDisplay']) : '—';
$check_out_display = is_scalar($stay['checkOutDisplay'] ?? null) ? trim((string) $stay['checkOutDisplay']) : '—';
$guests_label = is_scalar($stay['guestsLabel'] ?? null) ? trim((string) $stay['guestsLabel']) : '—';
$nights_label = is_scalar($stay['nightsLabel'] ?? null) ? trim((string) $stay['nightsLabel']) : '—';
$rent_label = (string) ($payments['rentLabel'] ?? __('Rent', 'barefoot-engine'));

if (!empty($stay['nights']) && is_numeric($stay['nights'])) {
    $rent_label = sprintf(
        /* translators: %s is the booking duration label such as "3 nights". */
        __('Rent (%s)', 'barefoot-engine'),
        $nights_label
    );
}

$directions_url = '';
if (isset($map['lat'], $map['lng']) && is_numeric($map['lat']) && is_numeric($map['lng'])) {
    $directions_url = add_query_arg(
        [
            'api' => 1,
            'query' => trim((string) $map['lat']) . ',' . trim((string) $map['lng']),
        ],
        'https://www.google.com/maps/search/'
    );
} elseif ($property_address !== '') {
    $directions_url = add_query_arg(
        [
            'api' => 1,
            'query' => $property_address,
        ],
        'https://www.google.com/maps/search/'
    );
}

$format_money = static function (mixed $value): string {
    if (!is_numeric($value)) {
        return '—';
    }

    return '$' . number_format((float) $value, 2, '.', ',');
};

get_header();
?>
<main class="barefoot-engine-public barefoot-engine-booking-confirmed">
    <div class="barefoot-engine-booking-confirmed__container">
        <?php if ($is_valid) : ?>
            <header class="barefoot-engine-booking-confirmed__hero">
                <div class="barefoot-engine-booking-confirmed__hero-copy">
                    <p class="barefoot-engine-booking-confirmed__eyebrow"><?php esc_html_e('Confirmed', 'barefoot-engine'); ?></p>
                    <h1 class="barefoot-engine-booking-confirmed__title"><?php esc_html_e('Your stay is all set.', 'barefoot-engine'); ?></h1>
                    <?php if ($folio_id !== '') : ?>
                        <p class="barefoot-engine-booking-confirmed__reservation-inline">
                            <?php esc_html_e('Reservation ID:', 'barefoot-engine'); ?>
                            <span><?php echo esc_html($folio_id); ?></span>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($property_permalink !== '') : ?>
                    <div class="barefoot-engine-booking-confirmed__hero-actions">
                        <a class="barefoot-engine-booking-confirmed__hero-action barefoot-engine-booking-confirmed__hero-action--ghost" href="<?php echo esc_url($property_permalink); ?>">
                            <?php esc_html_e('See listing details', 'barefoot-engine'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </header>

            <div class="barefoot-engine-booking-confirmed__layout">
                <section class="barefoot-engine-booking-confirmed__main">
                    <section class="barefoot-engine-booking-confirmed__summary">
                        <p class="barefoot-engine-booking-confirmed__section-label"><?php esc_html_e('Stay Summary', 'barefoot-engine'); ?></p>

                        <div class="barefoot-engine-booking-confirmed__media">
                            <?php if (!empty($property['imageUrl'])) : ?>
                                <img
                                    class="barefoot-engine-booking-confirmed__media-image"
                                    src="<?php echo esc_url((string) $property['imageUrl']); ?>"
                                    alt="<?php echo esc_attr((string) ($property['title'] ?? __('Property', 'barefoot-engine'))); ?>"
                                />
                            <?php else : ?>
                                <div class="barefoot-engine-booking-confirmed__media-image barefoot-engine-booking-confirmed__media-image--empty" aria-hidden="true"></div>
                            <?php endif; ?>
                        </div>

                        <div class="barefoot-engine-booking-confirmed__identity">
                            <h2 class="barefoot-engine-booking-confirmed__property-title"><?php echo esc_html((string) ($property['title'] ?? __('Property', 'barefoot-engine'))); ?></h2>
                            <?php if ($property_address !== '') : ?>
                                <p class="barefoot-engine-booking-confirmed__property-address"><?php echo esc_html($property_address); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="barefoot-engine-booking-confirmed__facts">
                            <div class="barefoot-engine-booking-confirmed__fact">
                                <span><?php esc_html_e('Check-in', 'barefoot-engine'); ?></span>
                                <strong><?php echo esc_html($check_in_display); ?></strong>
                            </div>
                            <div class="barefoot-engine-booking-confirmed__fact">
                                <span><?php esc_html_e('Check-out', 'barefoot-engine'); ?></span>
                                <strong><?php echo esc_html($check_out_display); ?></strong>
                            </div>
                            <div class="barefoot-engine-booking-confirmed__fact">
                                <span><?php esc_html_e('Duration', 'barefoot-engine'); ?></span>
                                <strong><?php echo esc_html($nights_label); ?></strong>
                            </div>
                            <div class="barefoot-engine-booking-confirmed__fact">
                                <span><?php esc_html_e('Guests', 'barefoot-engine'); ?></span>
                                <strong><?php echo esc_html($guests_label); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="barefoot-engine-booking-confirmed__location">
                        <div class="barefoot-engine-booking-confirmed__section-header">
                            <p class="barefoot-engine-booking-confirmed__section-label"><?php esc_html_e('Location', 'barefoot-engine'); ?></p>
                            <?php if ($directions_url !== '') : ?>
                                <a class="barefoot-engine-booking-confirmed__section-link" href="<?php echo esc_url($directions_url); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php esc_html_e('Get Directions', 'barefoot-engine'); ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($map['available']) && !empty($map['embedUrl'])) : ?>
                            <div class="barefoot-engine-booking-confirmed__map-shell">
                                <iframe
                                    class="barefoot-engine-booking-confirmed__map-frame"
                                    src="<?php echo esc_url((string) $map['embedUrl']); ?>"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    title="<?php esc_attr_e('Property location map', 'barefoot-engine'); ?>"
                                ></iframe>
                            </div>
                        <?php else : ?>
                            <div class="barefoot-engine-booking-confirmed__map-fallback">
                                <strong><?php esc_html_e('Map unavailable', 'barefoot-engine'); ?></strong>
                                <?php if ($property_address !== '') : ?>
                                    <p><?php echo esc_html($property_address); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </section>

                <aside class="barefoot-engine-booking-confirmed__sidebar">
                    <section class="barefoot-engine-booking-confirmed__card barefoot-engine-booking-confirmed__card--payments">
                        <p class="barefoot-engine-booking-confirmed__section-label"><?php esc_html_e('Payment Breakdown', 'barefoot-engine'); ?></p>

                        <div class="barefoot-engine-booking-confirmed__breakdown">
                            <div class="barefoot-engine-booking-confirmed__breakdown-row">
                                <span><?php echo esc_html($rent_label); ?></span>
                                <strong><?php echo esc_html($format_money($payments['rent'] ?? null)); ?></strong>
                            </div>
                            <div class="barefoot-engine-booking-confirmed__breakdown-row">
                                <span><?php echo esc_html((string) ($payments['taxLabel'] ?? __('Local and State Taxes', 'barefoot-engine'))); ?></span>
                                <strong><?php echo esc_html($format_money($payments['tax'] ?? null)); ?></strong>
                            </div>
                            <div class="barefoot-engine-booking-confirmed__breakdown-row">
                                <span><?php echo esc_html((string) ($payments['depositLabel'] ?? __('Deposit Amount', 'barefoot-engine'))); ?></span>
                                <strong><?php echo esc_html($format_money($payments['deposit'] ?? null)); ?></strong>
                            </div>
                            <div class="barefoot-engine-booking-confirmed__breakdown-row barefoot-engine-booking-confirmed__breakdown-row--total">
                                <span><?php echo esc_html((string) ($payments['totalLabel'] ?? __('Total', 'barefoot-engine'))); ?></span>
                                <strong><?php echo esc_html($format_money($payments['total'] ?? null)); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="barefoot-engine-booking-confirmed__due-now">
                        <p class="barefoot-engine-booking-confirmed__due-label"><?php esc_html_e('Amount Paid Now', 'barefoot-engine'); ?></p>
                        <p class="barefoot-engine-booking-confirmed__due-amount"><?php echo esc_html($format_money($payments['payable'] ?? null)); ?></p>
                        <div class="barefoot-engine-booking-confirmed__due-points">
                            <p><?php esc_html_e('Receipt sent to the booking email', 'barefoot-engine'); ?></p>
                        </div>
                    </section>

                </aside>
            </div>

            <div class="barefoot-engine-booking-confirmed__actions-row">
                <?php if (!empty($calendar['googleUrl'])) : ?>
                    <a class="barefoot-engine-booking-confirmed__action barefoot-engine-booking-confirmed__action--primary" href="<?php echo esc_url((string) $calendar['googleUrl']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Add to Google Calendar', 'barefoot-engine'); ?>
                    </a>
                <?php endif; ?>

                <?php if (!empty($calendar['outlookUrl'])) : ?>
                    <a class="barefoot-engine-booking-confirmed__action" href="<?php echo esc_url((string) $calendar['outlookUrl']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Add to Outlook', 'barefoot-engine'); ?>
                    </a>
                <?php endif; ?>

                <?php if (!empty($calendar['icsUrl'])) : ?>
                    <a class="barefoot-engine-booking-confirmed__action" href="<?php echo esc_url((string) $calendar['icsUrl']); ?>">
                        <?php esc_html_e('Download ICS', 'barefoot-engine'); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <section class="barefoot-engine-booking-confirmed__empty">
                <p class="barefoot-engine-booking-confirmed__eyebrow"><?php esc_html_e('Confirmed', 'barefoot-engine'); ?></p>
                <h1 class="barefoot-engine-booking-confirmed__title"><?php echo esc_html((string) ($context['title'] ?? __('Booking confirmation unavailable', 'barefoot-engine'))); ?></h1>
                <p class="barefoot-engine-booking-confirmed__intro"><?php echo esc_html((string) ($context['message'] ?? __('We could not load this booking confirmation.', 'barefoot-engine'))); ?></p>
                <div class="barefoot-engine-booking-confirmed__actions barefoot-engine-booking-confirmed__actions--empty">
                    <a class="barefoot-engine-booking-confirmed__action barefoot-engine-booking-confirmed__action--primary" href="<?php echo esc_url((string) ($context['propertiesUrl'] ?? home_url('/properties/'))); ?>">
                        <?php esc_html_e('Explore Properties', 'barefoot-engine'); ?>
                    </a>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
