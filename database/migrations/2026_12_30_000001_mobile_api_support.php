<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Sanctum tokens (one per phone). Skipped when `php artisan install:api` already created it.
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $t) {
                $t->id();
                $t->morphs('tokenable');
                $t->text('name');
                $t->string('token', 64)->unique();
                $t->text('abilities')->nullable();
                $t->timestamp('last_used_at')->nullable();
                $t->timestamp('expires_at')->nullable()->index();
                $t->timestamps();
            });
        }

        // push notification tokens of the phones (Firebase Cloud Messaging)
        Schema::create('user_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token', 255)->unique();                 // the FCM registration token
            $t->string('platform', 10)->default('android');     // android | ios
            $t->string('device_name', 100)->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });

        // where an online payment was started: the website or the mobile app (decides where the customer returns to)
        Schema::table('payment_attempts', function (Blueprint $t) {
            $t->string('source', 10)->default('web')->after('gateway');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::dropIfExists('user_devices');
    }
};
