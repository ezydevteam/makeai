<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmbedCorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS requests immediately
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        $origin = $request->headers->get('Origin') ?: '*';

        if (method_exists($response, 'header')) {
            $response->header('Access-Control-Allow-Origin', $origin);
            $response->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization');
            $response->header('Access-Control-Max-Age', '86400');
            $response->header('Vary', 'Origin');
        } elseif (method_exists($response, 'headers') && $response->headers) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization');
            $response->headers->set('Access-Control-Max-Age', '86400');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
