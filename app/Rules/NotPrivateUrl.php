<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NotPrivateUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! $host) {
            $fail('The :attribute must contain a valid host.');

            return;
        }

        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'])) {
            $fail('The :attribute must not point to a local address.');

            return;
        }

        $ips = gethostbynamel($host);

        if ($ips === false) {
            $fail('The :attribute hostname could not be resolved.');

            return;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail('The :attribute must not point to a private or reserved IP address.');

                return;
            }
        }
    }
}
