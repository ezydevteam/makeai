<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ChatbotProduct;
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
