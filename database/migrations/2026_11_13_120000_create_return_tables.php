<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Returns and exchanges (Jira ISM-107/ISM-108, decision D-014), owned by ShopOwnerOperations
 * and outside the ERD. A return asks a shop to take back goods of one recorded sale within
 * the return window; the refund is a recorded amount, handed back at the counter, not a
 * payment (C-02). Lines point at the sale's snapshot lines, so amounts never drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_returns', function (Blueprint $table) {
            $table->uuid('shop_return_id')->primary();
            $table->string('return_number', 20)->unique();
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->foreignUuid('purchase_id')->constrained('purchases', 'purchase_id');
            $table->foreignUuid('requested_by_user_id')->constrained('users', 'user_id');
            $table->enum('kind', ['return', 'exchange']);
            $table->enum('status', ['requested', 'approved', 'rejected', 'completed', 'cancelled']);
            $table->text('reason');
            $table->text('decision_note')->nullable();
            $table->decimal('value_amount', 12, 2);
            $table->boolean('restocked')->default(false);
            $table->timestampsTz();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->index(['shop_id', 'status', 'created_at'], 'shop_returns_shop_status_created_index');
            $table->index('purchase_id', 'shop_returns_purchase_index');
        });

        Schema::create('shop_return_items', function (Blueprint $table) {
            $table->uuid('shop_return_item_id')->primary();
            $table->foreignUuid('shop_return_id')->constrained('shop_returns', 'shop_return_id');
            $table->foreignUuid('purchase_item_id')->constrained('purchase_items', 'purchase_item_id');
            $table->unsignedInteger('quantity');
            $table->decimal('value_amount', 12, 2);

            $table->unique(['shop_return_id', 'purchase_item_id'], 'shop_return_items_return_item_unique');
            $table->index('purchase_item_id', 'shop_return_items_purchase_item_index');
        });

        Schema::create('shop_return_events', function (Blueprint $table) {
            $table->id('shop_return_event_id');
            $table->foreignUuid('shop_return_id')->constrained('shop_returns', 'shop_return_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users', 'user_id');
            $table->string('note', 500)->nullable();
            $table->timestampTz('created_at');

            $table->index(['shop_return_id', 'shop_return_event_id'], 'shop_return_events_return_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE shop_return_items ADD CONSTRAINT shop_return_items_quantity_check CHECK (quantity > 0 AND value_amount >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_return_events');
        Schema::dropIfExists('shop_return_items');
        Schema::dropIfExists('shop_returns');
    }
};
