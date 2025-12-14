<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invite-only Registration
    |--------------------------------------------------------------------------
    |
    | When enabled, users must verify a registration invite code before they
    | can access the registration form or submit registration.
    |
    */
    'invite_only' => (bool) env('INVITE_ONLY', true),
];

