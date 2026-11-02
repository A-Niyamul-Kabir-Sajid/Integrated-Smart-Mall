<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SHOP_PRODUCT (ERD domain 03): one shop's listing and price for one variant.
 * Reviews, favourites and purchases reference shop_product_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->uuid('shop_product_id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->foreignUuid('variant_id')->constrained('product_variants', 'variant_id');
            $table->string('seller_sku', 80);
            $table->decimal('price_amount', 12, 2);
            $table->char('currency', 3);
            $table->enum('availability_status', ['available', 'unavailable', 'unknown']);
            $table->enum('status', ['active', 'archived']);
            $table->timestampTz('updated_at');

            $table->unique(['shop_id', 'variant_id'], 'shop_products_shop_variant_unique');
            $table->unique(['shop_id', 'seller_sku'], 'shop_products_shop_sku_unique');
            $table->unique(['shop_product_id', 'shop_id'], 'shop_products_listing_shop_unique');
            $table->index(['shop_id', 'status'], 'shop_products_shop_status_index');
            $table->index(['variant_id', 'status'], 'shop_products_variant_status_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE shop_products ADD CONSTRAINT shop_products_price_check CHECK (price_amount >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_products');
    }
};
