<?php

namespace App\Http\Controllers;

use App\Models\CreditPack;
use App\Models\Plan;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function index()
    {
        $plans = Plan::active()
            ->orderBy('sort_order')
            ->get();

        $creditPacks = CreditPack::active()
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Pricing', [
            'plans' => $plans,
            'creditPacks' => $creditPacks,
        ]);
    }
}
