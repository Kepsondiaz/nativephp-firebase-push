<?php

declare(strict_types=1);

return [

    /*
     * The path to the google-services.json file (Android).
     * Relative to the project root or absolute.
     */
    'google_services_json' => env('FIREBASE_GOOGLE_SERVICES_JSON', 'google-services.json'),

    /*
     * The path to the GoogleService-Info.plist file (iOS).
     * Relative to the project root or absolute.
     */
    'google_service_info_plist' => env('FIREBASE_GOOGLE_SERVICE_INFO_PLIST', 'GoogleService-Info.plist'),

    /*
     * Android notification channel configuration.
     * Applied when the package registers the default FCM notification channel.
     */
    'android' => [
        'default_channel_id' => env('FIREBASE_ANDROID_CHANNEL_ID', 'default'),
        'default_channel_name' => env('FIREBASE_ANDROID_CHANNEL_NAME', 'Notifications'),
        'default_channel_description' => env('FIREBASE_ANDROID_CHANNEL_DESCRIPTION', ''),
        'default_channel_importance' => env('FIREBASE_ANDROID_CHANNEL_IMPORTANCE', 'high'),
    ],

    /*
     * iOS-specific options.
     */
    'ios' => [
        'request_permission_on_launch' => env('FIREBASE_IOS_REQUEST_PERMISSION_ON_LAUNCH', false),
        'badge_handling' => env('FIREBASE_IOS_BADGE_HANDLING', 'automatic'),
    ],

    /*
     * Token persistence driver.
     * 'session' stores the token in NativePHP's native key-value store.
     * 'cache'   stores the token in the configured Laravel cache.
     */
    'token_driver' => env('FIREBASE_PUSH_TOKEN_DRIVER', 'session'),

    /*
     * Whether to automatically dispatch Laravel events alongside
     * the FirebasePush facade callbacks.
     */
    'dispatch_events' => true,

];
