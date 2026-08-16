<?php

return [
    'libraries' => [
        'bootstrap' => [
            'version' => '5.3.8',
            'css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
            'css_integrity' => 'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB',
            'js' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
            'js_integrity' => 'sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI',
        ],

        'sweetalert2' => [
            'version' => '11.26.25',
            'css' => 'https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.min.css',
            'js' => 'https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js',
        ],

        'jdate' => [
            'version' => '1.5.0',
            'js' => 'https://cdn.jsdelivr.net/npm/jalali-date@1.5.0/lib/jdate.min.js',
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
            'css' => 'https://cdn.datatables.net/2.3.8/css/dataTables.bootstrap5.min.css',
            'js' => 'https://cdn.datatables.net/2.3.8/js/dataTables.min.js',
            'bootstrap5_js' => 'https://cdn.datatables.net/2.3.8/js/dataTables.bootstrap5.min.js',
            'responsive_version' => '3.0.8',
            'responsive_css' => 'https://cdn.datatables.net/responsive/3.0.8/css/responsive.bootstrap5.min.css',
            'responsive_js' => 'https://cdn.datatables.net/responsive/3.0.8/js/dataTables.responsive.min.js',
            'responsive_bootstrap5_js' => 'https://cdn.datatables.net/responsive/3.0.8/js/responsive.bootstrap5.min.js',
            'composer_package' => 'yajra/laravel-datatables-oracle',
            'version_constraint' => '^12.0',
        ],
    ],
];
