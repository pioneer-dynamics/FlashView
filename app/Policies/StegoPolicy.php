<?php

namespace App\Policies;

use App\Models\User;

class StegoPolicy
{
    /**
     * Determine whether the user can embed a secret into a stego image.
     * Requires the user to be authenticated and on a plan that supports steganography.
     */
    public function embed(User $user): bool
    {
        return $user->planSupportsStego();
    }
}
