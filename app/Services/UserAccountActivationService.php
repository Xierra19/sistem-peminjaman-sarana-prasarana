<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAccountStatusLog;
use Illuminate\Support\Facades\DB;

class UserAccountActivationService
{
    public function deactivate(User $user, User $actor, string $reason): void
    {
        DB::transaction(function () use ($user, $actor, $reason): void {
            $user->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivation_reason' => $reason,
                'deactivated_by' => $actor->id,
            ]);

            UserAccountStatusLog::query()->create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'action' => UserAccountStatusLog::ACTION_DEACTIVATED,
                'reason' => $reason,
            ]);

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->delete();
            }
        });
    }

    public function activate(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $user->update([
                'is_active' => true,
                'deactivated_at' => null,
                'deactivation_reason' => null,
                'deactivated_by' => null,
            ]);

            UserAccountStatusLog::query()->create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'action' => UserAccountStatusLog::ACTION_ACTIVATED,
            ]);
        });
    }
}
