<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(): Response
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'phone', 'role', 'created_at', 'updated_at'])
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => $users->count(),
            'super_admins' => $users->filter(fn (User $user) => $user->isSuperAdmin())->count(),
            'admin_bap' => $users->filter(fn (User $user) => $user->isAdminBap())->count(),
            'admin_sarpras' => $users->filter(fn (User $user) => $user->isAdminSarpras())->count(),
            'members' => $users->where('role', User::ROLE_USER)->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'created_at' => optional($user->created_at)?->toIso8601String(),
                'updated_at' => optional($user->updated_at)?->toIso8601String(),
            ])->values(),
            'stats' => $stats,
            'currentUserId' => Auth::id(),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => [
                ['label' => 'Super Admin', 'value' => User::ROLE_SUPER_ADMIN],
                ['label' => 'Admin BAP', 'value' => User::ROLE_ADMIN_BAP],
                ['label' => 'Admin Sarpras', 'value' => User::ROLE_ADMIN_SARPRAS],
                ['label' => 'User (Mahasiswa)', 'value' => User::ROLE_USER],
            ],
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(\App\Http\Requests\Admin\UpdateUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        
        // Hash password for new user
        $validated['password'] = bcrypt($validated['password']);
        
        User::create($validated);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'created_at' => optional($user->created_at)?->toIso8601String(),
                'updated_at' => optional($user->updated_at)?->toIso8601String(),
            ],
            'roles' => [
                ['label' => 'Super Admin', 'value' => User::ROLE_SUPER_ADMIN],
                ['label' => 'Admin BAP', 'value' => User::ROLE_ADMIN_BAP],
                ['label' => 'Admin Sarpras', 'value' => User::ROLE_ADMIN_SARPRAS],
                ['label' => 'User (Mahasiswa)', 'value' => User::ROLE_USER],
            ],
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if (
            $user->isSuperAdmin()
            && ($validated['role'] ?? $user->role) !== User::ROLE_SUPER_ADMIN
            && $this->isLastAdmin($user->id)
        ) {
            return back()
                ->withErrors(['role' => 'Tidak dapat mengubah role super admin terakhir.'])
                ->with('error', 'Tidak dapat mengubah role super admin terakhir.');
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        $currentUserId = Auth::id();

        if ($currentUserId === $user->id) {
            return back()
                ->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        if ($user->isSuperAdmin() && $this->isLastAdmin($user->id)) {
            return back()
                ->with('error', 'Tidak dapat menghapus super admin terakhir.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    /**
     * Determine whether the given user is the last super admin.
     */
    protected function isLastAdmin(int $userId): bool
    {
        return User::query()
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_LEGACY_ADMIN])
            ->where('id', '!=', $userId)
            ->doesntExist();
    }
}
