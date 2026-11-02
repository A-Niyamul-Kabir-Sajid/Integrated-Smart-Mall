<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SHOP_CATEGORY (ERD domain 02): shop classification such as food, clothing or electronics.
 *
 * The parent hierarchy must stay acyclic; that rule is enforced by the directory services.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_categories', function (Blueprint $table) {
            $table->uuid('shop_category_id')->primary();
            $table->foreignUuid('parent_category_id')->nullable()->constrained('shop_categories', 'shop_category_id');
            $table->string('slug', 80)->unique();
            $table->string('name', 100);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_categories');
    }
};
