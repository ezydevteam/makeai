<?php

namespace App\Console\Commands;

use App\Support\RedisProbe;
use Illuminate\Console\Command;

/**
 * Reports how (and whether) Redis is reachable from this server, and prints the
 * exact .env lines to make the application use it.
 *
 * Exists because "Redis is enabled on my host" and "the application can reach
 * Redis" are different claims, and nothing in the product previously let anyone
 * tell them apart on a server they cannot attach a debugger to.
 */
class RedisDiagnose extends Command
{
    protected $signature = 'redis:diagnose';

    protected $description = 'Check whether Redis is reachable and print the .env settings to use it';

    public function handle(): int
    {
        $this->line('');
        $this->info('Redis diagnostics');
        $this->line(str_repeat('─', 60));

        // What the application currently believes.
        $this->line('<comment>Current configuration</comment>');
        $this->row('REDIS_CLIENT', (string) env('REDIS_CLIENT', 'phpredis'));
        $this->row('REDIS_HOST', (string) env('REDIS_HOST', '(unset)'));
        $this->row('REDIS_PORT', (string) env('REDIS_PORT', '(unset)'));
        $this->row('REDIS_PASSWORD', filled(env('REDIS_PASSWORD')) && env('REDIS_PASSWORD') !== 'null' ? '(set)' : '(not set)');
        $this->row('REDIS_URL', (string) env('REDIS_URL', '(unset)'));
        $this->line('');

        $this->line('<comment>PHP support</comment>');
        $this->row('phpredis extension', extension_loaded('redis') ? 'loaded' : 'not loaded');
        $this->row('predis (bundled)', class_exists(\Predis\Client::class) ? 'available' : 'MISSING');
        $this->row('fsockopen()', function_exists('fsockopen') ? 'available' : 'DISABLED by host');
        $this->line('');

        $result = RedisProbe::detect();

        $this->line('<comment>Connection attempts</comment>');
        foreach ($result['attempts'] as $attempt) {
            $this->line(sprintf(
                '  %s %-38s %s%s',
                $attempt['ok'] ? '<fg=green>OK  </>' : '<fg=red>FAIL</>',
                $attempt['target'] . ' (' . $attempt['transport'] . ')',
                $attempt['ok'] ? 'PONG' : '',
                $attempt['error'] !== '' ? '<fg=yellow>' . $attempt['error'] . '</>' : ''
            ));
        }

        if ($result['attempts'] === []) {
            $this->line('  (nothing to try — REDIS_HOST is empty)');
        }

        $this->line('');

        // Printed even on success: knowing a socket exists but TCP answered first is
        // useful, and on failure this is the whole point — without it the report shows
        // a single failed attempt and reads as though nothing else was considered.
        $this->line('<comment>Socket search</comment>');

        if ($result['sockets_found'] !== []) {
            foreach ($result['sockets_found'] as $socket) {
                $this->line("  found: {$socket}");
            }
        } else {
            $this->line('  No socket files found. Locations checked:');

            foreach ($result['searched'] as $path) {
                $this->line("    {$path}");
            }
        }

        $this->line('');

        if (! $result['reachable']) {
            $this->error('No Redis server answered.');
            $this->line('');
            $this->line('The application will keep using the file/database drivers, which work');
            $this->line('everywhere and need no extra service. Nothing is broken — Redis is a');
            $this->line('speed-up, not a requirement.');
            $this->line('');

            // "Connection refused" with the extension present is the single most
            // confusing combination on shared hosting: the host installed PHP's Redis
            // client, which is what their control panel calls "Redis", while no server
            // is running for the account. Name that explicitly.
            $refused = false;
            foreach ($result['attempts'] as $attempt) {
                if (stripos($attempt['error'], 'refused') !== false) {
                    $refused = true;
                }
            }

            if ($refused && extension_loaded('redis')) {
                $this->line('<comment>What this particular result means</comment>');
                $this->line('  The phpredis EXTENSION is installed but the connection was REFUSED,');
                $this->line('  and no socket file was found. That combination almost always means');
                $this->line('  the host enabled PHP\'s Redis client while no Redis server is actually');
                $this->line('  running for your account — the two are separate things, and control');
                $this->line('  panels label both of them "Redis".');
                $this->line('');
            }

            $this->line('<comment>What to ask your host</comment>');
            $this->line('  "Is a Redis server running for my account, and if so do I connect over');
            $this->line('   TCP (which host and port?) or a unix socket (what is the full path?),');
            $this->line('   and does it need a password?"');
            $this->line('');
            $this->line('Put their answer in core/.env as REDIS_HOST / REDIS_PORT / REDIS_PASSWORD');
            $this->line('(REDIS_HOST takes the socket path itself if they give you one), then run');
            $this->line('this command again.');

            return self::FAILURE;
        }

        $this->info("Redis is reachable at {$result['target']} ({$result['transport']}).");
        $this->line('');

        $lines = $result['env'];
        $lines['REDIS_CLIENT'] = RedisProbe::preferredClient();
        $lines['CACHE_STORE'] = 'redis';

        $this->line('<comment>Put these in core/.env</comment>');
        foreach ($lines as $key => $value) {
            $this->line("  {$key}={$value}");
        }

        $this->line('');
        $this->line('Then clear the cached config:');
        $this->line('  php artisan config:clear');
        $this->line('');
        $this->line('<comment>About the queue</comment>');
        $this->line('  QUEUE_CONNECTION is deliberately left alone. Only move it off `sync`');
        $this->line('  once a queue worker is actually running — see core/deploy/cron.txt.');
        $this->line('  Without one, jobs on redis/database are stored and never run, and');
        $this->line('  sign-in codes and password-reset emails silently never arrive.');
        $this->line('');

        return self::SUCCESS;
    }

    private function row(string $label, string $value): void
    {
        $this->line(sprintf('  %-22s %s', $label, $value));
    }
}
