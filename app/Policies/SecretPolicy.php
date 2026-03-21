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
        return $user->tokenCan('secrets:list');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->tokenCan('secrets:create');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Secret $secret): bool
    {
        return $secret->user_id === $user->id && $user->tokenCan('secrets:delete');
    }
}
