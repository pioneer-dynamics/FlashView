<?php

return [
    'session_ttl_seconds' => env('PIPE_SESSION_TTL', 600),
    'max_chunk_size_bytes' => env('PIPE_MAX_CHUNK_SIZE', 65536),
    'max_chunks_per_session' => env('PIPE_MAX_CHUNKS', 10000),
    'p2p_timeout_seconds' => env('PIPE_P2P_TIMEOUT', 10),
    'signal_poll_max_seconds' => env('PIPE_SIGNAL_POLL_MAX', 30),
    'stun_servers' => [
        'stun:stun.l.google.com:19302',
        'stun:stun1.l.google.com:19302',
    ],
    'rate_limits' => [
        'guest' => ['create_per_minute' => 5],
        'user' => ['create_per_minute' => 30],
    ],
];
