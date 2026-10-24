<?php

return [
    // Day of the month by which the bill must be paid (5 or 10 ...). Change in .env: BILLING_DUE_DAY=10
    // Customers who have not paid are made inactive on the NEXT day (once a month). Allowed: 1 - 27.
    'due_day' => max(1, min(27, (int) env('BILLING_DUE_DAY', 10))),

    // Reminder notices: days BEFORE the due date (0 = on the due date itself)
    'reminder_days_before' => [3, 0],

    // If a bill is generated late (after its normal due date) the customer gets this many extra days
    'late_grace_days' => 3,

    'symbol' => '৳',

    'methods' => [
        'cash' => 'Cash', 'bank' => 'Bank Transfer', 'bkash' => 'bKash',
        'nagad' => 'Nagad', 'card' => 'Card', 'other' => 'Other',
    ],
];
