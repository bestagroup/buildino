<?php

return [
    'default_scale' => env('BUILDINO_DEMO_SCALE', 'medium'),

    'password' => env('BUILDINO_DEMO_PASSWORD', 'Demo@1405'),

    'scales' => [
        'small' => [
            'complexes' => 1,
            'buildings_per_complex' => 2,
            'blocks_per_building' => 1,
            'floors_per_block' => 4,
            'units_per_floor' => 4,
            'invoice_months' => 3,
            'providers_per_building' => 2,
            'reservations_per_building' => 18,
            'services_per_building' => 12,
            'tickets_per_building' => 10,
            'guest_visits_per_building' => 20,
            'notifications_per_resident' => 2,
        ],

        'medium' => [
            'complexes' => 2,
            'buildings_per_complex' => 3,
            'blocks_per_building' => 2,
            'floors_per_block' => 5,
            'units_per_floor' => 4,
            'invoice_months' => 4,
            'providers_per_building' => 4,
            'reservations_per_building' => 45,
            'services_per_building' => 32,
            'tickets_per_building' => 24,
            'guest_visits_per_building' => 55,
            'notifications_per_resident' => 3,
        ],

        'large' => [
            'complexes' => 3,
            'buildings_per_complex' => 4,
            'blocks_per_building' => 2,
            'floors_per_block' => 8,
            'units_per_floor' => 5,
            'invoice_months' => 6,
            'providers_per_building' => 6,
            'reservations_per_building' => 120,
            'services_per_building' => 80,
            'tickets_per_building' => 60,
            'guest_visits_per_building' => 140,
            'notifications_per_resident' => 4,
        ],
    ],
];
