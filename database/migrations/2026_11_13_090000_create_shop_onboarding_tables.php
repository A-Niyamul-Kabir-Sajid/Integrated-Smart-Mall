<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop onboarding and staff access (Jira ISM-101, decision D-014), owned by
 * ShopOwnerOperations and outside the ERD:
 *
 * - shop_applications: the request to open a shop and the mall's decision. The
 *   shop itself is a draft SHOP row; approval makes it active.
 * - shop_staff_invitations: a pending offer of staff work, identified only by a
 *   hash of its secret.
 * - shop_staff_functions: the work function (manager, catalogue editor,
 *   cashier) of one staff SHOP_MEMBERSHIP, which decides the workspace areas
 *   the member may use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_applications', function (Blueprint $table) {
            $table->uuid('shop_application_id')->primary();
            $table->foreignUuid('shop_id')->unique()->constrained('shops', 'shop_id');
            $table->foreignUuid('mall_id')->constrained('malls', 'mall_id');
            $table->foreignUuid('applicant_user_id')->constrained('users', 'user_id');
            $table->enum('status', ['pending', 'approved', 'rejected']);
            $table->text('message')->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignUuid('decided_by_user_id')->nullable()->constrained('users', 'user_id');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->index(['mall_id', 'status'], 'shop_applications_mall_status_index');
            $table->index(['applicant_user_id', 'status'], 'shop_applications_applicant_status_index');
        });

        Schema::create('shop_staff_invitations', function (Blueprint $table) {
            $table->uuid('shop_staff_invitation_id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops', 'shop_id');
            $table->string('email', 254);
            $table->enum('staff_function', ['manager', 'catalogue_editor', 'cashier']);
            $table->char('secret_hash', 64)->unique();
            $table->foreignUuid('invited_by_user_id')->constrained('users', 'user_id');
            $table->enum('status', ['pending', 'accepted', 'revoked']);
            $table->timestampTz('expires_at');
            $table->foreignUuid('accepted_by_user_id')->nullable()->constrained('users', 'user_id');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();

            $table->index(['shop_id', 'status'], 'shop_staff_invitations_shop_status_index');
            $table->index('email', 'shop_staff_invitations_email_index');
        });

        Schema::create('shop_staff_functions', function (Blueprint $table) {
            $table->foreignUuid('shop_membership_id')->primary()->constrained('shop_memberships', 'shop_membership_id');
            $table->enum('staff_function', ['manager', 'catalogue_editor', 'cashier']);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_staff_functions');
        Schema::dropIfExists('shop_staff_invitations');
        Schema::dropIfExists('shop_applications');
    }
};
