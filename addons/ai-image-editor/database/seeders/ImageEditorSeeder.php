<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Database\Seeders;

use Illuminate\Database\Seeder;

class ImageEditorSeeder extends Seeder
{
    public function run(): void
    {
        // No demo data needed — AI Image Editor works with existing images
        // from the core generated_images library.
        //
        // Prerequisites for testing:
        // 1. Core image generator must be configured (at least one provider API key set)
        // 2. At least one image must exist in the user's image library
        // 3. For AI operations: at least one of these API keys must be set in Admin → Settings:
        //    - Stability AI key (inpainting, outpainting, style transfer, object removal)
        //    - Replicate key (upscaling, SD-based inpainting fallback)
        //    - Remove.bg key (background removal)
        //    - Clipdrop key (alternative bg removal, object removal)
        //
        // Color correction and text overlay work without any API keys.
        //
        // To open the editor: navigate to image library → click "Edit Image" on any image.
    }
}
