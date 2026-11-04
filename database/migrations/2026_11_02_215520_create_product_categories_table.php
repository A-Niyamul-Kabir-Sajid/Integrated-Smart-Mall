<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRODUCT_CATEGORY (ERD domain 03): searchable product taxonomy, distinct from
 * shop categories. The parent chain must stay acyclic (enforced by the service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->uuid('product_category_id')->primary();
            $table->uuid('parent_category_id')->nullable()->index();
            $table->string('slug', 80)->unique();
            $table->string('name', 100);
        });

        // A self-reference needs the primary key to exist first; PostgreSQL rejects it inside the create.
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreign('parent_category_id')->references('product_category_id')->on('product_categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
