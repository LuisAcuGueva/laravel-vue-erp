<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Electronic Invoicing Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "null", (custom PSE/OSE drivers in future specs)
    |
    */
    'driver' => env('EINVOICE_DRIVER', 'null'),

    'drivers' => [
        'null' => [
            'driver' => 'null',
        ],
    ],
];
