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

    public int $timeout = 300;
    public int $tries = 2;
    public array $backoff = [30, 120];
    public string $queue = 'media';

    public function __construct(public readonly int $editId)
    {
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
            $outputUrl = Storage::url($outputPath);

            $absPath = storage_path('app/' . $outputPath);
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

            if ($edit->credits_deducted > 0) {
                DB::table('users')
                    ->where('id', $edit->user_id)
                    ->increment('credits', $edit->credits_deducted);

                $user = $edit->session->user;
                DB::table('credit_transactions')->insert([
                    'user_id' => $edit->user_id,
                    'amount' => $edit->credits_deducted,
                    'balance_after' => $user?->credits,
                    'type' => 'refund',
                    'description' => 'Image edit failed — refund: ' . $edit->ulid,
                    'created_at' => now(),
                ]);
            }

            event(new ImageEditCompleted($edit));
        }
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

        if ($edit->credits_deducted > 0) {
            DB::table('users')
                ->where('id', $edit->user_id)
                ->increment('credits', $edit->credits_deducted);
        }
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
