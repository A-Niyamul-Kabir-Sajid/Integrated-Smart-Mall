<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds Laravel's default users table as the ERD's APP_USER (decision D-008)
 * and the sessions table so its user_id holds the new UUID keys.
 *
 * The framework migration that created both tables is already on main and stays
 * untouched; this migration replaces the tables. It runs before any real account
 * exists, so dropping them loses no data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->string('display_name', 120);
            $table->string('email', 254)->nullable()->unique();
            $table->string('phone', 24)->nullable()->unique();
            $table->text('password_hash');
            $table->enum('status', ['active', 'suspended', 'anonymized'])->default('active');
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_active_contact_check CHECK (status <> 'active' OR email IS NOT NULL OR phone IS NOT NULL)");
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Restores the framework's default tables. Accounts created after this
     * migration are lost on rollback.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
};
