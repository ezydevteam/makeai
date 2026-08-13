<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\MailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The template list already hides every requires_pro row when pro is unavailable,
 * so the "Pro" type filter could only ever return an empty table there.
 */
class MailTemplateProFilterTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);

        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->admin = Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);

        MailTemplate::create([
            'slug' => 'free_one', 'name' => 'Free One', 'subject' => 'Hi', 'content' => '<p>Hi</p>',
            'category' => 'account', 'is_active' => true, 'is_system' => true, 'requires_pro' => false,
        ]);
        MailTemplate::create([
            'slug' => 'pro_one', 'name' => 'Pro One', 'subject' => 'Hi', 'content' => '<p>Hi</p>',
            'category' => 'subscription', 'is_active' => true, 'is_system' => true, 'requires_pro' => true,
        ]);
    }

    private function enablePro(): void
    {
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'features');
    }

    public function test_pro_filter_is_offered_when_pro_is_available(): void
    {
        $this->enablePro();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.mail.templates.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('proAvailable', true));
    }

    public function test_pro_filter_is_not_offered_without_pro(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.mail.templates.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('proAvailable', false));
    }

    /**
     * Every row the list would show for a query, across all pages.
     *
     * The index self-heals missing system templates by running MailTemplateSeeder,
     * so the table holds 40+ rows and the `latest()` paging pushes this class's two
     * fixtures off page 1 as soon as their created_at second differs from the
     * seeded rows' — which it does whenever the request lands a second after
     * setUp. Walking the pages keeps these assertions about visibility rather than
     * about ordering.
     *
     * @param  array<string, string>  $query
     * @return array<int, array<string, mixed>>
     */
    private function listedTemplates(array $query = []): array
    {
        $rows = [];
        $page = 1;
        $lastPage = 1;

        do {
            $this->actingAs($this->admin, 'admin')
                ->get(route('admin.mail.templates.index', $query + ['page' => $page]))
                ->assertInertia(function (AssertableInertia $props) use (&$rows, &$lastPage) {
                    $templates = $props->toArray()['props']['templates'];

                    $rows = array_merge($rows, $templates['data']);
                    $lastPage = $templates['last_page'];
                });

            $page++;
        } while ($page <= $lastPage);

        return $rows;
    }

    public function test_pro_filter_returns_only_pro_templates_when_available(): void
    {
        $this->enablePro();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.mail.templates.index', ['type' => 'pro']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.type', 'pro'));

        $rows = $this->listedTemplates(['type' => 'pro']);
        $slugs = array_column($rows, 'slug');

        $this->assertContains('pro_one', $slugs);
        $this->assertNotContains('free_one', $slugs);
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertTrue((bool) $row['requires_pro'], "{$row['slug']} is not a pro template");
        }
    }

    /**
     * A stale bookmark or hand-edited URL must not produce an unexplained empty
     * table — the type filter is dropped and the visible templates still show.
     */
    public function test_pro_filter_is_ignored_without_pro(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.mail.templates.index', ['type' => 'pro']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.type', ''));

        $slugs = array_column($this->listedTemplates(['type' => 'pro']), 'slug');

        $this->assertContains('free_one', $slugs);
        // Still hidden — the gate on the list itself is unchanged.
        $this->assertNotContains('pro_one', $slugs);
    }
}
