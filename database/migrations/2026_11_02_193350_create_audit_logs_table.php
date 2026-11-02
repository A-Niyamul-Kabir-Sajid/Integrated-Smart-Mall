<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only business audit trail, owned by SharedPlatform.
 *
 * Not part of the ERD; required by the shared-platform guide ("audit logs:
 * track sensitive changes and actions") and Jira ISM-15. Actor, subject and
 * scope identifiers are plain strings without foreign keys so the history
 * survives account anonymisation and record archiving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('audit_log_id')->primary();
            $table->timestampTz('occurred_at');
            $table->string('action', 100);
            $table->string('actor_id', 64)->nullable();
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('mall_id', 64)->nullable();
            $table->string('shop_id', 64)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata');

            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
            $table->index(['actor_id', 'occurred_at'], 'audit_logs_actor_index');
            $table->index(['mall_id', 'occurred_at'], 'audit_logs_mall_index');
            $table->index(['shop_id', 'occurred_at'], 'audit_logs_shop_index');
            $table->index('action', 'audit_logs_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
