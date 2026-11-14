<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orders, reservations and receipts (Jira ISM-107/ISM-108, decision D-014), owned by
 * ShopOwnerOperations and outside the ERD. An order is a customer's request to reserve
 * or collect goods from one shop; when the shop hands them over it becomes a recorded
 * sale (PURCHASE) and receives a receipt. No payment is processed here (C-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->uuid('shop_order_id')->primary();
            $table->string('order_number', 20)->unique();
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->foreignUuid('customer_user_id')->constrained('users', 'user_id');
            $table->enum('kind', ['reservation', 'click_and_collect']);
            $table->enum('status', ['requested', 'accepted', 'ready', 'completed', 'rejected', 'cancelled', 'expired']);
            $table->char('currency', 3);
            $table->decimal('subtotal_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('coupon_code', 30)->nullable();
            $table->string('promotion_title', 120)->nullable();
            $table->char('pickup_code', 6);
            $table->timestampTz('pickup_by');
            $table->text('customer_note')->nullable();
            $table->text('shop_note')->nullable();
            $table->foreignUuid('purchase_id')->nullable()->unique()->constrained('purchases', 'purchase_id');
            $table->timestampsTz();
            $table->timestampTz('completed_at')->nullable();

            $table->index(['shop_id', 'status', 'created_at'], 'shop_orders_shop_status_created_index');
            $table->index(['customer_user_id', 'created_at'], 'shop_orders_customer_created_index');
        });

        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->uuid('shop_order_item_id')->primary();
            $table->foreignUuid('shop_order_id')->constrained('shop_orders', 'shop_order_id');
            $table->unsignedInteger('line_no');
            $table->foreignUuid('shop_product_id')->constrained('shop_products', 'shop_product_id');
            $table->string('label_snapshot', 200);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_amount', 12, 2);
            $table->decimal('line_total_amount', 12, 2);

            $table->unique(['shop_order_id', 'line_no'], 'shop_order_items_order_line_unique');
            $table->index('shop_product_id', 'shop_order_items_listing_index');
        });

        Schema::create('shop_order_events', function (Blueprint $table) {
            $table->id('shop_order_event_id');
            $table->foreignUuid('shop_order_id')->constrained('shop_orders', 'shop_order_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users', 'user_id');
            $table->string('note', 500)->nullable();
            $table->timestampTz('created_at');

            $table->index(['shop_order_id', 'shop_order_event_id'], 'shop_order_events_order_index');
        });

        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->foreignUuid('purchase_id')->primary()->constrained('purchases', 'purchase_id');
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->string('receipt_number', 30);
            $table->foreignUuid('issued_by_user_id')->nullable()->constrained('users', 'user_id');
            $table->timestampTz('issued_at');

            $table->unique(['shop_id', 'receipt_number'], 'purchase_receipts_shop_number_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE shop_orders ADD CONSTRAINT shop_orders_amounts_check CHECK (subtotal_amount >= 0 AND discount_amount >= 0 AND discount_amount <= subtotal_amount AND total_amount = subtotal_amount - discount_amount)');
            DB::statement('ALTER TABLE shop_order_items ADD CONSTRAINT shop_order_items_amounts_check CHECK (quantity > 0 AND unit_price_amount >= 0 AND line_total_amount = quantity * unit_price_amount)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('shop_order_events');
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
    }
};
