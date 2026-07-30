<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Masks / style references / img2img references are transient, user-PRIVATE uploads.
 * ai-image-pro stores them on the `local` disk — they must never be written to, or read
 * from, the public media disk (which may be a world-readable cloud bucket).
 *
 * These were previously written to `local` but read back from `public`, so inpaint,
 * object-remove and style-transfer failed on EVERY driver. This pins the contract.
 *
 * The ai-image-editor half of this file went with the addon itself; only its assertions
 * were dropped, since the same contract still has to hold for the addon that remains.
 */
class PrivateMaskDiskTest extends TestCase
{
    public function test_image_pro_reads_mask_and_reference_from_the_private_disk(): void
    {
        $src = file_get_contents(base_path('addons/ai-image-pro/app/Services/ProviderOperationService.php'));

        $this->assertStringContainsString(
            "bytesOf('local', \$job->mask_path",
            $src,
            'The mask must be read from the private `local` disk it is written to (StudioController::storePrivate).',
        );
        $this->assertStringContainsString(
            "bytesOf('local', \$job->reference_path",
            $src,
            'The reference must be read from the private `local` disk it is written to.',
        );
    }

    public function test_masks_are_never_written_to_the_public_disk(): void
    {
        $file = 'addons/ai-image-pro/app/Http/Controllers/User/StudioController.php';
        $src = file_get_contents(base_path($file));

        // ai-image-pro funnels both transient uploads through one helper rather than
        // storing inline, so the disk is pinned in exactly one place. The old version of
        // this test scanned for a literal `masks`/`styles` inside the ->store() call — a
        // shape only ai-image-editor ever had — so once that addon went it matched nothing
        // and the test passed while asserting nothing at all.
        $this->assertMatchesRegularExpression(
            "/private function storePrivate\([^)]*\)[^{]*\{.*?->store\([^;]*,\s*'local'\s*\)/s",
            $src,
            "storePrivate() must write to the private `local` disk in {$file}",
        );

        // And both callers have to go through it — a direct store would bypass the pin above.
        foreach (['mask', 'reference'] as $input) {
            $this->assertStringContainsString(
                "\$this->storePrivate(\$request->file('{$input}')",
                $src,
                "The {$input} upload must be stored via storePrivate(), not written directly.",
            );
        }

        $this->assertStringNotContainsString(
            "->store(OperationRegistry::SLUG . '/' . \$owner . '/' . \$bucket, 'public')",
            $src,
            'Transient job inputs must never land on the public media disk.',
        );
    }

    public function test_private_disk_is_not_web_accessible(): void
    {
        // Sanity: the `local` disk must not be the rebindable public media disk.
        $this->assertNotSame(
            config('filesystems.disks.local.root'),
            config('filesystems.disks.public.root'),
        );
        $this->assertSame('local', config('filesystems.disks.local.driver'));
    }
}
