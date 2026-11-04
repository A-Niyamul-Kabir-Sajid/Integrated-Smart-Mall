<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRODUCT, PRODUCT_VARIANT and PRODUCT_IMAGE (ERD domain 03): shared product
 * definitions that many shops can list (decision D-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('product_id')->primary();
            $table->foreignUuid('product_category_id')->constrained('product_categories', 'product_category_id');
            $table->string('name', 160);
            $table->string('brand', 100)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'archived']);
            $table->timestampTz('created_at');

            $table->index(['product_category_id', 'status'], 'products_category_status_index');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('variant_id')->primary();
            $table->foreignUuid('product_id')->constrained('products', 'product_id');
            $table->string('variant_code', 60);
            $table->string('label', 120);
            $table->json('attributes_json');
            $table->string('barcode', 80)->nullable()->unique();
            $table->enum('status', ['active', 'archived']);

            $table->unique(['product_id', 'variant_code'], 'product_variants_product_code_unique');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('product_image_id')->primary();
            $table->foreignUuid('product_id')->constrained('products', 'product_id');
            $table->text('object_key');
            $table->string('alt_text', 200);
            $table->integer('sort_order');

            $table->unique(['product_id', 'sort_order'], 'product_images_product_sort_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
