<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REVIEW, REVIEW_VERIFICATION, REVIEW_REPLY, REVIEW_REPORT and MODERATION_ACTION
 * (ERD domain 06).
 *
 * One reviews table holds shop reviews (shop_product_id null) and product-listing
 * reviews. A user has one review per target, enforced by two partial unique indexes,
 * and a listing must belong to the reviewed shop, enforced by a composite foreign key.
 * The verified badge is derived from review_verifications, never stored on the review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('review_id')->primary();
            $table->foreignUuid('author_user_id')->constrained('users', 'user_id');
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->uuid('shop_product_id')->nullable();
            $table->unsignedSmallInteger('rating');
            $table->string('title', 160)->nullable();
            $table->text('body');
            $table->enum('status', ['pending', 'published', 'hidden', 'withdrawn']);
            $table->timestampsTz();

            $table->foreign(['shop_product_id', 'shop_id'], 'reviews_listing_shop_foreign')
                ->references(['shop_product_id', 'shop_id'])->on('shop_products');
            $table->index(['shop_id', 'status', 'created_at'], 'reviews_shop_status_created_index');
            $table->index(['shop_product_id', 'status'], 'reviews_listing_status_index');
            $table->index('author_user_id', 'reviews_author_index');
        });

        DB::statement('CREATE UNIQUE INDEX reviews_author_shop_unique ON reviews (author_user_id, shop_id) WHERE shop_product_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX reviews_author_listing_unique ON reviews (author_user_id, shop_product_id) WHERE shop_product_id IS NOT NULL');

        Schema::create('review_verifications', function (Blueprint $table) {
            $table->foreignUuid('review_id')->primary()->constrained('reviews', 'review_id');
            $table->foreignUuid('verification_token_id')->constrained('token_redemptions', 'verification_token_id');
            $table->timestampTz('verified_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();

            $table->unique('verification_token_id', 'review_verifications_token_unique');
        });

        Schema::create('review_replies', function (Blueprint $table) {
            $table->uuid('review_reply_id')->primary();
            $table->foreignUuid('review_id')->constrained('reviews', 'review_id');
            $table->foreignUuid('author_user_id')->constrained('users', 'user_id');
            $table->text('body');
            $table->enum('status', ['published', 'hidden']);
            $table->timestampsTz();

            $table->index(['review_id', 'status'], 'review_replies_review_status_index');
        });

        Schema::create('review_reports', function (Blueprint $table) {
            $table->uuid('review_report_id')->primary();
            $table->foreignUuid('review_id')->constrained('reviews', 'review_id');
            $table->foreignUuid('reporter_user_id')->constrained('users', 'user_id');
            $table->enum('reason_code', ['spam', 'abuse', 'conflict_of_interest', 'other']);
            $table->text('details')->nullable();
            $table->enum('status', ['open', 'resolved', 'dismissed']);
            $table->timestampTz('created_at');
            $table->timestampTz('resolved_at')->nullable();

            $table->unique(['review_id', 'reporter_user_id'], 'review_reports_review_reporter_unique');
            $table->index(['status', 'created_at'], 'review_reports_status_created_index');
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->uuid('moderation_action_id')->primary();
            $table->foreignUuid('review_id')->constrained('reviews', 'review_id');
            $table->foreignUuid('review_report_id')->nullable()->constrained('review_reports', 'review_report_id');
            $table->foreignUuid('actor_user_id')->constrained('users', 'user_id');
            $table->enum('action', ['publish', 'hide', 'restore', 'dismiss_report', 'revoke_verification']);
            $table->text('reason');
            $table->enum('previous_status', ['pending', 'published', 'hidden', 'withdrawn']);
            $table->enum('resulting_status', ['pending', 'published', 'hidden', 'withdrawn']);
            $table->timestampTz('created_at');

            $table->index(['review_id', 'created_at'], 'moderation_actions_review_created_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_replies');
        Schema::dropIfExists('review_verifications');
        Schema::dropIfExists('reviews');
    }
};
