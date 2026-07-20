<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

use App\Services\ToolFavoritesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ToolFavoriteController extends Controller
{
    public function toggle(string $toolSlug): JsonResponse
    {
        $user = Auth::user();
        $service = app(ToolFavoritesService::class);
        $favorited = $service->toggleFavorite($user, $toolSlug);

        return response()->json(['favorited' => $favorited]);
    }
}
