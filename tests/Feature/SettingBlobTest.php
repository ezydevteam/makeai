<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 3 of the settings refactor: cohesive groups (blog/gdpr/notifications) are stored
 * as a single json blob row per group, routed transparently by key prefix.
 */
class SettingBlobTest extends TestCase
{
    use RefreshDatabase;

    public function test_blob_key_round_trips_through_settings_helper(): void
    {
        settings_set('blog_posts_per_page', 12, 'integer', 'blog');
        settings_set('blog_show_tags_post', true, 'boolean', 'blog');
        settings_set('gdpr_banner_title', 'Cookies', 'string', 'gdpr');

        $this->assertSame(12, settings('blog_posts_per_page'));
        $this->assertSame(true, settings('blog_show_tags_post'));
        $this->assertSame('Cookies', settings('gdpr_banner_title'));
    }

    public function test_blob_group_lives_in_a_single_row(): void
    {
        settings_set('blog_posts_per_page', 12, 'integer', 'blog');
        settings_set('blog_words_per_minute', 200, 'integer', 'blog');
        settings_set('blog_default_author', 'Ada', 'string', 'blog');

        // Three keys, one physical row.
        $this->assertSame(0, Setting::where('group', 'blog')->where('key', 'not like', 'group:%')->count());
        $this->assertSame(1, Setting::where('key', 'group:blog')->count());
    }

    public function test_feature_toggles_route_to_features_blob_not_their_prefix_blob(): void
    {
        // blog_enabled / notifications_enabled share a cohesive prefix but belong to the
        // `features` group. The BLOB_GROUP_KEYS registry wins over the prefix table, so
        // they land in the features blob — not the blog/notifications blobs.
        settings_set('blog_enabled', false, 'boolean', 'features');
        settings_set('notifications_enabled', true, 'boolean', 'features');

        $this->assertSame(false, settings('blog_enabled'));
        $this->assertSame(true, settings('notifications_enabled'));
        $this->assertSame(1, Setting::where('key', 'group:features')->count());
        $this->assertSame(0, Setting::where('key', 'blog_enabled')->count());
        $this->assertSame(0, Setting::where('key', 'group:blog')->count());
        $this->assertSame(0, Setting::where('key', 'group:notifications')->count());
    }

    public function test_encrypted_blob_value_is_stored_ciphertext(): void
    {
        settings_set('notifications_reverb_app_secret', 'hunter2', 'encrypted', 'notifications');

        $raw = json_decode(Setting::where('key', 'group:notifications')->value('value'), true);
        $this->assertNotSame('hunter2', $raw['notifications_reverb_app_secret']['v']);
        $this->assertSame('encrypted', $raw['notifications_reverb_app_secret']['t']);
        $this->assertSame('hunter2', settings('notifications_reverb_app_secret'));
    }

    public function test_write_busts_blob_cache(): void
    {
        settings_set('gdpr_banner_title', 'One', 'string', 'gdpr');
        $this->assertSame('One', settings('gdpr_banner_title'));
        $this->assertTrue(Cache::has('settings:group:gdpr'));

        settings_set('gdpr_banner_title', 'Two', 'string', 'gdpr');
        $this->assertSame('Two', settings('gdpr_banner_title'));
    }

    public function test_getByGroup_returns_blob_contents(): void
    {
        settings_set('gdpr_enabled', true, 'boolean', 'gdpr');
        settings_set('gdpr_banner_title', 'Hi', 'string', 'gdpr');

        $group = Setting::getByGroup('gdpr');

        $this->assertSame(true, $group['gdpr_enabled']);
        $this->assertSame('Hi', $group['gdpr_banner_title']);
    }

    public function test_isPersisted_is_accurate_for_blob_keys(): void
    {
        $this->assertFalse(Setting::isPersisted('blog_posts_per_page'));

        settings_set('blog_posts_per_page', 9, 'integer', 'blog');

        $this->assertTrue(Setting::isPersisted('blog_posts_per_page'));
        $this->assertFalse(Setting::isPersisted('blog_never_set'));
    }

    public function test_collapse_absorbs_flat_rows_without_clobbering_blob_values(): void
    {
        // Simulate a pre-migration flat row plus an operator-changed blob value.
        Setting::create(['key' => 'blog_posts_per_page', 'value' => '9', 'type' => 'integer', 'group' => 'blog']);
        Setting::create(['key' => 'blog_default_author', 'value' => 'Seed', 'type' => 'string', 'group' => 'blog']);
        settings_set('blog_posts_per_page', 25, 'integer', 'blog'); // operator changes one → into blob

        Setting::collapseGroupToBlob('blog');

        // Operator value survives; the untouched flat row is absorbed; nothing left flat.
        $this->assertSame(25, settings('blog_posts_per_page'));
        $this->assertSame('Seed', settings('blog_default_author'));
        $this->assertSame(0, Setting::where('group', 'blog')->where('key', 'not like', 'group:%')->count());
    }

