<?php

namespace App\Console\Commands;

use App\Models\ReminderLog;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Warranty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWarrantyReminders extends Command
{
    protected $signature = 'warranties:send-reminders';
    protected $description = 'Create in-app + email reminders for warranties that are about to expire';

    public function handle(): int
    {
        $today = today();
        $sent  = 0;

        Warranty::with(['product', 'account.plan'])
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->whereDate('end_date', '>=', $today)
            ->chunkById(200, function ($chunk) use ($today, &$sent) {
                foreach ($chunk as $w) {
                    $account = $w->account;
                    if (! $account || $account->status !== 'active' || ! $account->plan?->reminder_enabled) continue;

                    $days = (int) $today->diffInDays($w->end_date, false);
                    if (! in_array($days, $w->reminder_days ?? [])) continue;

                    // unique key => the same reminder is never sent twice
                    $log = ReminderLog::firstOrCreate([
                        'warranty_id' => $w->id, 'end_date' => $w->end_date->toDateString(),
                        'reminder_days' => $days, 'channel' => 'app',
                    ]);
                    if (! $log->wasRecentlyCreated) continue;

                    $isDoc = $w->product->item_type === 'document';
                    $kind  = $isDoc ? 'Document' : 'Warranty';
                    $title = $days === 0 ? "{$kind} expires today" : "{$kind} expires in {$days} day(s)";
                    $msg   = $isDoc
                        ? "{$w->product->name} expires on {$w->end_date->format(config('warranty.date_format'))}. Renew it to keep it valid."
                        : "{$w->product->name} ({$w->component}) warranty ends on {$w->end_date->format(config('warranty.date_format'))}.";

                    $users = User::where('account_id', $account->id)->where('status', 'active')->get()
                        ->filter(fn ($u) => $u->hasPermission('products.view'));

                    foreach ($users as $u) {
                        UserNotification::create([
                            'account_id' => $account->id, 'user_id' => $u->id, 'product_id' => $w->product_id,
                            'type' => 'expiry', 'title' => $title, 'message' => $msg,
                        ]);
                        try {
                            Mail::raw("{$title}\n\n{$msg}", fn ($m) => $m->to($u->email)->subject($title));
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                    $sent++;
                }
            });

        $this->info("Reminders created: {$sent}");
        return self::SUCCESS;
    }
}
