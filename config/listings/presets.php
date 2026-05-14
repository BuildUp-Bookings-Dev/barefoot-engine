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
| 'stickyMap' => bool
| 'paginationMode' => 'pages' | 'infinite'
| 'fullHeightMap' => bool
| 'minDesktopColumns' => int
| 'maxDesktopColumns' => int
| 'markerFocusZoom' => int
| 'markerFocusCenter' => [float $lat, float $lng] | null
|
| 'searchWidget' => [
|   This config is only for the search widget embedded inside listings.
|   It does not use the standalone search-widget preset file unless you
|   explicitly pass search_widget_id on the shortcode.
|
|   'targetUrl' => string,
|   'showLocation' => bool,
|   'filterDisplayMode' => 'modal' | 'left-slide',
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
|         'center' => [33.944765, -78.578097],
|         'zoom' => 15,
|     ],
|     'showMapToggle' => true,
|     'showSort' => true,
|     'showPagination' => true,
|     'pageSize' => 12,
|     'stickyMap' => true,
|     'paginationMode' => 'infinite',
|     'fullHeightMap' => false,
|     'minDesktopColumns' => 3,
|     'maxDesktopColumns' => 8,
|     'searchWidget' => [
|         'showLocation' => true,
|         'showFilterButton' => true,
|         'locationLabel' => 'Keyword',
|         'locationPlaceholder' => 'Search keyword',
|         'dateLabel' => 'Dates',
|         'datePlaceholder' => 'Check in — Check out',
|         'fields' => [
|             [
|                 'label' => 'Guests',
|                 'type' => 'select',
|                 'options' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '12', '14', '16', '20'],
|                 'position' => 'end',
|                 'key' => 'guests',
|                 'icon' => 'fa-solid fa-users',
|             ],
|         ],
|         'filters' => [
|             [
|                 'label' => 'Bedrooms',
|                 'type' => 'select',
|                 'options' => ['1', '2', '3', '4', '5', '6', '7', '8+'],
|                 'key' => 'bedrooms',
|             ],
|             [
|                 'label' => 'Bathrooms',
|                 'type' => 'select',
|                 'options' => ['1', '2', '3', '4', '5', '6+'],
|                 'key' => 'bathrooms',
|             ],
|             [
|                 'label' => 'Amenities',
|                 'type' => 'checkbox',
|                 'options' => [],
|                 'key' => 'amenities',
|             ],
|             [
|                 'label' => 'Type',
|                 'type' => 'select',
|                 'options' => [],
|                 'key' => 'type',
|             ],
|             [
|                 'label' => 'Rating',
|                 'type' => 'select',
|                 'options' => [],
|                 'key' => 'rating',
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
            'center' => [33.944765, -78.578097],
            'zoom' => 15,
        ],
        'showMapToggle' => true,
        'showSort' => true,
        'showPagination' => true,
        'pageSize' => 12,
        'stickyMap' => true,
        'paginationMode' => 'infinite',
        'fullHeightMap' => false,
        'minDesktopColumns' => 3,
        'maxDesktopColumns' => 8,
        'searchWidget' => [
            'showLocation' => true,
            'filterDisplayMode' => 'modal',
            'showFilterButton' => true,
            'locationLabel' => 'Keyword',
            'locationPlaceholder' => 'Search keyword',
            'dateLabel' => 'Dates',
            'datePlaceholder' => 'Check in — Check out',
            'fields' => [
                [
                    'label' => 'Guests',
                    'type' => 'select',
                    'options' => [
                        '1', '2', '3', '4', '5', '6', '7', '8+',
                    ],
                    'position' => 'end',
                    'required' => false,
                    'key' => 'guests',
                    'icon' => 'fa-solid fa-users',
                ],
            ],
            'filters' => [
                [
                    'label' => 'Rating',
                    'type' => 'select',
                    'options' => [],
                    'required' => false,
                    'key' => 'rating',
                ],
                [
                    'label' => 'Type',
                    'type' => 'select',
                    'options' => [],
                    'required' => false,
                    'key' => 'type',
                ],
                [
                    'label' => 'View',
                    'type' => 'select',
                    'options' => ['Golf Course', 'Poolview'],
                    'required' => false,
                    'key' => 'view',
                ],
                [
                    'label' => 'Bathrooms',
                    'type' => 'select',
                    'options' => ['1', '2', '3', '4', '5', '6+'],
                    'required' => false,
                    'key' => 'bathrooms',
                ],
                [
                    'label' => 'Bedrooms',
                    'type' => 'select',
                    'options' => ['1', '2', '3', '4', '5', '6', '7', '8+'],
                    'required' => false,
                    'key' => 'bedrooms',
                ],
                [
                    'label' => 'Amenities',
                    'type' => 'checkbox',
                    'options' => [],
                    'required' => false,
                    'key' => 'amenities',
                ],
            ],
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
