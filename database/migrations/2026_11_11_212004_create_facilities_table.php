<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FACILITY (ERD domain 02, owned by MallShopDirectory): public destinations such
 * as toilets, prayer rooms and information desks.
 *
 * Decision D-013 adds the types atm and lift and an optional location note,
 * used while there is no map to place the facility on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->uuid('facility_id')->primary();
            $table->foreignUuid('floor_id')->constrained('floors', 'floor_id');
            $table->string('name', 120);
            $table->enum('facility_type', ['toilet', 'prayer_room', 'information', 'exit', 'atm', 'lift', 'other']);
            $table->boolean('is_accessible');
            $table->enum('status', ['open', 'temporarily_closed', 'archived']);
            $table->string('location_note', 160)->nullable();

            $table->index(['floor_id', 'status'], 'facilities_floor_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
