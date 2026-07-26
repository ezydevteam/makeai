<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A blank password field used to be unset on update ("keep current password"),
 * which meant a page could be protected but never unprotected again — the stored
 * hash is never sent to the browser, so there was no path back. The field is now
 * the single source of truth: a value sets it, blank removes it.
 */
class PagePasswordProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        // Frontend pages live under resources/themes/<theme>/js, not Inertia's default
        // resource_path('js/Pages'), so the component-file existence check can't find them.
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);

        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->admin = Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Docs',
            'slug' => 'docs',
            'content' => '<p>Body</p>',
            'status' => 'published',
            'show_title' => true,
            'show_excerpt' => false,
            'center_title' => false,
            'show_breadcrumbs' => true,
            'show_featured_image' => false,
            'show_sidebar' => false,
            'sidebar_position' => 'right',
            'container_width' => 'default',
        ], $overrides);
    }

    private function protectedPage(): Page
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.pages.store'), $this->payload(['password' => 's3cret']))
            ->assertRedirect();

        $page = Page::where('slug', 'docs')->firstOrFail();
        $this->assertNotNull($page->password);

        return $page;
    }

    public function test_a_password_can_be_set_and_is_hashed(): void
    {
        $page = $this->protectedPage();

        $this->assertNotSame('s3cret', $page->password);
        $this->assertTrue(Hash::check('s3cret', $page->password));
    }

    public function test_saving_with_a_blank_password_removes_the_protection(): void
    {
        $page = $this->protectedPage();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.pages.update', $page), $this->payload(['password' => '']))
            ->assertRedirect();

        $this->assertNull($page->fresh()->password);
    }

    public function test_an_unprotected_page_stays_reachable_and_a_protected_one_prompts(): void
    {
        $page = $this->protectedPage();

        $this->get(route('page.show', $page->slug))
            ->assertOk()
            ->assertInertia(fn ($assert) => $assert->component('PagePassword'));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.pages.update', $page), $this->payload(['password' => '']))
            ->assertRedirect();

        $this->get(route('page.show', $page->slug))
            ->assertOk()
            ->assertInertia(fn ($assert) => $assert->component('Page'));
    }

    public function test_updating_without_touching_the_password_field_also_clears_it(): void
    {
        // The form always posts the field, but a payload that omits it entirely
        // must not resurrect the old "keep silently" behaviour.
        $page = $this->protectedPage();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.pages.update', $page), $this->payload(['title' => 'Docs v2']))
            ->assertRedirect();

        $fresh = $page->fresh();
        $this->assertSame('Docs v2', $fresh->title);
        $this->assertNull($fresh->password);
    }
}
