<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ROLE and USER_ROLE (ERD domain 01, decision D-008).
 *
 * The role catalogue is reference data the application needs to work, so the
 * five roles are inserted here rather than by a demo seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('role_id')->primary();
            $table->string('code', 50)->unique();
            $table->enum('scope_type', ['platform', 'mall', 'shop']);
            $table->string('name', 80);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users', 'user_id');
            $table->foreignUuid('role_id')->constrained('roles', 'role_id');
            $table->timestampTz('assigned_at');

            $table->primary(['user_id', 'role_id'], 'user_roles_primary');
            $table->index('role_id', 'user_roles_role_index');
        });

        DB::table('roles')->insert(array_map(fn (array $role): array => [
            'role_id' => (string) Str::uuid(),
            'code' => $role[0],
            'scope_type' => $role[1],
            'name' => $role[2],
        ], [
            ['customer', 'platform', 'Customer'],
            ['platform_admin', 'platform', 'Platform administrator'],
            ['mall_admin', 'mall', 'Mall administrator'],
            ['shop_owner', 'shop', 'Shop owner'],
            ['shop_staff', 'shop', 'Shop staff'],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
    }
};
