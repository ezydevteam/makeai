<?php

namespace App\Support;

/**
 * The demo accounts, and whether they are allowed to sign in here.
 *
 * A demo site publishes its own credentials on the sign-in page — that is the point of it.
 * The problem is what happens afterwards: the demo package ships a seeded database, and a
 * buyer who installs it and then turns DEMO_ENABLED off is left running a real site whose
 * super-admin password is printed in the product listing. Nothing else stops that login,
 * because the account is an ordinary row with an ordinary hash.
 *
 * So the credentials are gated on demo mode itself rather than on the accounts existing:
 * with demo mode off, they are refused before authentication is even attempted.
 *
 * Two things are matched, because DemoSeeder creates far more than the two published
 * accounts:
 *
 *   - the configured demo emails — the pair shown on the sign-in page;
 *   - either configured demo PASSWORD — which is what the fifty sample users, the three
 *     staff admins and the referral fixtures all share.
 *
 * Matching on the password is what closes the long tail in one rule instead of a list of
 * addresses that would drift the moment the seeder gained another fixture. It cannot lock
 * a genuine user out of an account whose password is not already a string this site's own
 * configuration publishes.
 */
class DemoCredentials
{
    /**
     * Whether this sign-in attempt uses demo credentials that must not work on this install.
     */
    public static function blocked(?string $email, ?string $password): bool
    {
        // On a demo site these credentials are the front door, not a back one.
        if (config('demo.enabled')) {
            return false;
        }

        return self::matchesEmail($email) || self::matchesPassword($password);
    }

    /**
     * The message shown when an attempt is refused.
     *
     * Deliberately specific: it names demo mode rather than pretending the password is
     * wrong. The operator on the other end of this is usually the site's own owner
     * wondering why admin@demo.com stopped working, and there is nothing to conceal from
     * an attacker who read the same credentials in the product listing.
     */
    public static function message(): string
    {
        return translate('Demo accounts can only sign in while demo mode is enabled.');
    }

    private static function matchesEmail(?string $email): bool
    {
        $email = mb_strtolower(trim((string) $email));

        if ($email === '') {
            return false;
        }

        foreach ([config('demo.admin_email'), config('demo.user_email')] as $configured) {
            if (filled($configured) && mb_strtolower(trim((string) $configured)) === $email) {
                return true;
            }
        }

        return false;
    }

    private static function matchesPassword(?string $password): bool
    {
        $password = (string) $password;

        if ($password === '') {
            return false;
        }

        foreach ([config('demo.admin_password'), config('demo.user_password')] as $configured) {
            // A demo install with no password configured seeds no accounts either
            // (DemoSeeder refuses), so a blank one matches nothing.
            if (filled($configured) && hash_equals((string) $configured, $password)) {
                return true;
            }
        }

        return false;
    }
}
