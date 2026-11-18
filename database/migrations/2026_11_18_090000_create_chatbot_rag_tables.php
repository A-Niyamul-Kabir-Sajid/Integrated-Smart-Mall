<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chatbot and RAG (ERD domain 08, owned by ChatbotRag; Jira EPIC-13, decision D-018).
 *
 * Knowledge: a document is a controlled source the assistant may quote, written by mall
 * administrators (FAQ, mall guide, internal note) or captured from the mall's public records (shop
 * profile, product offer, floors). Its text lives in immutable revisions cut into chunks;
 * current_revision_id names the revision that may be retrieved, older ones stay so that earlier
 * answers keep their citations.
 *
 * Conversations: one per visitor and mall, with ordered messages, the chunks an answer cited and the
 * listings it recommended. A guest's conversation is reached through the server-side session only.
 *
 * Additions to the ERD: current_revision_id and timestamps on the document; heading, search_text
 * and token_count on the chunk; meta on the message; the assistant_interactions log. On PostgreSQL
 * the chunk also gets a generated tsvector and a trigram index, which is what retrieval searches.
 * CHUNK_EMBEDDING (optional in the ERD) is not created: there is no vector index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->uuid('document_id')->primary();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('shop_id')->nullable()->constrained('shops', 'shop_id');
            $table->foreignUuid('shop_product_id')->nullable()->constrained('shop_products', 'shop_product_id');
            $table->enum('source_kind', ['mall_guide', 'shop_profile', 'product_listing', 'faq', 'admin_report']);
            $table->string('title', 200);
            $table->text('source_locator');
            $table->enum('access_scope', ['public', 'mall_admin']);
            $table->enum('status', ['active', 'withdrawn']);
            $table->uuid('current_revision_id')->nullable();
            $table->timestampsTz();

            $table->index(['mall_id', 'status', 'access_scope'], 'knowledge_documents_mall_status_scope_index');
            $table->index('current_revision_id', 'knowledge_documents_current_revision_index');
        });

        Schema::create('document_revisions', function (Blueprint $table) {
            $table->uuid('revision_id')->primary();
            $table->foreignUuid('document_id')->constrained('knowledge_documents', 'document_id')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->char('content_hash', 64);
            $table->text('content_text');
            $table->timestampTz('source_updated_at');
            $table->timestampTz('captured_at');

            $table->unique(['document_id', 'revision_no'], 'document_revisions_document_number_unique');
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->uuid('chunk_id')->primary();
            $table->foreignUuid('revision_id')->constrained('document_revisions', 'revision_id')->cascadeOnDelete();
            $table->unsignedInteger('chunk_no');
            $table->string('heading', 200)->nullable();
            $table->text('chunk_text');
            $table->text('search_text');
            $table->unsignedInteger('token_count');

            $table->unique(['revision_id', 'chunk_no'], 'knowledge_chunks_revision_number_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Full-text search with English stemming plus the plain words (which covers Bangla), and
            // trigram matching for misspelt words. Both indexes are GIN.
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement(<<<'SQL'
                ALTER TABLE knowledge_chunks ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                    setweight(to_tsvector('simple'::regconfig, coalesce(heading, '')), 'A')
                    || setweight(to_tsvector('english'::regconfig, search_text), 'B')
                    || setweight(to_tsvector('simple'::regconfig, search_text), 'C')
                ) STORED
                SQL);
            DB::statement('CREATE INDEX knowledge_chunks_search_vector_index ON knowledge_chunks USING GIN (search_vector)');
            DB::statement('CREATE INDEX knowledge_chunks_search_text_trgm_index ON knowledge_chunks USING GIN (search_text gin_trgm_ops)');
        }

        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('conversation_id')->primary();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'user_id')->cascadeOnDelete();
            $table->enum('access_scope', ['public', 'mall_admin']);
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at');

            $table->index(['user_id', 'mall_id', 'access_scope'], 'conversations_user_mall_scope_index');
            $table->index('expires_at', 'conversations_expires_index');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('message_id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations', 'conversation_id')->cascadeOnDelete();
            $table->unsignedInteger('sequence_no');
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');
            $table->string('model_label', 120)->nullable();
            $table->json('meta')->nullable();
            $table->timestampTz('created_at');

            $table->unique(['conversation_id', 'sequence_no'], 'chat_messages_conversation_sequence_unique');
        });

        Schema::create('message_citations', function (Blueprint $table) {
            $table->foreignUuid('message_id')->constrained('chat_messages', 'message_id')->cascadeOnDelete();
            $table->foreignUuid('chunk_id')->constrained('knowledge_chunks', 'chunk_id');
            $table->unsignedSmallInteger('citation_no');
            $table->decimal('retrieval_score', 8, 4)->nullable();
            $table->text('quoted_excerpt')->nullable();

            $table->primary(['message_id', 'chunk_id']);
            $table->unique(['message_id', 'citation_no'], 'message_citations_message_number_unique');
            $table->index('chunk_id', 'message_citations_chunk_index');
        });

        Schema::create('message_recommendations', function (Blueprint $table) {
            $table->foreignUuid('message_id')->constrained('chat_messages', 'message_id')->cascadeOnDelete();
            $table->foreignUuid('shop_product_id')->constrained('shop_products', 'shop_product_id');
            $table->unsignedSmallInteger('rank_no');
            $table->text('reason');

            $table->primary(['message_id', 'shop_product_id']);
            $table->unique(['message_id', 'rank_no'], 'message_recommendations_message_rank_unique');
        });

        // What was asked and how it ended, without the account or the conversation, so that
        // administrators can see what the assistant could not answer.
        Schema::create('assistant_interactions', function (Blueprint $table) {
            $table->uuid('interaction_id')->primary();
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->enum('access_scope', ['public', 'mall_admin']);
            $table->string('tool', 32);
            $table->string('outcome', 20);
            $table->char('language', 2);
            $table->string('driver', 20);
            $table->string('fallback_reason', 40)->nullable();
            $table->string('refusal_reason', 40)->nullable();
            $table->string('question', 500)->nullable();
            $table->timestampTz('created_at');

            $table->index(['mall_id', 'created_at'], 'assistant_interactions_mall_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_interactions');
        Schema::dropIfExists('message_recommendations');
        Schema::dropIfExists('message_citations');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('knowledge_documents');
    }
};
