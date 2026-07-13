<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SmsService
{
    private bool $enabled;
    private ?string $provider;
    private int $timeout;

    private ?string $twilioAccountSid;
    private ?string $twilioAuthToken;
    private ?string $twilioFrom;

    private ?string $vonageApiKey;
    private ?string $vonageApiSecret;
    private ?string $vonageFrom;

    private ?string $messagebirdAccessKey;
    private ?string $messagebirdOriginator;

    public function __construct(
        bool $enabled = false,
        ?string $provider = null,
        int $timeout = 15,
        ?string $twilioAccountSid = null,
        ?string $twilioAuthToken = null,
        ?string $twilioFrom = null,
        ?string $vonageApiKey = null,
        ?string $vonageApiSecret = null,
        ?string $vonageFrom = null,
        ?string $messagebirdAccessKey = null,
        ?string $messagebirdOriginator = null,
    ) {
        $this->enabled = $enabled;
        $this->provider = $provider;
        $this->timeout = $timeout;

        $this->twilioAccountSid = $twilioAccountSid;
        $this->twilioAuthToken = $twilioAuthToken;
        $this->twilioFrom = $twilioFrom;

        $this->vonageApiKey = $vonageApiKey;
        $this->vonageApiSecret = $vonageApiSecret;
        $this->vonageFrom = $vonageFrom;

        $this->messagebirdAccessKey = $messagebirdAccessKey;
        $this->messagebirdOriginator = $messagebirdOriginator;
    }

    public static function fromSettings(): self
    {
        return new self(
            enabled: (bool) settings('external_sms_gateway_enabled', false),
            provider: (string) settings('external_sms_gateway_provider', 'twilio'),
            timeout: (int) settings('external_sms_gateway_timeout', 15),
            twilioAccountSid: settings('external_sms_gateway_twilio_account_sid'),
            twilioAuthToken: settings('external_sms_gateway_twilio_auth_token'),
            twilioFrom: settings('external_sms_gateway_twilio_from'),
            vonageApiKey: settings('external_sms_gateway_vonage_api_key'),
            vonageApiSecret: settings('external_sms_gateway_vonage_api_secret'),
            vonageFrom: settings('external_sms_gateway_vonage_from'),
            messagebirdAccessKey: settings('external_sms_gateway_messagebird_access_key'),
            messagebirdOriginator: settings('external_sms_gateway_messagebird_originator'),
        );
    }

    public function isConfigured(): bool
    {
        return match ($this->provider) {
            'twilio' => filled($this->twilioAccountSid) && filled($this->twilioAuthToken) && filled($this->twilioFrom),
            'vonage' => filled($this->vonageApiKey) && filled($this->vonageApiSecret) && filled($this->vonageFrom),
            'messagebird' => filled($this->messagebirdAccessKey) && filled($this->messagebirdOriginator),
            default => false,
        };
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    /**
     * Send a transactional SMS / OTP message.
     *
     * Fails OPEN toward the caller: never throws. When the gateway is not
     * enabled/configured, or the provider request errors, a structured
     * failure array is returned instead so callers can fall back gracefully
     * (e.g. to email OTP delivery).
     */
    public function send(string $to, string $message): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'error' => translate('SMS gateway is not enabled.')];
        }

        try {
            return match ($this->provider) {
                'twilio' => $this->sendViaTwilio($to, $message),
                'vonage' => $this->sendViaVonage($to, $message),
                'messagebird' => $this->sendViaMessagebird($to, $message),
                default => ['success' => false, 'error' => translate('Unsupported SMS gateway provider.')],
            };
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendViaTwilio(string $to, string $message): array
    {
        $response = Http::asForm()
            ->withBasicAuth((string) $this->twilioAccountSid, (string) $this->twilioAuthToken)
            ->timeout(max(5, $this->timeout))
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->twilioAccountSid}/Messages.json", [
                'To' => $to,
                'From' => $this->twilioFrom,
                'Body' => $message,
            ]);

        if ($response->successful()) {
            return ['success' => true];
        }

        $data = $response->json();

        return [
            'success' => false,
            'error' => $data['message'] ?? translate('Twilio failed to send the SMS.'),
        ];
    }

    private function sendViaVonage(string $to, string $message): array
    {
        $response = Http::asForm()
            ->timeout(max(5, $this->timeout))
            ->post('https://rest.nexmo.com/sms/json', [
                'api_key' => $this->vonageApiKey,
                'api_secret' => $this->vonageApiSecret,
                'to' => $to,
                'from' => $this->vonageFrom,
                'text' => $message,
            ]);

        $data = $response->json();
        $status = $data['messages'][0]['status'] ?? null;

        if ($status === '0') {
            return ['success' => true];
        }

        return [
            'success' => false,
            'error' => $data['messages'][0]['error-text'] ?? translate('Vonage failed to send the SMS.'),
        ];
    }

    private function sendViaMessagebird(string $to, string $message): array
    {
        $response = Http::asForm()
            ->withHeaders(['Authorization' => "AccessKey {$this->messagebirdAccessKey}"])
            ->timeout(max(5, $this->timeout))
            ->post('https://rest.messagebird.com/messages', [
                'recipients' => $to,
                'originator' => $this->messagebirdOriginator,
                'body' => $message,
            ]);

        if ($response->successful()) {
            return ['success' => true];
        }

        $data = $response->json();

        return [
            'success' => false,
            'error' => $data['errors'][0]['description'] ?? translate('MessageBird failed to send the SMS.'),
        ];
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => translate('SMS gateway is not fully configured.')];
        }

        try {
            return match ($this->provider) {
                'twilio' => $this->testTwilio(),
                'vonage' => $this->testVonage(),
                'messagebird' => $this->testMessagebird(),
                default => ['success' => false, 'error' => translate('Unsupported SMS gateway provider.')],
            };
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function testTwilio(): array
    {
        $response = Http::withBasicAuth((string) $this->twilioAccountSid, (string) $this->twilioAuthToken)
            ->timeout(max(5, $this->timeout))
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$this->twilioAccountSid}.json");

        return $response->successful()
            ? ['success' => true, 'message' => translate('Twilio credentials are valid.')]
            : ['success' => false, 'error' => translate('Twilio rejected the provided credentials.')];
    }

    private function testVonage(): array
    {
        $response = Http::timeout(max(5, $this->timeout))
            ->get('https://rest.nexmo.com/account/get-balance', [
                'api_key' => $this->vonageApiKey,
                'api_secret' => $this->vonageApiSecret,
            ]);

        return $response->successful()
            ? ['success' => true, 'message' => translate('Vonage credentials are valid.')]
            : ['success' => false, 'error' => translate('Vonage rejected the provided credentials.')];
    }

    private function testMessagebird(): array
    {
        $response = Http::withHeaders(['Authorization' => "AccessKey {$this->messagebirdAccessKey}"])
            ->timeout(max(5, $this->timeout))
            ->get('https://rest.messagebird.com/balance');

        return $response->successful()
            ? ['success' => true, 'message' => translate('MessageBird credentials are valid.')]
            : ['success' => false, 'error' => translate('MessageBird rejected the provided credentials.')];
    }
}
