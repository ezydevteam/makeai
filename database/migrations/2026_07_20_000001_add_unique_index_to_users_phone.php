<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Composite so the same *national* number is allowed to recur across different
    // countries (users.phone stores the national number without its dial code, paired
    // with phone_country). The pair identifies one real subscriber. A unique index
    // treats NULL as distinct, so the column stays nullable and any number of users
    // without a phone coexist — the "Require Phone Number" rule is enforced in the
    // app layer (see phone_requirement_met()), never here.
    private string $indexName = 'users_phone_phone_country_unique';

    public function up(): void
    {
        if (Schema::hasIndex('users', $this->indexName)) {
            return;
        }

        // An existing install could already hold two users sharing a
        // (phone, phone_country) pair; the index build would then fail mid-migration.
        // Detect that first and abort with an actionable message.
        $duplicates = DB::table('users')
            ->select('phone', 'phone_country')
            ->whereNotNull('phone')
            ->whereNotNull('phone_country')
            ->groupBy('phone', 'phone_country')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                "Cannot add a unique index on users(phone, phone_country): {$duplicates->count()} number(s) "
                .'are shared by more than one user. Resolve the duplicate phone numbers, then re-run the migration.'
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['phone', 'phone_country'], $this->indexName);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('users', $this->indexName)) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique($this->indexName);
        });
    }
};
