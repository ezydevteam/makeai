<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Jobs\PermanentlyDeleteUserJob;
use App\Models\Addon;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Comment;
use App\Models\GatewaySubscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payment\GatewaySubscriptionCanceller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Installer\Middleware\InstallationMiddleware;
use Mockery;
use Tests\TestCase;

/**
 * Permanent deletion has three entry points — the scheduled purge, the self-service GDPR flow,
 * and the admin trash screen — and for a long time only the first two ran any cleanup. The
 * admin screen called forceDelete() straight, so deleting a paying customer from the admin
 * panel left their subscription running at the gateway.
 *
 * The cleanup now lives in UserObserver::forceDeleting(), which every path goes through.
 * These tests pin that down.
 */
class UserPermanentDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);

        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $this->admin = Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    /** The gateway canceller talks to Stripe/PayPal over the wire — always a double here. */
    private function spyOnCanceller(): Mockery\MockInterface
    {
        $spy = Mockery::mock(GatewaySubscriptionCanceller::class);
        $this->app->instance(GatewaySubscriptionCanceller::class, $spy);

        return $spy;
    }

    private function subscribedUser(): array
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 20, 'price_yearly' => 200, 'vat_percentage' => 0,
            'credits' => 1000, 'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);

        $user = User::factory()->create();

        $subscription = GatewaySubscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'status' => GatewaySubscription::STATUS_ACTIVE, 'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_'.uniqid(), 'amount' => 20, 'currency' => 'USD',
            'current_period_start' => now()->subDays(5), 'current_period_end' => now()->addDays(25),
        ]);

        return [$user, $subscription];
    }

    /** Comments are polymorphic and commentable_id carries no foreign key, so no real post needed. */
    private function commentBy(?int $userId): Comment
    {
        return Comment::create([
            'commentable_type' => 'App\Models\BlogPost',
            'commentable_id' => 1,
            'user_id' => $userId,
            'content' => 'Outlives its author.',
            'status' => 'approved',
        ]);
    }

    /**
     * The regression this whole change exists for. Before, this path never touched the gateway.
     */
    public function test_admin_force_delete_cancels_the_subscription_at_the_gateway(): void
    {
        [$user, $subscription] = $this->subscribedUser();

        $this->spyOnCanceller()
            ->shouldReceive('cancelImmediately')
            ->once()
            ->withArgs(fn (GatewaySubscription $s) => $s->id === $subscription->id);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.users.force-delete', $user->ulid));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_the_purge_job_cancels_the_subscription_at_the_gateway(): void
    {
        [$user, $subscription] = $this->subscribedUser();

        $this->spyOnCanceller()
            ->shouldReceive('cancelImmediately')
            ->once()
            ->withArgs(fn (GatewaySubscription $s) => $s->id === $subscription->id);

        (new PermanentlyDeleteUserJob($user))->handle();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * A cancelled subscription is already settled with the gateway — cancelling it again would
     * be a pointless API call, and on some gateways an error.
     */
    public function test_an_inactive_subscription_is_left_alone(): void
    {
        [$user, $subscription] = $this->subscribedUser();
        $subscription->update(['status' => GatewaySubscription::STATUS_CANCELLED]);

        $this->spyOnCanceller()->shouldNotReceive('cancelImmediately');

        (new PermanentlyDeleteUserJob($user))->handle();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * comments.user_id is ON DELETE SET NULL, so without the observer a deleted user's comments
     * survive with no user_id AND no guest_name — rendering as a blank author.
     */
    public function test_comments_are_anonymised_rather_than_left_authorless(): void
    {
        $this->spyOnCanceller();

        $user = User::factory()->create();
        $comment = $this->commentBy($user->id);

        (new PermanentlyDeleteUserJob($user))->handle();

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'user_id' => null,
            'guest_name' => 'Deleted User',
        ]);
    }

    public function test_the_address_is_removed_from_the_newsletter(): void
    {
        $this->spyOnCanceller();

        $user = User::factory()->create();
        DB::table('newsletter_subscribers')->insert([
            'email' => $user->email,
            'status' => 'subscribed',
            'token' => bin2hex(random_bytes(16)),
            'subscribed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new PermanentlyDeleteUserJob($user))->handle();

        $this->assertDatabaseMissing('newsletter_subscribers', ['email' => $user->email]);
    }

    // ─── Owned files ────────────────────────────

    /**
     * The addon's own tables are created here rather than by running its migrations, so these
     * tests pin the contract the observer actually depends on — table and column names — and
     * still run on an install where the addon directory was never shipped.
     */
    private function fakeAipAssetsTable(): void
    {
        Schema::create('aip_assets', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('disk', 40)->default('public');
            $table->string('path', 500);
            $table->string('thumb_path', 500)->nullable();
        });
    }

    private function activateAddon(string $slug): void
    {
        Addon::create([
            'slug' => $slug, 'name' => ucfirst($slug), 'version' => '1.0.0',
            'is_active' => true, 'manifest' => ['name' => ucfirst($slug)],
        ]);
    }

    public function test_the_avatar_file_is_deleted(): void
    {
        $this->spyOnCanceller();
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => 'avatars/face.jpg']);
        Storage::disk('public')->put('avatars/face.jpg', 'x');

        (new PermanentlyDeleteUserJob($user))->handle();

        Storage::disk('public')->assertMissing('avatars/face.jpg');
    }

    /** A social-login avatar is hosted by the provider — not ours to delete, and not a key. */
    public function test_an_externally_hosted_avatar_is_left_alone(): void
    {
        $this->spyOnCanceller();
        Storage::fake('public');

        $user = User::factory()->create(['avatar' => 'https://lh3.googleusercontent.com/a/abc123']);

        (new PermanentlyDeleteUserJob($user))->handle();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * aip_assets cascades from users.id, so the rows vanish without firing a single model
     * event — AipAsset::deleteFiles() never runs and the images would be stranded on disk.
     */
    public function test_image_pro_assets_are_deleted_when_the_addon_is_active(): void
    {
        $this->spyOnCanceller();
        Storage::fake('public');
        $this->activateAddon('ai-image-pro');
        $this->fakeAipAssetsTable();

        $user = User::factory()->create();
        DB::table('aip_assets')->insert([
            'user_id' => $user->id, 'disk' => 'public',
            'path' => 'aip/one.png', 'thumb_path' => 'aip/one-thumb.png',
        ]);
        Storage::disk('public')->put('aip/one.png', 'x');
        Storage::disk('public')->put('aip/one-thumb.png', 'x');

        (new PermanentlyDeleteUserJob($user))->handle();

        Storage::disk('public')->assertMissing('aip/one.png');
        Storage::disk('public')->assertMissing('aip/one-thumb.png');
    }

    /** Another user's assets are not collateral. */
    public function test_only_the_deleted_users_assets_are_touched(): void
    {
        $this->spyOnCanceller();
        Storage::fake('public');
        $this->activateAddon('ai-image-pro');
        $this->fakeAipAssetsTable();

        $victim = User::factory()->create();
        $bystander = User::factory()->create();
        DB::table('aip_assets')->insert([
            ['user_id' => $victim->id, 'disk' => 'public', 'path' => 'aip/mine.png', 'thumb_path' => null],
            ['user_id' => $bystander->id, 'disk' => 'public', 'path' => 'aip/theirs.png', 'thumb_path' => null],
        ]);
        Storage::disk('public')->put('aip/mine.png', 'x');
        Storage::disk('public')->put('aip/theirs.png', 'x');

        (new PermanentlyDeleteUserJob($victim))->handle();

        Storage::disk('public')->assertMissing('aip/mine.png');
        Storage::disk('public')->assertExists('aip/theirs.png');
    }

    /**
     * Voiceover splits its storage: rendered audio and uploaded music go to the default disk
     * (the addon calls Storage::delete() with no disk argument), cover art to the public disk.
     * Getting the disk wrong here fails silently in production — the delete is a no-op on a
     * key that isn't there — so both are asserted.
     */
    public function test_voiceover_audio_and_cover_art_are_deleted_when_the_addon_is_active(): void
    {
        $this->spyOnCanceller();
        Storage::fake();
        Storage::fake('public');
        $this->activateAddon('ai-voiceover');

        Schema::create('vo_episodes', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('file_path', 500)->nullable();
        });
        Schema::create('vo_music_library', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('file_path', 500);
        });
        Schema::create('vo_projects', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('cover_art_path', 500)->nullable();
        });

        $user = User::factory()->create();
        DB::table('vo_episodes')->insert(['user_id' => $user->id, 'file_path' => 'vo/ep1.mp3']);
        DB::table('vo_music_library')->insert(['user_id' => $user->id, 'file_path' => 'vo/bed.mp3']);
        DB::table('vo_projects')->insert(['user_id' => $user->id, 'cover_art_path' => 'vo/cover.jpg']);

        Storage::put('vo/ep1.mp3', 'x');
        Storage::put('vo/bed.mp3', 'x');
        Storage::disk('public')->put('vo/cover.jpg', 'x');

        (new PermanentlyDeleteUserJob($user))->handle();

        Storage::assertMissing('vo/ep1.mp3');
        Storage::assertMissing('vo/bed.mp3');
        Storage::disk('public')->assertMissing('vo/cover.jpg');
    }

    /**
     * The addon is installed but switched off. Its tables may still hold rows, but core must
     * not reach into a disabled addon — and must certainly not fail the deletion over it.
     */
    public function test_an_inactive_addon_is_not_touched(): void
    {
        $this->spyOnCanceller();
        Storage::fake('public');
        $this->fakeAipAssetsTable(); // table exists, but no active Addon row

        $user = User::factory()->create();
        DB::table('aip_assets')->insert([
            'user_id' => $user->id, 'disk' => 'public', 'path' => 'aip/kept.png', 'thumb_path' => null,
        ]);
        Storage::disk('public')->put('aip/kept.png', 'x');

        (new PermanentlyDeleteUserJob($user))->handle();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        Storage::disk('public')->assertExists('aip/kept.png');
    }

    /**
     * The case that would be a fatal rather than a missed file: an addon marked active whose
     * tables were never migrated, or whose directory a packaged build never shipped. The
     * observer must never reference addon model classes or assume their tables exist.
     */
    public function test_an_active_addon_with_no_tables_does_not_break_the_deletion(): void
    {
        $this->spyOnCanceller();
        Storage::fake('public');
        $this->activateAddon('ai-image-pro');
        $this->activateAddon('ai-voiceover');

        $this->assertFalse(Schema::hasTable('aip_assets'));
        $this->assertFalse(Schema::hasTable('vo_episodes'));

        $user = User::factory()->create();

        (new PermanentlyDeleteUserJob($user))->handle();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * Laravel fires forceDeleting BEFORE deleting, so the observer runs ahead of the
     * internal-AI guard in User::booted(). Without its own guard the observer would cancel the
     * account's subscription and anonymise its comments, and only then have the delete
     * refused — leaving the system account alive but damaged.
     */
    public function test_the_internal_ai_account_survives_untouched(): void
    {
        $this->spyOnCanceller()->shouldNotReceive('cancelImmediately');

        $internal = User::internalAi();
        $comment = $this->commentBy($internal->id);

        $internal->forceDelete();

        $this->assertDatabaseHas('users', ['id' => $internal->id]);
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'user_id' => $internal->id]);
    }
}
