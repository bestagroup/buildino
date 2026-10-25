<?php

return [
    /*
     * Resources move to Vue/Inertia one at a time. Keeping this list explicit
     * prevents an unfinished frontend component from changing an existing
     * Blade route or API workflow.
     */
    'inertia_resources' => [
        'complexes',
        'buildings',
    ],

    'libraries' => [
        'bootstrap' => [
            'version' => '5.3.8',
            'css' => 'https://panel.buildino.ir/css/bootstrap.rtl.min.css',
            'css_integrity' => null,
            'js' => 'https://panel.buildino.ir/js/bootstrap.bundle.min.js',
            'js_integrity' => 'sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI',
        ],

        'sweetalert2' => [
            'version' => '11.26.25',
            'css' => 'https://panel.buildino.ir/css/sweetalert2.min.css',
            'js' => 'https://panel.buildino.ir/js/sweetalert2.all.min.js',
        ],

        'jdate' => [
            'version' => '1.5.0',
            'js' => 'https://panel.buildino.ir/js/jdate.min.js',
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
