<?php

return [
    // Day of the month by which a bill must be paid (5 or 10 ...). Change in .env: BILLING_DUE_DAY=10
    // Allowed: 1 - 27.
    'due_day' => max(1, min(27, (int) env('BILLING_DUE_DAY', 10))),

    // The bill of the NEXT period is created this many days before that period starts
    // (= 15 days before the current payment schedule ends). Never earlier: there are no advance bills.
    'invoice_days_before' => 15,

    // The customer gets a mail + a notification this many months before his payment schedule ends.
    'schedule_notice_months' => 1,

    // After the due date the customer can still login for this many months, but can only use the
    // Billing page (to pay). After that the account is suspended: no login at all.
    'grace_months' => 1,

    // Reminder notices: days BEFORE the due date (0 = on the due date itself)
    'reminder_days_before' => [3, 0],

    // If a bill is created late (after its normal due date) the customer gets this many extra days
    'late_grace_days' => 3,

    'symbol' => '৳',

    'methods' => [
        'cash' => 'Cash', 'bank' => 'Bank Transfer', 'bkash' => 'bKash',
        'nagad' => 'Nagad', 'card' => 'Card', 'sslcommerz' => 'SSLCommerz (online)', 'other' => 'Other',
    ],

    /*
     | Payment schedules (Monthly, Quarterly ...) are NOT here any more: the super admin manages them
     | on the "Payment Schedules" page (name, months per invoice, discount %).
     | Bill amount = plan monthly price x months - discount %.
     */

    /*
     | Online payment: SSLCommerz (Bangladesh, BDT). The credentials are static, they come from the .env file.
     |   APP_PG=sandbox  -> the test store (default)          APP_PG=live  -> the real store
     |   BILLING_ONLINE_ENABLED=true  switches "Pay online" on for the customers.
     */
    'online' => [
        'enabled' => (bool) env('BILLING_ONLINE_ENABLED', false),

        // the scheduler asks SSLCommerz about payments that are still "pending" after this many minutes
        'verify_after_minutes' => 10,
        // a try that was never finished is closed ("expired") after this many hours
        'expire_after_hours'   => 24,

        // After an online payment started from the mobile app, the customer sees a small result page which opens this link,
        // so the app can close the browser. Use your own scheme (or an Android App Link).
        'app_deep_link' => env('APP_DEEP_LINK', 'warrantytracker://payment-result'),

        'sslcommerz' => [
            'mode'    => env('APP_PG', 'sandbox'),                    // sandbox | live
            'sandbox' => ['store_id' => env('SSLCOMMERZ_SANDBOX_STORE_ID'), 'store_pass' => env('SSLCOMMERZ_SANDBOX_STORE_PASS')],
            'live'    => ['store_id' => env('SSLCOMMERZ_LIVE_STORE_ID'),    'store_pass' => env('SSLCOMMERZ_LIVE_STORE_PASS')],
            // check the SSL certificate of SSLCommerz: always on for live. For sandbox on a local XAMPP it is off by default
            // (XAMPP often has no certificate list). Set SSLCOMMERZ_VERIFY_SSL=true when your server has one.
            'verify_ssl' => (bool) env('SSLCOMMERZ_VERIFY_SSL', env('APP_PG') === 'live'),
        ],
    ],
];
