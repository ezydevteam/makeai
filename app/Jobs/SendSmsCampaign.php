<?php

namespace App\Jobs;

use App\Models\SmsCampaign;
use App\Models\SmsCampaignRecipient;
use App\Services\SmsService;
use App\Support\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a bulk SMS campaign to its pre-built recipient rows.
 *
 * Failures are recorded per recipient and never abort the batch, so one bad number
 * can't strand the rest. Re-dispatching with $retryFailed only re-sends recipients
 * that previously failed.
 */
class SendSmsCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        protected int $campaignId,
        protected bool $retryFailed = false,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $campaign = SmsCampaign::find($this->campaignId);

        if (! $campaign || ($campaign->status === 'sent' && ! $this->retryFailed)) {
            return;
        }

        // Optimistic lock: only one job may move a campaign into 'sending', so a
        // double dispatch can never text everyone twice.
        if (! $this->retryFailed) {
            $claimed = SmsCampaign::where('id', $this->campaignId)
                ->whereNotIn('status', ['sending', 'sent'])
                ->update(['status' => 'sending', 'started_at' => now()]);

            if (! $claimed) {
                return;
            }
        }

        $sms = SmsService::fromSettings();

        // The gateway can be switched off between queueing and running; leave the
        // recipients pending rather than marking them failed, so a retry can send.
        if (! $sms->isEnabled()) {
            $campaign->update(['status' => 'draft', 'started_at' => null]);

            return;
        }

        $body = SmsMessage::compose($campaign->message, $campaign->action_url, $campaign->action_label);

        $campaign->recipients()
            ->where('status', $this->retryFailed ? 'failed' : 'pending')
            ->orderBy('id')
            ->lazy(200)
            ->each(function (SmsCampaignRecipient $recipient) use ($sms, $body, $campaign) {
                $result = $sms->send($recipient->phone, $body);
                $success = (bool) ($result['success'] ?? false);

                // A retry that succeeds must also undo the earlier failure tally.
                $wasFailed = $recipient->status === 'failed';

                $recipient->update([
                    'status' => $success ? 'sent' : 'failed',
                    'error_message' => $success ? null : ($result['error'] ?? 'Unknown error'),
                    'sent_at' => $success ? now() : null,
                ]);

                if ($success) {
                    $campaign->increment('sent_count');
                    if ($wasFailed) {
                        $campaign->decrement('failed_count');
                    }
                } elseif (! $wasFailed) {
                    $campaign->increment('failed_count');
                }
            });

        $campaign->update(['status' => 'sent', 'finished_at' => now()]);
    }
}
