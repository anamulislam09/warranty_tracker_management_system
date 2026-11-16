<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $t) {
            // the id that is put into the gateway's return URL:  /ssl-process/{unique_id}
            $t->string('unique_id', 64)->nullable()->after('tran_id');

            // a payment goes to the gateway ONLY ONCE:
            //   0 = created, not sent to the gateway yet
            //   1 = sent to the gateway (session created), waiting for the customer's return
            //   2 = the gateway's answer was received and processed (never processed again)
            $t->unsignedTinyInteger('request_step')->default(0)->after('status');

            $t->text('gateway_page_url')->nullable()->after('gateway_session_key');   // where the customer was sent (reused, never asked twice)
            $t->string('card_type', 60)->nullable()->after('gateway_reference');
            $t->string('card_no', 40)->nullable()->after('card_type');                 // masked by the gateway
        });

        // rows that already exist
        foreach (DB::table('payment_attempts')->get(['id', 'status', 'gateway_session_key']) as $row) {
            DB::table('payment_attempts')->where('id', $row->id)->update([
                'unique_id'    => (string) Str::uuid(),
                'request_step' => in_array($row->status, ['success', 'failed', 'cancelled', 'needs_review'], true) ? 2 : ($row->gateway_session_key ? 1 : 0),
            ]);
        }

        Schema::table('payment_attempts', function (Blueprint $t) {
            $t->unique('unique_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $t) {
            $t->dropUnique(['unique_id']);
            $t->dropColumn(['unique_id', 'request_step', 'gateway_page_url', 'card_type', 'card_no']);
        });
    }
};
