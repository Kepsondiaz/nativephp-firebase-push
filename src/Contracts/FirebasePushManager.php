<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush\Contracts;

use Kepson\NativePhpFirebasePush\Data\PushNotification;

/**
 * Public entry point for the package. Backed by a single concrete
 * implementation bound as a container singleton and exposed through the
 * `FirebasePush` facade.
 *
 * This is the sole class permitted to dispatch the package's Laravel events.
 * Facade callbacks registered here fire alongside those events (unless
 * `dispatch_events` is disabled). See `docs/SPEC.md` for behavioural detail.
 */
interface FirebasePushManager
{
    /**
     * Return the current device FCM registration token, or null if none has
     * been acquired yet.
     */
    public function token(): ?string;

    /**
     * Trigger the OS-level push notification permission prompt on the device.
     *
     * On Android 13+ this requests the `POST_NOTIFICATIONS` runtime permission.
     * On iOS this invokes `UNUserNotificationCenter.requestAuthorization`.
     * The user's response arrives asynchronously via the permission callbacks
     * and the `PermissionGranted` / `PermissionDenied` events.
     */
    public function requestPermission(): void;

    /**
     * Return whether notification permission is currently granted. Does not
     * trigger a permission prompt.
     */
    public function isPermissionGranted(): bool;

    /**
     * Invalidate the current FCM token and instruct the platform SDK to delete
     * it. Fires the `TokenRevoked` event and invokes registered
     * `onTokenRevoked` callbacks once the platform confirms deletion.
     */
    public function revokeToken(): void;

    /**
     * Register a handler invoked when an FCM registration token is acquired or
     * refreshed.
     *
     * @param  callable(string $token, bool $refreshed): void  $callback
     *                                                                    Receives the token and whether it replaces a previously held one.
     */
    public function onTokenReceived(callable $callback): void;

    /**
     * Register a handler invoked when the FCM token is revoked.
     *
     * @param  callable(): void  $callback
     */
    public function onTokenRevoked(callable $callback): void;

    /**
     * Register a handler invoked when a notification arrives while the app is
     * in the foreground.
     *
     * @param  callable(PushNotification): void  $callback
     */
    public function onNotificationReceived(callable $callback): void;

    /**
     * Register a handler invoked when the user taps a notification that
     * launched or foregrounded the app.
     *
     * @param  callable(PushNotification): void  $callback
     */
    public function onNotificationTapped(callable $callback): void;

    /**
     * Register a handler invoked when the user grants notification permission.
     *
     * @param  callable(): void  $callback
     */
    public function onPermissionGranted(callable $callback): void;

    /**
     * Register a handler invoked when the user denies notification permission.
     *
     * @param  callable(): void  $callback
     */
    public function onPermissionDenied(callable $callback): void;
}
