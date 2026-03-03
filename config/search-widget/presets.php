<?php

if (!defined('ABSPATH')) {
    exit;
}

return [
    'default' => [
        'showLocation' => true,
        'showFilterButton' => false,
        'locationLabel' => 'Location',
        'locationPlaceholder' => 'Where are you going?',
        'dateLabel' => 'Dates',
        'datePlaceholder' => 'Check in — Check out',
        'calendarOptions' => [
            'monthsToShow' => 1,
            'datepickerPlacement' => 'auto',
        ],
        'fields' => [],
        'filters' => [],
    ],
];
