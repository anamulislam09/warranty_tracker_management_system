<?php

namespace App\Console\Commands;

use App\Services\OnlinePaymentService;
use Illuminate\Console\Command;

class VerifyOnlinePayments extends Command
{
    protected $signature = 'billing:verify-online-payments';
    protected $description = 'Ask the payment gateway about online payments that are still waiting, and close the ones that never finished';

    public function handle(OnlinePaymentService $payments): int
    {
        $r = $payments->reconcile();
        $this->info("Checked: {$r['verified']}, confirmed as paid: {$r['success']}, expired: {$r['expired']}");

        return self::SUCCESS;
    }
}
