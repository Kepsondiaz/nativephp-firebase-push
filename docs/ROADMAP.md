# Roadmap — NativePHP Firebase Push

This roadmap tracks the planned development trajectory from initial proof-of-concept through a stable production release. Each milestone is independently releasable and delivers concrete, testable value.

Milestone scope may be adjusted based on community feedback and upstream NativePHP changes. Nothing past the current milestone is a firm commitment.

---

> **Architecture note (adopted during v0.1):** NativePHP Mobile v3 already owns the
> native Android FCM / iOS APNs integration and exposes it via
> `Native\Mobile\PushNotifications` (`getToken`, `checkPermission`, `enroll`) plus the
> `TokenGenerated` Laravel event. This package therefore **adapts** over that surface
> instead of shipping its own Kotlin/Swift or a custom `firebase-push.*` bridge. Native
> bullets in the milestones below apply only to capabilities NativePHP Mobile does not
> yet expose to PHP. See `docs/ARCHITECTURE.md`.

## v0.1 — Foundation

**Goal:** Establish the PHP package skeleton, the adapter over NativePHP's push API, and token registration. Not intended for production use.

**Deliverables:**

- `composer.json` with correct PSR-4 autoloading and NativePHP v3+ dependency declaration.
- `FirebasePushServiceProvider` registered via auto-discovery.
- All contracts defined (`FirebasePushManager`, `TokenRepository`, `BridgeDispatcher`).
- `FirebasePushManager` concrete implementation — token storage and retrieval only.
- `NativeSessionTokenRepository` (native secure storage) and `CacheTokenRepository`.
- `FirebasePush` facade.
- `PushNotification` data object with `fromBridgePayload` and `toArray`.
- All six Laravel events defined (only `TokenReceived` dispatched at this milestone).
- `NativePushBridge` adapter over `Native\Mobile\PushNotifications`.
- Token registration wired: native `TokenGenerated` event → `handleNativeToken` → `TokenReceived`.
- Not-yet-available API (`revokeToken`, notification/permission callbacks) throws `FeatureNotSupported`.
- Configuration file published via `vendor:publish`.
- `php artisan firebase-push:token` command working.
- Unit + feature tests for `PushNotification`, repositories, manager, and service provider bindings.
- PHPStan at level 5, zero errors.

**Not included:** iOS-specific config, foreground notifications, permission-result events, revocation, background notifications.

---

## v0.2 — Android Complete

**Goal:** Full FCM feature set on Android. iOS scaffolding begins.

**Deliverables:**

- Android: Foreground notification delivery — `onMessageReceived` → `firebase-push.notification-received`.
- Android: Background/killed-state notification tap handling — `firebase-push.notification-tapped`.
- Android: `NotificationChannelManager` creating the default channel from PHP config.
- Android: `PermissionHandler` for `POST_NOTIFICATIONS` runtime permission (Android 13+).
- Android: `BridgeCallHandler` handling all three outbound calls (`request-permission`, `get-token`, `revoke-token`).
- `NotificationReceived`, `NotificationTapped`, `PermissionGranted`, `PermissionDenied`, `TokenRevoked` events dispatched from `FirebasePushManager`.
- `FirebasePush::requestPermission()`, `isPermissionGranted()`, `revokeToken()` fully wired.
- `php artisan firebase-push:test {token}` command working locally.
- Feature tests covering the full Android bridge communication flow (mocked bridge).
- PHPStan raised to level 7.
- `google-services.json` path resolution and build-time copy documented and tested against a real emulator.
- `CHANGELOG.md` introduced.

**Not included:** iOS implementation (structure only).

---

## v0.3 — iOS Complete

**Goal:** iOS reaches full parity with Android. Package enters beta.

**Deliverables:**

