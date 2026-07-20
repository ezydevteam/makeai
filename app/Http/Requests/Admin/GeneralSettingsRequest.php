<?php

namespace App\Http\Requests\Admin;

use App\Support\CurrencyCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check()
            && auth('admin')->user()->hasAnyPermission(['settings.general', 'settings.manage']);
    }

    public function rules(): array
    {
        return [
            'site_name'           => ['required', 'string', 'max:120'],
            'site_tagline'        => ['nullable', 'string', 'max:200'],
            'site_description'    => ['nullable', 'string', 'max:500'],
            'site_support_email'  => ['nullable', 'email', 'max:255'],
            'site_support_url'    => ['nullable', 'url', 'max:255'],
            'site_terms_url'      => ['nullable', 'url', 'max:255'],
            'site_privacy_url'    => ['nullable', 'url', 'max:255'],

            'site_url'           => ['nullable', 'url', 'max:255'],
            // NOTE: default_language is intentionally NOT a settings key. The default
            // language lives in the languages.is_default column (single source of
            // truth); the controller translates this field into a column flip.
            'default_language'   => ['sometimes', 'string', 'max:20', Rule::exists('languages', 'code')],
            // Validated against the CATALOG, not the `currencies` table. The picker offers
            // all 58 catalog currencies, but only four are seeded as rows — so requiring an
            // existing row rejected a perfectly valid base currency like INR or BRL, and
            // the store was effectively locked to USD/EUR/GBP/BDT. The controller creates
            // the row from catalog metadata straight after validation; with an exists()
            // rule that code could never be reached.
            'default_currency'   => ['required', 'string', 'size:3', Rule::in(CurrencyCatalog::codes())],
            'currency_symbol'    => ['required', 'string', 'max:10'],
            'currency_position'  => ['required', Rule::in(['before', 'before_with_space', 'after', 'after_with_space'])],
            'currency_decimals'  => ['required', 'integer', 'min:0', 'max:4'],
            'app_timezone'       => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ];
    }
}
