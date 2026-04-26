<?php

namespace App\Rules;

use App\Services\FeatureRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidExpiry implements ValidationRule
{
    public function __construct(private string $userType) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array($value, array_map(fn ($expiry) => $expiry['value'], $this->getAllowedExpiryOptions()))) {
            $fail('The :attribute is invalid.');
        }
    }

    private function getAllowedExpiryOptions(): array
    {
        if ($this->userType === 'guest') {
            return array_filter(config('secrets.expiry_options'), fn ($item) => $item['value'] <= config('secrets.expiry_limits.guest'));
        }

        $plan = request()->user()?->resolvePlan();

        if (! $plan) {
            return array_filter(config('secrets.expiry_options'), fn ($item) => $item['value'] <= config('secrets.expiry_limits.user'));
        }

        $config = $plan->features['expiry']['config'] ?? [];

        return array_filter(
            config('secrets.expiry_options'),
            fn ($item) => app(FeatureRegistry::class)->get('expiry')->withinLimit($item['value'], $config)
        );
    }
}