- iOS: `FirebasePushPlugin` fully integrated with NativePHP iOS plugin system.
- iOS: `AppDelegateExtension` hooking Firebase configuration and APNs registration.
- iOS: `MessagingDelegate` — `didReceiveRegistrationToken` → `firebase-push.token-received`.
- iOS: `UNUserNotificationCenterDelegate` — foreground and tap events bridged to PHP.
- iOS: `PermissionHandler` wrapping `UNUserNotificationCenter.requestAuthorization`.
- iOS: `BridgeCallHandler` for all three outbound PHP calls.
- `GoogleService-Info.plist` path resolution and build-time copy.
- `ios.request_permission_on_launch` config option wired on the iOS side.
- `ios.badge_handling` automatic and manual modes working.
- Cross-platform feature test matrix expanded to cover iOS bridge flows.
- README published with installation, configuration, and usage documentation.
- PHPStan raised to level 9.
- Tested on real devices: minimum one Android (API 26+) and one iOS (16+) physical device.

---

## v0.4 — Hardening & Developer Experience

**Goal:** Production hardening, edge-case coverage, and DX polish before the stable release.

**Deliverables:**

- Token refresh cycle validated end-to-end on both platforms: `TokenReceived` with `refreshed: true` fires correctly.
- Background notification delivery tested and documented (data-only FCM messages, `content-available: 1` on iOS).
- Notification grouping / collapse key behaviour documented and tested.
- `PushNotification::imageUrl` validated on both platforms (FCM image attachments are platform-dependent).
- `php artisan firebase-push:token` handles the case where no token is yet available with a clear error message and non-zero exit code.
- Config validation: the service provider fails loudly at boot if required config values are missing or the Firebase credential files cannot be found, with actionable error messages.
- Bridge payload versioning: `version` field added to all payloads, backward-compatible deserializer in place.
- All public API methods covered by PHPDoc with `@throws` annotations where applicable.
- Static analysis coverage ≥ 90% line coverage reported by pest `--coverage`.
- Upgrade guide from v0.3 to v0.4 in `CHANGELOG.md`.
- Security policy (`SECURITY.md`) published.

---

## v1.0 — Stable Release

**Goal:** Declare API stability. This release makes semantic versioning guarantees: no breaking public API changes within the v1.x line.

**Deliverables:**

- All v0.x milestones complete with no known production-blocking bugs.
- Complete README covering installation, FCM project setup, Android and iOS configuration, all public API methods, all events, configuration reference, and troubleshooting.
- Tested against the current stable Laravel version and the current stable NativePHP Mobile version.
- CI matrix covering PHP 8.2 and 8.3, Laravel 11 and 12.
- GitHub Actions workflow: lint, test, PHPStan on every push and pull request.
- Packagist release tagged `v1.0.0`.
- GitHub release with release notes summarising all milestone features.
- Publicly announced via NativePHP community channels.

**API stability guarantee:** All interfaces in `src/Contracts/`, all event class shapes, all `PushNotification` properties, and all facade method signatures are stable for the v1.x line. Internal classes (anything not in `Contracts/`, `Events/`, `Data/`, or `Facades/`) may change in minor releases without notice.

---

## Post-v1.0 Backlog (Unscheduled)

The following features have been discussed but are not yet scheduled into a milestone. They will be prioritised based on community need.

- **Rich notifications (Android):** Support for action buttons, reply actions, and custom notification layouts via FCM's advanced options.
- **Notification history:** An optional local log of received notifications stored in NativePHP's SQLite store, queryable via a `FirebasePush::history()` API.
- **Server-side companion package:** A separate `nativephp/firebase-push-server` package for dispatching FCM messages from the Laravel backend using the FCM HTTP v1 API.
- **Topic subscriptions:** `FirebasePush::subscribe(string $topic)` / `unsubscribe(string $topic)` via the FCM SDK's topic management API.
- **Condition messaging:** Support for FCM condition expressions in the server-side companion.
- **Laravel Octane compatibility:** Validate and document Octane (Swoole/RoadRunner) compatibility, particularly for the token repository and event dispatch.
- **Expo / bare React Native interop guide:** Documentation for teams migrating from Expo Notifications to this package.
- **Laravel Nova integration:** A first-party Nova tool for viewing token registrations and sending test notifications from the Nova dashboard.
