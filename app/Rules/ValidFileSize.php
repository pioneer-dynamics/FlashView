<?php

namespace App\Rules;

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
        $maxBytes = match ($this->userType) {
            'subscribed' => $this->getSubscribedLimit(),
            'user' => config('secrets.file_upload.max_file_size_mb.user') * 1024 * 1024,
            default => 0,
        };

        if ($maxBytes === 0 || $value->getSize() > $maxBytes) {
            $fail('File exceeds the maximum allowed size.');
        }
    }

    private function getSubscribedLimit(): int
    {
        $plan = request()->user()?->resolvePlan();
        $config = $plan?->features['file_upload']['config'] ?? [];
        $maxMb = $config['max_file_size_mb'] ?? config('secrets.file_upload.max_file_size_mb.user');

        return (int) ($maxMb * 1024 * 1024);
    }
}
