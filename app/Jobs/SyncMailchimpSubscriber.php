<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncMailchimpSubscriber implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    public function __construct(
        protected string $email,
        protected ?string $name,
        protected string $status
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $apiKey = settings('mailchimp_api_key');
        $serverPrefix = settings('mailchimp_server_prefix');
        $listId = settings('mailchimp_list_id');

        if (! $apiKey || ! $serverPrefix || ! $listId) {
            return;
        }

        $doubleOptin = (bool) settings('mailchimp_double_optin', false);
        $tagsString = settings('mailchimp_tags', '');
        $tags = array_filter(array_map('trim', explode(',', $tagsString)));
        $subscriberHash = md5(strtolower($this->email));
        $url = "https://{$serverPrefix}.api.mailchimp.com/3.0/lists/{$listId}/members/{$subscriberHash}";
        $mailchimpStatus = $this->status === 'subscribed' ? ($doubleOptin ? 'pending' : 'subscribed') : 'unsubscribed';

        $payload = [
            'email_address' => $this->email,
            'status_if_new' => $mailchimpStatus,
            'status' => $mailchimpStatus,
        ];

        if ($this->name) {
            $payload['merge_fields'] = ['FNAME' => $this->name];
        }

        if ($tags !== []) {
            $payload['tags'] = $tags;
        }

        try {
            Http::withBasicAuth('makeai', $apiKey)->put($url, $payload)->throw();
        } catch (\Throwable $e) {
            Log::error('Mailchimp sync failed: '.$e->getMessage(), [
                'email' => $this->email,
                'status' => $this->status,
            ]);

            throw $e;
        }
    }
}
