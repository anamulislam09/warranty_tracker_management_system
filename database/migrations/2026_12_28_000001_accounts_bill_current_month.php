<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $t) {
            // The admin decides when a new customer becomes active:
            //   true  = the month of activation is billed too (billing starts on the 1st of this month)
            //   false = that month is free (billing starts on the 1st of next month)
            //   null  = not chosen yet (customers that were active before this update keep their billing start)
            $t->boolean('bill_current_month')->nullable()->after('billing_starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', fn (Blueprint $t) => $t->dropColumn('bill_current_month'));
    }
};
