<?php

use App\Support\CurrencyCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-localized pricing (Phase 3) converts the base price into the visitor's
 * currency. For an UNDETECTED visitor the fallback country decides that currency —
 * and a stale seeded `default_pricing_country = US` made a non-USD store (e.g. BDT)
 * display its own prices in USD by default.
 *
 * Align the default pricing country with the store's base-currency country so the
 * default storefront view shows the base currency. Genuinely GeoIP-detected foreign
 * visitors still localize.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $country = CurrencyCatalog::country(base_currency());

        if ($country) {
            settings_set('default_pricing_country', $country, 'string', 'pricing');
        }
    }

    public function down(): void
    {
        //
    }
};
