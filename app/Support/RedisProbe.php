<?php

namespace App\Support;

/**
 * Finds out whether a Redis server is actually reachable, and how.
 *
 * The installer used to answer this with a single fsockopen() to
 * REDIS_HOST:REDIS_PORT, which only ever sees the one shape Redis can take:
 * plain TCP on 127.0.0.1:6379 with no password. Shared hosts — where most
 * copies of this product run — usually expose it as a per-user unix socket, and
 * often behind AUTH. Worse, the wizard has no Redis step, so that probe ran
 * against .env defaults the buyer never had a chance to correct: on those hosts
 * detection could not succeed no matter what the server was doing.
 *
 * So this probes every shape, reports which one answered, and hands back the
 * .env values that would make the application use it. Every probe is a raw
 * socket PING with a short timeout: no extension is assumed, no driver has to
 * boot, and a wrong address fails fast instead of throwing from inside Predis.
 */
class RedisProbe
{
    /** Seconds to wait for a connection and for the PING reply. */
    private const TIMEOUT = 1.0;

    /**
     * Unix sockets to try when the configured address does not answer, in the
     * order they are worth trying. cPanel/CloudLinux put a per-user socket under
     * the home directory; distro packages use /var/run or /run.
     *
     * Paths are candidates, not guarantees — each is only reported if a real
     * PING succeeds on it.
     */
    private const SOCKET_CANDIDATES = [
        '~/.redis/redis.sock',
        '~/.redis/redis-server.sock',
        '/var/run/redis/redis.sock',
        '/var/run/redis/redis-server.sock',
        '/run/redis/redis.sock',
        '/var/run/redis-server/redis-server.sock',
        '/tmp/redis.sock',
    ];

    /**
     * Where to go looking when none of the fixed paths exist. Hosts name the socket
     * whatever they like — redis.sock, redis-<user>.sock, .redis/6379.sock — so match
     * on shape and location instead of guessing the exact name.
     *
     * Ordered cheapest-first: the user's own home directory is always readable, while
     * /var/run entries are usually not listable on shared hosting.
     */
    private const SOCKET_SEARCH = [
        '~/.redis/*.sock',
        '~/tmp/*.sock',
        '~/*.sock',
        '/var/run/redis*/*.sock',
        '/run/redis*/*.sock',
        '/var/run/*redis*.sock',
        '/run/*redis*.sock',
        '/tmp/*redis*.sock',
    ];

