<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LanguageStoreRequest;
use App\Http\Requests\Admin\LanguageUpdateRequest;
use App\Models\Language;
use App\Services\TranslationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LanguageController extends Controller
{
    /**
     * List all languages.
     */
    public function index(\Illuminate\Http\Request $request)
    {
        $query = Language::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('date_format', 'like', "%{$search}%")
                    ->orWhere('time_format', 'like', "%{$search}%")
                    ->orWhere('number_system', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            switch ($status) {
                case 'active':
                    $query->where('is_active', true);
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
                case 'rtl':
                    $query->where('is_rtl', true);
                    break;
                case 'default':
                    $query->where('is_default', true);
                    break;
            }
        }

        return Inertia::render('Admin/Localization/Languages', [
            'manageLanguages' => $query->orderBy('is_default', 'desc')->get(),
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
            ],
        ]);
    }

    /**
     * Store a new language.
     */
    public function store(LanguageStoreRequest $request)
    {
        $this->authorizeLanguages();
        $data = $request->safe()->except('flag_file');
        $data['flag'] = $this->storeFlag($request);

        $lang = Language::create($data);

        // No key backfill: the translation screen derives its key list from the source
        // scan and lang/{code}.json, so a new language starts with everything on offer
        // and an empty catalogue. This used to insert one row per key per language —
        // ~6,000 of them, every one a copy of its own key.
        TranslationService::clearCache($lang->code);

        return back()->with('success', translate('Language created successfully.'));
    }

    /**
     * Update language details.
     */
    public function update(LanguageUpdateRequest $request, Language $language)
    {
        $this->authorizeLanguages();
        $data = $request->safe()->except('flag_file');
        $flag = $this->storeFlag($request);

        if ($flag) {
            $this->deleteFlag($language->flag);
            $data['flag'] = $flag;
        }

        $language->forceFill($data)->save();

        TranslationService::clearCache($language->code);

        return back()->with('success', translate('Language updated successfully.'));
    }

    /**
     * Set a language as default.
     */
    public function setDefault(Language $language)
    {
        $this->authorizeLanguages();
        // Mass update bypasses model events, so the cached resolutions are dropped
        // explicitly here rather than relying on the saved() hook.
        Language::where('is_default', true)->update(['is_default' => false]);
        $language->update(['is_default' => true, 'is_active' => true]);
        Language::forgetResolutionCaches();
        session([
            'locale' => $language->code,
            'locale_manually_selected' => false,
        ]);
        app()->setLocale($language->code);

        return back()->with('success', translate(':language is now the default language.', ['language' => $language->name]));
    }

    /**
     * Delete a language.
     */
    public function destroy(Language $language)
    {
        $this->authorizeLanguages();
        if ($language->is_default) {
            return back()->with('error', translate('Cannot delete the default language.'));
        }

        $code = $language->code;
        $this->deleteFlag($language->flag);
        $language->delete();
        TranslationService::clearCache($code);

        return back()->with('success', translate('Language deleted successfully.'));
    }

    private function storeFlag(LanguageStoreRequest|LanguageUpdateRequest $request): ?string
    {
        $file = $request->file('flag_file');

        if (! $file) {
            return null;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::slug((string) $request->input('code')).'-'.Str::ulid().'.'.$extension;
        // Return the RELATIVE disk key; FlagIcon.vue resolves it with mediaUrl().
        return store_public_upload($file, 'language-flags', null, $filename);
    }

    /**
     * Delete an uploaded flag image. media_path() normalises a relative key, a legacy
     * "/storage/..." URL, or a bucket/CDN URL down to the disk key. Non-upload flags
     * (an emoji or a two-letter code) never live under language-flags/, so they are
     * ignored — as are external URLs.
     */
    private function deleteFlag(?string $flag): void
    {
        $key = media_path($flag);

        if ($key === '' || ! str_starts_with($key, 'language-flags/')) {
            return;
        }

        if (Storage::disk('public')->exists($key)) {
            Storage::disk('public')->delete($key);
        }
    }

    private function authorizeLanguages(): void
    {
        if (! auth('admin')->check()) {
            abort(403, translate('Unauthorized.'));
        }
    }
}
