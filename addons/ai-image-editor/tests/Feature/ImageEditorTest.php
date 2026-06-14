<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Tests\Feature;

use Addons\AiImageEditor\Events\ImageEditCompleted;
use Addons\AiImageEditor\Jobs\ApplyImageEdit;
use Addons\AiImageEditor\Models\IeEdit;
use Addons\AiImageEditor\Models\IeSession;
use Addons\AiImageEditor\Services\GdEditService;
use Addons\AiImageEditor\Services\ImageEditorService;
use Addons\AiImageEditor\Services\Providers\ImageEditException;
use App\Models\Addon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageEditorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Manually autoload addon classes since composer dump-autoload not available.
     */
    private static function autoloadAddon(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $base = base_path('addons/ai-image-editor');
        $files = [
            '/app/Services/Providers/ImageEditException.php',
            '/app/Services/Providers/StabilityClient.php',
            '/app/Services/Providers/ReplicateClient.php',
            '/app/Services/Providers/RemoveBgClient.php',
            '/app/Services/Providers/ClipdropClient.php',
            '/app/Services/GdEditService.php',
            '/app/Services/ImageEditorService.php',
            '/app/Models/IeSession.php',
            '/app/Models/IeEdit.php',
            '/app/Jobs/ApplyImageEdit.php',
            '/app/Events/ImageEditCompleted.php',
            '/app/Http/Requests/ImageEditRequest.php',
            '/app/Http/Requests/ImageEditorSettingsRequest.php',
            '/app/Http/Controllers/User/ImageEditorController.php',
            '/app/Http/Controllers/Admin/ImageEditorSettingsController.php',
            '/AddonServiceProvider.php',
        ];
        foreach ($files as $file) {
            require_once $base . $file;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::autoloadAddon();

        Storage::fake('local');

        // Register the addon so routes and autoloading work
        Addon::create([
            'slug' => 'ai-image-editor',
            'name' => 'AI Image Editor',
            'version' => '1.0.0',
            'is_active' => true,
            'installed_at' => now(),
        ]);

        // Load addon routes
        require base_path('addons/ai-image-editor/routes/web.php');

        // Boot the addon service provider (registers singletons + Inertia share)
        require_once base_path('addons/ai-image-editor/AddonServiceProvider.php');
        app()->register(\Addons\AiImageEditor\AddonServiceProvider::class);

        // Create test PNG stub
        $stubPath = base_path('tests/stubs/test.png');
        if (! is_dir(dirname($stubPath))) {
            mkdir(dirname($stubPath), 0755, true);
        }
        if (! file_exists($stubPath)) {
            $img = imagecreatetruecolor(10, 10);
            $red = imagecolorallocate($img, 255, 0, 0);
            imagefill($img, 0, 0, $red);
            imagepng($img, $stubPath);
            imagedestroy($img);
        }

        // Set up fake HTTP responses
        Http::fake([
            'api.stability.ai/*' => Http::response(
                file_get_contents($stubPath),
                200,
                ['Content-Type' => 'image/png'],
            ),
            'api.replicate.com/v1/predictions' => Http::sequence()
                ->push(['id' => 'pred_123', 'status' => 'starting'], 201)
                ->push([
                    'id' => 'pred_123',
                    'status' => 'succeeded',
                    'output' => ['https://cdn.replicate.com/test.png'],
                ], 200)
                ->whenEmpty(Http::response(['id' => 'pred_999', 'status' => 'starting'], 201)),
            'api.replicate.com/v1/predictions/*' => Http::response([
                'id' => 'pred_123',
                'status' => 'succeeded',
                'output' => ['https://cdn.replicate.com/test.png'],
            ], 200),
            'api.remove.bg/*' => Http::response(
                file_get_contents($stubPath),
                200,
                ['Content-Type' => 'image/png'],
            ),
            'clipdrop-api.co/*' => Http::response(
                file_get_contents($stubPath),
                200,
                ['Content-Type' => 'image/png'],
            ),
            'cdn.replicate.com/*' => Http::response(
                file_get_contents($stubPath),
                200,
            ),
        ]);

        // Seed API keys so provider checks pass
        settings_set('addon_ai-image-editor_stability_api_key', 'test-key-stability', 'encrypted');
        settings_set('addon_ai-image-editor_replicate_api_key', 'test-key-replicate', 'encrypted');
        settings_set('addon_ai-image-editor_remove_bg_api_key', 'test-key-removebg', 'encrypted');
        settings_set('addon_ai-image-editor_clipdrop_api_key', 'test-key-clipdrop', 'encrypted');
    }

    private function createTestSession(User $user): IeSession
    {
        $sourcePath = 'image-editor/' . $user->id . '/sources/test.png';
        Storage::disk('local')->put($sourcePath, file_get_contents(base_path('tests/stubs/test.png')));

        return IeSession::create([
            'user_id' => $user->id,
            'source_type' => 'uploaded',
            'source_path' => $sourcePath,
            'source_url' => Storage::url($sourcePath),
            'current_path' => $sourcePath,
            'current_url' => Storage::url($sourcePath),
            'width' => 10,
            'height' => 10,
            'format' => 'png',
        ]);
    }

    // ─── SESSION TESTS ───

    public function test_opens_editor_session_from_uploaded_image(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $this->actingAs($user);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        $response = $this->post(route('addon.ie.user.upload'), ['upload' => $file]);

        $response->assertInertia(fn ($page) => $page->component('Addons/ai-image-editor/User/Editor'));
        $this->assertTrue(IeSession::where('user_id', $user->id)->exists());
    }

    public function test_replaces_previous_session_when_new_image_opened(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $this->actingAs($user);

        $session = $this->createTestSession($user);
        $this->assertEquals(1, IeSession::where('user_id', $user->id)->count());

        $file = UploadedFile::fake()->image('new.jpg', 200, 200);
        $this->post(route('addon.ie.user.upload'), ['upload' => $file]);

        $this->assertEquals(1, IeSession::where('user_id', $user->id)->count());
        $this->assertFalse(IeSession::where('id', $session->id)->exists());
    }

    public function test_404_when_no_active_session_and_no_image(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $this->actingAs($user);

        $this->get(route('addon.ie.user.editor'))->assertStatus(404);
    }

    public function test_resumes_existing_session(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $this->actingAs($user);
        $this->createTestSession($user);

        $response = $this->get(route('addon.ie.user.editor'));
        $response->assertInertia(fn ($page) => $page->component('Addons/ai-image-editor/User/Editor'));
    }

    // ─── APPLY OPERATION TESTS ───

    public function test_creates_ieedit_and_dispatches_job(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits' => 1000]);
        $this->actingAs($user);
        $this->createTestSession($user);

        $response = $this->post(route('addon.ie.user.apply'), [
            'operation' => 'bg_remove',
        ]);

        $response->assertJson(['success' => true, 'status' => 'queued']);
        $this->assertEquals(1, IeEdit::count());
        Queue::assertPushed(ApplyImageEdit::class);
    }

    public function test_deducts_credits_on_apply(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits' => 1000]);
        $this->actingAs($user);
        $this->createTestSession($user);

        $this->post(route('addon.ie.user.apply'), ['operation' => 'inpaint', 'params' => ['prompt' => 'test']]);

        $user->refresh();
        $this->assertEquals(985, $user->credits);
    }

    public function test_402_when_insufficient_credits(): void
    {
        $user = User::factory()->create(['credits' => 0]);
        $this->actingAs($user);
        $this->createTestSession($user);

        $response = $this->post(route('addon.ie.user.apply'), ['operation' => 'inpaint', 'params' => ['prompt' => 'test']]);

        $response->assertStatus(402);
    }

    public function test_increments_version_number_sequentially(): void
    {
        Queue::fake();
        $user = User::factory()->create(['credits' => 1000]);
        $this->actingAs($user);
        $session = $this->createTestSession($user);

        $this->post(route('addon.ie.user.apply'), ['operation' => 'bg_remove']);
        $this->post(route('addon.ie.user.apply'), ['operation' => 'bg_remove']);

        $this->assertEquals(1, IeEdit::orderBy('id')->first()->version_number);
        $this->assertEquals(2, IeEdit::orderBy('id')->latest('id')->first()->version_number);
    }

    public function test_requires_prompt_for_inpaint(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $this->actingAs($user);

        $response = $this->post(route('addon.ie.user.apply'), ['operation' => 'inpaint']);

        $response->assertSessionHasErrors('params.prompt');
    }

    public function test_requires_style_image_for_style_transfer(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $this->actingAs($user);

        $response = $this->post(route('addon.ie.user.apply'), ['operation' => 'style_transfer']);

        $response->assertSessionHasErrors('style_image');
    }

    // ─── JOB TESTS ───

    public function test_marks_edit_as_completed_and_marks_current(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $session = $this->createTestSession($user);

        $edit = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => $user->id,
            'operation' => 'bg_remove',
            'status' => 'queued',
            'provider' => 'remove_bg',
            'input_path' => $session->current_path,
            'credits_deducted' => 5,
            'version_number' => 1,
        ]);

        $job = new ApplyImageEdit($edit->id);
        $job->handle(app(ImageEditorService::class));

        $edit->refresh();
        $this->assertEquals('completed', $edit->status);
        $this->assertNotNull($edit->output_path);
        $this->assertTrue($edit->is_current);
    }

    public function test_marks_edit_failed_and_refunds_on_exception(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $session = $this->createTestSession($user);

        $edit = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => $user->id,
            'operation' => 'inpaint',
            'status' => 'queued',
            'provider' => 'stability',
            'input_path' => $session->current_path,
            'mask_path' => $session->current_path,
            'params' => ['prompt' => 'test'],
            'credits_deducted' => 15,
            'version_number' => 1,
        ]);

        // Force Stability to fail
        settings_set('addon_ai-image-editor_stability_api_key', 'bad-key', 'encrypted');
        Http::fake(['api.stability.ai/*' => Http::response('Error', 401)]);

        $job = new ApplyImageEdit($edit->id);
        $job->handle(app(ImageEditorService::class));

        $edit->refresh();
        $this->assertEquals('failed', $edit->status);

        $user->refresh();
        $this->assertEquals(1015, $user->credits);
    }

    public function test_broadcasts_event_on_completion(): void
    {
        Event::fake();
        $user = User::factory()->create(['credits' => 1000]);
        $session = $this->createTestSession($user);

        $edit = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => $user->id,
            'operation' => 'bg_remove',
            'status' => 'queued',
            'provider' => 'remove_bg',
            'input_path' => $session->current_path,
            'credits_deducted' => 5,
            'version_number' => 1,
        ]);

        $job = new ApplyImageEdit($edit->id);
        $job->handle(app(ImageEditorService::class));

        Event::assertDispatched(ImageEditCompleted::class);
    }

    public function test_refunds_credits_on_job_failed(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $session = $this->createTestSession($user);

        $edit = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => $user->id,
            'operation' => 'bg_remove',
            'status' => 'queued',
            'provider' => 'remove_bg',
            'input_path' => $session->current_path,
            'credits_deducted' => 10,
            'version_number' => 1,
        ]);

        $job = new ApplyImageEdit($edit->id);
        $job->failed(new \Exception('Job crashed'));

        $user->refresh();
        $this->assertEquals(1010, $user->credits);
    }

    // ─── PROVIDER ROUTING ───

    public function test_routes_bg_remove_to_removebg_client(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $session = $this->createTestSession($user);

        $edit = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => $user->id,
            'operation' => 'bg_remove',
            'status' => 'queued',
            'provider' => 'remove_bg',
            'input_path' => $session->current_path,
            'version_number' => 1,
        ]);

        $job = new ApplyImageEdit($edit->id);
        $job->handle(app(ImageEditorService::class));

        $edit->refresh();
        $this->assertEquals('completed', $edit->status);
    }

    public function test_throws_on_unknown_operation(): void
    {
        $this->expectException(ImageEditException::class);

        $edit = new IeEdit([
            'operation' => 'not_an_op',
            'input_path' => 'test.png',
        ]);

        app(ImageEditorService::class)->apply($edit);
    }

    // ─── GD SERVICE ───

    public function test_applies_color_correction_and_saves(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available.');
        }

        $sourcePath = base_path('tests/stubs/test.png');
        $inputPath = 'image-editor/test/cc-input.png';
        Storage::disk('local')->put($inputPath, file_get_contents($sourcePath));

        $outputPath = 'image-editor/test/cc-output.png';
        $service = app(GdEditService::class);
        $result = $service->colorCorrection($inputPath, ['brightness' => 20], $outputPath);

        $this->assertEquals($outputPath, $result);
        $this->assertTrue(Storage::disk('local')->exists($outputPath));
    }

    public function test_applies_text_overlay_and_saves(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available.');
        }

        $sourcePath = base_path('tests/stubs/test.png');
        $inputPath = 'image-editor/test/text-input.png';
        Storage::disk('local')->put($inputPath, file_get_contents($sourcePath));

        $outputPath = 'image-editor/test/text-output.png';
        $service = app(GdEditService::class);
        $result = $service->textOverlay($inputPath, [
            'text' => 'Hello',
            'font_size' => 12,
            'font_color' => '#FFFFFF',
            'x' => 0,
            'y' => 10,
        ], $outputPath);

        $this->assertEquals($outputPath, $result);
        $this->assertTrue(Storage::disk('local')->exists($outputPath));
    }

    // ─── VERSION / HISTORY ───

    public function test_revert_sets_previous_edit_as_current(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $session = $this->createTestSession($user);
        $this->actingAs($user);

        $edit1 = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => $user->id,
            'operation' => 'color_correction',
            'status' => 'completed',
            'input_path' => $session->current_path,
            'output_path' => $session->current_path,
            'output_url' => Storage::url($session->current_path),
            'is_current' => false,
            'version_number' => 1,
        ]);

        $edit2 = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => $user->id,
            'operation' => 'color_correction',
            'status' => 'completed',
            'input_path' => $session->current_path,
            'output_path' => $session->current_path,
            'output_url' => Storage::url($session->current_path),
            'is_current' => true,
            'version_number' => 2,
        ]);

        $response = $this->post(route('addon.ie.user.edits.revert', ['edit' => $edit1->id]));

        $response->assertJson(['success' => true, 'version' => 1]);
        $edit1->refresh();
        $edit2->refresh();
        $this->assertTrue($edit1->is_current);
        $this->assertFalse($edit2->is_current);
    }

    public function test_422_reverting_current_edit(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $session = $this->createTestSession($user);
        $this->actingAs($user);

        $edit = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => $user->id,
            'operation' => 'color_correction',
            'status' => 'completed',
            'input_path' => $session->current_path,
            'output_path' => $session->current_path,
            'output_url' => Storage::url($session->current_path),
            'is_current' => true,
            'version_number' => 1,
        ]);

        $this->post(route('addon.ie.user.edits.revert', ['edit' => $edit->id]))
            ->assertStatus(422);
    }

    // ─── PROVIDER AVAILABILITY ───

    public function test_is_provider_configured_for_local_operations(): void
    {
        $service = app(ImageEditorService::class);
        $this->assertTrue($service->isProviderConfigured('color_correction'));
        $this->assertTrue($service->isProviderConfigured('text_overlay'));
    }

    public function test_is_provider_configured_false_when_key_missing(): void
    {
        settings_set('addon_ai-image-editor_stability_api_key', null, 'encrypted');

        $service = app(ImageEditorService::class);
        $this->assertFalse($service->isProviderConfigured('inpaint'));
    }

    public function test_get_credits_for_operation(): void
    {
        $service = app(ImageEditorService::class);

        $this->assertEquals(15, $service->getCreditsForOperation('inpaint'));
        $this->assertEquals(5, $service->getCreditsForOperation('bg_remove'));
        $this->assertEquals(20, $service->getCreditsForOperation('upscale'));
        $this->assertEquals(0, $service->getCreditsForOperation('color_correction'));
        $this->assertEquals(0, $service->getCreditsForOperation('text_overlay'));
    }

    // ─── AUTHORIZATION ───

    public function test_403_for_edit_belonging_to_another_user(): void
    {
        $user1 = User::factory()->create(['credits' => 1000]);
        $user2 = User::factory()->create(['credits' => 1000]);
        $session = $this->createTestSession($user2);

        $edit = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => $user2->id,
            'operation' => 'bg_remove',
            'status' => 'completed',
            'input_path' => $session->current_path,
            'output_path' => $session->current_path,
            'output_url' => Storage::url($session->current_path),
            'is_current' => true,
            'version_number' => 1,
        ]);

        $this->actingAs($user1);
        $this->post(route('addon.ie.user.edits.revert', ['edit' => $edit->id]))
            ->assertStatus(403);
    }

    public function test_422_download_processing_edit(): void
    {
        $user = User::factory()->create(['credits' => 1000]);
        $session = $this->createTestSession($user);

        $edit = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => $user->id,
            'operation' => 'bg_remove',
            'status' => 'processing',
            'input_path' => $session->current_path,
            'version_number' => 1,
        ]);

        $this->actingAs($user);
        $this->get(route('addon.ie.user.edits.download', ['edit' => $edit->id]))
            ->assertStatus(422);
    }
}
