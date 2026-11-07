<?php

return [
    'public_code' => env('PUBLIC_RESTAURANT_CODE', 'MAIN'),
    'currency' => env('RESTAURANT_CURRENCY', 'UGX'),
    'tax_rate' => (float) env('RESTAURANT_TAX_RATE', 0),
    'delivery_fee' => (float) env('RESTAURANT_DELIVERY_FEE', 0),
    'kitchen_late_minutes' => (int) env('KITCHEN_LATE_MINUTES', 20),
    'gateways' => [
        'flutterwave' => [
            'enabled' => (bool) env('FLUTTERWAVE_ENABLED', false),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        ],
        'pesapal' => [
            'enabled' => (bool) env('PESAPAL_ENABLED', false),
            'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
            'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),
            'notification_id' => env('PESAPAL_NOTIFICATION_ID'),
            'base_url' => env('PESAPAL_BASE_URL', 'https://cybqa.pesapal.com'),
        ],
    ],
];
