<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use Inertia\Response;

class UserOnboardingController extends Controller
{
    /**
     * Tampilkan halaman onboarding user baru.
     */
    public function index(): Response
    {
        return inertia('Admin/Users/Onboarding', [
            'roles' => [
                ['label' => 'Admin', 'value' => 'admin'],
                ['label' => 'User', 'value' => 'user'],
            ],
        ]);
    }

    /**
     * Simpan user baru yang ditambahkan oleh admin.
     */
    public function store(StoreUserRequest $request)
    {
        User::create($request->validated());

        return redirect()
            ->route('admin.users.onboarding')
            ->with('success', 'User baru berhasil ditambahkan.');
    }
}

