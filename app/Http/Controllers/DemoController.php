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

        $key = (string) $request->query('demo', '');
        $redirect = redirect()->back(fallback: '/');

        // 'default', empty, or anything not on the current menu clears the selection
        // (falls back to the site's real configuration).
        if ($key === '' || $key === 'default' || ! $resolver->isValid($key)) {
            return $redirect->withCookie(cookie()->forget('demo_selection'));
        }

        // 30 days — long enough that the choice sticks while a buyer explores the demo.
        return $redirect->withCookie(cookie('demo_selection', $key, 60 * 24 * 30));
    }
}
