<?php

namespace Addons\FakerAi\Tests\Feature;

require_once dirname(__DIR__).'/FakerAiTestCase.php';

use Addons\FakerAi\Models\FakerBatch;
use Addons\FakerAi\Tests\FakerAiTestCase;
use App\Models\AiTool;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Testimonial;
use App\Models\ToolReview;
use App\Models\User;
use Illuminate\Support\Str;

class GenerationTest extends FakerAiTestCase
{
    public function test_admin_can_generate_testimonials(): void
    {
        $this->actingAsAdmin();
        $this->fakeAi([
            ['name' => 'Jane Doe', 'role' => 'CEO', 'company' => 'Acme', 'content' => 'Saved us hours every week.', 'rating' => 5],
            ['name' => 'John Roe', 'role' => 'CTO', 'company' => 'Globex', 'content' => 'Reliable and genuinely fast.', 'rating' => 4],
        ]);

        $this->post(route('addon.faker-ai.admin.generate'), [
            'type' => 'testimonials',
            'count' => 2,
        ])->assertRedirect();

        $this->assertSame(2, Testimonial::count());

        $batch = FakerBatch::firstOrFail();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->inserted_count);
        // Token spend was billed to the run and captured on the batch.
        $this->assertGreaterThan(0, $batch->tokens_output);
    }

    public function test_deleting_a_batch_rolls_back_its_rows(): void
    {
        $this->actingAsAdmin();
        $this->fakeAi([
            ['name' => 'Jane Doe', 'role' => 'CEO', 'company' => 'Acme', 'content' => 'Excellent.', 'rating' => 5],
        ]);

        $this->post(route('addon.faker-ai.admin.generate'), ['type' => 'testimonials', 'count' => 1])
            ->assertRedirect();
        $this->assertSame(1, Testimonial::count());

        $batch = FakerBatch::firstOrFail();
        $this->delete(route('addon.faker-ai.admin.batches.destroy', ['batch' => $batch->id]))
            ->assertRedirect();

        $this->assertSame(0, Testimonial::count());
        $this->assertNull(FakerBatch::find($batch->id));
    }

    public function test_generated_users_are_flagged_and_purged_on_rollback(): void
    {
        $this->actingAsAdmin();
        $this->fakeAi([
            ['name' => 'Amy Fake', 'profession' => 'Designer', 'country' => 'Canada'],
            ['name' => 'Ben Fake', 'profession' => 'Developer', 'country' => 'Spain'],
        ]);

        $this->post(route('addon.faker-ai.admin.generate'), ['type' => 'users', 'count' => 2])
            ->assertRedirect();

        $this->assertSame(2, User::where('email', 'like', '%@faker.local')->count());

        $batch = FakerBatch::firstOrFail();
        $this->delete(route('addon.faker-ai.admin.batches.destroy', ['batch' => $batch->id]))
            ->assertRedirect();

        // force-deleted past the soft delete.
        $this->assertSame(0, User::withTrashed()->where('email', 'like', '%@faker.local')->count());
    }

    public function test_tool_favorites_reuse_a_user_pool_and_roll_back_cleanly(): void
    {
        $this->actingAsAdmin();

        $toolA = $this->makeTool('Alpha');
        $toolB = $this->makeTool('Beta');

        // 6 favorites across 2 tools = 3 each. The same user may favorite both tools, so the
        // pool is the largest per-tool share (3), NOT one user per row (6).
        $this->post(route('addon.faker-ai.admin.generate'), [
            'type' => 'tool-favorites',
            'count' => 6,
            'target' => ['*'],
        ])->assertRedirect();

        $this->assertSame(3, $toolA->favorites()->count());
        $this->assertSame(3, $toolB->favorites()->count());
        $this->assertSame(3, User::where('email', 'like', '%@faker.local')->count());

        $batch = FakerBatch::firstOrFail();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(6, $batch->inserted_count);
        // Favorites are a plain user->tool link; nothing here is worth an AI call.
        $this->assertSame(0, $batch->tokens_output);

        $this->delete(route('addon.faker-ai.admin.batches.destroy', ['batch' => $batch->id]))
            ->assertRedirect();

        $this->assertSame(0, Favorite::count());
        $this->assertSame(0, User::withTrashed()->where('email', 'like', '%@faker.local')->count());
    }

    public function test_five_star_only_forces_every_rating_to_five(): void
    {
        $this->actingAsAdmin();
        // The model ignores the band and returns 3s and 4s — the chosen band must still win.
        $this->fakeAi([
            ['name' => 'A', 'role' => 'CEO', 'company' => 'X', 'content' => 'Great.', 'rating' => 3],
            ['name' => 'B', 'role' => 'CTO', 'company' => 'Y', 'content' => 'Good.', 'rating' => 4],
        ]);

        $this->post(route('addon.faker-ai.admin.generate'), [
            'type' => 'testimonials',
            'count' => 2,
            'options' => ['rating' => '5'],
        ])->assertRedirect();

        $this->assertSame([5, 5], Testimonial::orderBy('id')->pluck('rating')->all());
    }

    public function test_a_rating_band_clamps_out_of_band_values_into_range(): void
    {
        $this->actingAsAdmin();
        $this->fakeAi([
            ['name' => 'A', 'role' => 'CEO', 'company' => 'X', 'content' => 'Ok.', 'rating' => 1],
            ['name' => 'B', 'role' => 'CTO', 'company' => 'Y', 'content' => 'Fine.', 'rating' => 4],
        ]);

        $this->post(route('addon.faker-ai.admin.generate'), [
            'type' => 'testimonials',
            'count' => 2,
            'options' => ['rating' => '3-5'],
        ])->assertRedirect();

        // 1 clamps up to the band floor (3); an in-band 4 is left alone.
        $this->assertSame([3, 4], Testimonial::orderBy('id')->pluck('rating')->all());
    }

    public function test_tool_review_ratings_honour_the_chosen_band(): void
    {
        $this->actingAsAdmin();
        $tool = $this->makeTool('Rated');
        $this->fakeAi([
            ['author_name' => 'A', 'comment' => 'Solid tool.', 'rating' => 5],
            ['author_name' => 'B', 'comment' => 'Does the job.', 'rating' => 1],
        ]);

        $this->post(route('addon.faker-ai.admin.generate'), [
            'type' => 'tool-reviews',
            'count' => 2,
            'target' => [$tool->slug],
            'options' => ['rating' => '4'],
        ])->assertRedirect();

        $this->assertSame([4, 4], ToolReview::orderBy('id')->pluck('rating')->all());
    }

    public function test_an_unknown_rating_band_falls_back_to_the_default(): void
    {
        $this->actingAsAdmin();
        $this->fakeAi([
            ['name' => 'A', 'role' => 'CEO', 'company' => 'X', 'content' => 'Nice.', 'rating' => 2],
        ]);

        // A tampered/stale value must not widen the band beyond what the UI offers.
        $this->post(route('addon.faker-ai.admin.generate'), [
            'type' => 'testimonials',
            'count' => 1,
            'options' => ['rating' => 'bogus'],
        ])->assertRedirect();

        // Falls back to the 4-5 default, so the out-of-band 2 clamps to 4.
        $this->assertSame(4, Testimonial::first()->rating);
    }

    public function test_a_batch_can_target_several_specific_items_at_once(): void
    {
        $this->actingAsAdmin();

        $picked = $this->makeTool('Picked A');
        $alsoPicked = $this->makeTool('Picked B');
        $ignored = $this->makeTool('Ignored');

        // Two of the three tools selected: the count spreads over those only.
        $this->post(route('addon.faker-ai.admin.generate'), [
            'type' => 'tool-favorites',
            'count' => 4,
            'target' => [$picked->slug, $alsoPicked->slug],
        ])->assertRedirect();

        $this->assertSame(2, $picked->favorites()->count());
        $this->assertSame(2, $alsoPicked->favorites()->count());
        $this->assertSame(0, $ignored->favorites()->count());

        $batch = FakerBatch::firstOrFail();
        $this->assertSame([$picked->slug, $alsoPicked->slug], $batch->targetList());
        $this->assertFalse($batch->targetsAll());
        $this->assertSame('Picked A, Picked B', $batch->target_label);
    }

    public function test_selecting_all_alongside_specific_targets_collapses_to_all(): void
    {
        $this->actingAsAdmin();
        $tool = $this->makeTool('Solo');

        $this->post(route('addon.faker-ai.admin.generate'), [
            'type' => 'tool-favorites',
            'count' => 2,
            'target' => ['*', $tool->slug],
        ])->assertRedirect();

        // "Everything" plus a subset is still everything.
        $batch = FakerBatch::firstOrFail();
        $this->assertTrue($batch->targetsAll());
        $this->assertSame(['*'], $batch->targetList());
    }

    public function test_a_legacy_single_string_target_is_still_readable(): void
    {
        // Batches created before multi-select stored a bare slug, not a JSON list.
        $batch = FakerBatch::create([
            'type' => 'tool-favorites',
            'requested_count' => 1,
            'target' => 'some-slug',
            'status' => 'completed',
        ]);

        $this->assertSame(['some-slug'], $batch->targetList());
        $this->assertFalse($batch->targetsAll());

        $legacyAll = FakerBatch::create([
            'type' => 'tool-favorites',
            'requested_count' => 1,
            'target' => '*',
            'status' => 'completed',
        ]);

        $this->assertTrue($legacyAll->targetsAll());
    }

    public function test_tool_favorites_are_never_attached_to_real_users(): void
    {
        $this->actingAsAdmin();
        $tool = $this->makeTool('Gamma');

        $real = User::factory()->create(['is_active' => true]);

        $this->post(route('addon.faker-ai.admin.generate'), [
            'type' => 'tool-favorites',
            'count' => 3,
            'target' => [$tool->slug],
        ])->assertRedirect();

        // A real member's own "My Favorites" list must never gain tools they didn't pick.
        $this->assertSame(0, Favorite::where('user_id', $real->id)->count());
        $this->assertSame(3, $tool->favorites()->count());
    }

    private function makeTool(string $name): AiTool
    {
        $category = Category::create([
            'name' => 'Writing '.uniqid(),
            'slug' => 'writing-'.uniqid(),
            'type' => 'ai_tool',
            'is_active' => true,
        ]);

        return AiTool::create([
            'ulid' => (string) Str::ulid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'type' => 'template',
            'category_id' => $category->id,
            'prompt_system' => 'System',
            'prompt_user' => 'Topic: {{topic}}',
            'access_level' => 'guest',
            'is_active' => true,
        ]);
    }

    public function test_blog_share_counter_increments_and_reverses_exactly(): void
    {
        $admin = $this->actingAsAdmin();

        $post = BlogPost::create([
            'author_id' => $admin->id,
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'content' => '<p>Body</p>',
            'status' => 'published',
            'published_at' => now(),
            'share_count' => 0,
        ]);

        $this->post(route('addon.faker-ai.admin.generate'), [
            'type' => 'blog-shares',
            'count' => 50,
            'target' => ['*'],
        ])->assertRedirect();

        $this->assertSame(50, $post->fresh()->share_count);

        $batch = FakerBatch::firstOrFail();
        $this->assertSame('completed', $batch->status);
        // Counter-only types make no AI call.
        $this->assertSame(0, $batch->tokens_output);

        $this->delete(route('addon.faker-ai.admin.batches.destroy', ['batch' => $batch->id]))
            ->assertRedirect();

        $this->assertSame(0, $post->fresh()->share_count);
    }
}
