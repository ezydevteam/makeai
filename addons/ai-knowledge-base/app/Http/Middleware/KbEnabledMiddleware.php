<?php

namespace Addons\AiKnowledgeBase\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class KbEnabledMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! addon_setting('ai-knowledge-base', 'enabled', true)) {
            abort(404);
        }

        if (! addon_setting('ai-knowledge-base', 'allow_guest_search', true) && ! $request->user()) {
            return redirect('/login');
        }

        return $next($request);
    }
}
