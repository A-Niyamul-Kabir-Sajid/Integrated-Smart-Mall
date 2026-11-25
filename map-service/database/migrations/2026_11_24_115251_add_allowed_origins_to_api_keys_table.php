<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Web origins allowed to show this key's embedded map in an iframe
     * (ISM-64), e.g. ["http://127.0.0.1:8000"].
     */
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->json('allowed_origins')->nullable()->after('abilities');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('allowed_origins');
        });
    }
};
