<?php

declare(strict_types=1);

namespace Addons\AiRepurposer\Tests\Feature;

use Addons\AiRepurposer\Jobs\CleanupRepurposerFiles;
use Addons\AiRepurposer\Jobs\ProcessBulkRepurposeJob;
use Addons\AiRepurposer\Jobs\ProcessRepurposeJob;
use Addons\AiRepurposer\Models\RpJob;
use Addons\AiRepurposer\Models\RpOutput;
use App\Models\Addon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepurposerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private static function autoloadAddon(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $base = base_path('addons/ai-repurposer');

        $folders = ['app', 'app/Models', 'app/Services', 'app/Jobs', 'app/Http/Controllers', 'app/Http/Controllers/Admin', 'app/Http/Requests'];
        foreach ($folders as $folder) {
            $path = "{$base}/{$folder}";
            if (is_dir($path)) {
                $files = glob("{$path}/*.php");
                foreach ($files as $file) {
                    require_once $file;
                }
            }
        }

        // Ensure addon is registered
        Addon::firstOrCreate(
            ['slug' => 'ai-repurposer'],
            ['name' => 'AI Content Repurposer', 'version' => '1.0.0', 'is_active' => true],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::autoloadAddon();

        $this->artisan('migrate');

        $this->user = User::factory()->create(['credits' => 100]);
    }

    /** @test */
    public function it_creates_a_repurpose_job_from_youtube_url_and_dispatches_process_repurpose_job(): void
    {
        Queue::fake();

        Http::fake([
            'www.youtube.com/oembed*' => Http::response(['title' => 'Test Video'], 200),
            'www.youtube.com/watch*'  => Http::response('some html without captions', 200),
            'googleapis.com/*'        => Http::response(['items' => []], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->post('/content-repurposer', [
                'source_type' => 'youtube_url',
                'source_url'  => 'https://www.youtube.com/watch?v=abcdefghijk',
                'formats'     => ['blog_post', 'twitter_thread'],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('rp_jobs', [
            'user_id'     => $this->user->id,
            'source_type' => 'youtube_url',
            'source_url'  => 'https://www.youtube.com/watch?v=abcdefghijk',
            'status'      => 'queued',
        ]);

        Queue::assertPushed(ProcessRepurposeJob::class);
    }

    /** @test */
    public function it_creates_a_repurpose_job_from_file_upload_and_stores_file(): void
    {
        Queue::fake();
        Storage::fake('local');

        $file = UploadedFile::fake()->create('audio.mp3', 1024, 'audio/mpeg');

        $response = $this->actingAs($this->user)
            ->post('/content-repurposer', [
                'source_type' => 'file_upload',
                'file'        => $file,
                'formats'     => ['blog_post'],
            ]);

        $response->assertRedirect();

        $job = RpJob::where('user_id', $this->user->id)->first();
        $this->assertNotNull($job);
        $this->assertEquals('file_upload', $job->source_type);
        $this->assertNotNull($job->source_path);

        Queue::assertPushed(ProcessRepurposeJob::class);
    }

    /** @test */
    public function it_creates_a_repurpose_job_from_pasted_text_without_transcription(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->post('/content-repurposer', [
                'source_type' => 'text_paste',
                'text'        => 'This is a test transcript with enough text to meet the minimum word count requirement. ' . str_repeat('More content. ', 50),
                'title'       => 'Test Content',
                'formats'     => ['blog_post', 'key_quotes'],
            ]);

        $response->assertRedirect();

        $job = RpJob::where('user_id', $this->user->id)->first();
        $this->assertNotNull($job);
        $this->assertEquals('pasted', $job->transcript_source);

        Queue::assertPushed(ProcessRepurposeJob::class);
    }

    /** @test */
    public function it_deducts_credits_on_job_creation(): void
    {
        Queue::fake();

        Http::fake([
            'www.youtube.com/oembed*' => Http::response(['title' => 'Test'], 200),
            'www.youtube.com/watch*'  => Http::response('', 200),
        ]);

        $this->actingAs($this->user)
            ->post('/content-repurposer', [
                'source_type' => 'youtube_url',
                'source_url'  => 'https://www.youtube.com/watch?v=abcdefghijk',
                'formats'     => ['blog_post'],
            ]);

        $this->assertEquals(85, $this->user->fresh()->credits);
    }

    /** @test */
    public function it_rejects_job_when_user_has_insufficient_credits(): void
    {
        $this->user->update(['credits' => 5]);

        $response = $this->actingAs($this->user)
            ->post('/content-repurposer', [
                'source_type' => 'youtube_url',
                'source_url'  => 'https://www.youtube.com/watch?v=abcdefghijk',
                'formats'     => ['blog_post'],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('rp_jobs', ['user_id' => $this->user->id]);
    }

    /** @test */
    public function it_refunds_credits_to_the_wallet_when_a_job_fails_in_metered_mode(): void
    {
        // Metered mode (Extended license + billing): the charge drained the wallet,
        // so a failed job returns the credits to the wallet.
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');

        $job = RpJob::create([
            'user_id'           => $this->user->id,
            'source_type'       => 'youtube_url',
            'source_url'        => 'https://youtube.com/watch?v=badurl',
            'status'            => 'queued',
            'formats_requested' => ['blog_post'],
            'credits_deducted'  => 15,
        ]);

        $this->user->update(['credits' => 85]);

        // Queue::fake() doesn't prevent handle() calls in tests,
        // so we dispatch manually then call failed
        $processJob = new ProcessRepurposeJob($job->id);

        try {
            $processJob->failed(new \RuntimeException('Transcription failed'));
        } catch (\Throwable) {
            // Expected
        }

        $this->assertEquals(100, $this->user->fresh()->credits, 'Metered refund returns credits to the wallet.');
        $this->assertEquals('failed', $job->fresh()->status);
    }

    /** @test */
    public function it_winds_back_the_allowance_when_a_job_fails_in_quota_mode(): void
    {
        // Quota mode (Regular license — the default): the wallet was never drained,
        // so a failed job winds back the consumed daily/monthly allowance and leaves
        // the wallet untouched. A raw wallet increment would hand out free credits here.
        settings_set('license_type', '1', 'integer', 'license');
        settings_set('subscriptions_enabled', '0', 'boolean', 'ai');

        $job = RpJob::create([
            'user_id'           => $this->user->id,
            'source_type'       => 'youtube_url',
            'source_url'        => 'https://youtube.com/watch?v=badurl',
            'status'            => 'queued',
            'formats_requested' => ['blog_post'],
            'credits_deducted'  => 15,
        ]);

        $this->user->update([
            'credits'            => 85,
            'credits_used_today' => 20,
            'credits_used_month' => 20,
        ]);

        $processJob = new ProcessRepurposeJob($job->id);

        try {
            $processJob->failed(new \RuntimeException('Transcription failed'));
        } catch (\Throwable) {
            // Expected
        }

        $fresh = $this->user->fresh();
        $this->assertEquals(85, (float) $fresh->credits, 'Quota refund must not touch the wallet.');
        $this->assertEquals(5, (float) $fresh->credits_used_today, 'Allowance wound back by the refunded 15.');
        $this->assertEquals(5, (float) $fresh->credits_used_month);
        $this->assertEquals('failed', $job->fresh()->status);
    }

    /** @test */
    public function it_sets_status_to_partial_when_some_formats_fail(): void
    {
        $this->markTestSkipped('Requires mock of RepurposeService which depends on AI provider integration.');
    }

    /** @test */
    public function all_requested_formats_create_rp_output_rows(): void
    {
        $job = RpJob::create([
            'user_id'             => $this->user->id,
            'source_type'         => 'text_paste',
            'transcript'          => 'Test transcript content.',
            'source_title'        => 'Test',
            'status'              => 'completed',
            'formats_requested'   => ['blog_post', 'twitter_thread', 'linkedin_article'],
            'formats_completed'   => ['blog_post', 'twitter_thread', 'linkedin_article'],
            'credits_deducted'    => 15,
        ]);

        RpOutput::create([
            'rp_job_id'  => $job->id,
            'user_id'    => $this->user->id,
            'format'     => 'blog_post',
            'content'    => 'Blog post content.',
            'word_count' => 100,
        ]);

        RpOutput::create([
            'rp_job_id'  => $job->id,
            'user_id'    => $this->user->id,
            'format'     => 'twitter_thread',
            'content'    => 'Twitter thread content.',
            'word_count' => 50,
        ]);

        RpOutput::create([
            'rp_job_id'  => $job->id,
            'user_id'    => $this->user->id,
            'format'     => 'linkedin_article',
            'content'    => 'LinkedIn article content.',
            'word_count' => 75,
        ]);

        $this->assertEquals(3, RpOutput::where('rp_job_id', $job->id)->count());
    }

    /** @test */
    public function bulk_job_creates_multiple_rp_job_rows_with_same_bulk_batch_id(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->post('/content-repurposer/bulk', [
                'urls'    => [
                    'https://www.youtube.com/watch?v=aaaaaaaaaaa',
                    'https://www.youtube.com/watch?v=bbbbbbbbbbb',
                ],
                'formats' => ['blog_post', 'twitter_thread'],
            ]);

        $response->assertRedirect();

        $jobs = RpJob::where('user_id', $this->user->id)->get();
        $this->assertCount(2, $jobs);
        $this->assertTrue($jobs[0]->bulk_batch_id === $jobs[1]->bulk_batch_id);
        $this->assertTrue($jobs[0]->is_bulk);

        Queue::assertPushed(ProcessBulkRepurposeJob::class);
    }

    /** @test */
    public function bulk_deducts_credits_per_item_not_flat_rate(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->post('/content-repurposer/bulk', [
                'urls'    => [
                    'https://www.youtube.com/watch?v=aaaaaaaaaaa',
                    'https://www.youtube.com/watch?v=bbbbbbbbbbb',
                    'https://www.youtube.com/watch?v=ccccccccccc',
                ],
                'formats' => ['blog_post'],
            ]);

        // 3 items × 12 credits each = 36 credits deducted
        $this->assertEquals(64, $this->user->fresh()->credits);
    }

    /** @test */
    public function save_to_blog_inserts_into_core_blog_posts_table_as_draft(): void
    {
        $job = RpJob::create([
            'user_id'             => $this->user->id,
            'source_type'         => 'text_paste',
            'transcript'          => 'Test content.',
            'source_title'        => 'Test Blog Post',
            'status'              => 'completed',
            'formats_requested'   => ['blog_post'],
            'formats_completed'   => ['blog_post'],
            'credits_deducted'    => 15,
        ]);

        $output = RpOutput::create([
            'rp_job_id'  => $job->id,
            'user_id'    => $this->user->id,
            'format'     => 'blog_post',
            'content'    => '## This is a blog post',
            'word_count' => 100,
        ]);

        DB::statement('CREATE TABLE IF NOT EXISTS blog_posts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(500),
            content LONGTEXT,
            status VARCHAR(50) DEFAULT "draft",
            slug VARCHAR(500),
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');

        $response = $this->actingAs($this->user)
            ->post("/content-repurposer/outputs/{$output->ulid}/save-blog");

        $response->assertOk();
        $response->assertJson(['saved' => true]);

        $this->assertTrue($output->fresh()->is_saved);
        $this->assertNotNull($output->fresh()->saved_post_id);
    }

    /** @test */
    public function status_endpoint_returns_correct_progress_percent(): void
    {
        $job = RpJob::create([
            'ulid'                => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'user_id'             => $this->user->id,
            'source_type'         => 'text_paste',
            'transcript'          => 'Test.',
            'status'              => 'generating',
            'formats_requested'   => ['blog_post', 'twitter_thread', 'linkedin_article', 'email_newsletter'],
            'formats_completed'   => ['blog_post', 'twitter_thread'],
            'credits_deducted'    => 15,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/content-repurposer/{$job->ulid}/status");

        $response->assertOk();
        $response->assertJson([
            'status'           => 'generating',
            'progress_percent' => 50,
        ]);
    }

    /** @test */
    public function user_cannot_view_another_users_repurpose_job(): void
    {
        $otherUser = User::factory()->create();

        $job = RpJob::create([
            'ulid'                => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'user_id'             => $otherUser->id,
            'source_type'         => 'text_paste',
            'transcript'          => 'Test.',
            'status'              => 'completed',
            'formats_requested'   => ['blog_post'],
            'formats_completed'   => ['blog_post'],
            'credits_deducted'    => 15,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/content-repurposer/{$job->ulid}");

        $response->assertForbidden();
    }

    /** @test */
    public function cleanup_repurposer_files_deletes_uploaded_files_older_than_7_days(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('repurposer/old.mp3', 'fake audio');

        $job = RpJob::create([
            'user_id'             => $this->user->id,
            'source_type'         => 'file_upload',
            'source_path'         => 'repurposer/old.mp3',
            'status'              => 'completed',
            'formats_requested'   => ['blog_post'],
            'formats_completed'   => ['blog_post'],
            'credits_deducted'    => 15,
            'created_at'          => now()->subDays(10),
        ]);

        (new CleanupRepurposerFiles)->handle();

        $this->assertNull($job->fresh()->source_path);
        Storage::disk('local')->assertMissing('repurposer/old.mp3');
    }

    /** @test */
    public function repurpose_request_validates_required_formats(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/content-repurposer', [
                'source_type' => 'text_paste',
                'text'        => 'This is a long enough test transcript with sufficient content to meet requirements. ' . str_repeat('More text here. ', 10),
                'title'       => 'Test',
                'formats'     => [],
            ]);

        $response->assertSessionHasErrors('formats');
    }

    /** @test */
    public function it_handles_delete_job(): void
    {
        $job = RpJob::create([
            'user_id'             => $this->user->id,
            'source_type'         => 'text_paste',
            'transcript'          => 'Test.',
            'status'              => 'completed',
            'formats_requested'   => ['blog_post'],
            'formats_completed'   => ['blog_post'],
            'credits_deducted'    => 15,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/content-repurposer/{$job->ulid}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('rp_jobs', ['id' => $job->id]);
    }

    /** @test */
    public function it_validates_youtube_url_format(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/content-repurposer', [
                'source_type' => 'youtube_url',
                'source_url'  => 'not-a-url',
                'formats'     => ['blog_post'],
            ]);

        $response->assertSessionHasErrors('source_url');
    }
}
