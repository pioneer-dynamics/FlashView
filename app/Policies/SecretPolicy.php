<?php

namespace App\Policies;

use App\Models\Secret;
use App\Models\User;

class SecretPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->checkAbility($user, 'secrets:list');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->checkAbility($user, 'secrets:create');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Secret $secret): bool
    {
        return $secret->user_id === $user->id && $this->checkAbility($user, 'secrets:delete');
    }

    /**
     * Check token ability, allowing web session users without Sanctum guard.
     */
    private function checkAbility(User $user, string $ability): bool
    {
        if (! $user->currentAccessToken()) {
            return true;
        }

        return $user->tokenCan($ability);
    }
}
