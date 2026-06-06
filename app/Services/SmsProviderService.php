<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SmsProviderService
{
    private ?string $accountSid;
    private ?string $authToken;

    public function __construct(?string $accountSid = null, ?string $authToken = null)
    {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
    }

    public static function fromSettings(): self
    {
        return new self(
            accountSid: settings('external_sms_provider_twilio_account_sid'),
            authToken: settings('external_sms_provider_twilio_auth_token'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->accountSid) && filled($this->authToken);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Account SID and Auth Token are required.'];
        }

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}.json");

            return ['success' => $response->successful(), 'message' => 'Twilio API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
