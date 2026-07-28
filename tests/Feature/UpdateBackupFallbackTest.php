<?php

namespace Tests\Feature;

use App\Services\UpdateService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The pre-update database backup must not depend on a mysqldump binary.
 *
 * backupDatabase() runs at step 1 of an update, before a single file is touched, and its
 * fallback for a missing mysqldump used to be Artisan::call('schema:dump') — which runs
 * mysqldump itself. On a host without the binary that "fallback" threw the very error it
 * was meant to absorb, the exception escaped backupDatabase(), and the update aborted with
 * "The command mysqldump ... failed. Exit Code: 1". Shared hosting, where the MySQL client
 * tools are usually absent and exec() is often in disable_functions, could therefore never
 * self-update.
 *
 * The value-literal tests run on any driver. The full-dump test needs real MySQL — the
 * catalog queries are MySQL-specific by design, since backupDatabase() only reaches this
 * path on a non-sqlite connection — and skips when it is not reachable.
 */
class UpdateBackupFallbackTest extends TestCase
{
    private function service(): UpdateService
    {
        return app(UpdateService::class);
    }

    private function dumpValue(mixed $value): string
    {
        $method = new \ReflectionMethod(UpdateService::class, 'dumpValue');

        return $method->invoke($this->service(), $value, DB::connection()->getPdo());
    }

    // ─── Value literals (any driver) ──

    public function test_null_is_written_as_null_not_an_empty_string(): void
    {
        $this->assertSame('NULL', $this->dumpValue(null));
    }

    public function test_numbers_are_written_unquoted(): void
    {
        $this->assertSame('42', $this->dumpValue(42));
        $this->assertSame('1', $this->dumpValue(true));
        $this->assertSame('0', $this->dumpValue(false));
    }

    /**
     * The case that silently corrupts a backup: settings values, prompts and blog bodies
     * routinely contain apostrophes. Concatenating them would close the string early, and
     * the damage only surfaces when someone tries to restore.
     */
    public function test_quotes_and_backslashes_are_escaped(): void
    {
        $literal = $this->dumpValue("it's a \\ backslash");

        $this->assertStringStartsWith("'", $literal);
        $this->assertStringEndsWith("'", $literal);

        // The inner apostrophe must not sit unescaped inside the literal.
        $this->assertStringNotContainsString("it's", $literal);
    }

    public function test_a_value_that_looks_like_sql_cannot_end_the_statement(): void
    {
        $value = "'); DROP TABLE users; --";
        $literal = $this->dumpValue($value);

        // Driver-agnostic: MySQL escapes with a backslash and sqlite by doubling the
        // quote, so assert the outcome rather than either spelling — the literal must
        // differ from naive concatenation, which is what would break the statement.
        $this->assertNotSame("'".$value."'", $literal);
        $this->assertStringStartsWith("'", $literal);
        $this->assertStringEndsWith("'", $literal);
        $this->assertStringContainsString('DROP TABLE users', $literal); // preserved, just quoted
    }

    // ─── Full dump (needs MySQL) ──

    public function test_the_php_fallback_dumps_schema_and_rows_without_mysqldump(): void
    {
        $this->skipWithoutMysql();

        $path = storage_path('app/backups/pdo-dump-test-'.getmypid().'.sql');
        File::ensureDirectoryExists(dirname($path));
        File::delete($path);

        try {
            $method = new \ReflectionMethod(UpdateService::class, 'dumpDatabaseWithPdo');
            $method->invoke($this->service(), $path);

            $this->assertFileExists($path);
            $sql = File::get($path);

            $this->assertStringContainsString('CREATE TABLE', $sql);
            $this->assertStringContainsString('INSERT INTO `settings`', $sql);

            // Toggled off up front and back on at the end, so a restore is not defeated by
            // whatever order the tables happen to come back in.
            $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=0;', $sql);
            $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=1;', $sql);

            // Base tables only: SHOW CREATE TABLE returns a Create View column for views,
            // which would otherwise emit a broken statement.
            $this->assertStringContainsString('DROP TABLE IF EXISTS `settings`;', $sql);
        } finally {
            File::delete($path);
        }
    }

    /**
     * Read-only against the configured MySQL database: the dumper only issues SHOW and
     * SELECT and writes to a file, so it never mutates anything.
     */
    private function skipWithoutMysql(): void
    {
        if (! Config::get('database.connections.mysql')) {
            $this->markTestSkipped('No mysql connection configured.');
        }

        try {
            DB::connection('mysql')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL not reachable: '.$e->getMessage());
        }

        Config::set('database.default', 'mysql');
        DB::setDefaultConnection('mysql');
    }
}
