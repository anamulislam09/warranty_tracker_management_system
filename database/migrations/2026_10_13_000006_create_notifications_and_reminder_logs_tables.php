<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->default('expiry');
            $t->string('title');
            $t->text('message');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        // Prevents the same reminder from being created twice.
        Schema::create('reminder_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('warranty_id')->constrained()->cascadeOnDelete();
            $t->date('end_date');
            $t->unsignedSmallInteger('reminder_days');
            $t->string('channel')->default('app');
            $t->timestamps();
            $t->unique(['warranty_id', 'end_date', 'reminder_days', 'channel'], 'reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
        Schema::dropIfExists('user_notifications');
    }
};
