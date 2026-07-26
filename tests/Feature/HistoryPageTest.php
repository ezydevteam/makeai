<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\GenerationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Historian',
            'email' => 'historian@test.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        AiTool::create([
            'name' => 'Blog Article Generator',
            'slug' => 'blog-article-generator',
            'description' => 'Writes articles',
            'prompt_system' => 'system',
            'prompt_user' => 'user',
            'fields' => [],
            'is_active' => true,
        ]);

        // 25 rows so the list paginates (20 per page), 6 of them starred.
        foreach (range(1, 25) as $index) {
            GenerationHistory::create([
                'user_id' => $this->user->id,
                'tool_slug' => 'blog-article-generator',
                'prompt_system' => 'system',
                'prompt_user' => 'user',
                'field_values' => [],
                'model' => 'gpt-5.6-terra',
                'provider' => 'openai',
                'output_preview' => "Draft {$index}",
                'tokens_input' => 100,
                'tokens_output' => 200,
                'is_favorited' => $index <= 6,
                'created_at' => now()->subMinutes($index),
            ]);
        }
    }

    private function props(string $url): array
    {
        return $this->actingAs($this->user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get($url)
            ->assertOk()
            ->viewData('page')['props'];
    }

    public function test_history_rows_carry_the_tool_name(): void
    {
        $props = $this->props('/user/dashboard/history');

        $this->assertSame('Blog Article Generator', $props['history'][0]['tool_name']);
        $this->assertSame('blog-article-generator', $props['history'][0]['tool_slug']);
    }

    public function test_deleted_tool_falls_back_to_the_slug(): void
    {
        AiTool::where('slug', 'blog-article-generator')->delete();

        $props = $this->props('/user/dashboard/history');

        $this->assertSame('blog-article-generator', $props['history'][0]['tool_name']);
    }

    public function test_starred_tab_paginates_over_starred_rows_only(): void
    {
        $all = $this->props('/user/dashboard/history');

        $this->assertSame(25, $all['pagination']['total']);
        $this->assertSame(2, $all['pagination']['last_page']);
        $this->assertCount(20, $all['history']);
        // from/to feed the shared Pagination component's "showing X to Y of Z".
        $this->assertSame(1, $all['pagination']['from']);
        $this->assertSame(20, $all['pagination']['to']);

        $starred = $this->props('/user/dashboard/history?starred=1');

        // The pager describes the starred set, so it no longer offers pages that do not
        // exist inside the tab.
        $this->assertSame(6, $starred['pagination']['total']);
        $this->assertSame(1, $starred['pagination']['last_page']);
        $this->assertCount(6, $starred['history']);
        $this->assertTrue(collect($starred['history'])->every(fn ($row) => $row['is_favorited']));
        $this->assertTrue($starred['filters']['starred']);
    }

    public function test_tab_badges_count_the_whole_account(): void
    {
        $props = $this->props('/user/dashboard/history');

        // Not the 20 rows on this page, and not 6-of-20.
        $this->assertSame(25, $props['totalCount']);
        $this->assertSame(6, $props['starredCount']);

        $starred = $this->props('/user/dashboard/history?starred=1');

        $this->assertSame(25, $starred['totalCount']);
        $this->assertSame(6, $starred['starredCount']);
    }

    public function test_pagination_links_keep_the_starred_filter(): void
    {
        $props = $this->props('/user/dashboard/history?starred=1');

        $next = collect($props['pagination']['links'])->firstWhere('url', '!=', null);

        $this->assertNotNull($next);
        $this->assertStringContainsString('starred=1', $next['url']);
    }
}
