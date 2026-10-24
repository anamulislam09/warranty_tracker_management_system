<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Roles & permissions catalog. Created/edited by the SUPER ADMIN only.
// Customers only ASSIGN them to their own users.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();            // e.g. products.view
            $t->string('label');
            $t->string('group')->default('General');
            $t->boolean('is_active')->default(true);
            $t->boolean('is_system')->default(false); // system permissions are enforced by the app code
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
