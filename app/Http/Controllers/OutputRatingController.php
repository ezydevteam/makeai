<?php

namespace App\Http\Controllers;

use App\Models\AiOutputRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OutputRatingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tool_slug' => 'required|string|max:100',
            'generation_history_id' => 'nullable|integer',
            'rating' => 'required|in:0,1',
            'feedback_text' => 'nullable|string|max:500',
            'model' => 'nullable|string|max:100',
            'provider' => 'nullable|string|max:50',
        ]);

        $user = Auth::user();

        if ($validated['generation_history_id']) {
            $exists = AiOutputRating::where('user_id', $user->id)
                ->where('generation_history_id', $validated['generation_history_id'])
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Already rated'], 409);
            }
        }

        AiOutputRating::create([
            'user_id' => $user->id,
            'tool_slug' => $validated['tool_slug'],
            'generation_history_id' => $validated['generation_history_id'] ?? null,
            'rating' => $validated['rating'],
            'feedback_text' => $validated['feedback_text'] ?? null,
            'model' => $validated['model'] ?? null,
            'provider' => $validated['provider'] ?? null,
        ]);

        return response()->json(['message' => 'Rated'], 201);
    }
}
