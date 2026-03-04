<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Listings Preset Structure
|--------------------------------------------------------------------------
|
| Each top-level key is a preset id, for example:
| - 'default'
| - 'search-results'
| - 'homepage-listings'
|
| Use the preset id with:
| [barefoot_listings widget_id="default"]
|
| Available preset options:
|
| 'listings' => array
|   Optional static listings array.
|   In this plugin, live property data usually replaces this automatically.
|
| 'currency' => string
|   Currency prefix used by bp-listings.
|   Example: '$'
|
| 'mapOptions' => [
|   'center' => [float $lat, float $lng],
|   'zoom' => int, // 1 to 20
| ]
|
| 'showMapToggle' => bool
| 'showSort' => bool
| 'showPagination' => bool
| 'pageSize' => int
|   Use 0 or a non-positive value to disable pagination.
|
| 'searchWidget' => [
|   This config is only for the search widget embedded inside listings.
|   It does not use the standalone search-widget preset file unless you
|   explicitly pass search_widget_id on the shortcode.
|
|   'targetUrl' => string,
|   'showLocation' => bool,
|   'showFilterButton' => bool,
|   'locationLabel' => string,
|   'locationPlaceholder' => string,
|   'dateLabel' => string,
|   'datePlaceholder' => string,
|   'fields' => array,
|   'filters' => array,
|   'calendarOptions' => [
|     'monthsToShow' => int,
|     'datepickerPlacement' => string,
|     'defaultMinDays' => int,
|     'tooltipLabel' => string,
|     'showTooltip' => bool,
|     'showClearButton' => bool,
|   ],
| ]
|
| Example preset:
|
| 'search-results' => [
|     'currency' => '$',
|     'mapOptions' => [
|         'center' => [14.12, 120.97],
|         'zoom' => 10,
|     ],
|     'showMapToggle' => true,
|     'showSort' => true,
|     'showPagination' => true,
|     'pageSize' => 12,
|     'searchWidget' => [
|         'showLocation' => false,
|         'showFilterButton' => false,
|         'dateLabel' => 'Dates',
|         'datePlaceholder' => 'Check in — Check out',
|         'fields' => [
|             [
|                 'label' => 'Bedrooms',
|                 'type' => 'select',
|                 'options' => ['1', '2', '3', '4+'],
|                 'position' => 'end',
|                 'key' => 'bedrooms',
|                 'icon' => 'fa-solid fa-bed',
|             ],
|         ],
|         'calendarOptions' => [
|             'monthsToShow' => 2,
|             'showTooltip' => true,
|         ],
|     ],
| ],
|
| Optional static listing item shape:
|
| [
|     'id' => 'listing-1',
|     'title' => 'Unit 101 · 2 Bedroom/2 Bath Villa',
|     'subtitle' => 'Beautiful Villas in the Brunswick Plantation Resort',
|     'details' => '2 bedrooms · 2 bathrooms · Sleeps 8',
|     'badge' => 'Rating: A',
|     'price' => 109,
|     'pricePeriod' => 'per night',
|     'lat' => 14.12,
|     'lng' => 120.97,
|     'images' => [
|         'https://example.com/photo-1.jpg',
|         'https://example.com/photo-2.jpg',
|     ],
|     'permalink' => 'https://example.com/property/unit-101/',
|     'searchData' => [
|         'location' => ['Batangas', 'Nasugbu'],
|         'availability' => [
|             ['start' => '2026-03-10', 'end' => '2026-03-31'],
|         ],
|         'fields' => [
|             'guests' => '8',
|         ],
|         'filters' => [
|             'bedrooms' => 2,
|         ],
|     ],
| ],
|
*/

return [
    'default' => [
        'listings' => [],
        'currency' => '$',
        'mapOptions' => [
            'center' => [14.55, 121.03],
            'zoom' => 12,
        ],
        'showMapToggle' => true,
        'showSort' => true,
        'showPagination' => true,
        'pageSize' => 12,
        'searchWidget' => [
            'showLocation' => false,
            'showFilterButton' => true,
            'locationLabel' => 'Location',
            'locationPlaceholder' => 'Where are you going?',
            'dateLabel' => 'Dates',
            'datePlaceholder' => 'Check in — Check out',
            'fields' => [
                [
                    'label' => 'Bedrooms',
                    'type' => 'select',
                    'options' => ['1', '2', '3', '4+'],
                    'position' => 'end',
                    'required' => false,
                    'key' => 'bedrooms',
                    'icon' => 'fa-solid fa-bed',
                ],
                [
                    'label' => 'Bathrooms',
                    'type' => 'select',
                    'options' => ['1', '2', '3', '4+'],
                    'position' => 'end',
                    'required' => false,
                    'key' => 'bathrooms',
                    'icon' => 'fa-solid fa-bath',
                ],
            ],
            'filters' => [],
            'calendarOptions' => [
                'monthsToShow' => 2,
                'datepickerPlacement' => 'auto',
                'defaultMinDays' => 1,
                'tooltipLabel' => 'Nights',
                'showTooltip' => true,
                'showClearButton' => true,
            ],
        ],
    ],
];
