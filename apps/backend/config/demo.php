<?php

return [
    'admin_email' => env('DEV_ADMIN_EMAIL'),
    'admin_password' => env('DEV_ADMIN_PASSWORD'),
    'seed_password' => env('DEV_SEED_PASSWORD'),
    'owner_email' => env('DEV_OWNER_EMAIL', 'owner@trotamundos.local'),
    'platform_email' => env('DEV_PLATFORM_EMAIL', 'platform@trotamundos.local'),
];
