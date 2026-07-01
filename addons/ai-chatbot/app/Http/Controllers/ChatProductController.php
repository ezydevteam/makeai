<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Http\Controllers;

use App\Http\Controllers\Controller;
use Addons\AiChatbot\Models\ChatbotProduct;
use Illuminate\Http\JsonResponse;

class ChatProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ChatbotProduct::active()->orderBy('sort_order')->get(),
        ]);
    }
}
