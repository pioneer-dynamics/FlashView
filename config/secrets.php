<?php

return [

    /**
     * Expiry options
     */
    'expiry_options' => [
        [
            'label' => '5 minutes',
            'value' => 5,
        ],
        [
            'label' => '30 minutes',
            'value' => 30,
        ],
        [
            'label' => '1 hour',
            'value' => 60,
        ],
        [
            'label' => '4 hours',
            'value' => 240,
        ],
        [
            'label' => '12 hours',
            'value' => 720,
        ],
        [
            'label' => '1 day',
            'value' => 1440,
        ],
        [
            'label' => '3 days',
            'value' => 4320,
        ],
        [
            'label' => '7 days',
            'value' => 10080,
        ],
        [
            'label' => '14 days',
            'value' => 20160,
        ],
        [
            'label' => '30 days',
            'value' => 43200,
        ],
    ],

    /**
     * Max expiry limits for a non-susbscribed user or guest (in minutes)
     */
    'expiry_limits' => [
        'guest' => 10080,
        'user' => 20160,
    ],

    /**
     * Max message length for a non-susbscribed user or guest
     */
    'message_length' => [
        'guest' => env('GUEST_SECRET_MESSAGE_LENGTH', 160),
        'user' => env('USER_SECRET_MESSAGE_LENGTH', 1000),
    ],

    /**
     * Number of days for which to keep the metadata related to a message
     */
    'prune_after' => env('SECRET_PRUNE_AFTER_EXPIRY_DAYS_PLUS', 30),

    /**
     * File upload limits and allowed MIME types
     */
    'file_upload' => [
        'max_file_size_mb' => [
            'guest' => 0,
            'user' => env('USER_SECRET_FILE_SIZE_LIMIT_MB', 10),
        ],
        'max_active_file_secrets' => env('MAX_ACTIVE_FILE_SECRETS_PER_USER', 10),
        'allowed_mime_types' => [
            // Documents
            'application/pdf',
            'application/zip',
            'application/x-zip-compressed',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            // Images
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            // Video/Audio
            'video/mp4',
            'video/quicktime',
            'audio/mpeg',
            'audio/wav',
        ],
    ],

    /**
     * Rate limits for a non-susbscribed user or guest
     */
    'rate_limit' => [
        'guest' => [
            'per_minute' => env('GUEST_SECRET_RATE_LIMIT_PER_MINUTE', 3),
            'per_day' => env('GUEST_SECRET_RATE_LIMIT_PER_DAY', 10),
        ],
        'user' => [
            'per_minute' => env('USER_SECRET_RATE_LIMIT_PER_MINUTE', 60),
        ],
    ],
];
