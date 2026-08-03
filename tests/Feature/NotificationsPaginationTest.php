<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Notified',
            'email' => 'notified@test.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        // 20 notifications (15 per page), 8 of them unread.
        foreach (range(1, 20) as $index) {
            $createdAt = now()->subMinutes($index);

            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => \App\Notifications\InAppNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $this->user->id,
                'data' => json_encode([
                    'title' => "Notice {$index}",
                    'message' => 'Body',
                    'category' => 'system',
                    'level' => 'info',
                    'icon' => 'ti ti-bell',
                ]),
                'status' => $index <= 8 ? 'unread' : 'read',
                'read_at' => $index <= 8 ? null : $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function props(string $url): array
    {
        return $this->actingAs($this->user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get($url)
            ->assertOk()
            ->viewData('page')['props'];
    }

    public function test_pagination_payload_carries_the_shared_component_props(): void
    {
        $props = $this->props('/user/dashboard/notifications');
        $pagination = $props['notificationList'];

        $this->assertCount(15, $pagination['data']);
        $this->assertSame(20, $pagination['total']);
        $this->assertSame(2, $pagination['last_page']);
        // from/to feed the component's "showing X to Y of Z" row.
        $this->assertSame(1, $pagination['from']);
        $this->assertSame(15, $pagination['to']);
        $this->assertNotEmpty($pagination['links']);
    }

    public function test_reseeding_does_not_duplicate_demo_notifications(): void
    {
        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->seed(\Database\Seeders\DemoSeeder::class);

        $demoUser = User::where('email', config('demo.user_email'))->firstOrFail();
        $demoAdmin = \App\Models\Admin::where('email', config('demo.admin_email'))->firstOrFail();

        $count = fn ($type, $id) => DB::table('notifications')
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->count();

        $userFirst = $count(User::class, $demoUser->id);
        $adminFirst = $count(\App\Models\Admin::class, $demoAdmin->id);

        $this->assertGreaterThan(0, $userFirst);
        $this->assertGreaterThan(0, $adminFirst);

        // A standalone re-seed used to append a second full set, so the bell listed every
        // notification twice.
        $this->seed(\Database\Seeders\DemoSeeder::class);

        $this->assertSame($userFirst, $count(User::class, $demoUser->id));
        $this->assertSame($adminFirst, $count(\App\Models\Admin::class, $demoAdmin->id));

        // And each row is a distinct notification, not the same title repeated.
        $titles = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $demoUser->id)
            ->pluck('data')
            ->map(fn ($data) => json_decode($data, true)['title'] ?? null);

        $this->assertSame($titles->count(), $titles->unique()->count(), 'duplicate notification titles');
    }

    public function test_paging_keeps_the_status_filter(): void
    {
        $props = $this->props('/user/dashboard/notifications?status=unread');

        $this->assertSame(8, $props['notificationList']['total']);
        $this->assertSame('unread', $props['filters']['status']);

        // Every page link must carry the filter, or page 2 silently returns to the full list.
        foreach ($props['notificationList']['links'] as $link) {
            if ($link['url']) {
                $this->assertStringContainsString('status=unread', $link['url']);
            }
        }
    }
}
