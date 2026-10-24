<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('account_id')->nullable()->after('id')->constrained('accounts')->cascadeOnDelete();
            $t->string('mobile')->nullable();
            $t->string('country_code', 2)->default('BD');
            $t->string('language', 5)->default('en');
            $t->string('timezone')->default('Asia/Dhaka');
            $t->string('role')->default('owner');        // super_admin | owner | member
            $t->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete(); // dynamic role (members)
            $t->json('permissions')->nullable();         // extra permissions given by the customer owner
            $t->string('status')->default('active');     // active | inactive
            $t->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('role_id');
            $t->dropConstrainedForeignId('account_id');
            $t->dropColumn(['mobile', 'country_code', 'language', 'timezone', 'role', 'permissions', 'status', 'last_login_at']);
        });
    }
};
