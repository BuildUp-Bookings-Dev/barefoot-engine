<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Booking Widget Preset Structure
|--------------------------------------------------------------------------
|
| Each top-level key is a preset id, for example:
| - 'default'
| - 'property-booking'
|
| Use the preset id with:
| [barefoot_booking_widget widget_id="default"]
|
| Available preset options:
|
| 'currency' => string
|   Display currency symbol or prefix.
|   Example: '$'
|
| 'redirectUrl' => string
|   URL used by the BOOK NOW button when dates are available.
|   Query params appended automatically:
|   - property_id
|   - check_in
|   - check_out
|   - guests
|   - reztypeid
|   Example: '/booking-confirmation'
|
| 'reztypeid' => int
|   Barefoot reservation type ID used for quote totals.
|
| 'calendarOptions' => [
|   'monthsToShow' => int,            // 1 to 6
|   'datepickerPlacement' => string,  // 'auto' or 'default'
|   'defaultMinDays' => int,          // minimum nights, 1 or greater
|   'tooltipLabel' => string,         // example: 'Nights'
|   'showTooltip' => bool,
|   'showClearButton' => bool,
| ]
|
| 'guests' => [
|   'label' => string,
|   'placeholder' => string,
|   'defaultValue' => string|int,
|   'options' => array<string>,
| ]
|
| 'labels' => [
|   'title' => string,
|   'dates' => string,
|   'checking' => string,
|   'available' => string,
|   'unavailable' => string,
|   'error' => string,
|   'idle' => string,
|   'daily' => string,
|   'subtotal' => string,
|   'tax' => string,
|   'depositAmount' => string,
|   'total' => string,
|   'bookNow' => string,
|   'missingContext' => string,
| ]
|
*/

return [
    'default' => [
        'currency' => '$',
        'redirectUrl' => '/booking-confirmation',
        'reztypeid' => 26,
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
        'labels' => [
            'title' => 'Book This Property',
            'dates' => 'Dates',
            'checking' => 'Checking live availability...',
            'available' => 'Selected dates are available.',
            'unavailable' => 'Selected dates are unavailable.',
            'error' => 'Live availability check failed. Please try again.',
            'idle' => 'Select dates and guests to check availability.',
            'daily' => 'Rent',
            'subtotal' => 'Subtotal',
            'tax' => 'Tax',
            'depositAmount' => 'Deposit Amount',
            'total' => 'Total',
            'bookNow' => 'BOOK NOW',
            'missingContext' => 'Property context is required to load the booking widget.',
        ],
    ],
];
