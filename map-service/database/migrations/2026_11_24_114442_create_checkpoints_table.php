<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QR checkpoints of a mall (ERD QR_CHECKPOINT, ISM-61). A checkpoint keeps
     * its random public slug across map versions; the printed QR code carries
     * only that slug. Where it hangs in a given map is map_checkpoint_anchors.
     */
    public function up(): void
    {
        Schema::create('checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('mall_ref', 100);
            $table->string('checkpoint_code', 60);
            $table->string('public_slug', 24)->unique();
            $table->string('label', 160)->nullable();
            $table->string('status', 10)->default('active');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['mall_ref', 'checkpoint_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkpoints');
    }
};
