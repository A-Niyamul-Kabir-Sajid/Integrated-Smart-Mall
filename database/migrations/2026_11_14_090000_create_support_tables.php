<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer support threads (Jira ISM-107/ISM-109, decision D-014), owned by
 * ShopOwnerOperations and outside the ERD: a customer's conversation with one shop, as a
 * thread of append-only messages. Review replies stay in ReviewsModeration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_threads', function (Blueprint $table) {
            $table->uuid('support_thread_id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->foreignUuid('customer_user_id')->constrained('users', 'user_id');
            $table->string('subject', 150);
            $table->enum('kind', ['question', 'complaint']);
            $table->enum('status', ['open', 'answered', 'closed']);
            $table->timestampTz('last_message_at');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->index(['shop_id', 'status', 'last_message_at'], 'support_threads_shop_status_index');
            $table->index(['customer_user_id', 'last_message_at'], 'support_threads_customer_index');
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->uuid('support_message_id')->primary();
            $table->foreignUuid('support_thread_id')->constrained('support_threads', 'support_thread_id');
            $table->foreignUuid('author_user_id')->constrained('users', 'user_id');
            $table->enum('author_side', ['customer', 'shop']);
            $table->text('body');
            $table->timestampTz('created_at');

            $table->index(['support_thread_id', 'created_at'], 'support_messages_thread_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_threads');
    }
};
