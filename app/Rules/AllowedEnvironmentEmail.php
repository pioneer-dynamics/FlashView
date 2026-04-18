<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class AllowedEnvironmentEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('access.enabled')) {
            return;
        }

        $allowedEmails = array_map('strtolower', config('access.allowed_emails', []));
        $normalisedEmail = Str::lower($value);

        if (empty($allowedEmails) || ! in_array($normalisedEmail, $allowedEmails)) {
            $fail('Registration is restricted on this environment. Contact the team to request access.');
        }
    }
}
