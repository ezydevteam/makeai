<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class CurrencyController extends Controller
{
    /**
     * List all currencies.
     */
    public function index()
    {
        return Inertia::render('Admin/Localization/Currencies', [
            'currencies' => Currency::orderBy('is_default', 'desc')->get(),
        ]);
    }

    /**
     * Store a new currency.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:3|unique:currencies,code',
            'symbol' => 'required|string|max:10',
            'name' => 'required|string|max:100',
            'exchange_rate' => 'required|numeric|min:0',
            'decimal_places' => 'required|integer|min:0|max:5',
        ]);

        Currency::create($request->all());

        return back()->with('success', translate('Currency added successfully.'));
    }

    /**
     * Update currency details.
     */
    public function update(Request $request, Currency $currency)
    {
        $request->validate([
            'symbol' => 'required|string|max:10',
            'name' => 'required|string|max:100',
            'exchange_rate' => 'required|numeric|min:0',
            'decimal_places' => 'required|integer|min:0|max:5',
            'is_active' => 'required|boolean',
        ]);

        $currency->update($request->only(['symbol', 'name', 'exchange_rate', 'decimal_places', 'is_active']));

        return back()->with('success', translate('Currency updated successfully.'));
    }

    /**
     * Set a currency as default.
     */
    public function setDefault(Currency $currency)
    {
        Currency::where('is_default', true)->update(['is_default' => false]);
        $currency->update(['is_default' => true, 'is_active' => true, 'exchange_rate' => 1.000000]);

        return back()->with('success', translate(':currency is now the default currency.', ['currency' => $currency->code]));
    }

    /**
     * Sync exchange rates via external API.
     */
    public function syncRates()
    {
        $base = Currency::getDefault()?->code ?? 'USD';

        try {
            // Using a free, no-auth API for demonstration if possible,
            // but usually we need an API key. We'll check settings.
            $apiKey = settings('exchange_rate_api_key');
            if (! $apiKey) {
                return back()->with('error', translate('Please configure Exchange Rate API Key in settings first.'));
            }

            $response = Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/{$base}");

            if ($response->successful()) {
                $rates = $response->json('conversion_rates');
                foreach ($rates as $code => $rate) {
                    Currency::where('code', $code)->update(['exchange_rate' => $rate]);
                }

                return back()->with('success', translate('Exchange rates updated successfully.'));
            }

            return back()->with('error', translate('Failed to fetch exchange rates.'));
        } catch (\Exception $e) {
            return back()->with('error', translate('Exchange rate sync failed: :message', ['message' => $e->getMessage()]));
        }
    }

    /**
     * Delete a currency.
     */
    public function destroy(Currency $currency)
    {
        if ($currency->is_default) {
            return back()->with('error', translate('Cannot delete the default currency.'));
        }

        $currency->delete();

        return back()->with('success', translate('Currency deleted successfully.'));
    }
}
