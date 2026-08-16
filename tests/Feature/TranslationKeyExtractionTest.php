<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Language;
use App\Services\TranslationFileStore;
use App\Services\TranslationKeyScanner;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Translations live in lang/{code}.json, not in the database.
 *
 * The `translations` table used to be their only home, which made demo:reset's
 * migrate:fresh unrecoverable — no seeder, no export, and data.sql never carried a row —
 * and shipped every buyer six languages with no strings in any of them. These tests pin
 * both halves: that the catalogue survives a rebuild, and that the screen offers the
 * public-facing strings it used to miss entirely.
 */
class TranslationKeyExtractionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private string $langPath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);

        // Catalogues are real files, so the suite is pointed at a throwaway directory —
        // otherwise these tests would rewrite the shipped lang/*.json.
        $this->langPath = storage_path('framework/testing/lang-'.uniqid());
        File::ensureDirectoryExists($this->langPath);
        $this->app->useLangPath($this->langPath);

        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->admin = Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->langPath);

        parent::tearDown();
    }

    private function language(): Language
    {
        return Language::create([
            'code' => 'bn', 'name' => 'Bangla', 'is_rtl' => false,
            'is_default' => false, 'is_active' => true,
        ]);
    }

    /**
     * @return array<int, array{key: string, value: string}>
     */
    private function entriesFrom($response): array
    {
        return $response->viewData('page')['props']['translations']['data'];
    }

    /**
     * resources/themes was missing from the scanned paths, so every string in the
     * public-facing site — the part a buyer most wants localised — was absent from the
     * only screen that offers translations. The admin keys were all present, which is
     * exactly why it read as "translations work" for so long.
     */
    public function test_frontend_theme_strings_are_offered_for_translation(): void
    {
        $language = $this->language();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.translations.index', ['language' => $language, 'search' => 'Explore All Tools']))
            ->assertOk();

        $keys = array_column($this->entriesFrom($response), 'key');

        // A string that exists only in resources/themes, never in app/ or resources/js.
        $this->assertContains('Explore All Tools', $keys);
    }

    /**
     * Addons are the features a buyer pays extra for, and their screens were invisible to
     * the translation scan for the same reason resources/themes was — the directory simply
     * was not in the path list. ~900 strings across the Assistant, Chatbot, Knowledge Base,
     * Image Pro and FakerAI addons could never be translated.
     *
     * Skipped when the addons directory is empty, which is how a core-only install ships.
     */
    public function test_addon_strings_are_offered_for_translation(): void
    {
        $addonFiles = glob(base_path('addons/*/addon.json')) ?: [];

        if ($addonFiles === []) {
            $this->markTestSkipped('No addons installed — nothing for the scan to find.');
        }

        $language = $this->language();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.translations.index', ['language' => $language, 'search' => 'Close assistant']))
            ->assertOk();

        $keys = array_column($this->entriesFrom($response), 'key');

        // A string that exists only inside addons/, never in app/ or resources/.
        $this->assertContains('Close assistant', $keys);
    }

    /**
     * Addon menu labels, setting labels and permission names are declared as data inside
     * addons/{slug}/addon.json — not as translate() calls — so no regex over source files
     * can reach them. Adding addons/ to the file scan was not enough on its own: the admin
     * sidebar still rendered its addon entries in English because the labels were never
     * offered for translation in the first place.
     */
    public function test_addon_manifest_labels_are_offered_for_translation(): void
    {
        $manifests = glob(base_path('addons/*/addon.json')) ?: [];

        if ($manifests === []) {
            $this->markTestSkipped('No addons installed — no manifests to scan.');
        }

        // Assert on EVERY declared label rather than a sample. Picking one risks the test
        // quietly going vacuous the day someone happens to add a t() call with the same
        // wording somewhere in the source — the scan would then find it either way.
        $labels = [];

        foreach ($manifests as $path) {
            $manifest = json_decode((string) file_get_contents($path), true);

            foreach ($manifest['admin_menu'] ?? [] as $entry) {
                if (isset($entry['label']) && is_string($entry['label'])) {
                    $labels[] = trim($entry['label']);
                }
            }

            foreach ($manifest['settings'] ?? [] as $entry) {
                if (isset($entry['label']) && is_string($entry['label'])) {
                    $labels[] = trim($entry['label']);
                }
            }
        }

        $labels = array_values(array_unique($labels));

        $this->assertNotEmpty($labels, 'No addon declares menu or setting labels to test against.');

        $found = TranslationKeyScanner::scan();
        $missing = array_values(array_diff($labels, $found));

        $this->assertSame(
            [],
            $missing,
            count($missing) . ' addon manifest label(s) are not offered for translation, e.g. '
                . implode(', ', array_slice($missing, 0, 5))
        );
    }

    /**
     * Addon product names are brand names and must read identically in every language —
     * the admin sidebar should agree with the marketplace listing the buyer purchased from.
     *
     * Blocking them from the scan is what achieves that: translate() falls back to the key
     * when the catalogue has no entry, so an unoffered string renders as its English source.
     * Several are wrapped in translate() at their call sites (homepage-provider labels, the
     * assistant header), so excluding the manifest field alone was not enough.
     */
    public function test_addon_brand_names_are_never_offered_for_translation(): void
    {
        $brands = TranslationKeyScanner::brandNames();

        if ($brands === []) {
            $this->markTestSkipped('No addons installed — no brand names to protect.');
        }

        $found = TranslationKeyScanner::scan();
        $leaked = array_values(array_intersect($brands, $found));

        $this->assertSame([], $leaked, 'Addon brand name(s) offered for translation: ' . implode(', ', $leaked));

        // And the round trip: an unoffered string still renders, as English.
        $language = $this->language();
        TranslationFileStore::merge($language->code, ['Explore All Tools' => 'সব টুল দেখুন']);
        TranslationService::clearCache($language->code);
        $this->app->setLocale($language->code);

        $this->assertSame($brands[0], translate($brands[0]));
    }

    /**
     * The bug that cost a full set of translations: they lived only in the `translations`
     * table, and demo:reset runs migrate:fresh.
     */
    public function test_translations_survive_a_database_rebuild(): void
    {
        $language = $this->language();

        $this->assertTrue(
            TranslationFileStore::merge($language->code, ['Explore All Tools' => 'সব টুল দেখুন']),
            'Writing the catalogue file failed.'
        );

        // Stand-in for migrate:fresh, which cannot run inside RefreshDatabase's
        // transaction on sqlite. Emptying every table the feature could lean on is the
        // same assertion and a stricter one: rendering a translation must not touch the
        // database at all.
        Language::query()->delete();

        TranslationService::clearCache($language->code);
        $this->app->setLocale($language->code);

        $this->assertSame('সব টুল দেখুন', translate('Explore All Tools'));
    }

    /**
     * Saving through the admin screen has to reach the file — there is nowhere else for it
     * to go now, and a save that silently did nothing is how this went unnoticed before.
     */
    public function test_saving_a_translation_writes_the_catalogue_file(): void
    {
        $language = $this->language();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.translations.update', $language), [
                'key' => 'Explore All Tools',
                'value' => 'সব টুল দেখুন',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Explore All Tools' => 'সব টুল দেখুন'],
            TranslationFileStore::get($language->code)
        );
    }

    public function test_bulk_save_writes_every_entry(): void
    {
        $language = $this->language();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.translations.bulk_update', $language), [
                'translations' => [
                    ['key' => 'Explore All Tools', 'value' => 'সব টুল দেখুন'],
                    ['key' => 'No tools match your search', 'value' => 'কোনো টুল মেলেনি'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([
            'Explore All Tools' => 'সব টুল দেখুন',
            'No tools match your search' => 'কোনো টুল মেলেনি',
        ], TranslationFileStore::get($language->code));
    }

    /**
     * The screen reads straight from the catalogue, so a file edited outside the UI —
     * a direct edit, or a colleague's commit — cannot go stale. The previous design kept
     * a database index alongside the file and served the old value, which meant saving
     * from that screen wrote it straight back over the edit.
     */
    public function test_the_screen_reflects_a_catalogue_edited_outside_the_ui(): void
    {
        $language = $this->language();

        TranslationFileStore::merge($language->code, ['Explore All Tools' => 'EDITED-IN-FILE']);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.translations.index', ['language' => $language, 'search' => 'Explore All Tools']))
            ->assertOk();

        $entry = collect($this->entriesFrom($response))->firstWhere('key', 'Explore All Tools');

        $this->assertSame('EDITED-IN-FILE', $entry['value']);
    }

    /**
     * An entry equal to its key carries no information — every consumer falls back to the
     * key — and on a mostly-untranslated language that would be thousands of wasted lines
     * in a file meant to be read and diffed. Clearing a field reverts to the source string.
     */
    public function test_identity_and_cleared_entries_are_not_stored(): void
    {
        $language = $this->language();

        TranslationFileStore::merge($language->code, [
            'Explore All Tools' => 'সব টুল দেখুন',
            'Save' => 'Save',
            'Cancel' => '',
        ]);

        $this->assertSame(['Explore All Tools' => 'সব টুল দেখুন'], TranslationFileStore::get($language->code));
    }

    /**
     * A catalogue whose source string no longer exists is still shown, so a reworded
     * string does not silently discard work someone paid for.
     */
    public function test_catalogue_only_keys_are_still_listed(): void
    {
        $language = $this->language();

        TranslationFileStore::merge($language->code, ['A string no longer in the source' => 'অনুবাদ']);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.translations.index', ['language' => $language, 'search' => 'no longer in the source']))
            ->assertOk();

        $this->assertSame('অনুবাদ', $this->entriesFrom($response)[0]['value']);
    }

    /**
     * Malformed JSON must degrade to "no translations" rather than take the site down —
     * every consumer already falls back to the source string.
     */
    public function test_a_corrupt_catalogue_does_not_break_rendering(): void
    {
        $language = $this->language();

        File::put($this->langPath.'/'.$language->code.'.json', '{"broken": ');

        TranslationService::clearCache($language->code);
        $this->app->setLocale($language->code);

        $this->assertSame('Explore All Tools', translate('Explore All Tools'));
    }
}