    public function test_non_routed_key_in_a_blob_group_stays_flat_through_collapse(): void
    {
        // collapseGroupToBlob only absorbs keys that actually route to the group. A key
        // with no matching prefix and no registry entry (synthetic here — no production key
        // is flat anymore) must survive collapse as a readable flat row.
        Setting::updateOrCreate(['key' => 'frontend_show_hero'], ['value' => '1', 'type' => 'boolean', 'group' => 'appearance']);
        Setting::updateOrCreate(['key' => 'legacy_unrouted_widget'], ['value' => 'keep', 'type' => 'string', 'group' => 'appearance']);

        Setting::collapseGroupToBlob('appearance');

        $this->assertSame(true, settings('frontend_show_hero'));
        $this->assertSame('keep', settings('legacy_unrouted_widget'));
        $this->assertSame(1, Setting::where('key', 'group:appearance')->count());
        $this->assertSame(1, Setting::where('key', 'legacy_unrouted_widget')->count());
    }

    public function test_sidebar_config_folds_into_appearance_blob(): void
    {
        // sidebar_config has no frontend_ prefix but is registry-routed to `appearance`,
        // so collapse absorbs it into the appearance blob (Phase 8 — the last flat row).
        Setting::updateOrCreate(['key' => 'frontend_show_hero'], ['value' => '1', 'type' => 'boolean', 'group' => 'appearance']);
        Setting::updateOrCreate(['key' => 'sidebar_config'], ['value' => '{"pos":"left"}', 'type' => 'json', 'group' => 'appearance']);

        Setting::collapseGroupToBlob('appearance');

        $this->assertSame(true, settings('frontend_show_hero'));
        $this->assertSame(['pos' => 'left'], settings('sidebar_config'));
        $this->assertSame(1, Setting::where('key', 'group:appearance')->count());
        $this->assertSame(0, Setting::where('key', 'sidebar_config')->count());
    }

    public function test_default_pricing_country_folds_into_pricing_blob(): void
    {
        // default_pricing_country has no pricing_ prefix but is registry-routed to `pricing`,
        // so collapse now absorbs it into the pricing blob (Phase 7).
        Setting::updateOrCreate(['key' => 'pricing_show_monthly'], ['value' => '1', 'type' => 'boolean', 'group' => 'pricing']);
        Setting::updateOrCreate(['key' => 'default_pricing_country'], ['value' => 'US', 'type' => 'string', 'group' => 'pricing']);

        Setting::collapseGroupToBlob('pricing');

        $this->assertSame(true, settings('pricing_show_monthly'));
        $this->assertSame('US', settings('default_pricing_country'));
        $this->assertSame(1, Setting::where('key', 'group:pricing')->count());
        $this->assertSame(0, Setting::where('key', 'default_pricing_country')->count());
    }

    public function test_newsletter_group_blobs_both_prefixes_without_mail_collision(): void
    {
        settings_set('newsletter_driver', 'mailchimp', 'string', 'newsletter');
        settings_set('newsletter_enable_popup', true, 'boolean', 'newsletter');
        settings_set('mailchimp_api_key', 'mc-secret', 'encrypted', 'newsletter');
        settings_set('mail_driver', 'smtp', 'string', 'mail'); // must NOT be swept into newsletter

        // newsletter_ and mailchimp_ both land in the newsletter blob.
        $this->assertSame('mailchimp', settings('newsletter_driver'));
        $this->assertSame(true, settings('newsletter_enable_popup'));
        $this->assertSame('mc-secret', settings('mailchimp_api_key'));
        $this->assertSame(1, Setting::where('key', 'group:newsletter')->count());
        $this->assertSame(0, Setting::where('group', 'newsletter')->where('key', 'not like', 'group:%')->count());

        // The api key is encrypted at rest inside the blob.
        $raw = json_decode(Setting::where('key', 'group:newsletter')->value('value'), true);
        $this->assertNotSame('mc-secret', $raw['mailchimp_api_key']['v']);

        // mailchimp_ did not collide with mail_: mail_driver stayed in the mail blob.
        $this->assertSame('smtp', settings('mail_driver'));
        $this->assertSame('smtp', Setting::getByGroup('mail')['mail_driver']);
        $this->assertArrayNotHasKey('mail_driver', Setting::getByGroup('newsletter'));
    }

