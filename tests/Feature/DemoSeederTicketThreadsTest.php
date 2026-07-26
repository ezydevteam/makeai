<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTicketThreadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_demo_tickets_get_status_appropriate_threads(): void
    {
        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
        // Re-running is how the demo site resets itself every 6 hours.
        $this->seed(DemoSeeder::class);

        $tickets = SupportTicket::with('replies')
            ->where('ticket_number', 'like', 'DEMO-TKT-0%')
            ->orderBy('id')
            ->get();

        $this->assertCount(12, $tickets);

        foreach ($tickets as $ticket) {
            $public = $ticket->replies->where('is_internal_note', false)->values();
            $expected = ['open' => 1, 'in_progress' => 3, 'resolved' => 4, 'closed' => 4][$ticket->status];

            $this->assertCount($expected, $public, "{$ticket->ticket_number} ({$ticket->status}) thread length");
            $this->assertSame('user', $public->first()->author_type, 'a ticket opens with the customer');

            $times = $ticket->replies->pluck('created_at')->map->timestamp->all();
            $sorted = $times;
            sort($sorted);
            $this->assertSame($sorted, $times, "{$ticket->ticket_number} replies are out of order");

            $this->assertSame($ticket->last_reply_at->timestamp, $public->last()->created_at->timestamp);
            $this->assertSame($ticket->last_reply_by, $public->last()->author_type);

            $firstAdmin = $public->firstWhere('author_type', 'admin');
            $this->assertSame($firstAdmin?->created_at?->timestamp, $ticket->first_response_at?->timestamp);
            // Unanswered tickets stay unassigned; answered ones carry an admins.id.
            $this->assertSame($firstAdmin === null, $ticket->assigned_to === null);

            if ($ticket->assigned_to !== null) {
                $this->assertNotNull(\App\Models\Admin::find($ticket->assigned_to), 'assigned_to must reference an admin');
            }

            // Backdating survives updateOrCreate (created_at is not fillable).
            $this->assertTrue($ticket->created_at->lt(now()->subDay()), "{$ticket->ticket_number} was not backdated");
        }

        // Internal notes exist, are admin-authored, and never count as the last reply.
        $notes = $tickets->flatMap->replies->where('is_internal_note', true);
        $this->assertNotEmpty($notes);
        foreach ($notes as $note) {
            $this->assertSame('admin', $note->author_type);
            $this->assertTrue($note->ticket->last_reply_at->timestamp !== $note->created_at->timestamp);
        }

        // The in_progress tickets are the ones the customer has replied to since support
        // last answered, so the admin queue shows them as unread.
        $inProgress = $tickets->firstWhere('status', 'in_progress');
        $this->assertTrue($inProgress->admin_last_read_at->lt($inProgress->last_reply_at));

        $open = $tickets->firstWhere('status', 'open');
        $this->assertNull($open->admin_last_read_at);
        $this->assertNull($open->first_response_at);

        // The showcase account's own inbox is seeded by the same run and is not duplicated
        // by the second pass.
        $showcase = SupportTicket::with('replies')
            ->whereHas('user', fn ($query) => $query->where('email', config('demo.user_email')))
            ->get();

        $this->assertCount(6, $showcase);
        $this->assertSame(6, $showcase->filter(fn ($ticket) => $ticket->replies->isNotEmpty())->count());
    }
}
