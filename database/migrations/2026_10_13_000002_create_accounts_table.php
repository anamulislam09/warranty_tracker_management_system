<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "account" = one customer (tenant) of your SaaS.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('phone')->nullable();
            $t->string('country_code', 2)->default('BD');
            $t->string('account_type')->default('personal');   // personal | family | business
            $t->string('status')->default('pending');          // pending | active | inactive
            $t->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $t->string('billing_cycle')->default('monthly');
            $t->date('plan_ends_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
