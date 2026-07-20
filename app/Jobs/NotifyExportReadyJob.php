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
use Illuminate\Support\Facades\Log;
use Mail;

/**
 * Admin report exports (Export Center). The recipient is a site admin, not a
 * customer, so this stays a hardcoded system notification — deliberately not a
 * mail template: there is nothing here an admin would want to rebrand or edit.
 *
 * The customer-facing counterpart is ExportUserDataJob, which does use the
 * editable 'export_ready' template.
 */
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
            try {
                Mail::raw(
                    "Your export \"{$this->filename}\" is ready.\n\nDownload it here:\n{$downloadUrl}",
                    fn (Message $message) => $message
                        ->to($admin->email)
                        ->subject('Export ready: ' . $this->filename)
                );
            } catch (\Throwable $e) {
                Log::warning('NotifyExportReadyJob: email send failed', [
                    'admin_id' => $this->adminId,
                    'filename' => $this->filename,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
