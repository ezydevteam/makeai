<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TranslationBulkUpdateRequest;
use App\Http\Requests\Admin\TranslationUpdateRequest;
use App\Models\Language;
use App\Models\Translation;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function update(TranslationUpdateRequest $request, Translation $translation)
    {
        $translation->update(['value' => $request->validated('value')]);

        TranslationService::clearCache($translation->language->code);

        return back()->with('success', translate('Translation updated.'));
    }

    /**
     * Update multiple translations from the current page.
     */
    public function bulkUpdate(TranslationBulkUpdateRequest $request, Language $language)
    {
        $payload = collect($request->validated('translations'));
        $translations = Translation::where('language_id', $language->id)
            ->whereIn('id', $payload->pluck('id'))
            ->get()
            ->keyBy('id');

        if ($translations->count() !== $payload->count()) {
            return back()->with('error', translate('Some translations do not belong to this language.'));
        }

        DB::transaction(function () use ($payload, $translations) {
            $payload->each(function (array $item) use ($translations) {
                $translations->get($item['id'])->update(['value' => $item['value']]);
            });
        });

        TranslationService::clearCache($language->code);

        return back()->with('success', translate(':count translations updated.', ['count' => $payload->count()]));
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
                throw new \Exception(translate('No user found to process AI request.'));
            }

            $aiService = app(AiService::class);
            $result = $aiService->complete($user, $prompt, null, 'openai', 'gpt-4o-mini');
            $response = $result['content'];

            $translation->update(['value' => trim($response)]);
            TranslationService::clearCache($translation->language->code);

            return back()->with('success', translate('AI Translation successful.'));
        } catch (\Exception $e) {
            return back()->with('error', translate('AI Translation failed: :message', ['message' => $e->getMessage()]));
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
            return back()->with('info', translate('No translations needed processing.'));
        }

        $targetLang = $language->name;
        $texts = $missing->pluck('key')->toArray();
        $jsonTexts = json_encode($texts);

        $prompt = "Translate this JSON array of strings into {$targetLang}. Return ONLY a JSON array of translations in the same order.\n\nInput: {$jsonTexts}";

        try {
            $user = User::first();
            if (! $user) {
                throw new \Exception(translate('No user found.'));
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

                return back()->with('success', translate('Batch AI Translation successful.'));
            }

            return back()->with('error', translate('AI returned invalid format.'));
        } catch (\Exception $e) {
            return back()->with('error', translate('AI Batch Translation failed.'));
        }
    }
}
