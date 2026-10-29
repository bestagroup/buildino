<?php

return [
    'map' => [
        'tileUrl' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'),
        'center' => [32.4, 53.7],
        'zoom' => 5,
        'maxZoom' => 19,
    ],
    'libraries' => [
        'bootstrap' => [
            'version' => '5.3.8',
            'css' => '/css/buildino-bootstrap.rtl.min.css',
            'css_integrity' => null,
            'js' => '/js/buildino-bootstrap.bundle.min.js',
            'js_integrity' => 'sha384-oS8SVSrDDW5/9x7aYbtH+Z5NO+5Oh3BIpN/w6DYbnXw/rksXZ1A5+GP6eHeZu06r',
        ],

        'sweetalert2' => [
            'version' => '11.26.25',
            'css' => '/css/buildino-sweetalert2.min.css',
            'js' => '/js/buildino-sweetalert2.all.min.js',
        ],

        'jdate' => [
            'version' => '1.5.0',
            'js' => '/js/buildino-jdate.js',
        ],

        'morilog_jalali' => [
            'version_constraint' => '^3.5',
            'composer_package' => 'morilog/jalali',
        ],

        /*
        |--------------------------------------------------------------------------
        | DataTables / Yajra
        |--------------------------------------------------------------------------
        |
        | DataTables 2.x is intentionally pinned because Yajra DataTables 12
        | officially targets DataTables 1.x / 2.x with Laravel 12.
        |
        */
        'datatables' => [
            'version' => '2.3.8',
            'responsive_version' => '3.0.8',
            'npm_package' => 'datatables.net-bs5',
            'responsive_npm_package' => 'datatables.net-responsive-bs5',
            'composer_package' => 'yajra/laravel-datatables-oracle',
            'version_constraint' => '^12.0',
        ],

        'select2' => [
            'version' => '4.1.0',
            'npm_package' => 'select2',
            'jquery_version' => '3.7.1',
            'jquery_npm_package' => 'jquery',
        ],

        'jalali_datepicker' => [
            'version' => '1.0.0',
            'npm_package' => '@majidh1/jalalidatepicker',
            'converter_version' => '2.0.1',
            'converter_npm_package' => 'jalaali-js',
        ],
    ],
];
