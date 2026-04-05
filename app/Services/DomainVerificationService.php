<?php

namespace App\Services;

use App\Models\SenderIdentity;
use Illuminate\Support\Str;

class DomainVerificationService
{
    public function generateToken(): string
    {
        return 'flashview-verification-'.Str::uuid();
    }

    public function verify(SenderIdentity $identity): bool
    {
        if (blank($identity->domain)) {
            return false;
        }

        $records = dns_get_record($identity->domain, DNS_TXT);

        foreach ($records as $record) {
            $txt = $record['txt'] ?? $record['entries'][0] ?? '';
            if ($txt === $identity->verification_token) {
                return true;
            }
        }

        return false;
    }
}
