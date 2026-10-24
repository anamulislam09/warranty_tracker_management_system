<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendBillReminders extends Command
{
    protected $signature = 'billing:send-reminders {--date= : pretend today is this date (YYYY-MM-DD) - for testing}';
    protected $description = 'Notify customers whose bill is due soon / due today';

    public function handle(BillingService $billing): int
    {
        if ($d = $this->option('date')) Carbon::setTestNow(Carbon::parse($d));

        $this->info('Reminders sent: ' . $billing->sendReminders());
        return self::SUCCESS;
    }
}
