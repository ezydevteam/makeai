<?php

namespace App\Jobs;

use App\Models\AiChat;
use App\Models\AiUsageLog;
use App\Models\DataExportRequest;
use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ExportUserDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly User $user,
        private readonly DataExportRequest $exportRequest,
    ) {}

    public function handle(): void
    {
        $this->exportRequest->update(['status' => 'processing']);

        try {
            $zipPath = $this->buildZip();
            // Align the download window with the file-retention window: the
            // scheduled `exports:cleanup` deletes files under storage/app/exports
            // after 24h, so advertising a 48h window would leave a 24h gap where
            // the row looks downloadable but the file is already gone.
            $expiresAt = now()->addHours(24);

            $this->exportRequest->update([
                'status' => 'ready',
                'file_path' => $zipPath,
                'expires_at' => $expiresAt,
            ]);
        } catch (\Throwable $e) {
            Log::error('ExportUserDataJob failed', [
                'user_id' => $this->user->id,
                'export_id' => $this->exportRequest->id,
                'error' => $e->getMessage(),
            ]);

            $this->exportRequest->update(['status' => 'pending']);
            throw $e;
        }
    }

    private function buildZip(): string
    {
        $tempDir = storage_path('app/exports/' . $this->exportRequest->id);
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $this->writeProfileJson($tempDir);
        $this->writeDocuments($tempDir);
        $this->writeChatHistory($tempDir);
        $this->writeUsageHistory($tempDir);
        $this->writeCreditTransactions($tempDir);
        $this->writeLoginHistory($tempDir);

        $zipFile = storage_path('app/exports/user-' . $this->user->id . '-' . now()->format('Ymd') . '.zip');
        $this->createZip($tempDir, $zipFile);

        $this->cleanupTempDir($tempDir);

        return 'exports/' . basename($zipFile);
    }

    private function writeProfileJson(string $dir): void
    {
        $profile = [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'registered_at' => $this->user->created_at?->toIso8601String(),
            'locale' => $this->user->locale,
            'timezone' => $this->user->timezone,
            'theme_preference' => $this->user->theme_preference,
            'subscription_status' => $this->user->subscription_status,
            'plan_id' => $this->user->plan_id,
        ];

        file_put_contents($dir . '/profile.json', json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function writeDocuments(string $dir): void
    {
        $docsDir = $dir . '/documents';
        if (! is_dir($docsDir)) {
            mkdir($docsDir, 0755, true);
        }

        Document::where('user_id', $this->user->id)->chunk(200, function ($documents) use ($docsDir) {
            foreach ($documents as $doc) {
                $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $doc->title) . '.md';
                file_put_contents($docsDir . '/' . $filename, $doc->content ?? '');
            }
        });
    }

    private function writeChatHistory(string $dir): void
    {
        $chatsDir = $dir . '/chat-history';
        if (! is_dir($chatsDir)) {
            mkdir($chatsDir, 0755, true);
        }

        AiChat::where('user_id', $this->user->id)->with('messages')->chunk(50, function ($chats) use ($chatsDir) {
            foreach ($chats as $chat) {
                $conversation = [];
                foreach ($chat->messages as $msg) {
                    $conversation[] = [
                        'role' => $msg->role,
                        'content' => $msg->content,
                        'at' => $msg->created_at?->toIso8601String(),
                    ];
                }
                $title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $chat->title);
                file_put_contents(
                    $chatsDir . '/' . $title . '-' . $chat->ulid . '.json',
                    json_encode($conversation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
            }
        });
    }

    private function writeUsageHistory(string $dir): void
    {
        $fp = fopen($dir . '/usage-history.csv', 'w');
        try {
            fputcsv($fp, ['Date', 'Provider', 'Model', 'Type', 'Input Tokens', 'Output Tokens', 'Credits Used', 'Status']);

            AiUsageLog::where('user_id', $this->user->id)->chunk(500, function ($logs) use ($fp) {
                foreach ($logs as $log) {
                    fputcsv($fp, [
                        $log->created_at?->toIso8601String(),
                        $log->provider,
                        $log->model,
                        $log->type,
                        $log->input_tokens,
                        $log->output_tokens,
                        $log->credits_used,
                        $log->status,
                    ]);
                }
            });
        } finally {
            fclose($fp);
        }
    }

    private function writeCreditTransactions(string $dir): void
    {
        $fp = fopen($dir . '/credit-transactions.csv', 'w');
        try {
            fputcsv($fp, ['Date', 'Type', 'Amount', 'Description']);

            $this->user->creditTransactions()->chunk(500, function ($txns) use ($fp) {
                foreach ($txns as $txn) {
                    fputcsv($fp, [
                        $txn->created_at?->toIso8601String(),
                        $txn->type ?? '',
                        $txn->amount ?? 0,
                        $txn->description ?? '',
                    ]);
                }
            });
        } finally {
            fclose($fp);
        }
    }

    private function writeLoginHistory(string $dir): void
    {
        $fp = fopen($dir . '/login-history.csv', 'w');
        try {
            fputcsv($fp, ['Date', 'IP', 'Country', 'City', 'User Agent', 'Success']);

            $this->user->loginHistory()->chunk(500, function ($logins) use ($fp) {
                foreach ($logins as $login) {
                    fputcsv($fp, [
                        $login->created_at?->toIso8601String(),
                        $login->ip,
                        $login->country,
                        $login->city,
                        $login->user_agent,
                        $login->success ? 'yes' : 'no',
                    ]);
                }
            });
        } finally {
            fclose($fp);
        }
    }

    private function createZip(string $sourceDir, string $zipFile): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create zip file: ' . $zipFile);
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    private function cleanupTempDir(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($dir);
    }
}
