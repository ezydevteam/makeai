<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * Toggle a favorite.
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'favoriteable_type' => 'required|string',
            'favoriteable_id' => 'required|integer',
        ]);

        $userId = Auth::id();
        $type = $request->favoriteable_type;
        $id = $request->favoriteable_id;

        $favorite = Favorite::where('user_id', $userId)
            ->where('favoriteable_type', $type)
            ->where('favoriteable_id', $id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return back()->with('info', 'Removed from favorites.');
        } else {
            Favorite::create([
                'user_id' => $userId,
                'favoriteable_type' => $type,
                'favoriteable_id' => $id,
            ]);

            return back()->with('success', 'Added to favorites!');
        }
    }
}
