<?php

namespace App\Rules;

use App\Services\FeatureRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

class MessageLength implements ValidationRule
{
    public function __construct(private string $userType, private int $min_length = 1) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $messageLength = $this->getActualMessageLength($value);

        if (! $this->isWithinLimit($messageLength)) {
            $fail('The :attribute must be at most '.$this->getAllowedMessageLength().' characters.');
        }

        if ($messageLength < $this->min_length) {
            $fail('The :attribute must be at least '.$this->min_length.' '.Str::plural('character', $this->min_length).'.');
        }
    }

    private function isWithinLimit(int $messageLength): bool
    {
        if ($this->userType === 'guest') {
            return $messageLength <= $this->getAllowedMessageLength();
        }

        $plan = request()->user()?->resolvePlan();
        $config = $plan?->features['messages']['config'] ?? [];

        return app(FeatureRegistry::class)->get('messages')->withinLimit($messageLength, $config);
    }

    private function getAllowedMessageLength(): int
    {
        if ($this->userType === 'guest') {
            return config('secrets.message_length.guest');
        }

        $plan = request()->user()?->resolvePlan();
        $config = $plan?->features['messages']['config'] ?? [];

        return $config['message_length'] ?? config('secrets.message_length.user');
    }

    /**
     * Get the actual plain-text message length from message
     */
    private function getActualMessageLength(string $message): int
    {
        $ciphertext = substr($message, 16);
        $binary = base64_decode($ciphertext);
        $binary_length = strlen($binary);

        return $binary_length - 28;
    }
}
