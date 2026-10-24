<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('description')->nullable();
            $t->decimal('monthly_price', 10, 2)->default(0);
            $t->decimal('annual_price', 10, 2)->default(0);
            $t->unsignedInteger('max_products')->default(0);      // 0 = unlimited
            $t->unsignedInteger('storage_limit_mb')->default(500);
            $t->unsignedInteger('max_users')->default(1);
            $t->boolean('reminder_enabled')->default(true);
            $t->boolean('document_upload_enabled')->default(true);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
