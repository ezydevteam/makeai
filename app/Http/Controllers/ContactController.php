<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ContactController extends Controller
{
    /**
     * Store a new contact message.
     */
    public function store(Request $request)
    {
        $executed = RateLimiter::attempt(
            'contact-submission:'.$request->ip(),
            3,
            function () use ($request) {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|max:255',
                    'subject' => 'nullable|string|max:255',
                    'message' => 'required|string|max:5000',
                ]);

                ContactMessage::create(array_merge($validated, [
                    'ip_address' => $request->ip(),
                ]));
            },
            3600 // 1 hour
        );

        if (! $executed) {
            return back()->with('error', 'Too many messages. Please try again later.');
        }

        return back()->with('success', 'Your message has been sent successfully. We will get back to you soon!');
    }
}
