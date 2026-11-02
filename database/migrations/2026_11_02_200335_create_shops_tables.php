<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SHOP, SHOP_UNIT and SHOP_OPENING_HOUR (ERD domain 02).
 *
 * Rules a foreign key cannot express (a unit's floor belongs to the shop's mall,
 * non-overlapping opening intervals) are enforced by the directory services.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->uuid('shop_id')->primary();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('shop_category_id')->constrained('shop_categories', 'shop_category_id');
            $table->string('slug', 100);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('contact_phone', 24)->nullable();
            $table->enum('status', ['draft', 'active', 'closed', 'archived']);
            $table->timestampsTz();

            $table->unique(['mall_id', 'slug'], 'shops_mall_slug_unique');
            $table->index(['mall_id', 'status', 'shop_category_id'], 'shops_mall_status_category_index');
        });

        Schema::create('shop_units', function (Blueprint $table) {
            $table->uuid('shop_unit_id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->foreignUuid('floor_id')->constrained('floors', 'floor_id');
            $table->string('unit_code', 40);
            $table->decimal('area_sq_m', 10, 2)->nullable();
            $table->enum('status', ['open', 'temporarily_closed', 'archived']);

            $table->unique(['floor_id', 'unit_code'], 'shop_units_floor_unit_code_unique');
            $table->index('shop_id', 'shop_units_shop_index');
        });

        Schema::create('shop_opening_hours', function (Blueprint $table) {
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id')->cascadeOnDelete();
            $table->unsignedSmallInteger('weekday');
            $table->unsignedSmallInteger('interval_no');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->boolean('closes_next_day');

            $table->primary(['shop_id', 'weekday', 'interval_no'], 'shop_opening_hours_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_opening_hours');
        Schema::dropIfExists('shop_units');
        Schema::dropIfExists('shops');
    }
};
