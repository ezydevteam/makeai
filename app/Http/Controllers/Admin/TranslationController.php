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
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class TranslationController extends Controller
{
    /**
     * Show translations for a specific language.
     */
    public function index(Request $request, Language $language)
    {
        $this->ensureLanguageTranslations($language);

        $query = Translation::where('language_id', $language->id);
        foreach ($this->blockedTranslationFragments() as $fragment) {
            $query->where('key', 'not like', '%' . $fragment . '%');
        }

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

    private function ensureLanguageTranslations(Language $language): void
    {
        $existingKeys = Translation::where('language_id', $language->id)->pluck('key');

        $seedKeys = Translation::query()
            ->when(
                Language::where('is_default', true)->whereKeyNot($language->id)->exists(),
                function ($query) use ($language) {
                    $defaultLanguageId = Language::where('is_default', true)
                        ->whereKeyNot($language->id)
                        ->value('id');

                    return $query->where('language_id', $defaultLanguageId);
                }
            )
            ->distinct()
            ->pluck('key');

        if ($seedKeys->isEmpty()) {
            $seedKeys = collect($this->extractTranslationKeysFromSource());
        }

        $missingKeys = $seedKeys->diff($existingKeys)->values();

        if ($missingKeys->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($language, $missingKeys) {
            $missingKeys->each(function (string $key) use ($language) {
                Translation::firstOrCreate([
                    'language_id' => $language->id,
                    'key' => $key,
                ], [
                    'value' => $key,
                ]);
            });
        });
    }

    private function extractTranslationKeysFromSource(): array
    {
        $paths = [
            app_path(),
            resource_path('js'),
            resource_path('views'),
        ];

        $patterns = [
            '/translate\(\s*\'([^\']*(?:\\\\.[^\']*)*)\'/',
            '/translate\(\s*"([^"]*(?:\\\\.[^"]*)*)"/',
            '/\$t\(\s*\'([^\']*(?:\\\\.[^\']*)*)\'/',
            '/\$t\(\s*"([^"]*(?:\\\\.[^"]*)*)"/',
            '/(?<![A-Za-z0-9_])t\(\s*\'([^\']*(?:\\\\.[^\']*)*)\'/',
            '/(?<![A-Za-z0-9_])t\(\s*"([^"]*(?:\\\\.[^"]*)*)"/',
        ];

        $keys = [];

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $extension = $file->getExtension();

                if (!in_array($extension, ['php', 'vue', 'ts', 'js', 'blade.php'], true)) {
                    continue;
                }

                $contents = File::get($file->getPathname());

                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $contents, $matches);

                    foreach ($matches[1] ?? [] as $match) {
                        $value = trim(stripslashes($match));

                        if ($value !== '' && $this->shouldIncludeTranslationKey($value)) {
                            $keys[] = $value;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private function shouldIncludeTranslationKey(string $value): bool
    {
        foreach ($this->blockedTranslationFragments() as $fragment) {
            if (str_contains(mb_strtolower($value), $fragment)) {
                return false;
            }
        }

        return true;
    }

    private function blockedTranslationFragments(): array
    {
        return [
            '— type of field',
            '- type of field',
        ];
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

        $prompt = "Translate the following text into {$targetLang}. Provide ONLY the translated text, no explanation or quotes. IMPORTANT: Words starting with a colon (like :app, :count, :message) are placeholders and MUST be kept exactly as-is.\n\nText: {$text}";

        try {
            $user = User::first(); // System proxy user for admin tasks
            if (! $user) {
                throw new \Exception(translate('No user found to process AI request.'));
            }

            $aiService = app(AiService::class);
            $result = $aiService->complete($user, $prompt);

            return response()->json([
                'success' => true,
                'id' => $translation->id,
                'value' => trim($result->content),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => translate('AI Translation failed: :message', ['message' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * Bulk AI Auto-Translate.
     */
    public function aiTranslateAll(Language $language)
    {
        $missing = Translation::where('language_id', $language->id)
            ->whereRaw('`key` = `value`')
            ->where('key', 'not regexp', ':[a-z_][a-z0-9_]*')
            ->limit(20) // process in small batches to avoid timeouts
            ->get();

        if ($missing->isEmpty()) {
            return response()->json([
                'success' => false,
                'empty' => true,
                'message' => translate('No translations needed processing.'),
            ]);
        }

        $targetLang = $language->name;
        $texts = $missing->pluck('key')->toArray();
        $jsonTexts = json_encode($texts);

        $prompt = "Translate this JSON array of strings into {$targetLang}. Return ONLY a JSON array of translations in the same order. IMPORTANT: Words starting with a colon (like :app, :count, :message) are placeholders and MUST be kept exactly as-is.\n\nInput: {$jsonTexts}";

        try {
            $user = User::first();
            if (! $user) {
                throw new \Exception(translate('No user found.'));
            }

            $aiService = app(AiService::class);
            $result = $aiService->complete($user, $prompt);
            $response = $result->content;

            $translated = json_decode($response, true);

            if (! is_array($translated) || count($translated) !== count($texts)) {
                return response()->json([
                    'success' => false,
                    'message' => translate('AI returned invalid format.'),
                ], 500);
            }

            $items = [];
            foreach ($missing as $index => $item) {
                $items[] = ['id' => $item->id, 'value' => $translated[$index]];
            }

            return response()->json([
                'success' => true,
                'items' => $items,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => translate('AI Batch Translation failed.'),
            ], 500);
        }
    }
}
