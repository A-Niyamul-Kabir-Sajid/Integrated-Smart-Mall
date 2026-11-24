<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The navigation graph of each imported map (ISM-59, decision D-021): the
     * ERD domain 04 records FLOOR_MAP, NAVIGATION_NODE, NAVIGATION_EDGE,
     * VERTICAL_CONNECTOR, CONNECTOR_STOP, SHOP_ENTRANCE, FACILITY_ANCHOR and
     * CHECKPOINT_ANCHOR, one set per mall_maps row (the MAP_VERSION). The rows
     * are written from map_package.json in the import transaction. Composite
     * foreign keys keep every node, edge and anchor inside its own map.
     */
    public function up(): void
    {
        Schema::table('mall_maps', function (Blueprint $table) {
            $table->string('mall_ref', 100)->nullable()->after('name');
            $table->index(['mall_ref', 'status']);
        });

        Schema::create('map_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->string('floor_code', 20);
            $table->string('name', 120);
            $table->double('elevation_m');
            $table->unique(['mall_map_id', 'floor_code']);
        });

        Schema::create('map_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->string('floor_code', 20);
            $table->string('node_code', 80);
            $table->string('node_type', 20);
            $table->double('x_m');
            $table->double('y_m');
            $table->double('z_m');
            $table->boolean('is_accessible')->default(true);
            $table->unique(['mall_map_id', 'node_code']);
            $table->unique(['mall_map_id', 'id']);
            $table->foreign(['mall_map_id', 'floor_code'])->references(['mall_map_id', 'floor_code'])->on('map_floors')->cascadeOnDelete();
        });

        Schema::create('map_connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('connector_type', 20);
            $table->string('direction', 10);
            $table->boolean('is_accessible')->default(true);
            $table->unique(['mall_map_id', 'name']);
            $table->unique(['mall_map_id', 'id']);
        });

        Schema::create('map_connector_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->unsignedBigInteger('map_connector_id');
            $table->unsignedBigInteger('map_node_id');
            $table->string('floor_code', 20);
            $table->unique(['map_connector_id', 'floor_code']);
            $table->foreign(['mall_map_id', 'map_connector_id'])->references(['mall_map_id', 'id'])->on('map_connectors')->cascadeOnDelete();
            $table->foreign(['mall_map_id', 'map_node_id'])->references(['mall_map_id', 'id'])->on('map_nodes')->cascadeOnDelete();
        });

        Schema::create('map_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->unsignedBigInteger('from_node_id');
            $table->unsignedBigInteger('to_node_id');
            $table->double('length_m');
            $table->double('expected_seconds');
            $table->boolean('is_accessible')->default(true);
            $table->string('status', 10)->default('open');
            $table->unsignedBigInteger('map_connector_id')->nullable();
            $table->string('closed_reason', 200)->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->unique(['mall_map_id', 'from_node_id', 'to_node_id']);
            $table->index(['mall_map_id', 'status']);
            $table->foreign(['mall_map_id', 'from_node_id'])->references(['mall_map_id', 'id'])->on('map_nodes')->cascadeOnDelete();
            $table->foreign(['mall_map_id', 'to_node_id'])->references(['mall_map_id', 'id'])->on('map_nodes')->cascadeOnDelete();
            $table->foreign('map_connector_id')->references('id')->on('map_connectors')->cascadeOnDelete();
        });

        Schema::create('map_shop_entrances', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->string('unit_code', 40);
            $table->unsignedBigInteger('map_node_id');
            $table->boolean('is_primary')->default(false);
            $table->unique(['mall_map_id', 'unit_code', 'map_node_id']);
            $table->foreign(['mall_map_id', 'map_node_id'])->references(['mall_map_id', 'id'])->on('map_nodes')->cascadeOnDelete();
        });

        Schema::create('map_facility_anchors', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->string('facility_name', 120);
            $table->string('facility_type', 40);
            $table->unsignedBigInteger('map_node_id');
            $table->unique(['mall_map_id', 'facility_name']);
            $table->index(['mall_map_id', 'facility_type']);
            $table->foreign(['mall_map_id', 'map_node_id'])->references(['mall_map_id', 'id'])->on('map_nodes')->cascadeOnDelete();
        });

        Schema::create('map_checkpoint_anchors', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->string('checkpoint_code', 60);
            $table->string('label', 160)->nullable();
            $table->unsignedBigInteger('map_node_id');
            $table->json('qr');
            $table->unique(['mall_map_id', 'checkpoint_code']);
            $table->foreign(['mall_map_id', 'map_node_id'])->references(['mall_map_id', 'id'])->on('map_nodes')->cascadeOnDelete();
        });

        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            // At most one published map per mall, and one primary entrance per shop unit in a map.
            DB::statement("CREATE UNIQUE INDEX mall_maps_one_published_per_mall ON mall_maps (mall_ref) WHERE status = 'published' AND mall_ref IS NOT NULL");
            DB::statement('CREATE UNIQUE INDEX map_shop_entrances_one_primary ON map_shop_entrances (mall_map_id, unit_code) WHERE is_primary');
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS mall_maps_one_published_per_mall');
        }
        Schema::dropIfExists('map_checkpoint_anchors');
        Schema::dropIfExists('map_facility_anchors');
        Schema::dropIfExists('map_shop_entrances');
        Schema::dropIfExists('map_edges');
        Schema::dropIfExists('map_connector_stops');
        Schema::dropIfExists('map_connectors');
        Schema::dropIfExists('map_nodes');
        Schema::dropIfExists('map_floors');
        Schema::table('mall_maps', function (Blueprint $table) {
            $table->dropIndex(['mall_ref', 'status']);
            $table->dropColumn('mall_ref');
        });
    }
};
