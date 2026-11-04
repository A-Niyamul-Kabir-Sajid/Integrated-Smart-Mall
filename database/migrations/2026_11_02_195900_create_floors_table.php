<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FLOOR (ERD domain 02): a physical level of a mall, independent of any map version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floors', function (Blueprint $table) {
            $table->uuid('floor_id')->primary();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->string('code', 20);
            $table->string('name', 80);
            $table->integer('sort_order');
            $table->decimal('elevation_m', 8, 3);
            $table->enum('status', ['active', 'archived']);

            $table->unique(['mall_id', 'code'], 'floors_mall_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floors');
    }
};
