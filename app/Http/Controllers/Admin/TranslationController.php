<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TranslationBulkUpdateRequest;
use App\Http\Requests\Admin\TranslationUpdateRequest;
use App\Models\Language;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\TranslationFileStore;
use App\Services\TranslationKeyScanner;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

/**
 * Editing screen for the per-language catalogues in lang/{code}.json.
 *
 * There is no `translations` table any more. It used to hold a row per key per language —
 * roughly 37,000 rows of pure duplication once six languages existed — and being the only
 * home for translated text made it fatal: demo:reset runs migrate:fresh, which erased every
 * translation with no seeder, export or file to rebuild from, and a buyer's package shipped
 * with six languages and no strings in any of them.
 *
 * The catalogue files are now the single source of truth. This screen reads the key list
 * from the source scan (cached) unioned with whatever the catalogue already holds, and
 * paginates it in memory, so nothing can drift out of sync with what the site renders.
 */
class TranslationController extends Controller
{
    private const PER_PAGE = 50;

    /**
     * The source scan walks ~800 files, so its result is cached rather than repeated on
     * every page of every language. `translations:sync` clears it, as does any change to
     * a catalogue, so a new string appears without waiting the day out.
     */
    private const KEY_CACHE = 'makeai:translation-source-keys';

    public function index(Request $request, Language $language)
    {
        $catalogue = TranslationFileStore::get($language->code);

        $entries = $this->keyUniverse($catalogue)
            ->map(fn (string $key): array => [
                'key' => $key,
                // Absent means untranslated: every consumer falls back to the source
                // string, and the field is shown pre-filled with it for editing.
                'value' => $catalogue[$key] ?? $key,
                'translated' => isset($catalogue[$key]),
            ]);

        if ($search = trim((string) $request->query('search', ''))) {
            $needle = mb_strtolower($search);

            $entries = $entries->filter(
                fn (array $entry): bool => str_contains(mb_strtolower($entry['key']), $needle)
                    || str_contains(mb_strtolower($entry['value']), $needle)
            );
        }

        $entries = $entries->values();
        $page = LengthAwarePaginator::resolveCurrentPage();

        $paginator = new LengthAwarePaginator(
            $entries->forPage($page, self::PER_PAGE)->values(),
            $entries->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Admin/Localization/Translations', [
            'language' => $language,
            'translations' => $paginator,
            'filters' => $request->only(['search']),
            // Surfaced so a read-only lang/ is reported as the permissions problem it is,
            // rather than as edits that appear to save and are gone on the next request.
            'canWrite' => TranslationFileStore::isWritable(),
        ]);
    }

    /**
     * Every key worth showing: the strings the product actually contains, plus anything the
     * catalogue already translates. Catalogue keys are kept even when the scan no longer
     * finds them — a reworded string should not silently discard work someone paid for.
     */
    private function keyUniverse(array $catalogue): Collection
    {
        return collect($this->sourceKeys())
            ->merge(array_keys($catalogue))
            ->unique()
            ->filter(fn (string $key): bool => $this->shouldIncludeTranslationKey($key))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return list<string>
     */
    private function sourceKeys(): array
    {
        $cached = Cache::get(self::KEY_CACHE);

        if (is_array($cached)) {
            return $cached;
        }

        $keys = $this->extractTranslationKeysFromSource();

        Cache::put(self::KEY_CACHE, $keys, now()->addDay());

        return $keys;
    }

    public static function forgetKeyCache(): void
    {
        Cache::forget(self::KEY_CACHE);
    }

    private function extractTranslationKeysFromSource(): array
    {
        return TranslationKeyScanner::scan();
    }

    private function shouldIncludeTranslationKey(string $value): bool
    {
        return TranslationKeyScanner::isTranslatable($value);
    }

    /**
     * Update a single entry.
     */
    public function update(TranslationUpdateRequest $request, Language $language)
    {
        if (! TranslationFileStore::merge($language->code, [
            $request->validated('key') => (string) $request->validated('value'),
        ])) {
            return back()->with('error', $this->unwritableMessage($language));
        }

        return back()->with('success', translate('Translation updated.'));
    }

    /**
     * Update every changed entry on the current page.
     */
    public function bulkUpdate(TranslationBulkUpdateRequest $request, Language $language)
    {
        $pairs = collect($request->validated('translations'))
            ->mapWithKeys(fn (array $item): array => [$item['key'] => (string) $item['value']])
            ->all();

        if (! TranslationFileStore::merge($language->code, $pairs)) {
            return back()->with('error', $this->unwritableMessage($language));
        }

        return back()->with('success', translate(':count translations updated.', ['count' => count($pairs)]));
    }

    private function unwritableMessage(Language $language): string
    {
        return translate(
            'Could not write lang/:locale.json — check that the lang directory is writable.',
            ['locale' => $language->code]
        );
    }

    /**
     * AI auto-translate a single string. Returns the suggestion for the editor to save —
     * nothing is written here.
     */
    public function aiTranslate(Request $request, Language $language)
    {
        $key = (string) $request->input('key');

        if ($key === '') {
            return response()->json(['success' => false, 'message' => translate('No text to translate.')], 422);
        }

        $prompt = "Translate the following text into {$language->name}. Provide ONLY the translated text, no explanation or quotes. IMPORTANT: Words starting with a colon (like :app, :count, :message) are placeholders and MUST be kept exactly as-is.\n\nText: {$key}";

        try {
            $user = User::internalAi(); // Non-billable system user for admin AI tasks

            $result = app(AiService::class)->complete($user, $prompt);

            return response()->json([
                'success' => true,
                'key' => $key,
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
     * AI auto-translate a batch of still-untranslated strings.
     */
    public function aiTranslateAll(Language $language)
    {
        $catalogue = TranslationFileStore::get($language->code);

        $missing = $this->keyUniverse($catalogue)
            ->reject(fn (string $key): bool => isset($catalogue[$key]))
            // A key that is nothing but a placeholder has no words to translate.
            ->reject(fn (string $key): bool => preg_match('/^:[a-z_][a-z0-9_]*$/i', $key) === 1)
            ->take(20) // small batches, so a slow provider cannot time the request out
            ->values();

        if ($missing->isEmpty()) {
            return response()->json([
                'success' => false,
                'empty' => true,
                'message' => translate('No translations needed processing.'),
            ]);
        }

        $jsonTexts = json_encode($missing->all(), JSON_UNESCAPED_UNICODE);

        $prompt = "Translate this JSON array of strings into {$language->name}. Return ONLY a JSON array of translations in the same order. IMPORTANT: Words starting with a colon (like :app, :count, :message) are placeholders and MUST be kept exactly as-is.\n\nInput: {$jsonTexts}";

        try {
            $user = User::internalAi(); // Non-billable system user for admin AI tasks

            $result = app(AiService::class)->complete($user, $prompt);
            $translated = json_decode($result->content, true);

            if (! is_array($translated) || count($translated) !== $missing->count()) {
                return response()->json([
                    'success' => false,
                    'message' => translate('AI returned invalid format.'),
                ], 500);
            }

            $items = [];

            foreach ($missing as $index => $key) {
                $items[] = ['key' => $key, 'value' => (string) $translated[$index]];
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
