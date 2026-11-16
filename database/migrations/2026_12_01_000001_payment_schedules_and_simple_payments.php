<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ---------- 1) dynamic payment schedules (replaces the fixed "billing cycle" list) ----------
        Schema::create('payment_schedules', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedTinyInteger('months');                      // one invoice covers this many months
            $t->decimal('discount_percent', 5, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        $now = now();
        foreach ([['Monthly', 1, 0], ['Quarterly', 3, 0], ['Half-yearly', 6, 2], ['Yearly', 12, 3]] as $i => [$name, $months, $discount]) {
            DB::table('payment_schedules')->insert([
                'name' => $name, 'months' => $months, 'discount_percent' => $discount, 'is_active' => true,
                'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $ids   = DB::table('payment_schedules')->pluck('id', 'name');
        $byKey = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'half-yearly' => 'Half-yearly', 'yearly' => 'Yearly', 'annual' => 'Yearly'];

        // ---------- 2) every customer has a payment schedule ----------
        Schema::table('accounts', function (Blueprint $t) {
            $t->foreignId('payment_schedule_id')->nullable()->after('billing_cycle')->constrained('payment_schedules')->nullOnDelete();
        });
        foreach (DB::table('accounts')->get(['id', 'billing_cycle']) as $account) {
            $name = $byKey[$account->billing_cycle] ?? 'Monthly';
            DB::table('accounts')->where('id', $account->id)->update(['payment_schedule_id' => $ids[$name]]);
        }

        // ---------- 3) every bill remembers the schedule it was made for ----------
        Schema::table('invoices', function (Blueprint $t) {
            $t->foreignId('payment_schedule_id')->nullable()->after('cycle')->constrained('payment_schedules')->nullOnDelete();
            $t->string('schedule_name')->nullable()->after('payment_schedule_id');
        });
        foreach (DB::table('invoices')->get(['id', 'cycle']) as $invoice) {
            $name = $byKey[$invoice->cycle] ?? 'Monthly';
            DB::table('invoices')->where('id', $invoice->id)->update(['payment_schedule_id' => $ids[$name], 'schedule_name' => $name]);
        }

        // ---------- 4) history of status changes (for the "inactive customers per month" chart) ----------
        Schema::create('account_status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('reason')->nullable();
            $t->timestamps();
            $t->index(['to_status', 'created_at']);
        });
        foreach (DB::table('accounts')->where('status', 'inactive')->get(['id', 'suspended_reason', 'updated_at']) as $account) {
            DB::table('account_status_logs')->insert([
                'account_id' => $account->id, 'from_status' => 'active', 'to_status' => 'inactive',
                'reason' => $account->suspended_reason, 'created_at' => $account->updated_at, 'updated_at' => $account->updated_at,
            ]);
        }

        // ---------- 5) NO advance payments: every payment belongs to one bill ----------
        DB::table('payments')->whereNull('invoice_id')->delete();                       // advance payments of the test phase
        Schema::table('payments', fn (Blueprint $t) => $t->dropForeign(['invoice_id']));
        Schema::table('payments', fn (Blueprint $t) => $t->unsignedBigInteger('invoice_id')->nullable(false)->change());
        Schema::table('payments', fn (Blueprint $t) => $t->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete());
        Schema::dropIfExists('payment_allocations');

        // paid amount and status of each bill = the payments of that bill
        foreach (DB::table('invoices')->get(['id', 'amount', 'status']) as $invoice) {
            $paid = (float) DB::table('payments')->where('invoice_id', $invoice->id)->sum('amount');
            $status = $invoice->status === 'cancelled' ? 'cancelled'
                : ($paid >= (float) $invoice->amount - 0.004 ? 'paid' : ($paid > 0.004 ? 'partial' : 'unpaid'));
            DB::table('invoices')->where('id', $invoice->id)->update(['paid_amount' => $paid, 'status' => $status]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_status_logs');
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropConstrainedForeignId('payment_schedule_id');
            $t->dropColumn('schedule_name');
        });
        Schema::table('accounts', fn (Blueprint $t) => $t->dropConstrainedForeignId('payment_schedule_id'));
        Schema::dropIfExists('payment_schedules');
    }
};
