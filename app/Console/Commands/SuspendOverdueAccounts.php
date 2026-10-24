<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SuspendOverdueAccounts extends Command
{
    protected $signature = 'billing:suspend-overdue {--date= : pretend today is this date (YYYY-MM-DD) - for testing}';
    protected $description = 'Make customers inactive when a bill is not paid by its due date';

    public function handle(BillingService $billing): int
    {
        if ($d = $this->option('date')) Carbon::setTestNow(Carbon::parse($d));

        $this->info('Customers suspended: ' . $billing->suspendOverdue());
        return self::SUCCESS;
    }
}
