<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Http\Middleware\ThrottleAiRequests;
use App\Jobs\SendSmsCampaign;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Models\SmsCampaign;
use App\Models\User;
use App\Support\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Installer\Middleware\InstallationMiddleware;
use Tests\TestCase;

/**
 * Bulk SMS: campaigns go only to users who are reachable AND opted in, the body is
 * composed from message + optional link/label, and every recipient's outcome is
 * logged so failures can be retried. See SmsCampaignController, SendSmsCampaign and
 * App\Support\SmsMessage.
 */
class BulkSmsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false, 'broadcasting.default' => 'null']);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class, ThrottleAiRequests::class]);

        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->admin = Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->enableSmsGateway();
    }

    private function enableSmsGateway(): void
    {
        settings_set('external_sms_gateway_enabled', true, 'boolean', 'integrations');
        settings_set('external_sms_gateway_provider', 'twilio', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_account_sid', 'AC_test', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_auth_token', 'token', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_from', '+15550000000', 'string', 'integrations');
        Setting::flushCache();
    }

    private function optedInUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'phone' => '2025550173',
            'phone_country' => 'US',
            'phone_verified_at' => now(),
            'sms_marketing_opt_in' => true,
        ], $overrides));
    }

    // ── Message composition ───────────────────────────────

    public function test_body_is_message_alone_without_a_link(): void
    {
        $this->assertSame('Hello', SmsMessage::compose('Hello', null, null));
    }

    public function test_body_appends_bare_link_when_label_is_empty(): void
    {
        $this->assertSame("Hello\n\nhttps://x.test", SmsMessage::compose('Hello', 'https://x.test', ''));
    }

    public function test_body_labels_the_link_when_a_label_is_given(): void
    {
        $this->assertSame("Hello\n\nRead more: https://x.test", SmsMessage::compose('Hello', 'https://x.test', 'Read more'));
    }

    public function test_segments_account_for_unicode(): void
    {
        // 160 GSM-7 chars fit one segment; a single emoji forces UCS-2 (70/segment).
        $this->assertSame(1, SmsMessage::segments(str_repeat('a', 160)));
        $this->assertSame(2, SmsMessage::segments(str_repeat('a', 161)));
        $this->assertSame(2, SmsMessage::segments(str_repeat('a', 80).'🎉'));
    }

    // ── Eligibility ───────────────────────────────────────

    public function test_only_opted_in_users_are_listed(): void
    {
        $optedIn = $this->optedInUser();
        $this->optedInUser(['sms_marketing_opt_in' => false]);
        $this->optedInUser(['phone_verified_at' => null]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.bulk-sms.index'));

        $response->assertOk();
        $ids = collect($response->viewData('page')['props']['recipients'])->pluck('id');
        $this->assertEquals([$optedIn->id], $ids->all());
    }

    public function test_campaign_skips_users_who_are_not_eligible(): void
    {
        Queue::fake();
        $eligible = $this->optedInUser();
        $notOptedIn = $this->optedInUser(['sms_marketing_opt_in' => false]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.bulk-sms.store'), [
                'message' => 'Maintenance tonight',
                'user_ids' => [$eligible->id, $notOptedIn->id],
            ])
            ->assertSessionHas('success');

        $campaign = SmsCampaign::firstOrFail();
        $this->assertSame(1, $campaign->recipient_count);
        $this->assertSame([$eligible->id], $campaign->recipients()->pluck('user_id')->all());
        Queue::assertPushed(SendSmsCampaign::class);
    }

    public function test_campaign_is_rejected_when_nobody_is_eligible(): void
    {
        Queue::fake();
        $user = $this->optedInUser(['sms_marketing_opt_in' => false]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.bulk-sms.store'), ['message' => 'Hi', 'user_ids' => [$user->id]])
            ->assertSessionHas('error');

        $this->assertSame(0, SmsCampaign::count());
        Queue::assertNotPushed(SendSmsCampaign::class);
    }

    public function test_user_manages_sms_consent_from_privacy_preferences(): void
    {
        $user = $this->optedInUser(['sms_marketing_opt_in' => false]);

        // The checkbox lives in Privacy → Preferences, and is only offered when the
        // user is actually reachable by SMS.
        $page = $this->actingAs($user)->get(route('user.dashboard.privacy'));
        $page->assertOk();
        $this->assertTrue($page->viewData('page')['props']['smsAvailable']);

        $this->actingAs($user)->post(route('user.dashboard.privacy.preferences'), [
            'email_marketing' => true,
            'allow_data_improve' => true,
            'sms_marketing_opt_in' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue((bool) $user->fresh()->sms_marketing_opt_in);
    }

    public function test_consent_cannot_be_granted_without_a_verified_phone(): void
    {
        // Opting IN requires being reachable; a crafted request must not bypass that.
        $user = $this->optedInUser(['phone_verified_at' => null, 'sms_marketing_opt_in' => false]);

        $this->actingAs($user)->post(route('user.dashboard.privacy.preferences'), [
            'email_marketing' => true,
            'allow_data_improve' => true,
            'sms_marketing_opt_in' => true,
        ])->assertSessionHasNoErrors();

        $this->assertFalse((bool) $user->fresh()->sms_marketing_opt_in);
    }

    // ── Sending ───────────────────────────────────────────

    public function test_job_sends_and_logs_each_recipient(): void
    {
        $user = $this->optedInUser();
        $sent = [];
        Http::fake(function ($request) use (&$sent) {
            $sent[] = $request->data();

            return Http::response(['sid' => 'SM'], 201);
        });

        $this->actingAs($this->admin, 'admin')->post(route('admin.bulk-sms.store'), [
            'message' => 'Maintenance tonight',
            'action_url' => 'https://x.test/status',
            'action_label' => 'Details',
            'user_ids' => [$user->id],
        ]);

        $campaign = SmsCampaign::firstOrFail();
        (new SendSmsCampaign($campaign->id))->handle();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);

        $recipient = $campaign->recipients()->first();
        $this->assertSame('sent', $recipient->status);
        $this->assertSame('+12025550173', $recipient->phone);
        $this->assertStringContainsString('Details: https://x.test/status', (string) $sent[0]['Body']);
    }

    public function test_failures_are_recorded_and_can_be_retried(): void
    {
        $user = $this->optedInUser();
        // One stub whose outcome we flip: Http::fake() APPENDS stubs (first match
        // wins), so re-faking later would not override this one.
        $gatewayFails = true;
        Http::fake(function () use (&$gatewayFails) {
            return $gatewayFails
                ? Http::response(['message' => 'Gateway said no'], 400)
                : Http::response(['sid' => 'SM'], 201);
        });

        $this->actingAs($this->admin, 'admin')->post(route('admin.bulk-sms.store'), [
            'message' => 'Hi', 'user_ids' => [$user->id],
        ]);

        $campaign = SmsCampaign::firstOrFail();
        (new SendSmsCampaign($campaign->id))->handle();

        $campaign->refresh();
        $this->assertSame(1, $campaign->failed_count);
        $this->assertSame('failed', $campaign->recipients()->first()->status);

        // Retry with a working gateway: the failure tally is undone, not double counted.
        $gatewayFails = false;
        (new SendSmsCampaign($campaign->id, true))->handle();

        $campaign->refresh();
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertSame('sent', $campaign->recipients()->first()->status);
    }

    public function test_a_second_dispatch_cannot_send_twice(): void
    {
        $user = $this->optedInUser();
        Http::fake(fn () => Http::response(['sid' => 'SM'], 201));

        $this->actingAs($this->admin, 'admin')->post(route('admin.bulk-sms.store'), [
            'message' => 'Hi', 'user_ids' => [$user->id],
        ]);

        $campaign = SmsCampaign::firstOrFail();
        (new SendSmsCampaign($campaign->id))->handle();
        (new SendSmsCampaign($campaign->id))->handle(); // duplicate dispatch

        $this->assertSame(1, $campaign->fresh()->sent_count);
        Http::assertSentCount(1);
    }

    public function test_sending_is_blocked_without_a_gateway(): void
    {
        Queue::fake();
        settings_set('external_sms_gateway_enabled', false, 'boolean', 'integrations');
        Setting::flushCache();
        $user = $this->optedInUser();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.bulk-sms.store'), ['message' => 'Hi', 'user_ids' => [$user->id]])
            ->assertSessionHas('error');

        Queue::assertNotPushed(SendSmsCampaign::class);
    }
}
