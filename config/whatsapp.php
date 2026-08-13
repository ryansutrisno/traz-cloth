<?php

return [
    'default' => env('WHATSAPP_DRIVER', 'fonnte'),

    'drivers' => [
        'fonnte' => [
            'token' => env('FONNTE_TOKEN'),
            'sender_id' => env('FONNTE_SENDER_ID'),
            'base_url' => env('FONNTE_BASE_URL', 'https://api.fonnte.com'),
        ],
    ],
];
