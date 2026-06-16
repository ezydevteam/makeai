<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $articleCreatorIds = DB::table('kb_articles')
                ->whereNotNull('created_by')
                ->pluck('created_by')
                ->unique()
                ->values();

            if ($articleCreatorIds->isNotEmpty()) {
                $userEmails = DB::table('users')
                    ->whereIn('id', $articleCreatorIds)
                    ->pluck('email', 'id');

                $adminIdsByEmail = DB::table('admins')
                    ->whereIn('email', $userEmails->values())
                    ->pluck('id', 'email');

                foreach ($userEmails as $userId => $email) {
                    $adminId = $adminIdsByEmail->get($email);

                    DB::table('kb_articles')
                        ->where('created_by', $userId)
                        ->update(['created_by' => $adminId]);
                }
            }
        });

        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $articleCreatorIds = DB::table('kb_articles')
                ->whereNotNull('created_by')
                ->pluck('created_by')
                ->unique()
                ->values();

            if ($articleCreatorIds->isNotEmpty()) {
                $adminEmails = DB::table('admins')
                    ->whereIn('id', $articleCreatorIds)
                    ->pluck('email', 'id');

                $userIdsByEmail = DB::table('users')
                    ->whereIn('email', $adminEmails->values())
                    ->pluck('id', 'email');

                foreach ($adminEmails as $adminId => $email) {
                    $userId = $userIdsByEmail->get($email);

                    DB::table('kb_articles')
                        ->where('created_by', $adminId)
                        ->update(['created_by' => $userId]);
                }
            }
        });

        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
