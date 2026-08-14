<?php

namespace App\Http\Controllers;

use App\Services\DemoSelectionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Demo-only endpoints. Everything here 404s when demo mode is off, so these routes
 * simply do not exist on a real buyer's install.
 */
class DemoController extends Controller
{
    /**
     * Select the active demo (theme preset or addon homepage) shown by the demo bar's
     * modal. GET only — demo mode blocks writes, so the choice is stored in a cookie
     * rather than a setting. A full-page redirect back lets the browser re-fetch the
     * theme CSS with the new preset applied.
     */
    public function select(Request $request, DemoSelectionResolver $resolver): RedirectResponse
    {
        abort_unless(config('demo.enabled'), 404);

        // 'default', empty, or anything not on the current menu resolves to null and
        // clears the selection (falls back to the site's real configuration).
        $key = $resolver->resolveKey((string) $request->query('demo', ''));

        $redirect = redirect()->to(
            $this->targetUrl($key === null ? null : $resolver->shortName($key))
        );

        if ($key === null) {
            return $redirect->withCookie(cookie()->forget('demo_selection'));
        }

        // 30 days — long enough that the choice sticks while a buyer explores the demo.
        return $redirect->withCookie(cookie('demo_selection', $key, 60 * 24 * 30));
    }

    /**
     * The page to land back on, with `?demo=<name>` stamped into (or stripped out of) its
     * query string so the address bar names the demo being viewed and the link can be
     * copied and shared. The cookie still carries the choice across ordinary navigation
     * and to the theme-CSS request, which has no query string of its own.
     *
     * Only the path and query of the referrer are reused — the host is always rebuilt
     * from the app URL, so a forged Referer cannot turn this into an open redirect.
     */
    private function targetUrl(?string $name): string
    {
        $previous = parse_url(url()->previous() ?: '/');

        parse_str($previous['query'] ?? '', $query);
        unset($query['demo']);

        if ($name !== null) {
            $query['demo'] = $name;
        }

        return url($previous['path'] ?? '/')
            . ($query === [] ? '' : '?' . http_build_query($query))
            . (isset($previous['fragment']) ? '#' . $previous['fragment'] : '');
    }
}
