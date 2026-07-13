<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Jobs;

use Addons\AiImageEditor\Events\ImageEditCompleted;
use Addons\AiImageEditor\Models\IeEdit;
use Addons\AiImageEditor\Services\ImageEditorService;
use Addons\AiImageEditor\Services\Providers\ImageEditException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplyImageEdit implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;
    public array $backoff = [60, 300, 600];

    public function __construct(public readonly int $editId)
    {
        // Set here, not as a property default: Queueable already declares $queue
        // with no default, and redeclaring it with one is a fatal trait-composition
        // error — this is what made this addon's test suite unloadable.
        $this->queue = 'media';
    }

    public function handle(ImageEditorService $service): void
    {
        $edit = IeEdit::with('session.user')->find($this->editId);

        if (! $edit || $edit->status !== 'queued') {
            return;
        }

        $edit->update(['status' => 'processing']);

        try {
            $outputPath = $service->apply($edit);
            $outputUrl = Storage::disk('public')->url($outputPath);

            // Post-generation safety gate: discard unsafe output before it is
            // saved or served, and refund the credits charged up front. No-op
            // unless Content Moderation is on in `block` mode; flag mode logs only.
            if (\App\Services\ContentModerationService::fromSettings()->imageViolates($outputUrl, 'image-editor')) {
                Storage::disk('public')->delete($outputPath);
                $this->refundCredits($edit, 'Image edit blocked by content safety — refund');
                $edit->update([
                    'status' => 'failed',
                    'error_message' => 'This edit was blocked by content safety filters.',
                ]);

                event(new ImageEditCompleted($edit));

                return;
            }

            $absPath = Storage::disk('public')->path($outputPath);
            [$width, $height] = file_exists($absPath) ? getimagesize($absPath) : [null, null];

            $edit->update([
                'status' => 'completed',
                'output_path' => $outputPath,
                'output_url' => $outputUrl,
                'completed_at' => now(),
            ]);

            IeEdit::markAsCurrent($edit);

            $edit->session->update([
                'width' => $width ?? $edit->session->width,
                'height' => $height ?? $edit->session->height,
            ]);

            if (addon_setting('ai-image-editor', 'auto_save_to_library', true)) {
                $this->saveToLibrary($edit);
            }

            event(new ImageEditCompleted($edit));
        } catch (ImageEditException $e) {
            $edit->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);

            $this->refundCredits($edit, 'Image edit failed — refund');

            event(new ImageEditCompleted($edit));
        }
    }

    /**
     * Refund the credits deducted up front for this edit and record the
     * transaction. No-op when nothing was charged.
     */
    private function refundCredits(IeEdit $edit, string $reason): void
    {
        if ($edit->credits_deducted <= 0) {
            return;
        }

        $user = $edit->session->user;
        if (! $user) {
            return;
        }

        // Mode-correct: metered mode (Extended + billing) returns wallet credits;
        // quota mode (Regular license) winds back the consumed daily/monthly allowance
        // instead — the up-front charge went through the mode-aware deduct_credits()
        // helper, so the refund must match it (a raw credits increment would hand a
        // quota-mode user free wallet balance and leave their allowance spent).
        $user->refundCredits(
            (float) $edit->credits_deducted,
            $reason . ': ' . $edit->ulid,
            ['edit_ulid' => $edit->ulid],
        );
    }

    public function failed(\Throwable $e): void
    {
        $edit = IeEdit::find($this->editId);

        if (! $edit) {
            return;
        }

        $edit->update([
            'status' => 'failed',
            'error_message' => Str::limit($e->getMessage(), 500),
        ]);

        // Mode-aware refund (see refundCredits()) — never raw-increment the wallet.
        $this->refundCredits($edit, 'Image edit failed — refund');
    }

    public function saveToLibrary(IeEdit $edit): void
    {
        try {
            DB::table('documents')->insert([
                'user_id' => $edit->user_id,
                'title' => 'Edited Image — ' . $edit->operation_label,
                'content' => json_encode([
                    'operation' => $edit->operation,
                    'output_path' => $edit->output_path,
                    'output_url' => $edit->output_url,
                    'prompt' => $edit->params['prompt'] ?? '',
                ]),
                'tool_slug' => 'image-editor',
                'word_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Best-effort — don't break the edit on schema mismatch
        }
    }
}
