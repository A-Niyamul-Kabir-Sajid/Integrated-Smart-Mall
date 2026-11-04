<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MALL (ERD domain 02): the boundary used for directory, map and permission ownership.
 *
 * See DOCS/references/erd/DOC/05-modules/02-mall-shop-directory/DATA-DICTIONARY.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('malls', function (Blueprint $table) {
            $table->uuid('mall_id')->primary();
            $table->string('slug', 100)->unique();
            $table->string('name', 160);
            $table->text('address');
            $table->string('city', 100);
            $table->string('timezone', 60);
            $table->enum('status', ['draft', 'active', 'archived']);
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('malls');
    }
};
