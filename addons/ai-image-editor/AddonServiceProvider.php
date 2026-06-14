<?php

declare(strict_types=1);

namespace Addons\AiImageEditor;

use Addons\AiImageEditor\Services\GdEditService;
use Addons\AiImageEditor\Services\ImageEditorService;
use Addons\AiImageEditor\Services\Providers\ClipdropClient;
use Addons\AiImageEditor\Services\Providers\RemoveBgClient;
use Addons\AiImageEditor\Services\Providers\ReplicateClient;
use Addons\AiImageEditor\Services\Providers\StabilityClient;
use App\Services\AddonHookService;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StabilityClient::class);
        $this->app->singleton(ReplicateClient::class);
        $this->app->singleton(RemoveBgClient::class);
        $this->app->singleton(ClipdropClient::class);
        $this->app->singleton(GdEditService::class);
        $this->app->singleton(ImageEditorService::class);
    }

    public function boot(): void
    {
        if (! is_addon_active('ai-image-editor')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Inertia::share('imageEditor', function () {
            return [
                'enabled' => (bool) addon_setting('ai-image-editor', 'enabled', true),
                'creditCosts' => [
                    'inpaint'          => (int) addon_setting('ai-image-editor', 'credits_inpaint', 15),
                    'outpaint'         => (int) addon_setting('ai-image-editor', 'credits_outpaint', 15),
                    'bg_remove'        => (int) addon_setting('ai-image-editor', 'credits_bg_remove', 5),
                    'upscale'          => (int) addon_setting('ai-image-editor', 'credits_upscale', 20),
                    'style_transfer'   => (int) addon_setting('ai-image-editor', 'credits_style_transfer', 20),
                    'object_remove'    => (int) addon_setting('ai-image-editor', 'credits_object_remove', 15),
                    'color_correction' => (int) addon_setting('ai-image-editor', 'credits_color_correction', 0),
                    'text_overlay'     => (int) addon_setting('ai-image-editor', 'credits_text_overlay', 0),
                ],
            ];
        });

        $this->hookEditButton();
    }

    private function hookEditButton(): void
    {
        /** @var AddonHookService $hooks */
        $hooks = app(AddonHookService::class);

        $hooks->listen('makeai.image.card.actions', function ($image) {
            return [
                'label' => translate('Edit Image'),
                'route' => route('addon.ie.user.editor', ['image' => $image->ulid ?? $image->id]),
                'icon'  => 'ti-photo-edit',
            ];
        });
    }
}
