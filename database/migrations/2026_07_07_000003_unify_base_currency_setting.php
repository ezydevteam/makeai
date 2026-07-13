<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Currency was fragmented across three sources that could drift:
 *   - settings('default_currency')      (Admin → Settings, the intended home)
 *   - settings('pricing_currency_code') (Plans pricing — what actually drove charges)
 *   - currencies.is_default             (used by format_currency)
 *
 * Unify them onto ONE base currency. The value that was actually charging customers
 * (pricing_currency_code) wins, else the general default_currency, else USD. Then
 * align all three so they start — and stay — consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $pricing = $this->settingValue('pricing_currency_code');
        $default = $this->settingValue('default_currency');
        $base = strtoupper($pricing ?: ($default ?: 'USD'));

        settings_set('default_currency', $base, 'string', 'general');
        settings_set('pricing_currency_code', $base, 'string', 'pricing');

        if (Schema::hasTable('currencies')) {
            DB::table('currencies')->where('is_default', true)->update(['is_default' => false]);
            DB::table('currencies')->where('code', $base)->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        // Irreversible: the pre-unification split state is not worth restoring.
    }

    private function settingValue(string $key): ?string
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return filled($value) ? (string) $value : null;
    }
};
