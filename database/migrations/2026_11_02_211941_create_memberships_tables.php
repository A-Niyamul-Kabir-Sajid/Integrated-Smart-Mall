<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MALL_MEMBERSHIP and SHOP_MEMBERSHIP (ERD domain 01, decision D-008): a user
 * holding one mall- or shop-scoped role for one specific mall or shop.
 *
 * The rule that the role's scope matches the membership type is enforced by
 * MembershipService. Memberships are revoked, never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_memberships', function (Blueprint $table) {
            $table->uuid('mall_membership_id')->primary();
            $table->foreignUuid('user_id')->constrained('users', 'user_id');
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('role_id')->constrained('roles', 'role_id');
            $table->enum('status', ['active', 'revoked']);
            $table->timestampTz('assigned_at');

            $table->unique(['user_id', 'mall_id', 'role_id'], 'mall_memberships_user_mall_role_unique');
            $table->index(['mall_id', 'status'], 'mall_memberships_mall_status_index');
        });

        Schema::create('shop_memberships', function (Blueprint $table) {
            $table->uuid('shop_membership_id')->primary();
            $table->foreignUuid('user_id')->constrained('users', 'user_id');
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->foreignUuid('role_id')->constrained('roles', 'role_id');
            $table->enum('status', ['active', 'revoked']);
            $table->timestampTz('assigned_at');

            $table->unique(['user_id', 'shop_id', 'role_id'], 'shop_memberships_user_shop_role_unique');
            $table->index(['shop_id', 'status'], 'shop_memberships_shop_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_memberships');
        Schema::dropIfExists('mall_memberships');
    }
};
