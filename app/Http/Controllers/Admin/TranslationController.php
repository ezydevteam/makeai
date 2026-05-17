<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TranslationController extends Controller
{
    /**
     * Show translations for a specific language.
     */
    public function index(Request $request, Language $language)
    {
        $query = Translation::where('language_id', $language->id);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('key', 'like', "%{$request->search}%")
                    ->orWhere('value', 'like', "%{$request->search}%");
            });
        }

        $translations = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/Localization/Translations', [
            'language' => $language,
            'translations' => $translations,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Update a single translation.
     */
    public function update(Request $request, Translation $translation)
    {
        $request->validate(['value' => 'required|string']);

        $translation->update(['value' => $request->value]);

        TranslationService::clearCache($translation->language->code);

        return back()->with('success', 'Translation updated.');
    }

    /**
     * AI Auto-Translate a single string.
     */
    public function aiTranslate(Request $request, Translation $translation)
    {
        $targetLang = $translation->language->name;
        $text = $translation->key;

        $prompt = "Translate the following text into {$targetLang}. Provide ONLY the translated text, no explanation or quotes.\n\nText: {$text}";

        try {
            $user = User::first(); // System proxy user for admin tasks
            if (! $user) {
                throw new \Exception('No user found to process AI request.');
            }

            $aiService = app(AiService::class);
            $result = $aiService->complete($user, $prompt, null, 'openai', 'gpt-4o-mini');
            $response = $result['content'];

            $translation->update(['value' => trim($response)]);
            TranslationService::clearCache($translation->language->code);

            return back()->with('success', 'AI Translation successful.');
        } catch (\Exception $e) {
            return back()->with('error', 'AI Translation failed: '.$e->getMessage());
        }
    }

    /**
     * Bulk AI Auto-Translate.
     */
    public function aiTranslateAll(Language $language)
    {
        $missing = Translation::where('language_id', $language->id)
            ->whereRaw('`key` = `value`')
            ->limit(20) // process in small batches to avoid timeouts
            ->get();

        if ($missing->isEmpty()) {
            return back()->with('info', 'No translations needed processing.');
        }

        $targetLang = $language->name;
        $texts = $missing->pluck('key')->toArray();
        $jsonTexts = json_encode($texts);

        $prompt = "Translate this JSON array of strings into {$targetLang}. Return ONLY a JSON array of translations in the same order.\n\nInput: {$jsonTexts}";

        try {
            $user = User::first();
            if (! $user) {
                throw new \Exception('No user found.');
            }

            $aiService = app(AiService::class);
            $result = $aiService->complete($user, $prompt, null, 'openai', 'gpt-4o-mini');
            $response = $result['content'];

            $translated = json_decode($response, true);

            if (is_array($translated) && count($translated) === count($texts)) {
                foreach ($missing as $index => $item) {
                    $item->update(['value' => $translated[$index]]);
                }
                TranslationService::clearCache($language->code);

                return back()->with('success', 'Batch AI Translation successful.');
            }

            return back()->with('error', 'AI returned invalid format.');
        } catch (\Exception $e) {
            return back()->with('error', 'AI Batch Translation failed.');
        }
    }
}
