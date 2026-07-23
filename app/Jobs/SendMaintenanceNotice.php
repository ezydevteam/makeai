<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fans a maintenance notice out to the whole user base.
 *
 * The window's details are captured by the caller and passed in rather than read
 * here: the "back online" mail is sent after settings may have changed, and the
 * announcement should still describe the window users were actually told about.
 */
class SendMaintenanceNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    /**
     * @param  'maintenance_scheduled'|'maintenance_completed'  $slug
     * @param  array<string, string>  $context
     */
    public function __construct(
        protected string $slug,
        protected array $context = []
    ) {
        $this->onQueue('emails');
    }

    /**
     * Everyone a maintenance notice goes to. Shared with the admin screen so the
     * count it previews is the same set this job mails.
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public static function audience()
    {
        return User::query()
            ->where('is_active', true)
            ->where('is_banned', false)
            // The internal AI user is a billing construct with a synthetic address.
            ->where('is_internal', false)
            // An unverified address is one nobody has proven they can receive at;
            // a downtime blast is not the place to start bouncing off them.
            ->whereNotNull('email_verified_at');
    }

    public function handle(): void
    {
        // One SendTemplatedEmail per recipient rather than one giant send: each
        // gets its own MailLog row, retry, and notification-preference check, and
        // a single bad address cannot take the whole broadcast down.
        static::audience()
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    SendTemplatedEmail::dispatch($this->slug, $user->email, array_merge($this->context, [
                        'user_name' => $user->name,
                        'user_email' => $user->email,
                    ]))->onQueue('emails');
                }
            });
    }
}
