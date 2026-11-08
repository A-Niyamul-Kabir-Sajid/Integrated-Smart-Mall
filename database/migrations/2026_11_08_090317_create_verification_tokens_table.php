<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VERIFICATION_TOKEN (ERD domain 05): one rotatable review credential per purchase
 * line. Only the digest of the random secret is stored, never the secret itself.
 * Issuing, rotating and revoking are done by VerificationCredentialService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_tokens', function (Blueprint $table) {
            $table->uuid('verification_token_id')->primary();
            $table->foreignUuid('purchase_item_id')->constrained('purchase_items', 'purchase_item_id');
            $table->string('token_digest', 128);
            $table->foreignUuid('issued_by_user_id')->constrained('users', 'user_id');
            $table->timestampTz('issued_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();

            $table->unique('purchase_item_id', 'verification_tokens_purchase_item_unique');
            $table->unique('token_digest', 'verification_tokens_digest_unique');
            $table->index('issued_by_user_id', 'verification_tokens_issuer_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE verification_tokens ADD CONSTRAINT verification_tokens_expiry_check CHECK (expires_at > issued_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_tokens');
    }
};
