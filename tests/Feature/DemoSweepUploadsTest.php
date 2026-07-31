<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * demo:reset runs migrate:fresh, which drops the rows pointing at the previous window's
 * uploads and leaves the files on disk. Without this sweep the demo host accumulates a fresh
 * batch of orphans every six hours, forever — the database is flat by construction, the
 * filesystem was not.
 *
 * What is locked here is the blast radius on both sides: that visitor content actually goes,
 * and that neither a real install nor a misconfigured media root can be emptied by it.
 */
class DemoSweepUploadsTest extends TestCase
{
    private function fakeDisks(): void
    {
        Storage::fake('local_public_media');
        Storage::fake('local');
    }

    /** The tree a six-hour demo window leaves behind, across both roots. */
    private function seedOrphans(): void
    {
        Storage::disk('local_public_media')->put('rag-uploads/41/contract.pdf', 'x');
        Storage::disk('local_public_media')->put('avatars/user-812.png', 'x');
        Storage::disk('local_public_media')->put('ai-image-pro/demo/aurora-portrait.svg', '<svg/>');
        Storage::disk('local_public_media')->put('stray-at-root.png', 'x');

        Storage::disk('local')->put('payment-proofs/receipt-9.jpg', 'x');
        Storage::disk('local')->put('chat-attachments/812/notes.txt', 'x');
    }

    public function test_it_deletes_orphaned_uploads_on_both_disks(): void
    {
        config(['demo.enabled' => true]);
        $this->fakeDisks();
        $this->seedOrphans();

        $this->artisan('demo:sweep-uploads')->assertSuccessful();

        Storage::disk('local_public_media')->assertMissing('rag-uploads/41/contract.pdf');
        Storage::disk('local_public_media')->assertMissing('avatars/user-812.png');
        Storage::disk('local_public_media')->assertMissing('stray-at-root.png');
        Storage::disk('local')->assertMissing('payment-proofs/receipt-9.jpg');
        Storage::disk('local')->assertMissing('chat-attachments/812/notes.txt');
    }

    /**
     * The seeder's own artwork is not spared — it is rewritten by DemoSeeder moments later,
     * and sparing it would mean maintaining a list of every directory a seeder or addon
     * writes to, which is the enumeration this command exists to avoid.
     */
    public function test_it_deletes_seeder_owned_media_too(): void
    {
        config(['demo.enabled' => true]);
        $this->fakeDisks();
        $this->seedOrphans();

        $this->artisan('demo:sweep-uploads')->assertSuccessful();

        Storage::disk('local_public_media')->assertMissing('ai-image-pro/demo/aurora-portrait.svg');
    }

    /**
     * PUBLIC_DISK_ROOT has resolved to the wrong directory on misconfigured installs before,
     * which would put Laravel's own trees under the media root and in the path of the sweep.
     */
    public function test_it_preserves_framework_directories_and_gitkeep(): void
    {
        config(['demo.enabled' => true]);
        $this->fakeDisks();

        Storage::disk('local_public_media')->put('.gitkeep', '');
        Storage::disk('local')->put('.gitignore', "*\n!.gitignore\n");
        Storage::disk('local_public_media')->put('framework/sessions/abc', 'session');
        Storage::disk('local_public_media')->put('logs/laravel.log', 'log');
        Storage::disk('local_public_media')->put('__probe/health', 'ok');
        Storage::disk('local_public_media')->put('avatars/user-812.png', 'x');

        $this->artisan('demo:sweep-uploads')->assertSuccessful();

        Storage::disk('local_public_media')->assertExists('.gitkeep');
        Storage::disk('local')->assertExists('.gitignore');
        Storage::disk('local_public_media')->assertExists('framework/sessions/abc');
        Storage::disk('local_public_media')->assertExists('logs/laravel.log');
        Storage::disk('local_public_media')->assertExists('__probe/health');
        Storage::disk('local_public_media')->assertMissing('avatars/user-812.png');
    }

    public function test_it_refuses_outside_demo_mode(): void
    {
        config(['demo.enabled' => false]);
        $this->fakeDisks();
        $this->seedOrphans();

        $this->artisan('demo:sweep-uploads')->assertFailed();

        Storage::disk('local_public_media')->assertExists('rag-uploads/41/contract.pdf');
        Storage::disk('local')->assertExists('payment-proofs/receipt-9.jpg');
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        config(['demo.enabled' => true]);
        $this->fakeDisks();
        $this->seedOrphans();

        $this->artisan('demo:sweep-uploads', ['--dry-run' => true])->assertSuccessful();

        Storage::disk('local_public_media')->assertExists('rag-uploads/41/contract.pdf');
        Storage::disk('local_public_media')->assertExists('stray-at-root.png');
        Storage::disk('local')->assertExists('chat-attachments/812/notes.txt');
    }
}
