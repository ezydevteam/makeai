<?php

namespace Tests\Feature;

use App\Models\ToolChain;
use App\Models\ToolChainRun;
use App\Models\ToolEmbed;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoChainsAndEmbedsTest extends TestCase
{
    use RefreshDatabase;

    public function test_showcase_chains_and_embeds_render(): void
    {
        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
        // demo:reset re-runs the seeder; nothing may stack up.
        $this->seed(DemoSeeder::class);

        $user = User::where('email', config('demo.user_email'))->firstOrFail();

        $this->assertCount(3, ToolChain::where('user_id', $user->id)->get());
        $this->assertCount(4, ToolChainRun::where('user_id', $user->id)->get());
        $this->assertCount(3, ToolEmbed::where('user_id', $user->id)->get());

        $props = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/chains')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNotEmpty($props['chains']);
        $this->assertNotEmpty($props['runs']);

        // Steps must match the shape the builder writes, or a seeded chain cannot be edited.
        foreach ($props['chains'] as $chain) {
            $this->assertGreaterThanOrEqual(2, count($chain['steps']), 'a chain needs 2+ steps to run');

            foreach ($chain['steps'] as $index => $step) {
                $this->assertSame($index + 1, $step['step']);
                $this->assertArrayHasKey('tool_slug', $step);
                $this->assertArrayHasKey('static_inputs', $step);
                $this->assertArrayHasKey('field_map', $step);
                $this->assertDatabaseHas('ai_tools', ['slug' => $step['tool_slug']]);
            }
        }

        // Every run state the list renders differently is represented.
        $statuses = collect($props['runs'])->pluck('status');
        $this->assertContains('completed', $statuses);
        $this->assertContains('failed', $statuses);
        $this->assertNotNull(collect($props['runs'])->firstWhere('status', 'failed')['error']);

        foreach ($props['runs'] as $run) {
            $this->assertNotEmpty($run['step_outputs']);

            foreach ($run['step_outputs'] as $step) {
                $this->assertArrayHasKey('output', $step);
                $this->assertArrayHasKey('tokens', $step);
                $this->assertArrayHasKey('credits', $step);
                $this->assertDatabaseHas('ai_tools', ['slug' => $step['tool_slug']]);
            }
        }

        // A chain opens in the builder.
        $chain = ToolChain::where('user_id', $user->id)->firstOrFail();
        $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/chains/' . $chain->ulid)
            ->assertOk();

        $embedProps = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/tool-embeds')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(3, $embedProps['embeds']);
        $this->assertNotEmpty($embedProps['tools'], 'no embeddable tools to pick from');

        $embeds = collect($embedProps['embeds']);
        $this->assertTrue($embeds->contains(fn ($embed) => $embed['is_active'] === false), 'no paused embed');
        $this->assertTrue($embeds->contains(fn ($embed) => ! empty($embed['allowed_origins'])), 'no origin-locked embed');
        $this->assertTrue($embeds->every(fn ($embed) => ! array_key_exists('password_hash', $embed)), 'password hash must stay hidden');

        // The live embed endpoint serves a seeded token.
        $live = ToolEmbed::where('user_id', $user->id)->where('is_active', true)->whereNull('password_hash')->firstOrFail();
        $this->assertSame(64, strlen($live->token));

        $this->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/embed/' . $live->token)
            ->assertOk();
    }

    public function test_embed_preview_is_framable_by_the_owner_and_is_not_counted_as_usage(): void
    {
        $user = User::create([
            'name' => 'Embed Owner',
            'email' => 'owner@embed.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $tool = \App\Models\AiTool::create([
            'name' => 'Preview Tool',
            'slug' => 'preview-tool',
            'description' => 'Demo',
            'prompt_system' => 'You are a tool.',
            'prompt_user' => 'Do the thing: {{input}}',
            'fields' => [],
            'is_active' => true,
            'is_embeddable' => true,
        ]);

        $embed = ToolEmbed::create([
            'user_id' => $user->id,
            'tool_slug' => $tool->slug,
            'label' => 'Origin-locked embed',
            'allowed_origins' => ['https://client.example'],
            'usage_count' => 10,
            'is_active' => true,
        ]);

        // Guest first — actingAs() leaks the resolved guard into later requests in the same
        // test, so a "visitor" request made after it would still be the owner.
        $response = $this->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/embed/' . $embed->token)
            ->assertOk();

        // The dashboard has to be able to frame it, or the preview modal is a blank box.
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString('https://client.example', $csp);

        // A visitor counts.
        $this->assertSame(11, $embed->fresh()->usage_count);

        // The owner previewing their own embed does not.
        $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/embed/' . $embed->token)
            ->assertOk();

        $this->assertSame(11, $embed->fresh()->usage_count);

        // A paused embed stays 404 for everyone — the modal explains that state instead of
        // framing the error page.
        $embed->update(['is_active' => false]);

        $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/embed/' . $embed->token)
            ->assertNotFound();
    }
}
