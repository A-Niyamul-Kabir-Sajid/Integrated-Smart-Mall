<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOKEN_REDEMPTION (ERD domain 05): the one successful claim of a verification token.
 * The token id is the primary key, so the database itself rejects a second
 * redemption even when two requests race past the application checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_redemptions', function (Blueprint $table) {
            $table->foreignUuid('verification_token_id')->primary()->constrained('verification_tokens', 'verification_token_id');
            $table->foreignUuid('user_id')->constrained('users', 'user_id');
            $table->timestampTz('redeemed_at');

            $table->index(['user_id', 'redeemed_at'], 'token_redemptions_user_redeemed_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_redemptions');
    }
};
