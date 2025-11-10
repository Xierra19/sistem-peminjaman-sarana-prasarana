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
            'admins' => $users->where('role', 'admin')->count(),
            'members' => $users->where('role', 'user')->count(),
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
                ['label' => 'Admin', 'value' => 'admin'],
                ['label' => 'User', 'value' => 'user'],
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
            $user->role === 'admin'
            && ($validated['role'] ?? $user->role) !== 'admin'
            && $this->isLastAdmin($user->id)
        ) {
            return back()
                ->withErrors(['role' => 'Tidak dapat mengubah role admin terakhir menjadi user.'])
                ->with('error', 'Tidak dapat mengubah role admin terakhir menjadi user.');
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

        if ($user->role === 'admin' && $this->isLastAdmin($user->id)) {
            return back()
                ->with('error', 'Tidak dapat menghapus admin terakhir.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User berhasil dihapus.');
    }

    /**
     * Determine whether the given user is the last admin.
     */
    protected function isLastAdmin(int $userId): bool
    {
        return User::query()
            ->where('role', 'admin')
            ->where('id', '!=', $userId)
            ->doesntExist();
    }
}
