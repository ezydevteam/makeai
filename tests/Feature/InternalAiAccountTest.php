<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The internal AI account is the system user every admin AI feature bills against — the
 * assistant's admin chat, blog/page/FAQ assist, mail templates, ticket replies, translations.
 * It owns their entire usage history.
 *
 * It must be invisible to the admin (it is not a customer, and it skews every user stat) and
 * indestructible (deleting it orphans every ai_usage_logs row and breaks admin AI on the next
 * request).
 */
class InternalAiAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_cannot_be_soft_deleted(): void
    {
        $internal = User::internalAi();

        $this->assertFalse($internal->delete());
        $this->assertDatabaseHas('users', ['id' => $internal->id, 'deleted_at' => null]);
    }

    public function test_it_cannot_be_force_deleted(): void
    {
        $internal = User::internalAi();

        $internal->forceDelete();

        $this->assertDatabaseHas('users', ['id' => $internal->id]);
    }

    /**
     * The one that actually needed thinking about. A mass-delete QUERY does not fire Eloquent
     * model events, so the deleting() guard on the model is blind to it — the scope on the
     * bulk query is what saves us. If someone ever drops that scope, this fails.
     */
    public function test_a_bulk_delete_query_cannot_take_it(): void
    {
        $internal = User::internalAi();
        $victim = User::factory()->create();

        // Exactly what UserManagementController::bulkAction() runs.
        User::whereIn('id', [$internal->id, $victim->id])->excludingInternal()->delete();

        // Asserted against the database, not fresh(): fresh() builds its query with
        // newQueryWithoutScopes(), so it happily returns a soft-deleted row and would pass
        // even if the delete had gone through.
        $this->assertDatabaseHas('users', ['id' => $internal->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('users', ['id' => $victim->id]);
    }

    public function test_a_bulk_update_query_cannot_touch_it(): void
    {
        $internal = User::internalAi();

        User::whereIn('id', [$internal->id])->excludingInternal()->update(['is_active' => false]);

        $this->assertTrue((bool) $internal->fresh()->is_active);
    }

    public function test_it_is_hidden_from_user_listings_and_counts(): void
    {
        $internal = User::internalAi();
        User::factory()->count(3)->create();

        $listed = User::excludingInternal()->pluck('id');

        $this->assertCount(3, $listed);
        $this->assertNotContains($internal->id, $listed);
        $this->assertSame(3, User::excludingInternal()->count(), 'it must not inflate "Total users"');
    }

    /**
     * The scope must NOT be global. internalAi() has to keep finding the existing row — a
     * global scope would hide it from firstOrCreate(), which would then mint a duplicate
     * system account on every single admin AI call.
     */
    public function test_the_system_can_still_resolve_it(): void
    {
        $first = User::internalAi();
        $second = User::internalAi();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, User::where('is_internal', true)->count());
    }

    /**
     * The whole reason identity moved from the email to a column.
     *
     * The email is derived from config('app.url'). A buyer moving off a staging domain used
     * to silently orphan the system account: it stopped matching internalAiEmail(), so it
     * reappeared in the admin users table as an ordinary deletable customer, a duplicate was
     * created beside it, and its entire usage history was left pointing at nothing.
     */
    public function test_it_survives_an_app_url_change(): void
    {
        $original = User::internalAi();

        config(['app.url' => 'https://a-completely-different-domain.test']);

        $resolved = User::internalAi();

        $this->assertSame($original->id, $resolved->id, 'the same account must be reused after a domain change');
        $this->assertSame(1, User::where('is_internal', true)->count(), 'no duplicate system account');
        $this->assertTrue($resolved->isInternalAi(), 'it must still be recognised as internal');
        $this->assertFalse($resolved->delete(), 'and must still be undeletable');
    }

    /**
     * An account stranded by a domain change made BEFORE the flag existed is adopted, not
     * deleted, and not duplicated.
     *
     * Deleting it is not an option: ai_usage_logs.user_id is cascadeOnDelete, so removing the
     * account would destroy the entire admin AI usage history as a side effect of a domain
     * change. Adoption gets the same visible outcome — it leaves the admin users table —
     * while keeping every log row.
     */
    public function test_an_orphan_from_an_old_domain_is_adopted_not_duplicated(): void
    {
        // A system account exactly as a pre-migration install would have left it: right name,
        // reserved local part, old domain, never flagged.
        $orphan = User::factory()->create([
            'name' => User::internalAiName(),
            'email' => 'internalai@the-old-domain.test',
            'last_login_at' => null,
        ]);

        $this->assertFalse($orphan->fresh()->isInternalAi());

        $resolved = User::internalAi();

        $this->assertSame($orphan->id, $resolved->id, 'the orphan must be adopted, not stranded');
        $this->assertSame(1, User::where('is_internal', true)->count(), 'and not duplicated');
        $this->assertDatabaseHas('users', ['id' => $orphan->id]);

        // Adopted means: gone from the admin users table, and undeletable.
        $this->assertNotContains($orphan->id, User::excludingInternal()->pluck('id'));
        $this->assertFalse($resolved->delete());

        // ...and its address is realigned to the domain the site now runs on.
        $this->assertSame(User::internalAiEmail(), $resolved->fresh()->email);
    }

    /**
     * The adoption heuristic must not swallow a real person. The flag grants unlimited free
     * AI and immunity from deletion, so a false positive is far worse than a missed orphan.
     */
    public function test_adoption_does_not_capture_a_real_user(): void
    {
        // Reserved-looking address, but a real human: they have a name of their own and they
        // have actually signed in.
        $real = User::factory()->create([
            'name' => 'Priya Raman',
            'email' => 'internalai@gmail.com',
            'last_login_at' => now(),
        ]);

        $internal = User::internalAi();

        $this->assertNotSame($real->id, $internal->id, 'a real user must never be adopted as the system account');
        $this->assertFalse($real->fresh()->isInternalAi());
        $this->assertContains($real->id, User::excludingInternal()->pluck('id'), 'and must stay visible to the admin');
        $this->assertTrue((bool) $real->fresh()->delete(), 'and stay deletable');
    }

    /**
     * is_internal grants exemption from credit limits and immunity from deletion. If it were
     * mass-assignable, any endpoint that fills a User from request data would be a path to
     * unlimited free AI on an account an admin could then never remove.
     */
    public function test_the_internal_flag_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();

        $user->fill(['is_internal' => true])->save();

        $this->assertFalse($user->fresh()->isInternalAi());
        $this->assertTrue((bool) $user->fresh()->delete(), 'and it is still an ordinary, deletable user');
    }

    public function test_a_real_user_is_still_deletable(): void
    {
        $user = User::factory()->create();

        $this->assertTrue((bool) $user->delete());
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
