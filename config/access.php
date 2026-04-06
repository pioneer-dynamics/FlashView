<?php

return [
    'enabled' => ! in_array(env('APP_ENV'), ['production', 'prod', 'testing']),
    'allowed_emails' => array_filter(
        array_map('trim', explode(',', env('ACCESS_ALLOWED_EMAILS', '')))
    ),
];
