<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Http\Middleware;

use App\Services\AccessLevelService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the Voiceover Studio server-side:
 *  - the admin "Enabled" setting must actually block access (not just hide the menu);
 *  - the "Show to" access level is enforced using the same shared AccessLevelService
 *    the core AI tools use (guest / login / premium / plan:*).
 */
class VoiceoverAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Master enable toggle — disabled feature must be unreachable, not just hidden.
        if (! (bool) addon_setting('ai-voiceover', 'enabled', true)) {
            abort(404);
        }

        // Access level — identical semantics to core AI tools. Legacy values
        // (all/logged_in/pro) are mapped onto the shared access-level keys.
        $level = (string) addon_setting('ai-voiceover', 'show_to', 'login');
        $level = match ($level) {
            'all' => 'guest',
            'logged_in' => 'login',
            'pro' => 'premium',
            '', 'inherit' => 'login',
            default => $level,
        };

        if (! app(AccessLevelService::class)->checkAccess($level, $request->user())) {
            abort(403, translate('You do not have access to the Voiceover Studio.'));
        }

        return $next($request);
    }
}
