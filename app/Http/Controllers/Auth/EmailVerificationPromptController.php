<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $defaultRoute = $request->user()->isStaff() ? 'dashboard' : 'lobby';

        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route($defaultRoute, absolute: false))
                    : redirect()
                        ->route('lobby')
                        ->with('status', 'email-verification-required');
    }
}
