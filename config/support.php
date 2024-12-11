<?php

return [
    'email' => env('SUPPORT_EMAIL'),
    'legal' => env('LEGAL_EMAIL'),
    'security' => env('SECURITY_EMAIL', 'security@'.config('app.domain')),
];