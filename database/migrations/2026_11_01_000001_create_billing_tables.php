<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $t) {
            $t->date('billing_starts_on')->nullable()->after('plan_ends_at');   // first month that is billed
            $t->string('suspended_reason')->nullable()->after('status');        // overdue_bill | null
        });

        // one row per customer per month
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $t->string('plan_name')->nullable();                 // snapshot: the plan that was billed this month
            $t->string('invoice_no')->nullable()->unique();
            $t->date('billing_month');                           // always the 1st day of the month
            $t->decimal('amount', 12, 2);                        // snapshot of the price for this month
            $t->decimal('paid_amount', 12, 2)->default(0);
            $t->date('due_date');
            $t->string('status')->default('unpaid');             // unpaid | partial | paid | cancelled
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['account_id', 'billing_month']);
            $t->index(['status', 'due_date']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->date('paid_on');
            $t->string('method')->default('cash');
            $t->string('reference')->nullable();                 // transaction id / receipt no
            $t->text('note')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index('paid_on');
        });

        // makes sure every notice (reminder, suspension ...) is created only once
        Schema::create('invoice_notice_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->timestamps();
            $t->unique(['invoice_id', 'type']);
        });

        // customers that are already active start to be billed from next month
        DB::table('accounts')->where('status', 'active')->whereNull('billing_starts_on')
            ->update(['billing_starts_on' => today()->addMonthNoOverflow()->startOfMonth()->toDateString()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_notice_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::table('accounts', function (Blueprint $t) {
            $t->dropColumn(['billing_starts_on', 'suspended_reason']);
        });
    }
};
