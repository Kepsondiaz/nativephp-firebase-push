# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Foreground notification delivery: `onNotificationReceived()` and the `NotificationReceived` event.
- Notification tap handling with deep linking: `onNotificationTapped()` and the `NotificationTapped` event, plus a `link` field on `PushNotification` (mapped from the `link` or `url` payload key).
- Permission-result callbacks: `onPermissionGranted()` / `onPermissionDenied()` and the `PermissionGranted` / `PermissionDenied` events.
- `firebase-push:test {token}` command to locally simulate an inbound notification (no server-side FCM call).
- Internal bridge events (`NativeNotificationReceived`, `NativeNotificationTapped`, `NativePermissionResult`) forwarded by the service provider to the manager.
- Converted to a NativePHP plugin (`composer.json` type `nativephp-plugin`, `nativephp.json` manifest).
- Android native plugin drafts (`resources/android/`): `FirebasePushMessagingService.kt` (foreground receive + channel + notification) and `FirebasePushTapActivity.kt` (tap + deep link). **Unverified — pending device testing.**
- `CHANGELOG.md`.

### Changed

- PHPStan raised to level 7.
- `isPermissionGranted()` now treats `provisional` and `ephemeral` statuses as granted, in addition to `granted`.

### Notes

- Notification receiving, tap/deep-linking and permission results are implemented in the PHP layer and tested with the fake bridge. The corresponding Android native plugin code (Kotlin) is still in progress; these features require it to function on a device.

## [0.1.0] — Unreleased

### Added

- Package foundation: `FirebasePushServiceProvider`, `FirebasePush` facade, contracts (`FirebasePushManager`, `TokenRepository`, `BridgeDispatcher`).
- `PushNotification` immutable value object with `fromBridgePayload()` and `toArray()`.
- Token registration over the free `nativephp/mobile` base: `token()`, the `TokenReceived` event and `onTokenReceived()` callbacks, driven by the native `TokenGenerated` event.
- Permission querying (`isPermissionGranted()`) and enrollment (`requestPermission()`).
- Token persistence drivers: `NativeSessionTokenRepository` (native secure storage) and `CacheTokenRepository`.
- `firebase-push:token` command.
- Configuration file (`config/firebase-push.php`).
