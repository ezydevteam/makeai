<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_tools')
            ->where('requires_pro', true)
            ->where(function ($query) {
                $query->whereNull('access_level')
                    ->orWhere('access_level', 'inherit');
            })
            ->update([
                'access_level' => 'pro_plan',
            ]);

        DB::table('ai_tools')
            ->where('requires_pro', true)
            ->update([
                'requires_pro' => false,
            ]);
    }

    public function down(): void
    {
        // Irreversible normalization: prior tool-level pro flags are collapsed into access_level.
    }
};
