<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * Exports the current MySQL database as the installer's data-only bootstrap
 * (database/data/data.sql).
 *
 * The distribution ships migrations (which own the schema) but NOT the real
 * seeder classes, so the installer reconstructs the essential data — settings,
 * tools, pages, mail templates, etc. — by importing this dump AFTER migrating.
 * It is therefore data-only (no CREATE TABLE) and skips the migrations table
 * plus all transient/runtime tables. Regenerate it after any schema or seed
 * change:
 *
 *   php artisan migrate:fresh --seed && php artisan installer:export-data
 */
class InstallerExportData extends Command
{
    protected $signature = 'installer:export-data
        {--output= : Destination path (default: database/data/data.sql)}
        {--mysqldump= : Path to the mysqldump binary if it is not on PATH}
        {--fresh : Run migrate:fresh --seed before exporting (guarantees a clean baseline)}
        {--force : Skip confirmations (for --fresh and the dirty-data guard)}';

    protected $description = 'Export the current database as the installer data-only bootstrap (data.sql)';

    /**
     * Runtime/transient tables that must never ship: the migrations table is
     * owned by `migrate` (which runs before the import), and the rest are
     * session/cache/queue/token scratch data.
     */
    private const IGNORED_TABLES = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'personal_access_tokens',
        // Addons are sold and shipped separately, so the core package contains no
        // addon files. Exporting this table would pre-register addons the buyer
        // does not have: AddonService::syncFromFilesystem() cannot clean them up
        // (its deactivation pass is skipped entirely when addons/ is empty), so
        // they would sit in the admin panel looking installed. Rows appear here
        // legitimately when the buyer uploads an addon.
        'addons',
        // Per-addon config and purchased license keys, for the same reason —
        // addon_licenses additionally holds the developer's own license keys,
        // which must never leave this machine.
        'addon_settings',
        'addon_licenses',
    ];

    /**
     * Tables that must be empty in a clean baseline. Rows here mean the export
     * was taken from a working database and would leak real accounts, keys,
     * payments, chats or logs into the shipped dump. The guard below refuses to
     * proceed (without --force/--fresh) when any of these contain data.
     */
    private const USER_DATA_TABLES = [
        'users', 'ai_keys', 'user_byok', 'conversations', 'conversation_messages',
        'ai_chats', 'ai_chat_messages', 'generation_history', 'ai_usage_logs',
        'credit_transactions', 'payments', 'documents', 'faker_ai_records',
        'faker_ai_batches', 'notifications', 'contact_messages', 'support_tickets',
        'comments', 'tool_reviews', 'favorites', 'user_collections',
        'login_history', 'admin_login_history', 'admin_audit_logs', 'blog_post_views',
    ];

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error("The default connection [{$connection}] is not MySQL. Point your .env at the MySQL database you want to export, then retry.");

            return self::FAILURE;
        }

        // A clean baseline comes from a fresh seed. Offer to guarantee it.
        if ($this->option('fresh')) {
            if (! $this->option('force') && ! $this->confirm('This will DROP ALL TABLES and reseed the database before exporting. Continue?')) {
                return self::FAILURE;
            }

            $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        } elseif (! $this->guardAgainstDirtyData($connection)) {
            return self::FAILURE;
        }

        $mysqldump = $this->option('mysqldump') ?: 'mysqldump';

        // Fail early with actionable guidance if the binary isn't reachable.
        $version = Process::run([$mysqldump, '--version']);
        if (! $version->successful()) {
            $this->error('Could not run mysqldump.');
            $this->line('  • Ensure MySQL client tools are on your PATH, or pass --mysqldump="C:\\laragon\\bin\\mysql\\<version>\\bin\\mysqldump.exe".');

            return self::FAILURE;
        }

        $database = $config['database'];
        $output = $this->option('output') ?: database_path('data/data.sql');

        $dir = dirname($output);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Could not create output directory: {$dir}");

            return self::FAILURE;
        }

        // Credentials go through a temp defaults-file rather than the command
        // line — this avoids leaking the password in the process list and the
        // "Using a password on the command line is insecure" warning. It must be
        // the first argument passed to mysqldump.
        $cnf = tempnam(sys_get_temp_dir(), 'mad');
        $this->writeDefaultsFile($cnf, $config);

        try {
            $args = [
                $mysqldump,
                "--defaults-extra-file={$cnf}",
                '--no-create-info',       // data only — migrations own the schema
                '--complete-insert',      // column names survive schema column-order drift
                '--single-transaction',   // consistent snapshot, no locks
                '--skip-triggers',        // triggers belong to migrations
                '--no-tablespaces',       // avoids PROCESS privilege errors on shared hosts
                '--default-character-set=utf8mb4',
                '--skip-comments',        // no host/version/timestamp noise in the diff
            ];

            $addonTables = $this->addonOwnedTables();
            $ignored = array_merge(self::IGNORED_TABLES, $addonTables);

            if ($addonTables !== []) {
                $this->line(sprintf(
                    'Skipping %d addon-owned table(s): %s',
                    count($addonTables),
                    implode(', ', $addonTables),
                ));
            }

            foreach ($ignored as $table) {
                $args[] = "--ignore-table={$database}.{$table}";
            }

            $args[] = $database;

            $this->info("Exporting '{$database}' → {$output}");
            $result = Process::timeout(600)->run($args);

            if (! $result->successful()) {
                $this->error('mysqldump failed:');
                $this->line('  ' . trim($result->errorOutput() ?: $result->output()));

                return self::FAILURE;
            }

            $sql = $result->output();

            // Belt-and-suspenders: guarantee FK checks are off across the whole
            // import regardless of the buyer's mysqldump version defaults.
            $sql = "SET FOREIGN_KEY_CHECKS=0;\n" . $sql . "\nSET FOREIGN_KEY_CHECKS=1;\n";

            if (file_put_contents($output, $sql) === false) {
                $this->error("Could not write to {$output}");

                return self::FAILURE;
            }

            $this->newLine();
            $this->info(sprintf(
                '✔ Wrote %s (%s KB, %d INSERT statements, %d tables skipped).',
                $output,
                number_format(strlen($sql) / 1024, 1),
                substr_count($sql, 'INSERT INTO'),
                count($ignored),
            ));

            return self::SUCCESS;
        } finally {
            @unlink($cnf);
        }
    }

    /**
     * Tables created by addon migrations, discovered by scanning addons/ rather
     * than hard-coded, so the list cannot rot as addons are added or renamed.
     *
     * A dev database has every addon installed, so mysqldump would otherwise emit
     * a LOCK TABLES block for each of their tables. The buyer's core package
     * ships no addon migrations, so those tables never exist at import time and
     * the whole install dies on ERROR 1146 partway through — after migrate has
     * already run. Rows in them would also pre-populate addons the buyer has not
     * bought.
     *
     * @return list<string>
     */
    private function addonOwnedTables(): array
    {
        $tables = [];

        foreach (glob(base_path('addons/*/database/migrations/*.php')) ?: [] as $migration) {
            $source = file_get_contents($migration);

            if ($source === false) {
                continue;
            }

            if (preg_match_all('/Schema::create\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $source, $matches)) {
                foreach ($matches[1] as $table) {
                    $tables[$table] = true;
                }
            }
        }

        $tables = array_keys($tables);
        sort($tables);

        return $tables;
    }

    /**
     * Refuse to export when user/runtime tables contain rows — that means the
     * database isn't a clean baseline and the dump would leak real data. Returns
     * true if it's safe (or the operator confirms/forces) to continue.
     */
    private function guardAgainstDirtyData(string $connection): bool
    {
        $dirty = [];

        foreach (self::USER_DATA_TABLES as $table) {
            if (Schema::connection($connection)->hasTable($table)) {
                $count = DB::connection($connection)->table($table)->count();
                if ($count > 0) {
                    $dirty[$table] = $count;
                }
            }
        }

        if (empty($dirty)) {
            return true;
        }

        $this->warn('This database contains data in user/runtime tables — exporting now would ship it to buyers:');
        foreach ($dirty as $table => $count) {
            $this->line(sprintf('  • %-24s %s row(s)', $table, number_format($count)));
        }
        $this->newLine();
        $this->line('Generate the baseline from a clean seed instead:  php artisan installer:export-data --fresh');

        if ($this->option('force')) {
            $this->warn('Proceeding anyway because --force was given.');

            return true;
        }

        return $this->confirm('Export anyway (may include real/dev data)?', false);
    }

    /**
     * Write a MySQL client defaults-file with the connection credentials.
     */
    private function writeDefaultsFile(string $path, array $config): void
    {
        $q = static fn ($v) => '"' . str_replace('"', '\"', (string) $v) . '"';

        $lines = [
            '[client]',
            'host = ' . $q($config['host'] ?? '127.0.0.1'),
            'port = ' . $q($config['port'] ?? '3306'),
            'user = ' . $q($config['username'] ?? 'root'),
            'password = ' . $q($config['password'] ?? ''),
        ];

        file_put_contents($path, implode("\n", $lines) . "\n");
        @chmod($path, 0600);
    }
}
