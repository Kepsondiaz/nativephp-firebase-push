<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Bridge\Events;

/**
 * Native-layer event dispatched by the plugin's Kotlin/Swift code when a
 * notification arrives in the foreground. Declared in `nativephp.json` under
 * `events`. Carries the raw FCM payload; the service provider forwards it to
 * FirebasePushManager, which builds the typed PushNotification and dispatches
 * the public NotificationReceived event.
 *
 * This is an internal bridge event, not part of the consumer-facing API.
 */
final readonly class NativeNotificationReceived
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload = [],
    ) {}
}
