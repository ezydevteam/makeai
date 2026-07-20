<?php

use App\Support\AuditLogRedactor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off scrub of admin_audit_logs rows written BEFORE write-time redaction
 * existed. Those payloads may hold plaintext secrets (smtp_password,
 * stripe_secret, api keys, license keys). We run every stored payload through
 * the same AuditLogRedactor the middleware now uses and rewrite it in place.
 *
 * Irreversible by design — down() is a no-op because restoring the original
 * secrets would defeat the purpose (and we intentionally kept no copy).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_audit_logs')
            ->whereNotNull('payload')
            ->where('payload', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $decoded = json_decode((string) $row->payload, true);

                    if (! is_array($decoded) || $decoded === []) {
                        continue;
                    }

                    $clean = AuditLogRedactor::sanitize($decoded);

                    // Skip the write when redaction changed nothing.
                    if ($clean === $decoded) {
                        continue;
                    }

                    DB::table('admin_audit_logs')
                        ->where('id', $row->id)
                        ->update(['payload' => json_encode($clean)]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally irreversible: the plaintext secrets were removed on
        // purpose and were not preserved.
    }
};
