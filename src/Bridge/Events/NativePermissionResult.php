<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Bridge\Events;

/**
 * Native-layer event dispatched by the plugin's Kotlin/Swift code when the user
 * responds to the notification permission prompt. Declared in `nativephp.json`
 * under `events`. Carries the raw permission status string (`granted`, `denied`,
 * `provisional`, `ephemeral`, `not_determined`); the service provider forwards
 * it to FirebasePushManager, which dispatches the public PermissionGranted or
 * PermissionDenied event.
 *
 * This is an internal bridge event, not part of the consumer-facing API.
 */
final readonly class NativePermissionResult
{
    public function __construct(
        public string $status = 'denied',
    ) {}
}
