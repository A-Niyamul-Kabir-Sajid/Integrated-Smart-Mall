<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shop signs and advertisements placed in 3D on a map, in the map's
     * mall frame (metres, z up).
     */
    public function up(): void
    {
        Schema::create('annotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->string('code', 20);
            $table->enum('kind', ['shop_sign', 'advertisement']);
            $table->string('floor_code', 20)->nullable();
            $table->string('unit_code', 40)->nullable();
            $table->string('text', 60);
            $table->string('subtitle', 80)->default('');
            $table->char('background', 7);
            $table->char('color', 7);
            $table->string('image_path', 200)->nullable();
            $table->decimal('width_m', 6, 3);
            $table->decimal('lift_m', 6, 3);
            $table->enum('mount', ['wall', 'floor']);
            $table->decimal('x_m', 10, 4);
            $table->decimal('y_m', 10, 4);
            $table->decimal('z_m', 10, 4);
            $table->decimal('yaw_deg', 6, 2);
            $table->timestamps();
            $table->unique(['mall_map_id', 'code']);
            $table->index(['mall_map_id', 'unit_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotations');
    }
};