    public function test_subscriptions_enabled_routes_to_features_regardless_of_caller_group(): void
    {
        // It's a feature toggle. Callers historically passed varying group args (license/ai),
        // but the registry pins it to `features` — the passed group is ignored on write.
        settings_set('subscriptions_enabled', true, 'boolean', 'license'); // legacy caller arg

        $this->assertSame(true, settings('subscriptions_enabled'));
        $this->assertSame(true, Setting::getByGroup('features')['subscriptions_enabled']);
        $this->assertArrayNotHasKey('subscriptions_enabled', Setting::getByGroup('general'));
        $this->assertSame(0, Setting::where('key', 'subscriptions_enabled')->count()); // no flat row
    }

    public function test_rate_limit_abuse_scalars_blob(): void
    {
        // The rl_ prefix blobs the non-matrix abuse scalars (the tier matrix is in the
        // dedicated rate_limit_rules table). There is no `security` blob group — those keys
        // were all dead seeds, removed 2026_07_17_000012.
        settings_set('rl_ai_abuse_threshold', 20, 'integer', 'rate_limits');
        settings_set('rl_ai_abuse_window', 60, 'integer', 'rate_limits');

        $this->assertSame(20, settings('rl_ai_abuse_threshold'));
        $this->assertSame(60, settings('rl_ai_abuse_window'));
        $this->assertSame(1, Setting::where('key', 'group:rate_limits')->count());
        $this->assertSame(0, Setting::where('group', 'rate_limits')->where('key', 'not like', 'group:%')->count());
    }

    public function test_registry_overrides_prefix_and_alias_routes_correctly(): void
    {
        settings_set('contact_enabled', true, 'boolean', 'features');    // registry → features blob
        settings_set('site_url', 'https://x.test', 'string', 'general'); // registry → general blob
        settings_set('site_name', 'MakeAI', 'string', 'branding');       // prefix → branding blob

        // contact_enabled routes to features (registry), not the contact prefix blob.
        $this->assertSame(true, settings('contact_enabled'));
        $this->assertSame(0, Setting::where('key', 'contact_enabled')->count());
        $this->assertSame(1, Setting::where('key', 'group:features')->count());

        // site_url routes to general (registry), not the site_ prefix (branding).
        $this->assertSame('https://x.test', settings('site_url'));
        $this->assertSame(0, Setting::where('key', 'site_url')->count());
        $this->assertSame('https://x.test', settings('app_url')); // alias app_url → site_url → general blob

        // A real branding key still follows the site_ prefix into the branding blob.
        $this->assertSame('MakeAI', settings('site_name'));
        // Legacy alias resolves before routing: app_name → site_name → branding blob.
        $this->assertSame('MakeAI', settings('app_name'));
    }

    public function test_colliding_ai_prefix_splits_between_ai_and_support_blobs(): void
    {
        // `ai_reply_suggestion` is a support toggle; `default_ai_model` is an ai default.
        // Both are registry-routed, so the ai/support ambiguity is resolved explicitly.
        settings_set('ai_reply_suggestion', true, 'boolean', 'support');
        settings_set('default_ai_model', 'claude-opus-4-8', 'string', 'ai');

        $this->assertSame(true, settings('ai_reply_suggestion'));
        $this->assertSame('claude-opus-4-8', settings('default_ai_model'));
        $this->assertSame('claude-opus-4-8', Setting::getByGroup('ai')['default_ai_model']);
        $this->assertSame(true, Setting::getByGroup('support')['ai_reply_suggestion']);
        $this->assertSame(0, Setting::where('group', 'ai')->where('key', 'not like', 'group:%')->count());
        $this->assertSame(0, Setting::where('group', 'support')->where('key', 'not like', 'group:%')->count());
    }

    public function test_corrupt_encrypted_blob_value_reads_as_null_not_throw(): void
    {
        // A legacy empty/corrupt encrypted value must not blow up a blob read.
        Setting::updateOrCreate(
            ['key' => 'group:license'],
            [
                'type' => 'json',
                'group' => 'license',
                'value' => json_encode(['license_purchase_code' => ['v' => '', 't' => 'encrypted']]),
            ]
        );

        $this->assertNull(settings('license_purchase_code'));
        $this->assertSame('fallback', settings('license_purchase_code', 'fallback'));
        // getByGroup must survive the bad row too.
        $this->assertArrayHasKey('license_purchase_code', Setting::getByGroup('license'));
    }

