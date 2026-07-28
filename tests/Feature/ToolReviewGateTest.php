<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\RateLimiterService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The "you may only review a tool you have actually used" gate.
 *
 * Two places answer that question and they must agree: AiToolController builds the
 * `canReview` Inertia prop that decides whether the review form renders, and
 * ToolReviewController::store decides whether the submission is accepted. They used
 * to read different sources — the column vs. metadata->template_slug — so a tool
 * billed through TokenGuard::chargeExternalTool() (which writes only the column)
 * rendered the form and then rejected the post. Both now read `tool_slug`.
 */
class ToolReviewGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        // LicenseMiddleware runs before auth:sanctum on the api group; without this
        // every request here 403s with LICENSE_INVALID before reaching the controller.
        config(['license.require_verified' => false]);

        $this->clearPublicThrottle();
    }

    /**
     * The review mutation routes sit behind `throttle:public,30,60`, a per-IP counter
     * that lives in Redis when Redis is up — which RefreshDatabase does not roll back.
     * Clear it on both ends so a 429 cannot bleed into unrelated tests.
     */
    protected function clearPublicThrottle(): void
    {
        app(RateLimiterService::class)->clear('public', '127.0.0.1');
    }

    protected function tearDown(): void
    {
        $this->clearPublicThrottle();
        parent::tearDown();
    }

    private function tool(string $slug = 'blog-intro'): AiTool
    {
        return AiTool::create([
            'name' => 'Blog Intro',
            'slug' => $slug,
            'description' => 'Writes an intro.',
            'prompt_system' => 'You write intros.',
            'prompt_user' => 'Topic: {{topic}}',
            'is_active' => true,
            'show_reviews' => true,
        ]);
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    private function logUsage(User $user, string $slug, array $overrides = []): AiUsageLog
    {
        return AiUsageLog::create(array_merge([
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'type' => 'tool',
            'tool_slug' => $slug,
            'status' => 'completed',
            'metadata' => ['template_slug' => $slug],
        ], $overrides));
    }

    public function test_user_who_never_used_the_tool_cannot_review(): void
    {
        $tool = $this->tool();
        Sanctum::actingAs($this->user());

        $this->postJson("/api/v1/tools/{$tool->slug}/reviews", ['rating' => 5, 'comment' => 'Great.'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_completed_usage_unlocks_the_review(): void
    {
        $tool = $this->tool();
        $user = $this->user();
        $this->logUsage($user, $tool->slug);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/tools/{$tool->slug}/reviews", ['rating' => 5, 'comment' => 'Great.'])
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    /**
     * The regression this fix targets. chargeExternalTool() writes `tool_slug` but no
     * `template_slug` in metadata, so the old JSON-path lookup found nothing while the
     * canReview prop — which reads the column — had already rendered the form.
     */
    public function test_integration_billed_usage_without_template_slug_metadata_unlocks_the_review(): void
    {
        $tool = $this->tool();
        $user = $this->user();
        $this->logUsage($user, $tool->slug, [
            'provider' => 'integration',
            'metadata' => ['integration' => 'some-external-tool'],
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/tools/{$tool->slug}/reviews", ['rating' => 4, 'comment' => 'Useful.'])
            ->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_usage_of_a_different_tool_does_not_unlock_the_review(): void
    {
        $tool = $this->tool();
        $other = $this->tool('other-tool');
        $user = $this->user();
        $this->logUsage($user, $other->slug);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/tools/{$tool->slug}/reviews", ['rating' => 5])
            ->assertStatus(422);
    }

    public function test_failed_usage_does_not_unlock_the_review(): void
    {
        $tool = $this->tool();
        $user = $this->user();
        $this->logUsage($user, $tool->slug, ['status' => 'failed']);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/tools/{$tool->slug}/reviews", ['rating' => 5])
            ->assertStatus(422);
    }

    public function test_another_users_usage_does_not_unlock_the_review(): void
    {
        $tool = $this->tool();
        $this->logUsage($this->user(), $tool->slug);
        Sanctum::actingAs($this->user());

        $this->postJson("/api/v1/tools/{$tool->slug}/reviews", ['rating' => 5])
            ->assertStatus(422);
    }
}
