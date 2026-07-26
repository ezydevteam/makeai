<?php

namespace Tests\Feature;

use App\Models\SupportDepartment;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_ticket_list_carries_the_shared_pagination_props(): void
    {
        $user = User::create([
            'name' => 'Ticket Holder',
            'email' => 'holder@test.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $department = SupportDepartment::create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        settings_set('tickets_enabled', '1', 'boolean', 'support');

        // 20 tickets at 15 per page.
        foreach (range(1, 20) as $index) {
            SupportTicket::create([
                'ticket_number' => 'TKT-TEST-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'user_id' => $user->id,
                'department_id' => $department->id,
                'subject' => "Question {$index}",
                'status' => $index <= 5 ? 'open' : 'closed',
                'priority' => 'medium',
                'source' => 'web',
                'last_reply_at' => now()->subMinutes($index),
            ]);
        }

        $props = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/support')
            ->assertOk()
            ->viewData('page')['props'];

        $tickets = $props['tickets'];

        $this->assertCount(15, $tickets['data']);
        $this->assertSame(20, $tickets['total']);
        $this->assertSame(2, $tickets['last_page']);
        // from/to feed the component's "showing X to Y of Z" row.
        $this->assertSame(1, $tickets['from']);
        $this->assertSame(15, $tickets['to']);

        // Paging while filtered must keep the filter.
        $filtered = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/support?status=open')
            ->assertOk()
            ->viewData('page')['props']['tickets'];

        $this->assertSame(5, $filtered['total']);

        foreach ($filtered['links'] as $link) {
            if ($link['url']) {
                $this->assertStringContainsString('status=open', $link['url']);
            }
        }
    }
}
