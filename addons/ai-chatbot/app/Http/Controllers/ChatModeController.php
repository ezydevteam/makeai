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
        return response()->json([
            'success' => true,
            'data' => ChatbotMode::active()->orderBy('sort_order')->get(),
        ]);
    }
}
