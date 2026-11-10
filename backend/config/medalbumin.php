<?php

return [
    // Set true to force every admin to set up an authenticator app (optional by default).
    'require_admin_2fa' => env('REQUIRE_ADMIN_2FA', false),

    // Shown on the public site and in the app's "About".
    'play_store_url' => env('PLAY_STORE_URL'),
    'app_store_url' => env('APP_STORE_URL'),

    // Video compression (Admin → Video library). Full paths if not on PATH.
    'ffmpeg' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),

    // Minimum app version the API supports; older apps are asked to update.
    'min_app_version' => env('MIN_APP_VERSION', '1.0.0'),

    // Languages are managed in Admin → Languages (table `languages`); the list of
    // languages that may be added is in config/languages.php.
];
