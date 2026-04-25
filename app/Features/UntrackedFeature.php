<?php

namespace App\Features;

class UntrackedFeature extends AbstractFeature
{
    public function key(): string
    {
        return 'untracked';
    }

    public function label(): string
    {
        return 'Untracked access';
    }

    public function description(): string
    {
        return 'Secrets are not tied to the user account and appear untracked.';
    }

    public function defaultOrder(): float
    {
        return 1;
    }
}
