<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Height above its floor at which a point's marker or a QR code sits
     * (ISM-114). Points start at 1.5 m and QR codes at 1.4 m; administrators
     * can change it.
     */
    public function up(): void
    {
        Schema::table('map_end_points', function (Blueprint $table) {
            $table->double('height_m')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('map_end_points', function (Blueprint $table) {
            $table->dropColumn('height_m');
        });
    }
};
