<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChatGuestAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            return $next($request);
        }

        if (addon_setting('ai-chatbot', 'allow_guest_messages', false)) {
            return $next($request);
        }

        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
    }
}