<?php

return [
    // how dates are SHOWN on every page: d/m/y = 05/11/26
    'date_format'       => 'd/m/y',
    'datetime_format'   => 'd/m/y h:i A',

    'expiring_days'     => 30,
    'default_reminders' => [30, 7, 1],
    'reminder_options'  => [90, 60, 30, 14, 7, 3, 1, 0],

    'countries' => [
        'BD' => 'Bangladesh', 'IN' => 'India', 'PK' => 'Pakistan', 'NP' => 'Nepal', 'LK' => 'Sri Lanka',
        'SA' => 'Saudi Arabia', 'AE' => 'UAE', 'OM' => 'Oman', 'QA' => 'Qatar', 'KW' => 'Kuwait',
        'MY' => 'Malaysia', 'SG' => 'Singapore', 'GB' => 'United Kingdom', 'US' => 'United States',
        'CA' => 'Canada', 'AU' => 'Australia',
    ],

    'currencies' => ['BDT', 'USD', 'INR', 'EUR', 'GBP', 'SAR', 'AED', 'MYR'],

    'timezones' => [
        'Asia/Dhaka' => '(GMT+06:00) Dhaka', 'Asia/Kolkata' => '(GMT+05:30) India',
        'Asia/Dubai' => '(GMT+04:00) Dubai', 'Asia/Riyadh' => '(GMT+03:00) Riyadh',
        'Asia/Kuala_Lumpur' => '(GMT+08:00) Kuala Lumpur', 'Europe/London' => '(GMT+00:00) London',
        'America/New_York' => '(GMT-05:00) New York',
    ],

    'languages' => ['en' => 'English', 'bn' => 'বাংলা'],
    'document_types' => [
        'Driving License', 'Passport', 'National ID', 'Birth Certificate', 'Insurance Policy',
        'Visa', 'Trade License', 'Vehicle Registration', 'Tax Certificate', 'Other',
    ],
];
