<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor activity (Jira EPIC-14, decision D-016), owned by AnalyticsReports and outside the ERD.
 * Raw events carry no user id, name, email or phone number: a search holds the normalised text and
 * a signed-in flag; a view holds a daily-reset anonymous key. Daily summaries are what dashboards read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_search_events', function (Blueprint $table) {
            $table->id('activity_search_event_id');
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->enum('kind', ['shop', 'product']);
            $table->string('term', 100)->nullable();
            $table->boolean('term_masked')->default(false);
            $table->string('category', 80)->nullable();
            $table->string('floor', 20)->nullable();
            $table->unsignedInteger('result_count');
            $table->boolean('signed_in');
            $table->enum('dataset_label', ['real', 'demo']);
            $table->timestampTz('occurred_at');

            $table->index(['mall_id', 'occurred_at'], 'activity_search_events_mall_time_index');
        });

        Schema::create('activity_view_events', function (Blueprint $table) {
            $table->id('activity_view_event_id');
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->enum('kind', ['shop', 'product']);
            $table->foreignUuid('shop_id')->nullable()->constrained('shops', 'shop_id');
            $table->foreignUuid('product_id')->nullable()->constrained('products', 'product_id');
            $table->char('visitor_key', 64);
            $table->enum('dataset_label', ['real', 'demo']);
            $table->timestampTz('occurred_at');

            $table->index(['mall_id', 'occurred_at'], 'activity_view_events_mall_time_index');
            $table->index(['shop_id', 'occurred_at'], 'activity_view_events_shop_time_index');
            $table->index(['product_id', 'occurred_at'], 'activity_view_events_product_time_index');
        });

        Schema::create('activity_daily_summaries', function (Blueprint $table) {
            $table->id('activity_daily_summary_id');
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->date('day');
            $table->string('metric_code', 40);
            $table->string('kind', 10)->default('');
            $table->string('subject_key', 120)->default('');
            $table->foreignUuid('shop_id')->nullable()->constrained('shops', 'shop_id');
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('secondary')->default(0);
            $table->enum('dataset_label', ['real', 'demo']);
            $table->unsignedSmallInteger('formula_version');
            $table->timestampTz('source_cutoff_at');

            $table->unique(['mall_id', 'day', 'metric_code', 'kind', 'subject_key', 'dataset_label'], 'activity_daily_summaries_unique');
            $table->index(['mall_id', 'metric_code', 'day'], 'activity_daily_summaries_metric_index');
            $table->index(['shop_id', 'metric_code', 'day'], 'activity_daily_summaries_shop_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_daily_summaries');
        Schema::dropIfExists('activity_view_events');
        Schema::dropIfExists('activity_search_events');
    }
};
