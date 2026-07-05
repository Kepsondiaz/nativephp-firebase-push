<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Bridge\Events;

/**
 * Native-layer event dispatched by the plugin's Kotlin/Swift code when the user
 * taps a notification (cold start or resume). Declared in `nativephp.json` under
 * `events`. Carries the raw FCM payload (including any deep-link target); the
 * service provider forwards it to FirebasePushManager, which builds the typed
 * PushNotification and dispatches the public NotificationTapped event.
 *
 * This is an internal bridge event, not part of the consumer-facing API.
 */
final readonly class NativeNotificationTapped
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload = [],
    ) {}
}
