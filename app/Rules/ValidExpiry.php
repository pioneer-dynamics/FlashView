<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidExpiry implements ValidationRule
{
    public function __construct(private string $userType) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if(!in_array($value, array_map(fn($expiry) => $expiry['value'], $this->getAllowedExpiryOptions()) )) {
            $fail('The :attribute is invalid.');
        }
    }

    /**
     * Get the allowed expiry options for the user
     */
    private function getAllowedExpiryOptions(): array
    {
        return array_filter(config('secrets.expiry_options'), fn($item) => $item['value'] <= $this->getMaxAllowedExpiry());
    }

    /**
     * Get the allowed expiry for the user
     */
    private function getMaxAllowedExpiry(): int
    {
        $user = request()->user();

        $plan = $user->plan->jsonSerialize();

        return match($this->userType) {
            'subscribed' => $plan['settings']['expiry']['expiry_minutes'],
            'user' => config('secrets.expiry_limits.user'),
            'guest' => config('secrets.expiry_limits.guest'),
        };
    }
}
