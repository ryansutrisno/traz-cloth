<?php

return [
    'base_url' => env('BITESHIP_BASE_URL', 'https://api.biteship.com'),
    'api_key' => env('BITESHIP_API_KEY', ''),

    'origin' => [
        'name' => env('BITESHIP_ORIGIN_NAME', ''),
        'phone' => env('BITESHIP_ORIGIN_PHONE', ''),
        'address' => env('BITESHIP_ORIGIN_ADDRESS', ''),
        'postal_code' => (int) env('BITESHIP_ORIGIN_POSTAL_CODE', 0),
    ],

    'couriers' => explode(',', env('BITESHIP_COURIERS', 'jne,sicepat,anteraja,jnt,pos')),
    'item_weight_grams' => (int) env('BITESHIP_ITEM_WEIGHT_GRAMS', 250),
    'item_length_cm' => 30,
    'item_width_cm' => 25,
    'item_height_cm' => 5,
    'webhook_signature_key' => env('BITESHIP_WEBHOOK_SIGNATURE_KEY', ''),
    'webhook_signature_secret' => env('BITESHIP_WEBHOOK_SIGNATURE_SECRET', ''),
];
