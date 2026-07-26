<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\SupportDepartment;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoShowcaseSupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_showcase_support_tickets_are_seeded_with_threads(): void
    {
        config(['demo.admin_email' => 'admin@demo.test']);

        $role = AdminRole::create(['name' => 'Support', 'slug' => 'support']);
        Admin::create([
            'name' => 'Demo Administrator',
            'email' => 'admin@demo.test',
            'password' => 'secret-hash',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        foreach (['general', 'technical', 'billing', 'feature-request'] as $index => $slug) {
            SupportDepartment::create([
                'name' => ucfirst($slug),
                'slug' => $slug,
                'is_active' => true,
                'sort_order' => $index * 10,
            ]);
        }

        $user = User::create([
            'name' => 'Demo Creator',
            'email' => 'creator@demo.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $seeder = new DemoSeeder();
        $method = new \ReflectionMethod($seeder, 'seedShowcaseSupportTickets');
        $method->setAccessible(true);
        $method->invoke($seeder, $user);
        // Idempotency: a second pass must not duplicate or collide on ticket_number.
        $method->invoke($seeder, $user);

        $tickets = SupportTicket::with('replies')->where('user_id', $user->id)->get();

        $this->assertCount(6, $tickets);
        $this->assertEqualsCanonicalizing(
            ['open', 'open', 'in_progress', 'waiting_user', 'resolved', 'closed'],
            $tickets->pluck('status')->all()
        );

        foreach ($tickets as $ticket) {
            $this->assertNotEmpty($ticket->replies, "{$ticket->ticket_number} has no replies");
            $this->assertTrue($ticket->created_at->lt(now()->subHour()) || $ticket->ticket_number === 'DEMO-TKT-1006');

            $times = $ticket->replies->pluck('created_at')->map->timestamp->all();
            $sorted = $times;
            sort($sorted);
            $this->assertSame($sorted, $times, "{$ticket->ticket_number} replies are out of order");
            $this->assertTrue($ticket->replies->first()->created_at->gte($ticket->created_at->subMinute()));
            $this->assertSame($ticket->last_reply_at->timestamp, end($times));
        }

        $waiting = $tickets->firstWhere('ticket_number', 'DEMO-TKT-1004');
        $this->assertSame('admin', $waiting->last_reply_by);
        $this->assertTrue($waiting->user_last_read_at->lt($waiting->last_reply_at), 'unread thread should read as unread');

        $resolved = $tickets->firstWhere('ticket_number', 'DEMO-TKT-1002');
        $this->assertNull($resolved->satisfaction_rating);
        $this->assertNotNull($resolved->resolved_at);

        $closed = $tickets->firstWhere('ticket_number', 'DEMO-TKT-1001');
        $this->assertSame(5, $closed->satisfaction_rating);
        $this->assertNotNull($closed->closed_at);

        $fresh = $tickets->firstWhere('ticket_number', 'DEMO-TKT-1006');
        $this->assertNull($fresh->first_response_at);
        $this->assertNull($fresh->assigned_to);

        // The seeded rows have to survive the real user-facing controller, not just the DB.
        settings_set('tickets_enabled', '1', 'boolean', 'support');

        $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/support')
            ->assertOk();

        $response = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/support/tickets/DEMO-TKT-1004');

        $response->assertOk();
        $payload = $response->viewData('page')['props']['ticket'];
        $this->assertCount(2, $payload['replies']);
        $this->assertSame('Demo Administrator', $payload['replies'][1]['author_name']);
    }
}
