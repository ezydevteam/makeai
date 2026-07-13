<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

/**
 * Rename user_api_keys → user_byok to reflect its purpose (bring-your-own-key
 * provider credentials), distinct from the platform's own ai_keys. The UserApiKey
 * model now points at the new table name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_api_keys') && ! Schema::hasTable('user_byok')) {
            Schema::rename('user_api_keys', 'user_byok');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_byok') && ! Schema::hasTable('user_api_keys')) {
            Schema::rename('user_byok', 'user_api_keys');
        }
    }
};
