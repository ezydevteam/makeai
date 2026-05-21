<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'language' => ['required', 'string', 'exists:languages,code'],
        ]);

        $language = Language::where('code', $data['language'])
            ->where('is_active', true)
            ->firstOrFail();

        session([
            'locale' => $language->code,
            'locale_manually_selected' => true,
        ]);
        App::setLocale($language->code);

        if ($request->user()) {
            $request->user()->update(['locale' => $language->code]);
        }

        return back();
    }
}
