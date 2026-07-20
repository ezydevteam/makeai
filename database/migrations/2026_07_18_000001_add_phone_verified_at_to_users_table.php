<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded so it is a no-op on fresh installs where the column may later
        // be folded into the base users migration, and safe to run on existing
        // databases that predate phone verification.
        if (Schema::hasColumn('users', 'phone_verified_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            // Set once the user confirms an SMS OTP sent to `phone`; reset to null
            // whenever phone/phone_country changes so re-verification is required.
            // No ->after() so the add doesn't depend on sibling-column ordering,
            // which drifts across this project's squashed/legacy schemas.
            $table->timestamp('phone_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'phone_verified_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone_verified_at');
        });
    }
};
