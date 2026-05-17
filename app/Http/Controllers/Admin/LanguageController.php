<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LanguageController extends Controller
{
    /**
     * List all languages.
     */
    public function index()
    {
        return Inertia::render('Admin/Localization/Languages', [
            'languages' => Language::orderBy('is_default', 'desc')->get(),
        ]);
    }

    /**
     * Store a new language.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:10|unique:languages,code',
            'flag' => 'nullable|string',
            'is_rtl' => 'required|boolean',
        ]);

        $lang = Language::create($request->all());

        // Sync existing keys to this new language
        $keys = Translation::distinct()->pluck('key');
        foreach ($keys as $key) {
            Translation::create([
                'language_id' => $lang->id,
                'key' => $key,
                'value' => $key,
            ]);
        }

        TranslationService::clearCache($lang->code);

        return back()->with('success', 'Language created successfully.');
    }

    /**
     * Update language details.
     */
    public function update(Request $request, Language $language)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'flag' => 'nullable|string',
            'is_rtl' => 'required|boolean',
            'is_active' => 'required|boolean',
        ]);

        $language->update($request->only(['name', 'flag', 'is_rtl', 'is_active']));

        TranslationService::clearCache($language->code);

        return back()->with('success', 'Language updated successfully.');
    }

    /**
     * Set a language as default.
     */
    public function setDefault(Language $language)
    {
        Language::where('is_default', true)->update(['is_default' => false]);
        $language->update(['is_default' => true, 'is_active' => true]);

        return back()->with('success', "{$language->name} is now the default language.");
    }

    /**
     * Delete a language.
     */
    public function destroy(Language $language)
    {
        if ($language->is_default) {
            return back()->with('error', 'Cannot delete the default language.');
        }

        $code = $language->code;
        $language->delete();
        TranslationService::clearCache($code);

        return back()->with('success', 'Language deleted successfully.');
    }
}
