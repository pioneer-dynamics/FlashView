<?php

namespace App\Policies;

use App\Models\Secret;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SecretPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Secret $secret): bool
    {
        if($secret->user_id === $user->id)
            return true;

        return false;
    }
}
