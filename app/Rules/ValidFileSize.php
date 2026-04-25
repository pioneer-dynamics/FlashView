<?php

namespace App\Rules;

use App\Services\FeatureRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidFileSize implements ValidationRule
{
    public function __construct(private string $userType) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->userType === 'subscribed') {
            $plan = request()->user()?->resolvePlan();
            $config = $plan?->features['file_upload']['config'] ?? [];

            if (! app(FeatureRegistry::class)->get('file_upload')->withinLimit($value->getSize(), $config)) {
                $fail('File exceeds the maximum allowed size.');
            }

            return;
        }

        $maxBytes = match ($this->userType) {
            'user' => config('secrets.file_upload.max_file_size_mb.user') * 1024 * 1024,
            default => 0,
        };

        if ($maxBytes === 0 || $value->getSize() > $maxBytes) {
            $fail('File exceeds the maximum allowed size.');
        }
    }
}
