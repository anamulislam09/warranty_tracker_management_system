<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily warranty expiry reminders
Schedule::command('warranties:send-reminders')->dailyAt('08:00');

// ---------------------------------------------------------------- Billing
// 1) ONCE a month, on the 1st: create the new month's bill for every customer.
Schedule::command('billing:generate-invoices')->monthlyOn(1, '00:05');

// 2) ONCE a month, just after the due day: customers who did not pay become inactive.
//    BILLING_DUE_DAY=10 (default) -> runs on the 11th at 00:30, so the customer can pay up to the end of the 10th.
//    BILLING_DUE_DAY=5            -> runs on the 6th.
Schedule::command('billing:suspend-overdue')->monthlyOn(config('billing.due_day') + 1, '00:30');

// 3) EVERY day: notifications ("bill due in 3 days", "bill due today").
Schedule::command('billing:send-reminders')->dailyAt('09:00');
