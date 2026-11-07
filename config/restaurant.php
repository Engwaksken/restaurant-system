<?php

return [
    'public_code' => env('PUBLIC_RESTAURANT_CODE', 'MAIN'),
    'currency' => env('RESTAURANT_CURRENCY', 'UGX'),
    'tax_rate' => (float) env('RESTAURANT_TAX_RATE', 0),
    'delivery_fee' => (float) env('RESTAURANT_DELIVERY_FEE', 0),
    'kitchen_late_minutes' => (int) env('KITCHEN_LATE_MINUTES', 20),
    // A table is held this many minutes either side of a reservation time.
    'reservation_duration_minutes' => (int) env('RESERVATION_DURATION_MINUTES', 120),

    'cash' => [
        // Cash payments and cash refunds must belong to the cashier's open drawer.
        'require_open_session' => (bool) env('CASH_REQUIRE_OPEN_SESSION', true),
        // Count differences up to this amount close without explanation or review.
        'variance_tolerance' => (float) env('CASH_VARIANCE_TOLERANCE', 0),
    ],

    // Non-cash payment methods staff can record; cash is reconciled by drawer counts.
    'payment_methods' => [
        'cash' => 'Cash',
        'mtn_mobile_money' => 'MTN Mobile Money',
        'airtel_money' => 'Airtel Money',
        'card' => 'Card',
        'bank' => 'Bank transfer',
        'manual' => 'Other (manual)',
    ],

    'loyalty' => [
        // Off until the owner approves the programme rates below.
        'enabled' => (bool) env('LOYALTY_ENABLED', false),
        // Customers earn 1 point per this much net spend (after discounts, before tax).
        'earn_amount' => (float) env('LOYALTY_EARN_AMOUNT', 1000),
        // Currency value of one point when redeemed.
        'point_value' => (float) env('LOYALTY_POINT_VALUE', 10),
        'min_redeem_points' => (int) env('LOYALTY_MIN_REDEEM_POINTS', 100),
        // Points may pay for at most this share of an order subtotal.
        'max_redeem_percent' => (float) env('LOYALTY_MAX_REDEEM_PERCENT', 50),
    ],

    'payroll' => [
        // Four-eyes control: the approver must not be the preparer.
        'separate_approver' => (bool) env('PAYROLL_SEPARATE_APPROVER', true),
        'expense_category' => env('PAYROLL_EXPENSE_CATEGORY', 'Payroll'),
        // Statutory deductions applied to gross pay on every payslip. Configure the
        // rates your jurisdiction requires; none are assumed. Example:
        // ['label' => 'NSSF (employee)', 'type' => 'percent', 'value' => 5],
        // ['label' => 'Union dues', 'type' => 'fixed', 'value' => 2000],
        'statutory_deductions' => [],
    ],
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
        'iotec' => [
            'enabled' => (bool) env('IOTEC_ENABLED', false),
            'client_id' => env('IOTEC_CLIENT_ID'),
            'client_secret' => env('IOTEC_CLIENT_SECRET'),
            'wallet_id' => env('IOTEC_WALLET_ID'),
            'base_url' => env('IOTEC_BASE_URL', 'https://pay.iotec.io'),
        ],
    ],
];
