<?php

return [
    'email' => env('SUPPORT_EMAIL', 'support@'.config('app.domain')),
    'legal' => env('LEGAL_EMAIL', 'legal@'.config('app.domain')),
    'security' => env('SECURITY_EMAIL', 'security@'.config('app.domain')),
    'abuse' => env('SECURITY_EMAIL', 'abuse@'.config('app.domain')),
];
