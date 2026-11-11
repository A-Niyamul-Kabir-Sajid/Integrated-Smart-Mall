<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAVOURITE_SHOP and FAVOURITE_PRODUCT (ERD domain 07, owned by FavouritesPreferences).
 *
 * The composite primary keys make saving idempotent: one row per account and
 * shop or listing. A favourite never outlives its account; shops and listings
 * are archived rather than deleted, so their favourites stay and are shown as
 * no longer available.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favourite_shops', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id')->cascadeOnDelete();
            $table->timestampTz('created_at');

            $table->primary(['user_id', 'shop_id']);
            $table->index('shop_id', 'favourite_shops_shop_index');
        });

        Schema::create('favourite_products', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignUuid('shop_product_id')->constrained('shop_products', 'shop_product_id')->cascadeOnDelete();
            $table->timestampTz('created_at');

            $table->primary(['user_id', 'shop_product_id']);
            $table->index('shop_product_id', 'favourite_products_listing_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favourite_products');
        Schema::dropIfExists('favourite_shops');
    }
};
