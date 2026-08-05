<?php

namespace Tests\Feature;

use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\AiTool;
use App\Models\Page;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The About page is the one CMS page with a designed layout of its own, and the layout is
 * fed by counts rather than by copy. What is pinned here is the honesty of those counts:
 * a figure is shown because this install can prove it, never to fill a slot.
 */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        // Frontend pages live under resources/themes/<theme>/js, not Inertia's default
        // resource_path('js/Pages'), so the component-file existence check can't find them.
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);
    }

    private function page(string $slug = 'about'): Page
    {
        return Page::create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'content' => '<h2>Our mission</h2><p>Something worth reading.</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_about_page_carries_its_own_payload(): void
    {
        $this->page();

        $this->get('/about')->assertInertia(
            fn (Assert $page) => $page->component('Page')
                ->has('about.stats')
                ->has('about.testimonials')
                ->has('about.cta.secondary')
        );
    }

    public function test_other_pages_do_not_pay_for_the_about_payload(): void
    {
        $this->page('privacy-policy');

        $this->get('/privacy-policy')->assertInertia(
            fn (Assert $page) => $page->where('about', null)
        );
    }

    /**
     * A single-digit figure beside a six-digit one reads as a site with nothing to show, so
     * anything under ten is dropped — and a strip that thin is dropped whole.
     */
    public function test_stats_never_report_a_figure_the_site_cannot_stand_behind(): void
    {
        $this->page();

        $stats = $this->get('/about')->viewData('page')['props']['about']['stats'];

        $this->assertTrue($stats === [] || count($stats) >= 2, 'A one-stat strip should not be rendered at all.');

        foreach ($stats as $stat) {
            $this->assertNotSame('0', $stat['value']);
            $this->assertMatchesRegularExpression('/^\d/', $stat['value']);
        }
    }

    public function test_testimonials_fall_back_to_active_ones_when_none_are_featured(): void
    {
        $this->page();

        Testimonial::create([
            'name' => 'Ada Whitlock',
            'role' => 'Editor',
            'company' => 'Fieldnote',
            'content' => 'It replaced a week of drafting with an afternoon.',
            'rating' => 5,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $this->get('/about')->assertInertia(
            fn (Assert $page) => $page->where('about.testimonials.0.name', 'Ada Whitlock')
                // role and company are joined for display, so the card needs only one line.
                ->where('about.testimonials.0.role', 'Editor, Fieldnote')
        );
    }

    public function test_sign_up_is_not_offered_where_registration_is_closed(): void
    {
        $this->page();
        settings_set('registration_enabled', false, 'boolean', 'features');

        $this->get('/about')->assertInertia(
            fn (Assert $page) => $page->where('about.cta.primary', null)
                ->has('about.cta.secondary')
        );
    }

    /**
     * The counts come from real tables, so an install with tools and traffic reports both.
     */
    public function test_a_populated_install_reports_what_it_has(): void
    {
        $this->page();

        for ($i = 0; $i < 12; $i++) {
            AiTool::create([
                'name' => "Tool {$i}",
                'slug' => "tool-{$i}",
                'description' => 'A tool.',
                'is_active' => true,
                'usage_count' => 100,
            ]);
        }

        $stats = $this->get('/about')->viewData('page')['props']['about']['stats'];
        $labels = array_column($stats, 'label');

        $this->assertContains('AI tools', $labels);
        $this->assertContains('Generations run', $labels);
        $this->assertSame('12', $stats[array_search('AI tools', $labels, true)]['value']);
    }
}
