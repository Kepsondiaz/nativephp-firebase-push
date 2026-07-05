<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Contracts;

/**
 * Abstraction over NativePHP Mobile's native push mechanism.
 *
 * NativePHP Mobile v3 already owns the platform (Android FCM / iOS APNs) SDK
 * integration and exposes it synchronously via `Native\Mobile\PushNotifications`
 * plus the `TokenGenerated` Laravel event. This contract is the seam the package
 * adapts over, so the manager never depends on NativePHP facades directly and
 * tests can substitute a fake.
 *
 * Inbound token delivery is not modelled here: it arrives asynchronously as the
 * native `Native\Mobile\Events\PushNotification\TokenGenerated` Laravel event,
 * wired to the manager by the service provider.
 */
interface BridgeDispatcher
{
    /**
     * Return the current push registration token from the native layer
     * (FCM on Android, APNs on iOS), or null if none is available yet.
     */
    public function getToken(): ?string;

    /**
     * Return the raw native permission status without prompting the user.
     * One of: `granted`, `denied`, `not_determined`, `provisional`,
     * `ephemeral`, or null when it cannot be determined.
     */
    public function permissionStatus(): ?string;

    /**
     * Trigger the native permission prompt and push enrollment. The resulting
     * token is delivered asynchronously via the TokenGenerated event.
     */
    public function requestPermission(): void;
}
