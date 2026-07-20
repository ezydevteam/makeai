<?php

namespace App\Http\Controllers;

use App\Jobs\IncrementAdImpressions;
use App\Jobs\TrackAdClick;
use App\Models\Ad;
use App\Services\AdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function __construct(private readonly AdsService $ads) {}

    public function getActive(Request $request, string $zone): JsonResponse
    {
        $ad = $this->ads->activeForZone($zone, $request->user());

        if (! $ad) {
            return response()->json(null);
        }

        return response()->json($this->ads->toFrontend($ad));
    }

    public function trackView(Ad $ad): JsonResponse
    {
        IncrementAdImpressions::dispatch($ad->id)->onQueue('low');

        return response()->json(['success' => true]);
    }

    public function trackClick(Ad $ad): JsonResponse
    {
        TrackAdClick::dispatch($ad->id)->onQueue('low');

        return response()->json(['success' => true]);
    }

    public function click(Request $request, Ad $ad): RedirectResponse
    {
        TrackAdClick::dispatch($ad->id)->onQueue('low');

        return redirect()->away($ad->link_url ?: route('home'));
    }
}
