<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'name' => 'required|string|max:255',
                'nim' => [
                    'required',
                    'string',
                    'digits:11',
                    'unique:users,nim',
                ],
                'phone' => [
                    'required',
                    'string',
                    'max:20',
                    'regex:/^(?:\+62\d{8,13}|0\d{8,13})$/',
                ],
                'email' => [
                    'required',
                    'string',
                    'lowercase',
                    'email',
                    'max:255',
                    'unique:'.User::class,
                    'regex:/^[^@\\s]+@student\\.esaunggul\\.ac\\.id$/i',
                ],
                'password' => [
                    'required',
                    'confirmed',
                    Rules\Password::min(8)->mixedCase()->numbers(),
                ],
            ],
            [
                'email.regex' => 'Registrasi hanya tersedia untuk email student.esaunggul.ac.id.',
                'nim.digits' => 'NIM harus terdiri dari tepat 11 digit angka.',
                'nim.unique' => 'NIM sudah terdaftar.',
                'phone.regex' => 'Nomor telepon harus menggunakan format Indonesia yang valid.',
                'password.mixed' => 'Password harus mengandung minimal satu huruf besar dan satu huruf kecil.',
                'password.numbers' => 'Password harus mengandung minimal satu angka.',
            ]
        );

        $user = User::create([
            'name' => $validated['name'],
            'nim' => $validated['nim'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'],
            'role' => User::ROLE_USER,
            'password' => $validated['password'],
        ]);

        Auth::login($user);

        event(new Registered($user));

        return redirect()->route('verification.notice')
            ->with('status', 'verification-link-sent');
    }
}
