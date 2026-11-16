<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ---------- every online payment try: what we sent, what the gateway answered, what we verified ----------
        Schema::create('payment_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();         // who pressed "Pay online"

            $t->string('gateway', 30);                                                   // sslcommerz | fake
            $t->string('tran_id', 60)->unique();                                         // OUR reference, sent to the gateway
            $t->decimal('amount', 12, 2);
            $t->string('currency', 3)->default('BDT');

            // initiated -> pending (customer is at the gateway) -> success | failed | cancelled | expired
            // needs_review = the gateway says "paid" but we could not apply it to the bill (an admin must look)
            $t->string('status', 20)->default('initiated');

            $t->string('gateway_session_key')->nullable();                               // session key of the gateway
            $t->string('gateway_reference')->nullable();                                 // gateway's own reference (bank_tran_id / val_id)
            $t->string('gateway_status')->nullable();                                    // the raw status text of the gateway

            $t->json('request_payload')->nullable();                                     // what we sent (never the secret password)
            $t->json('callback_payload')->nullable();                                    // what the gateway sent back (browser / IPN)
            $t->json('validation_payload')->nullable();                                  // the answer of the gateway's verify / query API

            $t->timestamp('verified_at')->nullable();
            $t->string('verified_by')->nullable();                                       // auto | ipn | manual
            $t->foreignId('verified_user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();   // the payment that was recorded
            $t->text('failure_reason')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->unsignedInteger('callback_count')->default(0);
            $t->timestamp('last_callback_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at']);
        });

        // ---------- simple key / value settings (company name, logo, bank details ...) ----------
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        $now = now();
        $defaults = [
            'company_name'        => 'WonderTech BD',
            'company_tagline'     => 'SOFTWARE SOLUTIONS FOR A BETTER TOMORROW',
            'company_office'      => 'Billing Office',
            'company_address'     => "House 12, Road 5, Sector 10, Uttara\nDhaka 1230, Bangladesh",
            'company_email'       => 'billing@wondertechbd.com',
            'company_website'     => 'www.wondertechbd.com',
            'company_phone'       => '',
            'logo_path'           => 'uploads/branding/wondertechbd.jpeg',
            'invoice_subtitle'    => 'SAAS SUBSCRIPTION',
            'product_name'        => 'Warranty Tracker',
            'product_description' => 'Cloud-based SaaS warranty management',
            'tax_text'            => 'Not charged',
            'payment_method'      => 'Bank Transfer / Mobile Financial Service',
            'bank_name'           => 'Sample Bank Ltd.',
            'account_name'        => 'WonderTech BD',
            'account_no'          => '1234567890123',
            'bank_branch'         => 'Uttara, Dhaka',
            'invoice_notes'       => "Your subscription will remain active for the stated subscription period after payment confirmation.\n"
                                   . "Renewal is subject to the then-current plan price and applicable promotional discount.\n"
                                   . 'Please quote the invoice number when making payment.',
            'invoice_footer'      => 'Thank you for choosing Warranty Tracker.',
        ];
        foreach ($defaults as $key => $value) {
            DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('payment_attempts');
    }
};
