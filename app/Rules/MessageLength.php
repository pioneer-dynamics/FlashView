<?php

namespace App\Rules;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Contracts\Validation\ValidationRule;

class MessageLength implements ValidationRule
{
    public function __construct(private int $length, private int $min_length = 1)
    {
        
    }
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ciphertext = substr($value, 16);

        $binary = base64_decode($ciphertext);

        $binary_length = strlen($binary);

        $message_length = $binary_length - 28; // 28 is the length of the header

        if ($message_length > $this->length) {
            $fail('The :attribute must be at most ' . $this->length . ' characters.');
        }

        if($message_length < $this->min_length) {
            $fail('The :attribute must be at least ' . $this->min_length . ' ' . Str::plural('character', $this->min_length) . '.');
        }
    }
}
