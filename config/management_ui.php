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
    ],
];
