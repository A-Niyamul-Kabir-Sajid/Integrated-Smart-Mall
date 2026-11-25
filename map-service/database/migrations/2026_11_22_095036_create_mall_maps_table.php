<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Imported map packages (exports of tools/blender/ism_map.py).
     */
    public function up(): void
    {
        Schema::create('mall_maps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->unsignedInteger('format_version');
            $table->timestamp('package_generated_at')->nullable();
            $table->string('storage_dir', 200);
            $table->json('stats');
            $table->json('floor_codes');
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_maps');
    }
};
