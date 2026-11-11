<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shopping lists (decision D-012, owned by FavouritesPreferences; not in the ERD).
 *
 * A list belongs to one account and one mall, because product availability is
 * per mall. An item may point at a shared product or be free text; its label is
 * a snapshot, so the item keeps its meaning when the product is archived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_lists', function (Blueprint $table) {
            $table->uuid('shopping_list_id')->primary();
            $table->foreignUuid('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->string('name', 80);
            $table->timestampsTz();

            $table->index(['user_id', 'updated_at'], 'shopping_lists_user_updated_index');
        });

        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->uuid('shopping_list_item_id')->primary();
            $table->foreignUuid('shopping_list_id')->constrained('shopping_lists', 'shopping_list_id')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products', 'product_id')->nullOnDelete();
            $table->string('label', 160);
            $table->unsignedSmallInteger('quantity');
            $table->string('note', 200)->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestampsTz();

            $table->index(['shopping_list_id', 'created_at'], 'shopping_list_items_list_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
        Schema::dropIfExists('shopping_lists');
    }
};