    public function test_social_group_blobs_encrypted_keys_and_json_together(): void
    {
        // Phase 4: the whole social group is blobbed (per-platform encrypted api keys +
        // external ids + share/display toggles), one row.
        settings_set('social_follow_api_key_facebook', 'fb-token', 'encrypted', 'social');
        settings_set('social_follow_external_id_facebook', 'fb-123', 'string', 'social');
        settings_set('social_share_networks', ['facebook', 'x'], 'json', 'social');

        $raw = json_decode(Setting::where('key', 'group:social')->value('value'), true);
        $this->assertNotSame('fb-token', $raw['social_follow_api_key_facebook']['v']); // encrypted at rest
        $this->assertSame('fb-token', settings('social_follow_api_key_facebook'));
        $this->assertSame('fb-123', settings('social_follow_external_id_facebook'));
        $this->assertSame(['facebook', 'x'], settings('social_share_networks'));
        $this->assertSame(0, Setting::where('group', 'social')->where('key', 'not like', 'group:%')->count());
    }

    public function test_open_ended_key_families_blob_by_prefix(): void
    {
        // Three families whose key sets are generated, not enumerable: external services
        // (`external_{integration}_{provider}_{field}`), per-theme config/update state
        // (`theme_{slug}_{key}`), and the ads toggles. Each must blob by prefix.
        settings_set('external_captcha_provider', 'recaptcha', 'string', 'external_apis');
        settings_set('external_captcha_recaptcha_secret_key', 'rc-secret', 'encrypted', 'external_apis');
        settings_set('theme_default_update_available', true, 'boolean', 'theme');
        settings_set('theme_default_primary_color', '#fff', 'string', 'theme');
        settings_set('ads_enabled', true, 'boolean', 'ads');
        settings_set('adsense_publisher_id', 'ca-pub-42', 'string', 'ads'); // misses `ads_` → registry

        $this->assertSame('recaptcha', settings('external_captcha_provider'));
        $this->assertSame('rc-secret', settings('external_captcha_recaptcha_secret_key'));
        $this->assertSame(true, settings('theme_default_update_available'));
        $this->assertSame('#fff', settings('theme_default_primary_color'));
        $this->assertSame(true, settings('ads_enabled'));
        $this->assertSame('ca-pub-42', settings('adsense_publisher_id'));

        // One row per group, no flat rows, and the secret is still ciphertext at rest.
        $raw = json_decode(Setting::where('key', 'group:external_apis')->value('value'), true);
        $this->assertNotSame('rc-secret', $raw['external_captcha_recaptcha_secret_key']['v']);
        foreach (['external_apis', 'theme', 'ads'] as $group) {
            $this->assertSame(1, Setting::where('key', "group:{$group}")->count());
        }
        $this->assertSame(0, Setting::where('key', 'not like', 'group:%')->count());
    }

    public function test_late_registered_stragglers_route_to_their_group(): void
    {
        // Keys that had a live writer but no routing entry, so every save left a flat row.
        settings_set('active_theme_preset', 'creative', 'string', 'appearance');
        settings_set('default_language', 'en', 'string', 'general');
        settings_set('registration_default_plan', 'none', 'string', 'pricing');
        settings_set('credits_month_last_reset', '2026-07', 'string', 'ai');
        settings_set('last_queue_worker_run', '2026-07-31 09:00:00', 'string', 'system');

        $this->assertSame('creative', Setting::getByGroup('appearance')['active_theme_preset']);
        $this->assertSame('en', Setting::getByGroup('general')['default_language']);
        $this->assertSame('none', Setting::getByGroup('pricing')['registration_default_plan']);
        $this->assertSame('2026-07', Setting::getByGroup('ai')['credits_month_last_reset']);
        $this->assertSame('2026-07-31 09:00:00', Setting::getByGroup('system')['last_queue_worker_run']);
        $this->assertSame(0, Setting::where('key', 'not like', 'group:%')->count());
    }

    public function test_app_version_routes_to_system_not_general(): void
    {
        // It was registered in BOTH groups; PHP kept the last (system) while the seeder
        // wrote group=general, so the row could never be collapsed. One entry now.
        settings_set('app_version', '2.1.0', 'string', 'general'); // legacy caller arg

        $this->assertSame('2.1.0', settings('app_version'));
        $this->assertSame('2.1.0', Setting::getByGroup('system')['app_version']);
        $this->assertArrayNotHasKey('app_version', Setting::getByGroup('general'));
        $this->assertSame(0, Setting::where('key', 'app_version')->count());
    }

