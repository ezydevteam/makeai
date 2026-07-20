<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Update the user's theme preference.
     */
    public function updateTheme(Request $request)
    {
        $request->validate([
            'theme' => 'required|string|in:light,dark,system',
        ]);

        $user = Auth::user();
        $user->theme_preference = $request->theme;
        $user->save();

        return back();
    }
}
