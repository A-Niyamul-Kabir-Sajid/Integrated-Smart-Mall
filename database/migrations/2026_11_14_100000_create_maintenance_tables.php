<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance requests from shops to mall management (Jira ISM-107/ISM-109, decision D-014),
 * owned by ShopOwnerOperations and outside the ERD. A request belongs to one shop and the
 * mall it sits in; shop staff raise and confirm it, the mall's administrators work it
 * (the mall-side screens come with EPIC-11) and every change is kept in the history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->uuid('maintenance_request_id')->primary();
            $table->string('request_number', 20)->unique();
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('requested_by_user_id')->constrained('users', 'user_id');
            $table->string('title', 150);
            $table->text('description');
            $table->enum('category', ['plumbing', 'electrical', 'cleaning', 'air_conditioning', 'security', 'other']);
            $table->enum('priority', ['low', 'normal', 'urgent']);
            $table->enum('status', ['open', 'acknowledged', 'in_progress', 'resolved', 'closed', 'cancelled']);
            $table->text('resolution_note')->nullable();
            $table->timestampsTz();
            $table->timestampTz('closed_at')->nullable();

            $table->index(['shop_id', 'status', 'created_at'], 'maintenance_requests_shop_status_index');
            $table->index(['mall_id', 'status', 'priority'], 'maintenance_requests_mall_status_index');
        });

        Schema::create('maintenance_request_events', function (Blueprint $table) {
            $table->id('maintenance_request_event_id');
            $table->foreignUuid('maintenance_request_id')->constrained('maintenance_requests', 'maintenance_request_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users', 'user_id');
            $table->string('note', 500)->nullable();
            $table->timestampTz('created_at');

            $table->index(['maintenance_request_id', 'maintenance_request_event_id'], 'maintenance_events_request_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_request_events');
        Schema::dropIfExists('maintenance_requests');
    }
};
