<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions and coupons (Jira ISM-107/ISM-109, decision D-014), owned by
 * ShopOwnerOperations and outside the ERD. A promotion without a coupon code applies to
 * every order of the shop while it runs; one with a code applies when the customer enters it.
 * A redemption ties one promotion to one order, so a code is counted exactly once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_promotions', function (Blueprint $table) {
            $table->uuid('shop_promotion_id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percent', 'amount_off']);
            $table->decimal('discount_value', 12, 2);
            $table->string('coupon_code', 30)->nullable();
            $table->decimal('min_subtotal', 12, 2)->default(0);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->default(1);
            $table->unsignedInteger('times_redeemed')->default(0);
            $table->boolean('is_paused')->default(false);
            $table->foreignUuid('created_by_user_id')->constrained('users', 'user_id');
            $table->timestampsTz();

            $table->unique(['shop_id', 'coupon_code'], 'shop_promotions_shop_code_unique');
            $table->index(['shop_id', 'starts_at', 'ends_at'], 'shop_promotions_shop_window_index');
        });

        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->uuid('promotion_redemption_id')->primary();
            $table->foreignUuid('shop_promotion_id')->constrained('shop_promotions', 'shop_promotion_id');
            $table->foreignUuid('shop_order_id')->unique()->constrained('shop_orders', 'shop_order_id');
            $table->foreignUuid('user_id')->constrained('users', 'user_id');
            $table->decimal('discount_amount', 12, 2);
            $table->timestampTz('created_at');
            $table->timestampTz('released_at')->nullable();

            $table->index(['shop_promotion_id', 'user_id'], 'promotion_redemptions_promotion_user_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE shop_promotions ADD CONSTRAINT shop_promotions_value_check CHECK (discount_value > 0 AND min_subtotal >= 0 AND ends_at > starts_at AND (discount_type <> \'percent\' OR discount_value <= 90))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('shop_promotions');
    }
};
