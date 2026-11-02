<?php

return [
    // Day of the month by which a bill must be paid (5 or 10 ...). Change in .env: BILLING_DUE_DAY=10
    // Allowed: 1 - 27.
    'due_day' => max(1, min(27, (int) env('BILLING_DUE_DAY', 10))),

    // After the due date the customer can still login for this many months, but can only use the
    // Billing page (to pay). After that the account is suspended: no login at all.
    'grace_months' => 1,

    // Reminder notices: days BEFORE the due date (0 = on the due date itself)
    'reminder_days_before' => [3, 0],

    // If a bill is generated late (after its normal due date) the customer gets this many extra days
    'late_grace_days' => 3,

    'symbol' => '৳',

    'methods' => [
        'cash' => 'Cash',
        'bank' => 'Bank Transfer',
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'card' => 'Card',
        'sslcommerz' => 'SSLCommerz (online)',
        'other' => 'Other',
    ],

    /*
     | How often a customer pays, how many months one bill covers, and the discount for paying ahead.
     | Bill amount = plan monthly price x months - discount %.
     | (The plan's own "annual price" is only information; billing always uses monthly price x months.)
     */
    'cycles' => [
        'monthly'     => ['label' => 'Monthly',                  'months' => 1,  'discount' => 0],
        'quarterly'   => ['label' => 'Quarterly (3 months)',     'months' => 3,  'discount' => 0],
        'half-yearly' => ['label' => 'Half-yearly (6 months)',   'months' => 6,  'discount' => 2],
        'yearly'      => ['label' => 'Yearly (12 months)',       'months' => 12, 'discount' => 3],
    ],

    /*
     | Online payment (customer pays from his own panel). Nothing is switched on yet.
     | When SSLCommerz is ready: implement app/Services/Gateways/SslCommerzGateway.php, fill the .env values
     | and set BILLING_ONLINE_ENABLED=true.
     */
    'online' => [
        'enabled' => (bool) env('BILLING_ONLINE_ENABLED', false),
        'driver'  => env('BILLING_ONLINE_DRIVER', 'sslcommerz'),

        'sslcommerz' => [
            'store_id'       => env('SSLCOMMERZ_STORE_ID'),
            'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
            'sandbox'        => (bool) env('SSLCOMMERZ_SANDBOX', true),
        ],
    ],
];
