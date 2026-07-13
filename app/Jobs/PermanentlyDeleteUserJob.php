<?php

namespace App\Jobs;

use App\Models\AiChat;
use App\Models\AiChatMessage;
use App\Models\AiUsageLog;
use App\Models\Comment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermanentlyDeleteUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly User $user) {}

    public function handle(): void
    {
        DB::transaction(function () {
            // Delete documents
            Document::where('user_id', $this->user->id)->delete();

            // Delete chat messages and chats
            $chatIds = AiChat::where('user_id', $this->user->id)->pluck('id');
            AiChatMessage::whereIn('chat_id', $chatIds)->delete();
            AiChat::whereIn('id', $chatIds)->delete();

            // Delete usage logs
            AiUsageLog::where('user_id', $this->user->id)->delete();

            // Delete credit transactions
            DB::table('credit_transactions')->where('user_id', $this->user->id)->delete();

            // Delete login history
            DB::table('login_history')->where('user_id', $this->user->id)->delete();

            // Anonymize comments
            Comment::where('user_id', $this->user->id)->update([
                'user_id' => null,
                'guest_name' => 'Deleted User',
            ]);

            // Cancel subscriptions via payment gateway
            if ($this->user->subscription_status === 'active') {
                try {
                    $this->user->subscription()?->cancelNow();
                } catch (\Throwable $e) {
                    Log::warning('Failed to cancel subscription during deletion', [
                        'user_id' => $this->user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Remove from newsletter
            DB::table('newsletter_subscribers')->where('email', $this->user->email)->delete();

            // Delete personal API keys
            DB::table('user_byok')->where('user_id', $this->user->id)->delete();

            // Hard delete the user (already soft-deleted with scheduled_deletion_at set)
            $this->user->forceDelete();
        });

        Log::info('User permanently deleted', ['user_id' => $this->user->id]);
    }
}
