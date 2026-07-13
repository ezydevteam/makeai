<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'credit_price_per_unit',
                'value' => '0.01',
                'type' => 'string',
                'group' => 'billing',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'credit_topup_minimum',
                'value' => '1.00',
                'type' => 'string',
                'group' => 'billing',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'credit_topup_quick_amounts',
                'value' => json_encode([5, 10, 25, 50, 100]),
                'type' => 'json',
                'group' => 'billing',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'credit_topup_bonus_tiers',
                'value' => json_encode([
                    ['min_amount' => 50, 'bonus_percent' => 10],
                    ['min_amount' => 100, 'bonus_percent' => 20],
                ]),
                'type' => 'json',
                'group' => 'billing',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'credit_topup_enabled',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'billing',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'credit_price_per_unit',
            'credit_topup_minimum',
            'credit_topup_quick_amounts',
            'credit_topup_bonus_tiers',
            'credit_topup_enabled',
        ])->delete();
    }
};
