<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => [
                    'is_admin' => $user?->isAdmin() ?? false,
                    'can_manage_users' => $user?->canManageUsers() ?? false,
                    'can_manage_history' => $user?->canManageHistory() ?? false,
                    'can_manage_room_module' => $user?->canManageRoomModule() ?? false,
                    'can_manage_item_module' => $user?->canManageItemModule() ?? false,
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->pull('success'),
                'error'   => fn () => $request->session()->pull('error'),
            ],
        ];
    }
}
