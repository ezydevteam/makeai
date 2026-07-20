<?php

namespace Addons\FakerAi;

use Addons\FakerAi\Generators\BlogCommentsGenerator;
use Addons\FakerAi\Generators\BlogSharesGenerator;
use Addons\FakerAi\Generators\BlogViewsGenerator;
use Addons\FakerAi\Generators\KbRatingsGenerator;
use Addons\FakerAi\Generators\TestimonialsGenerator;
use Addons\FakerAi\Generators\ToolFavoritesGenerator;
use Addons\FakerAi\Generators\ToolReviewsGenerator;
use Addons\FakerAi\Generators\ToolUsageGenerator;
use Addons\FakerAi\Generators\UsersGenerator;
use Addons\FakerAi\Services\GeneratorRegistry;
use Illuminate\Support\ServiceProvider;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The registry is a singleton so the controller, the job and the tests all see the
        // same set of generators. Each generator is a plain resolvable class — adding a new
        // fake-data type later is just one more line here plus its class.
        $this->app->singleton(GeneratorRegistry::class, function ($app) {
            $registry = new GeneratorRegistry;

            $registry->register($app->make(UsersGenerator::class));
            $registry->register($app->make(TestimonialsGenerator::class));
            $registry->register($app->make(ToolReviewsGenerator::class));
            $registry->register($app->make(ToolUsageGenerator::class));
            $registry->register($app->make(ToolFavoritesGenerator::class));
            $registry->register($app->make(BlogCommentsGenerator::class));
            $registry->register($app->make(BlogViewsGenerator::class));
            $registry->register($app->make(BlogSharesGenerator::class));
            $registry->register($app->make(KbRatingsGenerator::class));

            return $registry;
        });
    }

    public function boot(): void
    {
        // Routes loaded via loadRoutesFrom() do NOT inherit the 'web' group — the route file
        // wraps them itself (see routes/admin.php).
        $this->loadRoutesFrom(__DIR__.'/routes/admin.php');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
