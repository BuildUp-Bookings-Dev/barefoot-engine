<?php

if (!defined('ABSPATH')) {
    exit;
}

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
    ],
];
