<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PURCHASE and PURCHASE_ITEM (ERD domain 05): a sale recorded by trusted shop
 * staff, kept as purchase evidence. It is not a checkout or payment record.
 *
 * Items snapshot the product label and price at sale time, so later catalogue
 * edits never rewrite history. The cross-row rules (items sum to the total,
 * listing belongs to the purchase shop) are enforced by PurchaseService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('purchase_id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->foreignUuid('buyer_user_id')->nullable()->constrained('users', 'user_id');
            $table->string('external_receipt_ref', 120);
            $table->enum('status', ['draft', 'completed', 'voided', 'refunded']);
            $table->char('currency', 3);
            $table->decimal('total_amount', 12, 2);
            $table->timestampTz('purchased_at');
            $table->timestampTz('recorded_at');

            $table->unique(['shop_id', 'external_receipt_ref'], 'purchases_shop_receipt_unique');
            $table->index(['shop_id', 'purchased_at'], 'purchases_shop_purchased_at_index');
            $table->index(['buyer_user_id', 'purchased_at'], 'purchases_buyer_purchased_at_index');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->uuid('purchase_item_id')->primary();
            $table->foreignUuid('purchase_id')->constrained('purchases', 'purchase_id');
            $table->unsignedInteger('line_no');
            $table->foreignUuid('shop_product_id')->constrained('shop_products', 'shop_product_id');
            $table->string('product_label_snapshot', 200);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_amount', 12, 2);
            $table->decimal('line_discount_amount', 12, 2);
            $table->decimal('line_total_amount', 12, 2);

            $table->unique(['purchase_id', 'line_no'], 'purchase_items_purchase_line_unique');
            $table->index('shop_product_id', 'purchase_items_shop_product_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE purchases ADD CONSTRAINT purchases_total_check CHECK (total_amount >= 0)');
            DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT purchase_items_amounts_check CHECK (quantity > 0 AND unit_price_amount >= 0 AND line_discount_amount >= 0 AND line_discount_amount <= quantity * unit_price_amount AND line_total_amount = quantity * unit_price_amount - line_discount_amount)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
