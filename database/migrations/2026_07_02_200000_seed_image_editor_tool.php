<?php

use App\Models\AiTool;
use App\Models\Category;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $category = Category::updateOrCreate(
            ['slug' => 'design-graphics', 'type' => 'ai_tool'],
            [
                'name' => '🎨 Design & Graphics',
                'description' => 'Edit images, apply style transfer, remove backgrounds, and enhance visual assets.',
                'icon' => 'ti ti-photo-edit',
                'color' => '#3b82f6',
                'is_active' => true,
                'is_system' => false,
                'requires_pro' => false,
                'sort_order' => 26,
            ]
        );

        AiTool::updateOrCreate(
            ['slug' => 'image-editor'],
            [
                'name' => 'Image Editor',
                'type' => 'template',
                'description' => 'Modify images with AI: background removal, upscale, inpainting, object removal, and quick presets.',
                'category_id' => $category->id,
                'icon' => 'ti ti-photo-edit',
                'color' => '#3b82f6',
                'fields' => json_encode([]),
                'output_type' => 'image',
                'access_level' => 'login_required',
                'is_active' => true,
                'is_featured' => true,
                'requires_pro' => false,
                'is_system' => false,
                'supports_brand_voice' => false,
                'avg_output_tokens' => 0,
                'sort_order' => 1,
                'about_content' => 'The AI Image Editor lets you modify images using state of the art models: apply styles, upscale resolution, fill/outpaint, and perform local color corrections.',
                'how_it_works' => json_encode([
                    ['step' => 1, 'title' => 'Select/Upload Image', 'description' => 'Upload a photo or choose an existing one.'],
                    ['step' => 2, 'title' => 'Choose Operation', 'description' => 'Select background removal, upscale, inpainting, or filters.'],
                    ['step' => 3, 'title' => 'Apply & Download', 'description' => 'Run the AI edit and download or save the result.'],
                ]),
                'meta_title' => 'AI Image Editor — Edit & Enhance Images',
                'meta_description' => 'Apply AI inpainting, outpainting, background removal, upscaling, and styling to your images.',
            ]
        );

        $category->updateToolsCount();
        ToolCatalogCacheService::invalidateAll();
    }

    public function down(): void
    {
        AiTool::where('slug', 'image-editor')->delete();
        Category::where('slug', 'design-graphics')->delete();
        ToolCatalogCacheService::invalidateAll();
    }
};
