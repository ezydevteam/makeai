<?php

namespace App\Support;

/**
 * Keep .env readable only by the account that owns it.
 *
 * This is the one piece of the nginx exposure problem PHP can actually act on. The
 * distribution layout keeps the app in <webroot>/core, private by web server deny rules,
 * and nginx honours none of them — so an unconfigured VPS serves core/.env in the clear.
 * A config file cannot be written or reloaded from here, but permissions can.
 *
 * On the usual split — PHP-FPM running as the site user, nginx workers as nginx/www-data
 * — mode 0600 means the worker cannot open the file, and the request it would have
 * answered with your database password becomes a 403. Where both run as the same user it
 * changes nothing, which is why this hardens rather than fixes, and the health check keeps
 * warning until the server config is right.
 */
class EnvFilePermissions
{
    /** Owner read/write only. */
    public const SECURE_MODE = 0600;

    /**
     * Returns true when the file ends up owner-only, false when it could not be changed
     * (another owner, an immutable mount, a filesystem without POSIX modes).
     */
    public static function harden(?string $path = null): bool
    {
        $path ??= base_path('.env');

        if (! is_file($path)) {
            return false;
        }

        if (self::mode($path) === self::SECURE_MODE) {
            return true;
        }

        // Silenced deliberately: a host that forbids chmod must not turn an install or a
        // health check into a 500. The caller reports the outcome instead.
        if (! @chmod($path, self::SECURE_MODE)) {
            return false;
        }

        clearstatcache(true, $path);

        return self::mode($path) === self::SECURE_MODE;
    }

    /** Current permission bits, e.g. 0644. Null when the file is unreadable. */
    public static function mode(?string $path = null): ?int
    {
        $path ??= base_path('.env');

        if (! is_file($path)) {
            return null;
        }

        $perms = @fileperms($path);

        return $perms === false ? null : ($perms & 0777);
    }

    /** Readable by group or other — the state that lets a foreign nginx worker serve it. */
    public static function isWorldOrGroupReadable(?string $path = null): bool
    {
        $mode = self::mode($path);

        return $mode !== null && ($mode & 0044) !== 0;
    }
}
