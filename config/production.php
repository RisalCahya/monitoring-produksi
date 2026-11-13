<?php

return [
    'simulator' => [
        'interval_seconds' => env('PRODUCTION_SIMULATOR_INTERVAL_SECONDS', 5),
        'machines' => [
            [
                'code' => 'FILL-GALLON',
                'name' => 'Filling Gallon',
                'monitoring_point' => 'Filling Gallon',
                'unit' => 'gallon',
                'initial_counter' => '0',
                'increment' => '2',
            ],
            [
                'code' => 'FILL-BOTTLE-1',
                'name' => 'Filling Bottle 1',
                'monitoring_point' => 'Filling Bottle 1',
                'unit' => 'bottle',
                'initial_counter' => '0',
                'increment' => '12',
            ],
            [
                'code' => 'FILL-BOTTLE-2',
                'name' => 'Filling Bottle 2',
                'monitoring_point' => 'Filling Bottle 2',
                'unit' => 'bottle',
                'initial_counter' => '0',
                'increment' => '12',
            ],
            [
                'code' => 'FILL-CUP-1',
                'name' => 'Filling Cup 1',
                'monitoring_point' => 'Filling Cup 1',
                'unit' => 'cup',
                'initial_counter' => '0',
                'increment' => '24',
            ],
            [
                'code' => 'FILL-CUP-2',
                'name' => 'Filling Cup 2',
                'monitoring_point' => 'Filling Cup 2',
                'unit' => 'cup',
                'initial_counter' => '0',
                'increment' => '24',
            ],
        ],
    ],
    'dashboard' => [
        'stale_after_minutes' => env('PRODUCTION_DATA_STALE_AFTER_MINUTES', 5),
    ],
];
