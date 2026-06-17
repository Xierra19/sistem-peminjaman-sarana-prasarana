<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function verify(Request $request): Response
    {
        return Inertia::render('Auth/ForgotVerify', [
            'email' => $request->query('email', ''),
            'expiresAt' => null,
        ]);
    }
}
