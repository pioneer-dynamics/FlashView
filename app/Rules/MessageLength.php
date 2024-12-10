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
        $message_length = $this->getActualMessageLength($value);

        if ($message_length > $this->getAllowedMessageLength()) {
            $fail('The :attribute must be at most ' . $this->length . ' characters.');
        }

        if($message_length < $this->min_length) {
            $fail('The :attribute must be at least ' . $this->min_length . ' ' . Str::plural('character', $this->min_length) . '.');
        }
    }

    /**
     * Get the allowed message length based on the user
     */
    private function getAllowedMessageLength(): int
    {
        $user = request()->user();

        return match($this->getUserType()) {
            'subscribed' => $user->plan->settings->messages->message_length,
            'user' => config('secrets.message_length.user'),
            'guest' => config('secrets.message_length.guest'),
        };
    }

    /**
     * Identify the type of user submitting the request
     */
    private function getUserType(): string
    {
        if($user = request()->user()) {
            if($user->subscribed()) {
                return 'subscribed';
            }
            else {
                return 'user';
            }
        }
        else {
            return 'guest';
        }
    }

    /**
     * Get the actual plain-text message length from message
     */
    private function getActualMessageLength(string $message): int
    {
        /**
         * Remove the `salt` from the message
         */
        $ciphertext = substr($message, 16);

        /**
         * Decode the message to get the actual binary
         */
        $binary = base64_decode($ciphertext);

        /**
         * Find the binary length
         */
        $binary_length = strlen($binary);

        /**
         * Get the actual message by subtracting the length of the header (28 bytes)
         */
        $message_length = $binary_length - 28;

        return $message_length;
    }
}
