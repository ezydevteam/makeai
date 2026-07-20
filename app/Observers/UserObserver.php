<?php

namespace App\Observers;

use App\Models\AiChat;
use App\Models\AiChatMessage;
use App\Models\AiUsageLog;
use App\Models\Comment;
use App\Models\Document;
use App\Models\GatewaySubscription;
use App\Models\User;
use App\Services\Payment\GatewaySubscriptionCanceller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class UserObserver
{
    /**
     * Everything that must happen before a user row is destroyed for good.
     *
     * This lives on the model rather than in PermanentlyDeleteUserJob because there is more
     * than one way to permanently delete a user — the scheduled purge (AccountsPurgeDeleted),
     * the self-service GDPR flow (PrivacyController), and the admin trash screen
     * (UserManagementController::forceDelete). The admin path used to call forceDelete()
     * directly and so skipped all of this: the subscription was never cancelled at the
     * gateway, comments kept a dangling author, and the address stayed on the newsletter.
     *
     * Hooking forceDeleting (not forceDeleted) is deliberate. The work below has to read
     * rows that the foreign keys will cascade away the moment the user is gone, and the
     * comment anonymisation has to win the race against comments.user_id's ON DELETE SET NULL.
     */
    public function forceDeleting(User $user): bool
    {
        // Must be the first thing here. Laravel fires forceDeleting BEFORE deleting, so the
        // internal-AI guard in User::booted() has not run yet — without this we would cancel
        // its subscription and anonymise its comments and only then have the delete refused,
        // leaving the account alive but damaged. See InternalAiAccountTest.
        if ($user->isInternalAi()) {
            return false;
        }

        $this->cancelSubscriptionAtGateway($user);
        $this->deleteOwnedFiles($user);

        // Comments outlive their author: comments.user_id is ON DELETE SET NULL, which would
        // otherwise leave a comment with no user_id and no guest_name — i.e. a blank author.
        Comment::where('user_id', $user->id)->update([
            'user_id' => null,
            'guest_name' => 'Deleted User',
        ]);

        // Keyed by email, not user_id, so no foreign key reaches it. Has to be explicit.
        DB::table('newsletter_subscribers')->where('email', $user->email)->delete();

        // The rows below all cascade from users.id, so this is belt-and-braces: it keeps the
        // purge correct even where foreign key enforcement is off (SQLite without the pragma,
        // MyISAM tables on an old shared host).
        Document::where('user_id', $user->id)->delete();

        $chatIds = AiChat::where('user_id', $user->id)->pluck('id');
        AiChatMessage::whereIn('chat_id', $chatIds)->delete();
        AiChat::whereIn('id', $chatIds)->delete();

        AiUsageLog::where('user_id', $user->id)->delete();
        DB::table('credit_transactions')->where('user_id', $user->id)->delete();
        DB::table('login_history')->where('user_id', $user->id)->delete();
        DB::table('user_byok')->where('user_id', $user->id)->delete();

        return true;
    }

    public function forceDeleted(User $user): void
    {
        Log::info('User permanently deleted', ['user_id' => $user->id]);
    }

    /**
     * Delete the files this user owns, while their rows still exist to point at them.
     *
     * Every table below cascades from users.id, so the database drops the rows the instant the
     * user goes — without model events, because a foreign key cascade fires none. Nothing would
     * ever revisit those paths, and the files would sit on disk forever. This is the last
     * moment they are addressable.
     *
     * Storage failures are logged, never thrown: a file we cannot unlink must not abort the
     * deletion of the account itself. The row disappearing is the part that is legally binding.
     */
    private function deleteOwnedFiles(User $user): void
    {
        $this->guard('avatar', function () use ($user) {
            $avatar = media_path((string) $user->avatar);

            // media_path() returns a foreign absolute URL untouched — a social-login avatar
            // hosted by the provider. That is not ours to delete, and is not a storage key.
            if ($avatar !== '' && ! preg_match('#^(https?:)?//#i', $avatar)) {
                Storage::disk('public')->delete($avatar);
            }
        });

        // Addon tables are only touched when the addon is actually installed AND migrated.
        // is_addon_active() alone is not enough: an addon can be present and inactive, and a
        // packaged build may not ship the directory at all — hence no addon model classes are
        // referenced anywhere here, only table names.
        $this->guard('ai-image-pro', function () use ($user) {
            if (is_addon_active('ai-image-pro') && Schema::hasTable('aip_assets')) {
                DB::table('aip_assets')
                    ->where('user_id', $user->id)
                    ->select('id', 'disk', 'path', 'thumb_path')
                    ->orderBy('id')
                    ->chunk(500, function ($assets) {
                        foreach ($assets as $asset) {
                            // Mirrors AipAsset::deleteFiles() — per-row disk, defaulting to public.
                            $disk = Storage::disk($asset->disk ?: 'public');

                            foreach ([$asset->path, $asset->thumb_path] as $path) {
                                if ($path) {
                                    $disk->delete($path);
                                }
                            }
                        }
                    });
            }
        });

        // Rendered audio and uploaded music live on the default disk (the addon calls
        // Storage::delete() with no disk argument); cover art is on the public disk.
        $this->guard('ai-voiceover', function () use ($user) {
            if (! is_addon_active('ai-voiceover')) {
                return;
            }

            foreach (['vo_episodes', 'vo_music_library'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)->where('user_id', $user->id)
                    ->orderBy('id')
                    ->chunk(500, function ($rows) {
                        foreach ($rows as $row) {
                            if ($row->file_path) {
                                Storage::delete($row->file_path);
                            }
                        }
                    });
            }

            if (Schema::hasTable('vo_projects')) {
                DB::table('vo_projects')->where('user_id', $user->id)
                    ->orderBy('id')
                    ->chunk(500, function ($rows) {
                        foreach ($rows as $row) {
                            if ($row->cover_art_path) {
                                Storage::disk('public')->delete($row->cover_art_path);
                            }
                        }
                    });
            }
        });
    }

    /** Never let a storage problem in one source stop the others, or the deletion itself. */
    private function guard(string $source, callable $cleanup): void
    {
        try {
            $cleanup();
        } catch (\Throwable $e) {
            Log::warning('Could not delete user files during permanent deletion', [
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Stop billing before the account disappears.
     *
     * The old implementation called $user->subscription()?->cancelNow() — neither method has
     * ever existed on User, so every permanent deletion threw and was swallowed by its own
     * try/catch, logging "Failed to cancel subscription" and leaving the gateway happily
     * charging a customer who no longer had an account.
     *
     * GatewaySubscriptionCanceller never throws; it logs remote failures and returns, so a
     * gateway being down cannot block the deletion.
     */
    private function cancelSubscriptionAtGateway(User $user): void
    {
        $subscription = GatewaySubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                GatewaySubscription::STATUS_ACTIVE,
                GatewaySubscription::STATUS_TRIALING,
            ])
            ->first();

        if (! $subscription) {
            return;
        }

        app(GatewaySubscriptionCanceller::class)->cancelImmediately($subscription);
    }
}
