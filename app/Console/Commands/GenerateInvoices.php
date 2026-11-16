<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateInvoices extends Command
{
    protected $signature = 'billing:generate-invoices {--date= : pretend today is this date (YYYY-MM-DD) - for testing}';
    protected $description = 'Create the next bill of every customer 15 days before the new payment-schedule period starts';

    public function handle(BillingService $billing): int
    {
        if ($d = $this->option('date')) Carbon::setTestNow(Carbon::parse($d));

        $this->info('Bills created: ' . $billing->generateInvoices());
        return self::SUCCESS;
    }
}
