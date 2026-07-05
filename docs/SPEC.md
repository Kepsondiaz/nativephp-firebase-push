# Specification — NativePHP Firebase Push

## Project Vision

NativePHP Firebase Push is a **free, open-source (MIT) alternative to the paid `nativephp/mobile-firebase` plugin**. It provides a first-class, Laravel-native developer experience for sending, receiving, and responding to Firebase Cloud Messaging (FCM) push notifications on Android and iOS — without writing a single line of platform-specific code as a consumer.

It builds on top of the free `nativephp/mobile` base package (MIT), which ships the token and permission primitives, and adds the higher-level capabilities that are otherwise only available in the commercial plugin.

The package is designed to become a community standard: production-ready, opinionated where it improves reliability, and flexible where teams genuinely need customization.

### Relationship to NativePHP Mobile

| Layer | Package | License | Provides |
|---|---|---|---|
| Base runtime | `nativephp/mobile` | MIT (free) | FCM/APNs **token + permission** primitives (`PushNotifications::getToken/checkPermission/enroll/clearBadge`) and the `TokenGenerated` event |
| Commercial plugin | `nativephp/mobile-firebase` | Proprietary ($99 / NativePHP Ultra) | Notification receiving, tap + **deep linking**, data-only background messages, badge handling, **server-side FCM sending** |
| **This package** | `kepson/nativephp-firebase-push` | **MIT (free)** | A free equivalent of the commercial plugin's feature set, layered over the free base |

This package **requires** `nativephp/mobile` (it wraps the free base) and is a drop-in-spirit free alternative to `nativephp/mobile-firebase` — it does not remove the need for NativePHP Mobile itself.

---

## Goals

- Provide a free (MIT) equivalent to the commercial `nativephp/mobile-firebase` plugin so teams are not forced into a paid plugin for standard FCM push.
- Enable Laravel developers to integrate FCM push notifications into NativePHP Mobile apps using familiar Laravel patterns.
- Abstract all Android (Kotlin) and iOS (Swift) FCM SDK details behind a PHP/NativePHP bridge — no platform code required from consumers.
- Support the full FCM notification lifecycle: token registration, foreground and background delivery, user interaction (tap), token refresh, and revocation.
- Expose clean Laravel events so application code can react to any notification state without coupling to platform internals.
- Ship a single `composer require` installation path — no separate npm, Gradle, or CocoaPods steps beyond what NativePHP already manages.
- Maintain strict compatibility with NativePHP v3+ and Laravel 11+.

---

## Non-Goals

- Web push notifications (FCM Web SDK is out of scope).
- Direct APNs integration without FCM (Apple Push Notification service bypassing Firebase is not supported).
- In-app messaging, Firebase Analytics, Firebase Crashlytics, or any other Firebase product beyond FCM.
- Server-side FCM message dispatch is **not** part of the initial mobile-client milestones, but — because the commercial plugin includes it — it is a planned parity feature (see the Roadmap), delivered as pure PHP against the FCM HTTP v1 API. It requires no native code.
- Support for NativePHP Desktop (Electron) — mobile only.
- Support for NativePHP versions below v3.

---

## Supported Platforms

| Platform | Minimum OS Version | FCM Transport |
|---|---|---|
| Android | Android 8.0 (API 26) | FCM via Google Play Services |
| iOS | iOS 16.0 | FCM via APNs |

Both platforms must reach feature parity before any stable release is tagged.

---

## Public API

### Service Provider

The package registers a single Laravel service provider: `Kepson\NativePhpFirebasePush\FirebasePushServiceProvider`.

Auto-discovery via `composer.json` `extra.laravel.providers` handles registration automatically.

### Facade / Contract

```
Kepson\NativePhpFirebasePush\Facades\FirebasePush
Kepson\NativePhpFirebasePush\Contracts\FirebasePushManager
```

#### Methods

```
FirebasePush::token(): ?string
```
Returns the current device FCM registration token, or `null` if no token has been acquired yet.

```
FirebasePush::requestPermission(): void
```
Triggers the OS-level push notification permission prompt on the device. On Android 13+ this requests the `POST_NOTIFICATIONS` runtime permission. On iOS this invokes `UNUserNotificationCenter.requestAuthorization`.

```
FirebasePush::isPermissionGranted(): bool
```
Returns whether the user has granted notification permission. Does not trigger a prompt.

```
FirebasePush::revokeToken(): void
```
Invalidates the current FCM token and instructs the platform SDK to delete it. Fires the `TokenRevoked` event and invokes registered `onTokenRevoked` callbacks once the platform confirms deletion. Typically called on user sign-out.

```
FirebasePush::onTokenReceived(callable $callback): void
```
Registers a handler invoked when an FCM registration token is acquired or refreshed. The callable receives `(string $token, bool $refreshed)`.

```
FirebasePush::onTokenRevoked(callable $callback): void
```
Registers a handler invoked when the FCM token is revoked. The callable receives no arguments.

```
FirebasePush::onNotificationReceived(callable $callback): void
```
Registers a foreground notification handler. The callable receives a `PushNotification` value object.

```
FirebasePush::onNotificationTapped(callable $callback): void
```
Registers a handler invoked when the user taps a notification that launched or foregrounded the app. The callable receives a `PushNotification` value object.

```
FirebasePush::onPermissionGranted(callable $callback): void
```
Registers a handler invoked when the user grants notification permission in response to `requestPermission()`. The callable receives no arguments.

```
FirebasePush::onPermissionDenied(callable $callback): void
```
Registers a handler invoked when the user denies notification permission. The callable receives no arguments.

#### Capability Availability

