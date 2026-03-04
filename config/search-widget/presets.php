<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Search Widget Preset Structure
|--------------------------------------------------------------------------
|
| Each top-level key is a preset id, for example:
| - 'default'
| - 'search-results'
| - 'homepage-search'
|
| Use the preset id with:
| [barefoot_search_widget widget_id="default"]
| [barefoot_listings search_widget_id="default"]
|
| Available preset options:
|
| 'targetUrl' => string
|   Where the search redirects after submit.
|   Example: '/search-results/'
|
| 'showLocation' => bool
|   Show or hide the location field.
|
| 'showFilterButton' => bool
|   Show or hide the filter button.
|   Note: the filter button only renders when filters exist.
|
| 'locationLabel' => string
| 'locationPlaceholder' => string
| 'dateLabel' => string
| 'datePlaceholder' => string
|
| 'calendarOptions' => [
|   'monthsToShow' => int,            // 1 to 6
|   'datepickerPlacement' => string,  // usually 'auto' or 'default'
|   'defaultMinDays' => int,          // minimum nights, 1 or greater
|   'tooltipLabel' => string,         // example: 'Nights'
|   'showTooltip' => bool,
|   'showClearButton' => bool,
| ]
|
| 'fields' => [
|   [
|     'label' => string,
|     'type' => 'input' | 'select' | 'checkbox' | 'radio',
|     'options' => string[] | [ ['label' => string, 'value' => string] ],
|     'position' => 'start' | 'end',
|     'required' => bool,
|     'key' => string,
|     'icon' => string, // Font Awesome class, example: 'fa-solid fa-users'
|   ],
| ]
|
| Field rules:
| - 'options' is required for 'select', 'checkbox', and 'radio'
| - 'position' defaults to 'end'
| - 'required' defaults to false
| - 'width' is not supported on fields
|
| 'filters' => [
|   [
|     'label' => string,
|     'type' => 'input' | 'select' | 'checkbox' | 'radio' | 'counter',
|     'options' => string[] | [ ['label' => string, 'value' => string] ],
|     'required' => bool,
|     'key' => string,
|     'width' => number | '30%',
|     'min' => number,
|     'max' => number,
|     'step' => number,
|     'defaultValue' => number,
|   ],
| ]
|
| Filter rules:
| - 'options' is required for 'select', 'checkbox', and 'radio'
| - 'counter' does not use 'options'
| - 'position' and 'icon' are not supported on filters
| - 'width' is supported on filters only
| - counter defaults are: min=0, max=Infinity, step=1, defaultValue=min
|
| Full example:
|
| 'search-results' => [
|     'targetUrl' => '/search-results/',
|     'showLocation' => true,
|     'showFilterButton' => true,
|     'locationLabel' => 'Where to?',
|     'locationPlaceholder' => 'Choose a destination',
|     'dateLabel' => 'Dates',
|     'datePlaceholder' => 'Check in — Check out',
|     'calendarOptions' => [
|         'monthsToShow' => 2,
|         'datepickerPlacement' => 'auto',
|         'defaultMinDays' => 2,
|         'tooltipLabel' => 'Nights',
|         'showTooltip' => true,
|         'showClearButton' => true,
|     ],
|     'fields' => [
|         [
|             'label' => 'Guests',
|             'type' => 'select',
|             'options' => ['1', '2', '3', '4+'],
|             'position' => 'end',
|             'required' => true,
|             'key' => 'guests',
|             'icon' => 'fa-solid fa-users',
|         ],
|     ],
|     'filters' => [
|         [
|             'label' => 'Bedrooms',
|             'type' => 'counter',
|             'min' => 0,
|             'max' => 8,
|             'defaultValue' => 0,
|             'key' => 'bedrooms',
|             'width' => '30%',
|         ],
|         [
|             'label' => 'View',
|             'type' => 'select',
|             'options' => ['Ocean', 'Garden', 'City'],
|             'key' => 'view',
|             'width' => '30%',
|         ],
|     ],
| ],
|
*/

return [
    'default' => [
        'showLocation' => false,
        'showFilterButton' => false,
        'locationLabel' => 'Location',
        'locationPlaceholder' => 'Where are you going?',
        'dateLabel' => 'Dates',
        'datePlaceholder' => 'Check in — Check out',
        'calendarOptions' => [
            'monthsToShow' => 1,
            'datepickerPlacement' => 'auto',
            'tooltipLabel' => 'Nights',
            'showTooltip' => true,
            'showClearButton' => true,
            'defaultMinDays' => 1,
        ],
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
    ],
];
