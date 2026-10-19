<?php

/*
 | Authentication is intentionally deferred until an approved Identity task
 | defines its user model and persistence. Keep this foundation fail-closed:
 | do not name an authentication model that is not part of the application.
 */

return [
    'defaults' => [
        'guard' => null,
        'passwords' => null,
    ],

    'guards' => [],
    'providers' => [],
    'passwords' => [],
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
