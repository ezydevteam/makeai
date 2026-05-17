<?php

namespace App\Http\Controllers;

use App\Models\Ad;

class AdController extends Controller
{
    public function getActive(string $placement)
    {
        $ad = Ad::active()
            ->where('placement', $placement)
            ->inRandomOrder()
            ->first();

        if (! $ad) {
            return response()->json(null);
        }

        return response()->json([
            'id' => $ad->id,
            'type' => $ad->type,
            'content' => $ad->content,
            'image_url' => $ad->image_path ? asset('storage/'.$ad->image_path) : null,
            'link_url' => $ad->link_url,
        ]);
    }

    public function trackView(Ad $ad)
    {
        $ad->increment('views');

        return response()->json(['success' => true]);
    }

    public function trackClick(Ad $ad)
    {
        $ad->increment('clicks');

        return response()->json(['success' => true]);
    }
}
