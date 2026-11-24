<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * End points (ISM-114): destinations an administrator adds to a map, each
     * with a short random key apps use as "point:<key>". An end point becomes
     * one node joined to the nearest walkway through a junction node; the
     * nodes and edges it adds carry its id, and the edges it adds next to an
     * existing walkway name that walkway as their parent, so a closure of the
     * walkway closes them too. The service removes them with the end point
     * (plain columns: SQLite cannot add foreign keys to existing tables).
     */
    public function up(): void
    {
        Schema::create('map_end_points', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->string('key', 16);
            $table->string('name', 120);
            $table->string('floor_code', 20);
            $table->double('x_m');
            $table->double('y_m');
            $table->double('z_m');
            $table->unsignedBigInteger('map_node_id')->nullable();
            $table->double('link_m')->nullable();
            $table->timestamps();
            $table->unique(['mall_map_id', 'key']);
            $table->index('key');
        });

        Schema::table('map_nodes', function (Blueprint $table) {
            $table->unsignedBigInteger('map_end_point_id')->nullable();
            $table->index('map_end_point_id');
        });

        Schema::table('map_edges', function (Blueprint $table) {
            $table->unsignedBigInteger('map_end_point_id')->nullable();
            $table->unsignedBigInteger('parent_edge_id')->nullable();
            $table->index('map_end_point_id');
            $table->index('parent_edge_id');
        });
    }

    public function down(): void
    {
        Schema::table('map_edges', function (Blueprint $table) {
            $table->dropIndex(['map_end_point_id']);
            $table->dropIndex(['parent_edge_id']);
            $table->dropColumn(['map_end_point_id', 'parent_edge_id']);
        });
        Schema::table('map_nodes', function (Blueprint $table) {
            $table->dropIndex(['map_end_point_id']);
            $table->dropColumn('map_end_point_id');
        });
        Schema::dropIfExists('map_end_points');
    }
};
