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
// 1) EVERY day: create the next bill of a customer exactly 15 days before the new period of his payment schedule
//    starts (a Yearly customer gets 1 bill a year, Half-yearly 2, Quarterly 4). Most days there is nothing to create.
Schedule::command('billing:generate-invoices')->dailyAt('00:05');

// 2) ONCE a month, just after the due day: customers who did not pay become inactive.
//    BILLING_DUE_DAY=10 (default) -> runs on the 11th at 00:30, so the customer can pay up to the end of the 10th.
//    BILLING_DUE_DAY=5            -> runs on the 6th.
Schedule::command('billing:suspend-overdue')->monthlyOn(config('billing.due_day') + 1, '00:30');

// 3) EVERY day: notifications ("bill due in 3 days", "bill due today").
Schedule::command('billing:send-reminders')->dailyAt('09:00');

// 4) EVERY 10 minutes: online payments that are still "pending" are asked at the gateway
//    (the customer may have paid but the way back to our site was lost). Never-finished tries are closed.
Schedule::command('billing:verify-online-payments')->everyTenMinutes();

// 5) EVERY day: delete the expired mobile app tokens
Schedule::command('sanctum:prune-expired --hours=24')->daily();
