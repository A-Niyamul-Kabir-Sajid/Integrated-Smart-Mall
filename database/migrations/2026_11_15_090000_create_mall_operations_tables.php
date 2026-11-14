<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mall operations (Jira ISM-116/ISM-117, decision D-015), owned by MallOwnerAdmin and outside
 * the ERD: announcements and events, customer complaints with their histories, and maintenance
 * work orders with their histories. Every row belongs to one mall; histories are append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('announcement_id')->primary();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->enum('kind', ['notice', 'event']);
            $table->string('title', 150);
            $table->text('body');
            $table->string('location_note', 160)->nullable();
            $table->enum('status', ['draft', 'published', 'archived']);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignUuid('created_by_user_id')->constrained('users', 'user_id');
            $table->timestampsTz();

            $table->index(['mall_id', 'status', 'ends_at'], 'announcements_mall_status_index');
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->uuid('complaint_id')->primary();
            $table->string('complaint_number', 20)->unique();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('customer_user_id')->constrained('users', 'user_id');
            $table->foreignUuid('shop_id')->nullable()->constrained('shops', 'shop_id');
            $table->enum('category', ['cleanliness', 'safety', 'staff_conduct', 'facilities', 'shop_service', 'noise', 'other']);
            $table->string('subject', 150);
            $table->text('description');
            $table->enum('status', ['open', 'assigned', 'in_progress', 'resolved', 'closed', 'rejected', 'withdrawn']);
            $table->foreignUuid('assigned_to_user_id')->nullable()->constrained('users', 'user_id');
            $table->text('resolution_note')->nullable();
            $table->timestampsTz();
            $table->timestampTz('closed_at')->nullable();

            $table->index(['mall_id', 'status', 'created_at'], 'complaints_mall_status_index');
            $table->index(['customer_user_id', 'created_at'], 'complaints_customer_index');
            $table->index(['assigned_to_user_id', 'status'], 'complaints_assignee_index');
        });

        Schema::create('complaint_events', function (Blueprint $table) {
            $table->id('complaint_event_id');
            $table->foreignUuid('complaint_id')->constrained('complaints', 'complaint_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users', 'user_id');
            $table->string('note', 1000)->nullable();
            $table->timestampTz('created_at');

            $table->index(['complaint_id', 'complaint_event_id'], 'complaint_events_complaint_index');
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->uuid('work_order_id')->primary();
            $table->string('work_order_number', 20)->unique();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('maintenance_request_id')->nullable()->constrained('maintenance_requests', 'maintenance_request_id');
            $table->foreignUuid('floor_id')->nullable()->constrained('floors', 'floor_id');
            $table->foreignUuid('shop_id')->nullable()->constrained('shops', 'shop_id');
            $table->string('title', 150);
            $table->text('description');
            $table->string('location_note', 160)->nullable();
            $table->enum('priority', ['low', 'normal', 'urgent']);
            $table->enum('status', ['open', 'assigned', 'in_progress', 'completed', 'cancelled']);
            $table->foreignUuid('assigned_to_user_id')->nullable()->constrained('users', 'user_id');
            $table->date('due_on')->nullable();
            $table->text('completion_note')->nullable();
            $table->foreignUuid('created_by_user_id')->constrained('users', 'user_id');
            $table->timestampsTz();
            $table->timestampTz('completed_at')->nullable();

            $table->index(['mall_id', 'status', 'priority'], 'work_orders_mall_status_index');
            $table->index(['assigned_to_user_id', 'status'], 'work_orders_assignee_index');
            $table->index('maintenance_request_id', 'work_orders_request_index');
        });

        Schema::create('work_order_events', function (Blueprint $table) {
            $table->id('work_order_event_id');
            $table->foreignUuid('work_order_id')->constrained('work_orders', 'work_order_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users', 'user_id');
            $table->string('note', 1000)->nullable();
            $table->timestampTz('created_at');

            $table->index(['work_order_id', 'work_order_event_id'], 'work_order_events_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_events');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('complaint_events');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('announcements');
    }
};
