<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Services\InAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;

class NotifyExportReadyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $adminId,
        private string $filePath,
        private string $filename,
    ) {}

    public function handle(InAppNotificationService $notifications): void
    {
        $admin = Admin::find($this->adminId);
        if (! $admin) {
            return;
        }

        $downloadUrl = route('admin.reports.export.download', ['file' => basename($this->filePath)]);

        $notifications->send($admin, [
            'title' => 'Export ready: ' . $this->filename,
            'message' => 'Your export is ready to download.',
            'category' => 'export',
            'level' => 'success',
            'icon' => 'ti-download',
            'action_url' => $downloadUrl,
            'action_label' => 'Download',
        ]);

        if ($admin->email) {
            Mail::raw(
                "Your export \"{$this->filename}\" is ready.\n\nDownload it here:\n{$downloadUrl}",
                fn (Message $message) => $message
                    ->to($admin->email)
                    ->subject('Export ready: ' . $this->filename)
            );
        }
    }
}
