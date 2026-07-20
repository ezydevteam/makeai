<?php

namespace Tests\Feature;

use App\Exports\Registry\DatasetRegistry;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\User;
use Tests\TestCase;

/**
 * Export Center registry gating.
 *
 * Datasets are exposed/exportable strictly by data availability:
 *  - Regular license (no billing, no affiliate): users + ai-usage only.
 *  - Extended license + billing/affiliate on: revenue + affiliate appear.
 * The `type` validation rule is driven by the registry, so gated datasets 422
 * at both /export and /estimate rather than leaking data.
 */
class ExportCenterRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        settings_set('license_status', 'valid', 'string', 'license');
    }

    private function superAdmin(string $email = 'export-registry-test@makeai.com'): Admin
    {
        $superSlug = config('auth.providers.admins.super_admin_slug', 'super-admin');
        $role = AdminRole::firstOrCreate(
            ['slug' => $superSlug],
            ['name' => 'Super Admin', 'is_system' => true],
        );

        return Admin::create([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function regularLicense(): void
    {
        settings_set('license_type', '1', 'integer', 'license');
        settings_set('subscriptions_enabled', '0', 'boolean', 'ai');
        settings_set('affiliate_enabled', '0', 'boolean', 'affiliate');
    }

    private function extendedLicense(): void
    {
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');
        settings_set('affiliate_enabled', '1', 'boolean', 'affiliate');
    }

    public function test_registry_exposes_only_core_datasets_under_regular_license(): void
    {
        $this->regularLicense();

        $keys = array_keys(app(DatasetRegistry::class)->available());

        $this->assertContains('users', $keys);
        $this->assertContains('ai-usage', $keys);
        $this->assertNotContains('revenue', $keys);
        $this->assertNotContains('affiliates', $keys);
    }

    public function test_registry_exposes_billing_datasets_under_extended_license(): void
    {
        $this->extendedLicense();

        $keys = array_keys(app(DatasetRegistry::class)->available());

        foreach (['revenue', 'subscriptions', 'refunds', 'coupon-redemptions', 'affiliates', 'affiliate-referrals', 'affiliate-payouts'] as $billing) {
            $this->assertContains($billing, $keys, "$billing should be available under Extended license");
        }
    }

    public function test_core_datasets_present_under_regular_license(): void
    {
        $this->regularLicense();

        $keys = array_keys(app(DatasetRegistry::class)->available());

        foreach (['users', 'ai-usage', 'generation-history', 'credit-ledger', 'ai-tools-catalog', 'newsletter-subscribers', 'support-tickets', 'contact-messages', 'login-history'] as $core) {
            $this->assertContains($core, $keys, "$core should be available under Regular license");
        }
        foreach (['revenue', 'subscriptions', 'refunds', 'coupon-redemptions', 'affiliates', 'affiliate-referrals', 'affiliate-payouts'] as $billing) {
            $this->assertNotContains($billing, $keys, "$billing must be hidden under Regular license");
        }
    }

    public function test_every_available_dataset_estimates_without_error_regular(): void
    {
        $this->regularLicense();

        $this->assertDatasetsEstimateCleanly();
    }

    public function test_every_available_dataset_estimates_without_error_extended(): void
    {
        $this->extendedLicense();

        $this->assertDatasetsEstimateCleanly();
    }

    /**
     * Hit /estimate for every currently-available dataset. A bad column or
     * relation in any Dataset::query surfaces here as a 500 rather than in prod.
     */
    private function assertDatasetsEstimateCleanly(): void
    {
        $admin = $this->superAdmin();
        // Support tickets are pinned to a dedicated 'mysql' connection (see
        // SupportTicket::$connection); the sqlite test harness can't reach it,
        // so exercise it in prod, not here.
        $skip = ['support-tickets'];
        $keys = array_diff(array_keys(app(DatasetRegistry::class)->available()), $skip);
        $this->assertNotEmpty($keys);

        foreach ($keys as $key) {
            $this->actingAs($admin, 'admin')
                ->postJson(route('admin.reports.export.estimate'), [
                    'type' => $key,
                    'date_from' => now()->subYear()->toDateString(),
                    'date_to' => now()->addDay()->toDateString(),
                ])
                ->assertOk()
                ->assertJsonStructure(['count']);
        }
    }

    public function test_revenue_export_is_blocked_under_regular_license(): void
    {
        $this->regularLicense();

        $this->actingAs($this->superAdmin(), 'admin')
            ->postJson(route('admin.reports.export'), ['type' => 'revenue', 'format' => 'csv'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_affiliate_estimate_is_blocked_under_regular_license(): void
    {
        $this->regularLicense();

        $this->actingAs($this->superAdmin(), 'admin')
            ->postJson(route('admin.reports.export.estimate'), ['type' => 'affiliates'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_users_csv_export_streams_under_regular_license(): void
    {
        $this->regularLicense();
        User::factory()->count(3)->create();

        $response = $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.reports.export'), ['type' => 'users', 'format' => 'csv']);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_users_estimate_returns_count(): void
    {
        $this->regularLicense();
        User::factory()->count(4)->create();

        $this->actingAs($this->superAdmin(), 'admin')
            ->postJson(route('admin.reports.export.estimate'), [
                'type' => 'users',
                'date_from' => now()->subYear()->toDateString(),
                'date_to' => now()->addDay()->toDateString(),
            ])
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('count', fn ($c) => $c >= 4)->etc());
    }

    public function test_users_pdf_export_renders_via_generic_view(): void
    {
        $this->regularLicense();
        User::factory()->count(2)->create();

        $response = $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.reports.export'), ['type' => 'users', 'format' => 'pdf']);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_preset_can_be_saved_with_columns_and_filters(): void
    {
        $this->regularLicense();

        $this->actingAs($this->superAdmin(), 'admin')
            ->postJson(route('admin.reports.export.presets.store'), [
                'name' => 'New users this month',
                'dataset' => 'users',
                'format' => 'csv',
                'filters' => ['status' => 'active', 'date_from' => '2026-01-01', 'bogus' => 'drop-me'],
                'columns' => ['name', 'email', 'not_a_column'],
            ])
            ->assertOk()
            ->assertJsonPath('preset.name', 'New users this month')
            ->assertJsonPath('preset.columns', ['name', 'email']); // invalid key dropped

        $this->assertDatabaseHas('export_presets', ['name' => 'New users this month', 'dataset' => 'users']);
    }

    public function test_preset_store_rejects_unavailable_dataset(): void
    {
        $this->regularLicense();

        $this->actingAs($this->superAdmin(), 'admin')
            ->postJson(route('admin.reports.export.presets.store'), [
                'name' => 'Revenue', 'dataset' => 'revenue', 'format' => 'xlsx',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dataset');
    }

    public function test_preset_delete_is_owner_scoped(): void
    {
        $this->regularLicense();
        $owner = $this->superAdmin('owner@makeai.com');
        $other = $this->superAdmin('other@makeai.com');

        $preset = \App\Models\ExportPreset::create([
            'admin_id' => $owner->id, 'name' => 'Mine', 'dataset' => 'users', 'format' => 'csv',
        ]);

        // A different admin cannot delete it.
        $this->actingAs($other, 'admin')
            ->deleteJson(route('admin.reports.export.presets.destroy', $preset))
            ->assertStatus(403);
        $this->assertDatabaseHas('export_presets', ['id' => $preset->id]);

        // The owner can.
        $this->actingAs($owner, 'admin')
            ->deleteJson(route('admin.reports.export.presets.destroy', $preset))
            ->assertOk();
        $this->assertDatabaseMissing('export_presets', ['id' => $preset->id]);
    }

    public function test_schedule_can_be_created_and_armed_for_the_future(): void
    {
        $this->regularLicense();

        $this->actingAs($this->superAdmin(), 'admin')
            ->postJson(route('admin.reports.export.schedules.store'), [
                'name' => 'Weekly users',
                'dataset' => 'users',
                'format' => 'csv',
                'frequency' => 'weekly',
            ])
            ->assertOk()
            ->assertJsonPath('schedule.frequency', 'weekly');

        $schedule = \App\Models\ScheduledExport::first();
        $this->assertNotNull($schedule->next_run_at);
        $this->assertTrue($schedule->next_run_at->isFuture(), 'A new schedule must not fire immediately.');
        $this->assertNull($schedule->last_run_at);
    }

    public function test_schedule_store_rejects_bad_frequency_and_unavailable_dataset(): void
    {
        $this->regularLicense();

        $this->actingAs($this->superAdmin(), 'admin')
            ->postJson(route('admin.reports.export.schedules.store'), [
                'name' => 'X', 'dataset' => 'revenue', 'format' => 'csv', 'frequency' => 'weekly',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dataset');

        $this->actingAs($this->superAdmin('a2@makeai.com'), 'admin')
            ->postJson(route('admin.reports.export.schedules.store'), [
                'name' => 'X', 'dataset' => 'users', 'format' => 'csv', 'frequency' => 'yearly',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('frequency');
    }

    public function test_schedule_toggle_is_owner_scoped(): void
    {
        $this->regularLicense();
        $owner = $this->superAdmin('owner2@makeai.com');
        $other = $this->superAdmin('other2@makeai.com');

        $schedule = \App\Models\ScheduledExport::create([
            'admin_id' => $owner->id, 'name' => 'S', 'dataset' => 'users', 'format' => 'csv',
            'frequency' => 'daily', 'is_active' => true, 'next_run_at' => now()->addDay(),
        ]);

        $this->actingAs($other, 'admin')
            ->patchJson(route('admin.reports.export.schedules.toggle', $schedule))
            ->assertStatus(403);

        $this->actingAs($owner, 'admin')
            ->patchJson(route('admin.reports.export.schedules.toggle', $schedule))
            ->assertOk()
            ->assertJsonPath('schedule.is_active', false);
    }

    public function test_run_scheduled_command_generates_file_and_advances_clock(): void
    {
        $this->regularLicense();
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->instance(
            \App\Services\InAppNotificationService::class,
            \Mockery::mock(\App\Services\InAppNotificationService::class)->shouldIgnoreMissing(),
        );
        User::factory()->count(2)->create();
        $admin = $this->superAdmin();

        $schedule = \App\Models\ScheduledExport::create([
            'admin_id' => $admin->id, 'name' => 'Weekly users', 'dataset' => 'users',
            'format' => 'csv', 'frequency' => 'weekly', 'is_active' => true,
            'next_run_at' => now()->subHour(),
        ]);

        $this->artisan('exports:run-scheduled')->assertExitCode(0);

        $files = \Illuminate\Support\Facades\Storage::disk('local')->files('exports/' . $admin->id);
        $this->assertNotEmpty($files, 'Scheduled run should write an export file.');

        $schedule->refresh();
        $this->assertNotNull($schedule->last_run_at);
        $this->assertTrue($schedule->next_run_at->isFuture(), 'next_run_at should advance past now.');
    }

    public function test_column_selection_limits_csv_headers(): void
    {
        $this->regularLicense();
        User::factory()->create();

        $response = $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.reports.export'), [
                'type' => 'users',
                'format' => 'csv',
                'columns' => ['name', 'email'],
            ]);

        $response->assertOk();
        $body = $response->streamedContent();
        // Selected headers present, unselected ("Credits") absent.
        $this->assertStringContainsString('Name', $body);
        $this->assertStringContainsString('Email', $body);
        $this->assertStringNotContainsString('Credits', $body);
    }
}
