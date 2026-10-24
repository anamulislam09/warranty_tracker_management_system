<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateInvoices extends Command
{
    protected $signature = 'billing:generate-invoices {--date= : pretend today is this date (YYYY-MM-DD) - for testing}';
    protected $description = 'Create this month\'s bill for every active customer that does not have one yet';

    public function handle(BillingService $billing): int
    {
        if ($d = $this->option('date')) Carbon::setTestNow(Carbon::parse($d));

        $this->info('Bills created: ' . $billing->generateMonthly());
        return self::SUCCESS;
    }
}
