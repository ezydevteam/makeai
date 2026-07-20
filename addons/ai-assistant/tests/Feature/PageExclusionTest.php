<?php

namespace Addons\AiAssistant\Tests\Feature;

require_once dirname(__DIR__) . '/AssistantTestCase.php';

use Addons\AiAssistant\AddonServiceProvider;
use Addons\AiAssistant\Tests\AssistantTestCase;
use Illuminate\Http\Request;

/**
 * The widget is shown everywhere by default, except auth flows, and the admin can hide it
 * on specific paths. Exercised against the provider's own matcher, since driving it through
 * a full Inertia render would drag in every unrelated global share.
 */
class PageExclusionTest extends AssistantTestCase
{
    private function excludedOn(string $path): bool
    {
        // Bind the request the matcher reads, then invoke the private matcher directly.
        $this->app->instance('request', Request::create($path, 'GET'));

        $provider = new AddonServiceProvider($this->app);

        $method = new \ReflectionMethod($provider, 'isExcludedPage');
        $method->setAccessible(true);

        return (bool) $method->invoke($provider);
    }

    public function test_shown_on_ordinary_pages_by_default(): void
    {
        $this->assertFalse($this->excludedOn('/'));
        $this->assertFalse($this->excludedOn('/pricing'));
        $this->assertFalse($this->excludedOn('/blog/some-post'));
        $this->assertFalse($this->excludedOn('/dashboard'));
    }

    public function test_auth_pages_are_always_excluded(): void
    {
        $this->assertTrue($this->excludedOn('/login'));
        $this->assertTrue($this->excludedOn('/register'));
        $this->assertTrue($this->excludedOn('/forgot-password'));
        $this->assertTrue($this->excludedOn('/reset-password/some-token'));
        $this->assertTrue($this->excludedOn('/verify-email'));
        $this->assertTrue($this->excludedOn('/two-factor'));
        $this->assertTrue($this->excludedOn('/install'));
    }

    public function test_admin_can_exclude_an_exact_path(): void
    {
        addon_setting_set('ai-assistant', 'excluded_pages', "/pricing\n/contact");

        $this->assertTrue($this->excludedOn('/pricing'));
        $this->assertTrue($this->excludedOn('/contact'));
        $this->assertFalse($this->excludedOn('/about'));
    }

    public function test_a_trailing_wildcard_matches_everything_beneath_a_path(): void
    {
        addon_setting_set('ai-assistant', 'excluded_pages', '/blog/*');

        $this->assertTrue($this->excludedOn('/blog/hello-world'));
        $this->assertTrue($this->excludedOn('/blog/category/news'));
        // The wildcard covers what's UNDER /blog, not /blog itself.
        $this->assertFalse($this->excludedOn('/blog'));
    }

    /**
     * An admin shouldn't have to guess the exact form of a path — leading and trailing
     * slashes, and comma separation, all behave the same.
     */
    public function test_path_forms_are_normalised(): void
    {
        addon_setting_set('ai-assistant', 'excluded_pages', "pricing/ , /contact");

        $this->assertTrue($this->excludedOn('/pricing'));
        $this->assertTrue($this->excludedOn('/contact'));
    }

    public function test_the_home_page_can_be_excluded(): void
    {
        addon_setting_set('ai-assistant', 'excluded_pages', '/');

        $this->assertTrue($this->excludedOn('/'));
        $this->assertFalse($this->excludedOn('/pricing'), 'excluding home must not exclude everything');
    }

    public function test_an_empty_setting_excludes_nothing_extra(): void
    {
        addon_setting_set('ai-assistant', 'excluded_pages', '');

        $this->assertFalse($this->excludedOn('/pricing'));
        $this->assertTrue($this->excludedOn('/login'), 'auth pages stay excluded regardless');
    }
}