    public function test_fold_sweep_absorbs_rows_whose_group_column_disagrees_with_routing(): void
    {
        // The exact shape the per-group collapse could never fix: the `group` column says
        // one thing, the key routes somewhere else. collapseGroupToBlob(column) misses it
        // from both sides; the key-routed sweep gets it.
        Setting::create(['key' => 'app_version', 'value' => '1.0.0', 'type' => 'string', 'group' => 'general']);
        Setting::create(['key' => 'tickets_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'support']);
        Setting::create(['key' => 'legacy_unrouted_widget', 'value' => 'keep', 'type' => 'string', 'group' => 'appearance']);

        Setting::collapseGroupToBlob('general');
        Setting::collapseGroupToBlob('support');
        $this->assertSame(2, Setting::whereIn('key', ['app_version', 'tickets_enabled'])->count()); // still flat

        $absorbed = Setting::foldFlatRowsIntoBlobs();

        $this->assertSame(2, $absorbed);
        $this->assertSame('1.0.0', Setting::getByGroup('system')['app_version']);
        $this->assertSame(true, Setting::getByGroup('features')['tickets_enabled']);
        $this->assertSame(0, Setting::whereIn('key', ['app_version', 'tickets_enabled'])->count());
        // A key that routes nowhere is left alone — the sweep is not a blanket delete.
        $this->assertSame('keep', settings('legacy_unrouted_widget'));
    }

    public function test_fold_sweep_never_clobbers_an_existing_blob_value(): void
    {
        settings_set('blog_posts_per_page', 25, 'integer', 'blog');  // operator value, in the blob
        Setting::create(['key' => 'blog_posts_per_page', 'value' => '9', 'type' => 'integer', 'group' => 'blog']);

        Setting::foldFlatRowsIntoBlobs();

        $this->assertSame(25, settings('blog_posts_per_page'));
        $this->assertSame(0, Setting::where('key', 'blog_posts_per_page')->count());
    }

    public function test_seeding_leaves_no_flat_settings_rows(): void
    {
        // The regression guard for the whole class of bugs this file describes: the settings
        // seeders must end with every row a `group:*` blob. SupportSeeder/ContactSeeder run
        // AFTER FoundationSeeder's collapse, so anything they write must route itself.
        $this->seed(\Database\Seeders\FoundationSeeder::class);
        $this->seed(\Database\Seeders\SupportSeeder::class);
        $this->seed(\Database\Seeders\ContactSeeder::class);

        $flat = Setting::where('key', 'not like', 'group:%')->pluck('key')->all();

        $this->assertSame([], $flat, 'Seeded settings left flat rows: '.implode(', ', $flat));
        $this->assertGreaterThan(0, Setting::count());

        // Spot-check that the seeded values actually survived the routing.
        $this->assertSame('1.0.0', settings('app_version'));
        $this->assertSame(24, settings('sla_first_response_hours'));
        $this->assertSame(true, settings('tickets_enabled'));
        $this->assertSame(true, settings('contact_enabled'));
        $this->assertSame('text', settings('contact_subject_mode'));
    }

    public function test_reseeding_does_not_overwrite_operator_edited_values(): void
    {
        $this->seed(\Database\Seeders\SupportSeeder::class);
        settings_set('sla_first_response_hours', 99, 'integer', 'support');

        $this->seed(\Database\Seeders\SupportSeeder::class);

        // isPersisted() is blob-aware, so the re-seed sees the key as present and skips it.
        // The old Setting::firstOrCreate() guard checked the flat `key` column, which a
        // blobbed key no longer has — it re-inserted the default as a flat row every run.
        $this->assertSame(99, settings('sla_first_response_hours'));
        $this->assertSame(0, Setting::where('key', 'sla_first_response_hours')->count());
    }

    public function test_expand_reverses_collapse(): void
    {
        settings_set('gdpr_banner_title', 'Hi', 'string', 'gdpr');
        settings_set('gdpr_enabled', true, 'boolean', 'gdpr');

        Setting::expandBlobToFlat('gdpr');

        $this->assertSame(0, Setting::where('key', 'group:gdpr')->count());
        $this->assertSame('Hi', Setting::where('key', 'gdpr_banner_title')->first()->castValue());
        $this->assertSame('Hi', settings('gdpr_banner_title'));
    }
}
