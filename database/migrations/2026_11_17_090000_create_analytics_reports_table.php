<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stored monthly reports (Jira EPIC-12, decision D-017), owned by AnalyticsReports and outside the ERD.
 * A report is a snapshot: the figures as they were when it was generated (payload), the metric catalogue
 * version, and the moment the data was read. Generating the same month again replaces the snapshot.
 * The subject key is the shop id or the mall id, so one month of one subject has one report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_monthly_reports', function (Blueprint $table) {
            $table->uuid('report_id')->primary();
            $table->enum('scope', ['shop', 'mall']);
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('shop_id')->nullable()->constrained('shops', 'shop_id');
            $table->char('subject_key', 36);
            $table->date('month');
            $table->unsignedSmallInteger('metric_version');
            $table->boolean('partial')->default(false);
            $table->timestampTz('source_cutoff_at');
            $table->json('payload');
            $table->foreignUuid('generated_by_user_id')->constrained('users', 'user_id');
            $table->timestampsTz();

            $table->unique(['scope', 'subject_key', 'month'], 'analytics_monthly_reports_subject_month_unique');
            $table->index(['mall_id', 'scope', 'month'], 'analytics_monthly_reports_mall_month_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_monthly_reports');
    }
};
