<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StripeController extends Controller
{
    public function billingPortal(Request $request): RedirectResponse
    {
        return $request->user()->redirectToBillingPortal(route('user.dashboard'));
    }

    public function success(Request $request): Response
    {
        return Inertia::render('Checkout/Success');
    }
}
