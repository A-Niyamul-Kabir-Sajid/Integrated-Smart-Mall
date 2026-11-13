<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop stock (Jira ISM-104, decision D-014), owned by ShopOwnerOperations and
 * outside the ERD: a quantity on hand per listing and an append-only ledger of
 * every change. The quantity always equals the sum of the ledger, which the
 * StockService keeps true and a reconciliation check proves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_stocks', function (Blueprint $table) {
            $table->uuid('shop_product_id')->primary();
            $table->uuid('shop_id');
            $table->unsignedInteger('on_hand')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->timestampTz('updated_at');

            $table->foreign(['shop_product_id', 'shop_id'], 'listing_stocks_listing_shop_foreign')
                ->references(['shop_product_id', 'shop_id'])->on('shop_products');
            $table->index(['shop_id', 'on_hand'], 'listing_stocks_shop_on_hand_index');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id('stock_movement_id');
            $table->uuid('shop_product_id');
            $table->uuid('shop_id');
            $table->enum('movement_type', ['receipt', 'adjustment', 'sale', 'return']);
            $table->integer('quantity_delta');
            $table->unsignedInteger('balance_after');
            $table->string('reason', 200)->nullable();
            $table->string('reference_type', 40)->nullable();
            $table->string('reference_id', 36)->nullable();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users', 'user_id');
            $table->timestampTz('created_at');

            $table->foreign(['shop_product_id', 'shop_id'], 'stock_movements_listing_shop_foreign')
                ->references(['shop_product_id', 'shop_id'])->on('shop_products');
            $table->index(['shop_product_id', 'created_at'], 'stock_movements_listing_created_index');
            $table->index(['shop_id', 'created_at'], 'stock_movements_shop_created_index');
            $table->index(['reference_type', 'reference_id'], 'stock_movements_reference_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_delta_check CHECK (quantity_delta <> 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('listing_stocks');
    }
};