The package adapts over the native push surface exposed by NativePHP Mobile (`Native\Mobile\PushNotifications` and the `TokenGenerated` event). Where a method's underlying native capability is not yet exposed by NativePHP Mobile, calling it throws `Kepson\NativePhpFirebasePush\Exceptions\FeatureNotSupported` rather than silently doing nothing. As of the current milestone this applies to `revokeToken()`, `onTokenRevoked()`, `onNotificationReceived()`, `onNotificationTapped()`, `onPermissionGranted()`, and `onPermissionDenied()`. `docs/ROADMAP.md` tracks when each becomes available.

### Configuration

Published via `php artisan vendor:publish --tag=firebase-push-config`.

See the [Configuration](#configuration) section for the full schema.

### Artisan Commands

```
php artisan firebase-push:token
```
Outputs the current device FCM token to stdout. Useful during development and debugging.

```
php artisan firebase-push:test {token}
```
Sends a test notification payload through the NativePHP bridge to the local device. Does not make any server-side FCM API call.

---

## Notification Object

All notification data is represented as an immutable value object:

```
Kepson\NativePhpFirebasePush\Data\PushNotification
```

### Properties

| Property | Type | Description |
|---|---|---|
| `id` | `string` | Unique message ID assigned by FCM |
| `title` | `?string` | Notification title |
| `body` | `?string` | Notification body text |
| `imageUrl` | `?string` | Optional image URL attached to the notification |
| `data` | `array<string, string>` | Arbitrary key/value data payload from FCM |
| `sentAt` | `?CarbonImmutable` | UTC timestamp set by the FCM server at send time |
| `receivedAt` | `CarbonImmutable` | UTC timestamp recorded by the device on receipt |
| `channel` | `?string` | Android notification channel ID (Android only) |
| `collapseKey` | `?string` | FCM collapse key, if set |
| `tapped` | `bool` | `true` when the notification was tapped to open the app |
| `foreground` | `bool` | `true` when the notification was received while the app was active |

### Methods

```
PushNotification::fromBridgePayload(array $payload): static
```
Constructs the object from the raw bridge event payload. Internal use only.

```
PushNotification::toArray(): array
```
Returns the notification as a plain array, suitable for serialization or logging.

---

## Events

All events are standard Laravel events dispatchable via `event()` or `Event::dispatch()` and listenable in `EventServiceProvider` or discovered automatically.

### `Kepson\NativePhpFirebasePush\Events\TokenReceived`

Fired when a new FCM registration token is acquired or refreshed.

| Property | Type | Description |
|---|---|---|
| `token` | `string` | The new FCM registration token |
| `refreshed` | `bool` | `true` if this replaces a previously held token |

### `Kepson\NativePhpFirebasePush\Events\NotificationReceived`

Fired when a push notification arrives while the app is in the foreground.

| Property | Type | Description |
|---|---|---|
| `notification` | `PushNotification` | The received notification |

### `Kepson\NativePhpFirebasePush\Events\NotificationTapped`

Fired when the user taps a notification to open the app (cold start or resume).

| Property | Type | Description |
|---|---|---|
| `notification` | `PushNotification` | The tapped notification |

### `Kepson\NativePhpFirebasePush\Events\PermissionGranted`

Fired when the user grants notification permission in response to `requestPermission()`.

### `Kepson\NativePhpFirebasePush\Events\PermissionDenied`

Fired when the user denies notification permission. Carries no additional properties.

### `Kepson\NativePhpFirebasePush\Events\TokenRevoked`

Fired when the FCM token is explicitly invalidated (e.g., the user signs out and the application calls `FirebasePush::revokeToken()`).

---

## Configuration

File: `config/firebase-push.php`

```
[
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
        'default_channel_id'          => env('FIREBASE_ANDROID_CHANNEL_ID', 'default'),
        'default_channel_name'        => env('FIREBASE_ANDROID_CHANNEL_NAME', 'Notifications'),
        'default_channel_description' => env('FIREBASE_ANDROID_CHANNEL_DESCRIPTION', ''),
        'default_channel_importance'  => env('FIREBASE_ANDROID_CHANNEL_IMPORTANCE', 'high'),
    ],

    /*
     * iOS-specific options.
     */
    'ios' => [
        'request_permission_on_launch' => env('FIREBASE_IOS_REQUEST_PERMISSION_ON_LAUNCH', false),
        'badge_handling'               => env('FIREBASE_IOS_BADGE_HANDLING', 'automatic'),
    ],

    /*
     * Token persistence driver.
     * 'session' stores the token in NativePHP's native secure storage
     *           (Android Keystore / iOS Keychain). The default.
     * 'cache'   stores the token in the configured Laravel cache.
     */
    'token_driver' => env('FIREBASE_PUSH_TOKEN_DRIVER', 'session'),

    /*
     * Whether to automatically dispatch Laravel events alongside
     * the FirebasePush facade callbacks.
     */
    'dispatch_events' => true,
]
```

---

## Success Criteria

The package is considered production-ready when all of the following are true:

1. A developer can add FCM push notifications to a new NativePHP Mobile app in under 15 minutes following only the README.
2. FCM tokens are reliably acquired on both Android and iOS within 5 seconds of app first launch on a real device with a network connection.
3. Foreground and background notification delivery is confirmed on physical Android and iOS devices across a matrix of OS versions (Android 8–15, iOS 16–18).
4. Token refresh is transparently handled — the `TokenReceived` event fires with `refreshed: true` and no developer action is required.
5. All public API methods, events, and the configuration schema are covered by unit tests with ≥ 90% line coverage.
6. The package passes static analysis at PHPStan level 9 with zero errors.
7. No breaking changes are introduced between minor versions within the same major.
8. The package works without modification in a fresh Laravel + NativePHP Mobile scaffold project.
