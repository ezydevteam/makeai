<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Masks / style references / img2img references are transient, user-PRIVATE uploads.
 * Both image addons store them on the `local` disk — they must never be written to, or
 * read from, the public media disk (which may be a world-readable cloud bucket).
 *
 * These were previously written to `local` but read back from `public`, so inpaint,
 * object-remove and style-transfer failed on EVERY driver. This pins the contract.
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

    public function test_image_editor_providers_read_mask_and_style_from_the_private_disk(): void
    {
        $stability = file_get_contents(base_path('addons/ai-image-editor/app/Services/Providers/StabilityClient.php'));
        $clipdrop = file_get_contents(base_path('addons/ai-image-editor/app/Services/Providers/ClipdropClient.php'));

        $this->assertStringContainsString("Storage::disk('local')->get(\$maskPath)", $stability);
        $this->assertStringContainsString("Storage::disk('local')->get(\$stylePath)", $stability);
        $this->assertStringContainsString("Storage::disk('local')->get(\$maskPath)", $clipdrop);

        // The SOURCE image is a public library asset and must still come from the public disk.
        $this->assertStringContainsString("Storage::disk('public')->get(\$imagePath)", $stability);
    }

    public function test_masks_are_never_written_to_the_public_disk(): void
    {
        foreach ([
            'addons/ai-image-pro/app/Http/Controllers/User/StudioController.php',
            'addons/ai-image-editor/app/Http/Controllers/User/ImageEditorController.php',
        ] as $file) {
            $src = file_get_contents(base_path($file));

            // Every mask/style store call must target the private disk.
            preg_match_all("/->store\(\s*[^;]*?(masks|styles)[^;]*?'(\w+)'\s*,?\s*\)/s", $src, $m);
            foreach ($m[2] ?? [] as $disk) {
                $this->assertSame('local', $disk, "Masks/styles must stay on the private disk in {$file}");
            }
        }
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
