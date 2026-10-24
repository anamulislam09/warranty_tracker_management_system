<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // "products" holds BOTH item types: product (phone, fridge...) and document (passport, license...)
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('item_type')->default('product');          // product | document
            $t->foreignId('category_id')->nullable()->constrained('categories');
            $t->foreignId('subcategory_id')->nullable()->constrained('subcategories');
            $t->string('warranty_mode')->default('single');        // single | multiple (products only)
            $t->string('document_type')->nullable();               // Passport, Driving License ... (documents only)
            $t->string('issued_by')->nullable();                   // issuing authority (documents only)
            $t->string('name');
            $t->string('brand')->nullable();
            $t->string('model')->nullable();
            $t->string('serial_number')->nullable();               // for documents = document number
            $t->text('description')->nullable();
            $t->date('purchase_date')->nullable();
            $t->decimal('purchase_price', 12, 2)->nullable();
            $t->string('currency', 3)->default('BDT');
            $t->string('seller_name')->nullable();
            $t->string('seller_phone')->nullable();
            $t->string('invoice_number')->nullable();
            $t->string('purchase_location')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);               // a renewed document makes the old one inactive
            $t->foreignId('renewed_from_id')->nullable()->constrained('products')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['account_id', 'name']);
            $t->index('serial_number');
        });

        // single mode: 1 row for the whole product. multiple mode: 1 row per component (e.g. Compressor).
        // documents: 1 row = validity period.
        Schema::create('warranties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('component')->default('Whole Product');
            $t->string('warranty_type')->default('manufacturer'); // manufacturer|extended|seller|other
            $t->date('start_date');
            $t->unsignedInteger('duration');
            $t->string('duration_unit')->default('month');        // day|month|year
            $t->date('end_date');
            $t->string('end_date_source')->default('calculated'); // calculated|manual
            $t->string('provider')->nullable();
            $t->text('terms')->nullable();
            $t->json('reminder_days')->nullable();                // e.g. [30,7,1]
            $t->timestamps();
            $t->index('end_date');
        });

        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('invoice');   // invoice|warranty_card|receipt|other
            $t->string('original_name');
            $t->string('path');
            $t->string('mime_type')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('products');
    }
};
