<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Http\Controllers;

use App\Http\Controllers\Controller;
use Addons\AiChatbot\Models\ChatbotMode;
use Illuminate\Http\JsonResponse;

class ChatModeController extends Controller
{
    public function index(): JsonResponse
    {
        $query = ChatbotMode::active()->orderBy('sort_order');

        // Restrict to the admin-selected set of modes. An empty/unset list means
        // "no restriction" — all active modes are available (also lets modes added
        // later appear automatically).
        $enabled = addon_setting('ai-chatbot', 'enabled_modes', []);
        if (is_string($enabled)) {
            $decoded = json_decode($enabled, true);
            $enabled = is_array($decoded) ? $decoded : [];
        }
        if (is_array($enabled) && count($enabled) > 0) {
            $query->whereIn('slug', $enabled);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }
}
