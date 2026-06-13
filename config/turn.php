<?php

return [
    'host' => env('TURN_HOST', '0.0.0.0'),
    'port' => (int) env('TURN_PORT', 3478),
    'public_ip' => env('TURN_PUBLIC_IP', ''),   // Optional — auto-detected on startup if empty (see TurnServer::resolvePublicIp())
    'realm' => env('TURN_REALM', 'flashview.io'),
    'username' => env('TURN_USERNAME', 'flash'),
    'password' => env('TURN_PASSWORD', 'changeme'),
    'allocation_ttl' => (int) env('TURN_ALLOCATION_TTL', 600),
    'relay_min_port' => (int) env('TURN_RELAY_MIN_PORT', 49152),
    'relay_max_port' => (int) env('TURN_RELAY_MAX_PORT', 65535),
];
