<?php

namespace App\Services;

use Illuminate\Support\Str;

class EmailMaskingService
{
    /**
     * Mask an email address, preserving the first character of each part.
     *
     * Examples:
     *
     *   john.doe@example.com  → j*******@e******.com
     *
     *   user@mail.example.com → u***@m***.example.com
     *   a@b.io                → a@b.io
     */
    public function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        $domainParts = explode('.', $domain);
        $tld = array_pop($domainParts);

        // Guard: bare hostname with no dot (e.g. user@localhost).
        // The email validation rule in StoreSecretRequest prevents this in
        // production, but this guard ensures no null-dereference defensively.
        if (empty($domainParts)) {
            return Str::mask($local, '*', 1).'@'.Str::mask($tld, '*', 1);
        }

        $firstLabel = array_shift($domainParts);
        $remaining = count($domainParts) > 0 ? '.'.implode('.', $domainParts) : '';

        return Str::mask($local, '*', 1).'@'.Str::mask($firstLabel, '*', 1).$remaining.'.'.$tld;
    }
}
