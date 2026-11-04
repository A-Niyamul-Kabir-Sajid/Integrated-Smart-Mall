<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * USER_PREFERENCE (ERD domain 07, owned by FavouritesPreferences). A missing
 * row means application defaults.
 *
 * notify_by_email and notify_by_sms extend the ERD for the notification
 * preferences Jira ISM-158 requires (decision D-010).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users', 'user_id')->cascadeOnDelete();
            $table->foreignUuid('default_mall_id')->nullable()->constrained('malls', 'mall_id')->nullOnDelete();
            $table->string('locale', 20);
            $table->enum('route_mode', ['shortest_distance', 'fastest', 'accessible_distance']);
            $table->boolean('ar_preferred');
            $table->boolean('personalization_opt_in');
            $table->boolean('notify_by_email');
            $table->boolean('notify_by_sms');
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
