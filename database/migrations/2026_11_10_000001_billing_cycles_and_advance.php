<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // a bill now covers a PERIOD (1, 3, 6 or 12 months) and keeps its discount
        Schema::table('invoices', function (Blueprint $t) {
            $t->date('period_end')->nullable()->after('billing_month');
            $t->string('cycle')->default('monthly')->after('period_end');
            $t->unsignedTinyInteger('months')->default(1)->after('cycle');
            $t->decimal('base_amount', 12, 2)->default(0)->after('months');
            $t->decimal('discount_percent', 5, 2)->default(0)->after('base_amount');
            $t->decimal('discount_amount', 12, 2)->default(0)->after('discount_percent');
        });

        // old monthly bills: period = that month
        DB::table('invoices')->orderBy('id')->each(function ($invoice) {
            DB::table('invoices')->where('id', $invoice->id)->update([
                'period_end'  => Carbon::parse($invoice->billing_month)->endOfMonth()->toDateString(),
                'base_amount' => $invoice->amount,
            ]);
        });

        // a payment can be an ADVANCE (not tied to a bill yet)
        Schema::table('payments', fn (Blueprint $t) => $t->dropForeign(['invoice_id']));
        Schema::table('payments', fn (Blueprint $t) => $t->unsignedBigInteger('invoice_id')->nullable()->change());
        Schema::table('payments', fn (Blueprint $t) => $t->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete());

        // which part of which payment was used for which bill
        Schema::create('payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->timestamps();
            $t->index('account_id');
        });

        DB::table('payments')->whereNotNull('invoice_id')->orderBy('id')->each(function ($payment) {
            DB::table('payment_allocations')->insert([
                'payment_id' => $payment->id, 'invoice_id' => $payment->invoice_id, 'account_id' => $payment->account_id,
                'amount' => $payment->amount, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        // old value "annual" is now "yearly"
        DB::table('accounts')->where('billing_cycle', 'annual')->update(['billing_cycle' => 'yearly']);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropColumn(['period_end', 'cycle', 'months', 'base_amount', 'discount_percent', 'discount_amount']);
        });
    }
};
