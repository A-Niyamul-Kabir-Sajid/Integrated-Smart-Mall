<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Administrators' map edits (ISM-114, decision D-022): a point is a plain
     * node, a shop entrance, a facility or a QR code; a path is a walkway an
     * administrator draws. Both carry a unique key and a sequence number, so
     * they are applied again in the order they were made. The shop entrance,
     * facility and QR rows an edit adds carry its id, like its nodes and edges.
     */
    public function up(): void
    {
        Schema::table('map_end_points', function (Blueprint $table) {
            $table->string('kind', 20)->default('node');
            $table->unsignedBigInteger('sequence')->default(0);
            $table->string('unit_code', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('facility_type', 40)->nullable();
            $table->string('checkpoint_code', 60)->nullable();
            $table->json('qr')->nullable();
            $table->string('problem', 250)->nullable();
        });

        Schema::create('map_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->string('key', 16);
            $table->unsignedBigInteger('sequence')->default(0);
            $table->string('name', 120)->nullable();
            $table->string('floor_code', 20);
            $table->json('points');
            $table->boolean('is_accessible')->default(true);
            $table->boolean('one_way')->default(false);
            $table->string('problem', 250)->nullable();
            $table->timestamps();
            $table->unique(['mall_map_id', 'key']);
            $table->index('key');
        });

        foreach (['map_nodes', 'map_edges'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('map_path_id')->nullable();
                $table->index('map_path_id');
            });
        }

        foreach (['map_shop_entrances', 'map_facility_anchors', 'map_checkpoint_anchors'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('map_end_point_id')->nullable();
                $table->index('map_end_point_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['map_shop_entrances', 'map_facility_anchors', 'map_checkpoint_anchors'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['map_end_point_id']);
                $table->dropColumn('map_end_point_id');
            });
        }
        foreach (['map_nodes', 'map_edges'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['map_path_id']);
                $table->dropColumn('map_path_id');
            });
        }
        Schema::dropIfExists('map_paths');
        Schema::table('map_end_points', function (Blueprint $table) {
            $table->dropColumn(['kind', 'sequence', 'unit_code', 'is_primary', 'facility_type', 'checkpoint_code', 'qr', 'problem']);
        });
    }
};
