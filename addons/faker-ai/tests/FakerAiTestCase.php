<?php

namespace Addons\FakerAi\Tests;

use Addons\FakerAi\AddonServiceProvider;
use App\DTO\CompletionResponse;
use App\Http\Middleware\ThrottleAiRequests;
use App\Models\Addon;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Services\AI\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

/**
 * Shared bootstrap for FakerAI tests. Like the AI Assistant's case, it has to register the
 * addon by hand (the `addons` table doesn't exist when the app boots under RefreshDatabase) and
 * fake the AI so no test hits a real provider.
 */
abstract class FakerAiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Addon::updateOrCreate(['slug' => 'faker-ai'], [
            'name' => 'FakerAI',
            'version' => '1.0.0',
            'is_active' => true,
            'manifest' => json_decode(File::get(base_path('addons/faker-ai/addon.json')), true),
        ]);

        // Addon migrations live outside database/migrations, so migrate:fresh never ran them.
        // Core migrations (which created blog_posts) already ran via RefreshDatabase, so the
        // share_count column migration has its table.
        $this->artisan('migrate', ['--path' => 'addons/faker-ai/database/migrations']);

        require_once base_path('addons/faker-ai/AddonServiceProvider.php');
        $this->app->register(AddonServiceProvider::class);

        // The provider is registered after the router's booted() lookup refresh, so route()
        // names wouldn't resolve without forcing another refresh.
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $this->verifyLicense();
        $this->withoutMiddleware(ThrottleAiRequests::class);
    }

    protected function verifyLicense(): void
    {
        settings_set('license_status', 'valid', 'string', 'license');
        Cache::forget('license.status');
    }

    protected function actingAsAdmin(): Admin
    {
        $role = AdminRole::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'is_system' => true],
        );

        config(['auth.providers.admins.super_admin_slug' => 'super-admin']);

        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin+'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $admin->load('role');

        $this->actingAs($admin, 'admin');

        return $admin;
    }

    /**
     * Bind a fake AiService whose completion returns the given rows as a JSON array — the exact
     * shape FakeContentGenerator parses. Must be called before the request so the lazily-built
     * generator registry injects the fake.
     */
    protected function fakeAi(array $rows): void
    {
        $mock = Mockery::mock(AiService::class);
        $mock->shouldReceive('complete')->andReturn(new CompletionResponse(
            content: json_encode($rows),
            inputTokens: 50,
            outputTokens: 120,
            model: 'fake-model',
        ));

        $this->app->instance(AiService::class, $mock);
    }
}
