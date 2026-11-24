<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Administrators' changes to the navigation graph itself (ISM-114): move
     * or remove a node, connect two nodes, change or remove a walkway. They
     * name nodes by code, are replayed in order on top of the package's graph
     * and can be undone one by one.
     */
    public function up(): void
    {
        Schema::create('map_graph_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('mall_map_id')->constrained('mall_maps')->cascadeOnDelete();
            $table->string('key', 16);
            $table->unsignedBigInteger('sequence')->default(0);
            $table->string('action', 20);
            $table->string('node_code', 80)->nullable();
            $table->string('from_code', 80)->nullable();
            $table->string('to_code', 80)->nullable();
            $table->json('data')->nullable();
            $table->string('problem', 250)->nullable();
            $table->timestamps();
            $table->unique(['mall_map_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_graph_edits');
    }
};