    /**
     * Probe every known shape and return the first that answers.
     *
     * @return array{
     *     reachable: bool,
     *     target: string|null,
     *     transport: string|null,
     *     env: array<string, string>,
     *     attempts: list<array{target: string, transport: string, ok: bool, error: string}>,
     *     searched: list<string>,
     *     sockets_found: list<string>
     * }
     *   `searched` and `sockets_found` exist so a report can say where it looked, not
     *   just that it failed. A probe that silently skips every non-existent path reads
     *   as "only tried one thing" and sends the operator chasing the wrong problem.
     *   `env` holds the .env keys that would point the app at whatever answered —
     *   empty when the configured address already works, so callers can tell
     *   "found it where you said" from "found it somewhere else".
     */
    public static function detect(): array
    {
        $attempts = [];
        $searched = [];
        $sockets = [];

        $configuredHost = (string) env('REDIS_HOST', '127.0.0.1');
        $configuredPort = (int) env('REDIS_PORT', 6379);

        // 1. Whatever is configured right now, in whichever shape it is written.
        //    A host beginning with / is a socket path — that is how both Predis and
        //    PhpRedis read it, so it is how we read it too.
        if ($configuredHost !== '') {
            $isSocket = str_starts_with($configuredHost, '/');

            $result = $isSocket
                ? self::ping('unix://' . $configuredHost, -1)
                : self::ping($configuredHost, $configuredPort);

            $attempts[] = [
                'target' => $isSocket ? $configuredHost : "{$configuredHost}:{$configuredPort}",
                'transport' => $isSocket ? 'unix socket' : 'tcp',
                'ok' => $result === true,
                'error' => is_string($result) ? $result : '',
            ];

            if ($result === true) {
                return [
                    'reachable' => true,
                    'target' => $attempts[0]['target'],
                    'transport' => $attempts[0]['transport'],
                    // Already correct in .env — nothing to rewrite.
                    'env' => [],
                    'attempts' => $attempts,
                    'searched' => $searched,
                    'sockets_found' => $sockets,
                ];
            }
        }

        // 2. Unix sockets: the fixed candidates first, then anything socket-shaped in
        //    the places hosts put them. Only reached when the configured address
        //    failed, which is the shared-hosting case the old probe could not see.
        $sockets = self::findSockets($searched);

        foreach ($sockets as $path) {
            if ($path === $configuredHost) {
                continue;
            }

            $result = self::ping('unix://' . $path, -1);

            $attempts[] = [
                'target' => $path,
                'transport' => 'unix socket',
                'ok' => $result === true,
                'error' => is_string($result) ? $result : '',
            ];

            if ($result === true) {
                return [
                    'reachable' => true,
                    'target' => $path,
                    'transport' => 'unix socket',
                    'env' => ['REDIS_HOST' => $path],
                    'attempts' => $attempts,
                    'searched' => $searched,
                    'sockets_found' => $sockets,
                ];
            }
        }

        // 3. Plain localhost TCP, unless that is exactly what was already tried. Catches
        //    a stale hostname from another environment (`redis` from a Docker compose
        //    file is the classic) and a wrong port, both of which leave a perfectly
        //    healthy local server undetected.
        $alreadyTried = ($configuredHost === '127.0.0.1' || $configuredHost === 'localhost')
            && $configuredPort === 6379;

        if (! $alreadyTried) {
            $result = self::ping('127.0.0.1', 6379);

            $attempts[] = [
                'target' => '127.0.0.1:6379',
                'transport' => 'tcp',
                'ok' => $result === true,
                'error' => is_string($result) ? $result : '',
            ];

            if ($result === true) {
                return [
                    'reachable' => true,
                    'target' => '127.0.0.1:6379',
                    'transport' => 'tcp',
                    'env' => ['REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '6379'],
                    'attempts' => $attempts,
                    'searched' => $searched,
                    'sockets_found' => $sockets,
                ];
            }
        }

        return [
            'reachable' => false,
            'target' => null,
            'transport' => null,
            'env' => [],
            'attempts' => $attempts,
            'searched' => $searched,
            'sockets_found' => $sockets,
        ];
    }

    /** Convenience for callers that only care whether Redis is there at all. */
    public static function isReachable(): bool
    {
        return self::detect()['reachable'];
    }

    /**
     * The client the app should use: PhpRedis when the extension is present,
     * otherwise the bundled Predis, so Redis works either way.
     */
    public static function preferredClient(): string
    {
        return extension_loaded('redis') ? 'phpredis' : 'predis';
    }

    /**
     * PING one address. Returns true on +PONG, or a short reason string.
     *
     * The reason matters: "connection refused" and "NOAUTH" send the operator to
     * completely different places, and a bare false told them neither.
     */
    private static function ping(string $host, int $port): bool|string
    {
        $errno = 0;
        $errstr = '';

        $fp = @fsockopen($host, $port, $errno, $errstr, self::TIMEOUT);

        if (! $fp) {
            return $errstr !== '' ? trim($errstr) : 'could not connect';
        }

        try {
            stream_set_timeout($fp, (int) self::TIMEOUT);

            $password = (string) env('REDIS_PASSWORD', '');
            $username = (string) env('REDIS_USERNAME', '');

            if ($password !== '' && $password !== 'null') {
                // Redis 6 ACLs take a username; older servers take the password alone.
                $auth = $username !== ''
                    ? "AUTH {$username} {$password}\r\n"
                    : "AUTH {$password}\r\n";

                fwrite($fp, $auth);
                $reply = fgets($fp, 128);

                if (! is_string($reply) || $reply === '' || $reply[0] !== '+') {
                    return 'authentication failed: ' . trim((string) $reply);
                }
            }

            fwrite($fp, "PING\r\n");
            $pong = fgets($fp, 128);

            if (is_string($pong) && str_starts_with($pong, '+PONG')) {
                return true;
            }

            // A server with requirepass set answers PING with -NOAUTH when no
            // password was sent — the single most useful thing to report back.
            return $pong === false || $pong === ''
                ? 'no reply (timed out)'
                : trim($pong);
        } catch (\Throwable $e) {
            return $e->getMessage();
        } finally {
            fclose($fp);
        }
    }

    /**
     * Every socket file worth trying, fixed candidates first then globbed ones.
     *
     * $searched collects each location examined — including the ones that turned up
     * nothing — because "we looked in these eight places and found no socket" is what
     * lets someone go back to their host with a specific question, and a silent skip
     * looks identical to never having checked.
     *
     * @param  list<string>  $searched
     * @return list<string>
     */
    private static function findSockets(array &$searched): array
    {
        $found = [];

        foreach (self::SOCKET_CANDIDATES as $candidate) {
            $path = self::expandHome($candidate);

            if ($path === null) {
                continue;
            }

            $searched[] = $path;

            if (@file_exists($path)) {
                $found[] = $path;
            }
        }

        foreach (self::SOCKET_SEARCH as $pattern) {
            $expanded = self::expandHome($pattern);

            if ($expanded === null) {
                continue;
            }

            $searched[] = $expanded;

            // GLOB_NOSORT keeps this cheap; glob() returns false on an unreadable
            // directory, which is the norm for /var/run on shared hosting.
            $matches = @glob($expanded, GLOB_NOSORT) ?: [];

            foreach ($matches as $match) {
                if (! in_array($match, $found, true)) {
                    $found[] = $match;
                }
            }
        }

        return $found;
    }

    /** Resolve a leading ~ against the process's home directory. */
    private static function expandHome(string $path): ?string
    {
        if (! str_starts_with($path, '~')) {
            return $path;
        }

        $home = getenv('HOME') ?: null;

        if ($home === null && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $home = (posix_getpwuid(posix_geteuid())['dir'] ?? null);
        }

        return $home ? rtrim($home, '/') . substr($path, 1) : null;
    }
}
