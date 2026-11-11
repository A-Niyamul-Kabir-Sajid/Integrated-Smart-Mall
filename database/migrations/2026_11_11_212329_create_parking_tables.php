<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parking (decision D-013, owned by ParkingFacilities; not in the ERD).
 *
 * parking_zones: the marked areas of a mall's car park, maintained by mall
 * administrators; capacity feeds the labelled occupancy simulation.
 * parking_locations: the one spot an account saved per mall; saving again
 * replaces it, clearing deletes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_zones', function (Blueprint $table) {
            $table->uuid('parking_zone_id')->primary();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('floor_id')->constrained('floors', 'floor_id');
            $table->string('code', 20);
            $table->string('name', 80);
            $table->unsignedInteger('capacity');
            $table->enum('status', ['active', 'archived']);

            $table->unique(['mall_id', 'code'], 'parking_zones_mall_code_unique');
        });

        Schema::create('parking_locations', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('parking_zone_id')->constrained('parking_zones', 'parking_zone_id');
            $table->string('bay_label', 20)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestampTz('saved_at');

            $table->primary(['user_id', 'mall_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_locations');
        Schema::dropIfExists('parking_zones');
    }
};
