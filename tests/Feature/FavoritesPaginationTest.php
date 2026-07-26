<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoritesPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorites_pagination_carries_the_shared_component_props(): void
    {
        $user = User::create([
            'name' => 'Collector',
            'email' => 'collector@test.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        // 30 favorites so the list paginates (24 per page).
        foreach (range(1, 30) as $index) {
            $tool = AiTool::create([
                'name' => "Tool {$index}",
                'slug' => "tool-{$index}",
                'description' => 'Demo',
                'prompt_system' => 'system',
                'prompt_user' => 'user',
                'fields' => [],
                'is_active' => true,
            ]);

            Favorite::create([
                'user_id' => $user->id,
                'favoriteable_type' => AiTool::class,
                'favoriteable_id' => $tool->id,
            ]);
        }

        $props = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/favorites')
            ->assertOk()
            ->viewData('page')['props'];

        $pagination = $props['pagination'];

        $this->assertSame(30, $pagination['total']);
        $this->assertSame(2, $pagination['last_page']);
        // from/to feed the shared Pagination component's "showing X to Y of Z" line.
        $this->assertSame(1, $pagination['from']);
        $this->assertSame(24, $pagination['to']);
        $this->assertNotEmpty($pagination['links']);
    }
}
