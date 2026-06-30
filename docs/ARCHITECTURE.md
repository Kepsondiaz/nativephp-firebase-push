# Architecture — NativePHP Firebase Push

## Overview

NativePHP Firebase Push is a layered package. Each layer has one responsibility and communicates only with the layer directly adjacent to it. The PHP application layer never speaks to the platform SDK directly; the platform layer never speaks to Laravel business logic directly.

```
┌─────────────────────────────────────────────┐
│            Laravel Application               │
│    (EventServiceProvider, Listeners, etc.)  │
└────────────────────┬────────────────────────┘
                     │  Laravel Events / Facade
┌────────────────────▼────────────────────────┐
│         Package PHP Layer                    │
│  FirebasePushManager · Events · DataObjects │
└────────────────────┬────────────────────────┘
                     │  NativePHP Bridge API
┌────────────────────▼────────────────────────┐
│         NativePHP Bridge                    │
│     (event dispatch ↔ native calls)         │
└──────────┬──────────────────────┬───────────┘
           │                      │
┌──────────▼──────────┐  ┌────────▼────────────┐
│   Android Layer     │  │    iOS Layer         │
│  (Kotlin / FCM SDK) │  │  (Swift / FCM SDK)   │
└─────────────────────┘  └─────────────────────┘
```

No layer may skip another. A platform event always travels upward through the bridge before reaching Laravel. A PHP call always travels downward through the bridge before reaching the platform SDK.

---

## PHP Architecture

### Service Provider

`FirebasePushServiceProvider` is the package entry point. It:

- Binds `FirebasePushManager` into the service container as a singleton behind the `FirebasePushManager` contract.
- Registers the `FirebasePush` facade alias.
- Merges the package configuration.
- Registers Artisan commands.
- Boots the bridge listener that wires incoming native events to Laravel event dispatch.

### Contracts

All public behaviours are expressed as interfaces in `src/Contracts/`. Concrete classes depend on contracts, not on each other, enabling substitution in tests without mocking framework internals.

```
Contracts/
    FirebasePushManager.php    — main facade contract
    TokenRepository.php        — persistence contract for the FCM token
    BridgeDispatcher.php       — abstraction over NativePHP's native call mechanism
```

### FirebasePushManager

`src/FirebasePushManager.php` is the single concrete implementation of `FirebasePushManager`. It:

- Holds a reference to the `TokenRepository` and `BridgeDispatcher`.
- Provides all public facade methods (`token()`, `requestPermission()`, `isPermissionGranted()`, `revokeToken()`).
- Registers foreground and tap callbacks through the bridge dispatcher.
- Fires Laravel events after each native event is received and after each facade action.

### Data Objects

`src/Data/PushNotification.php` is a readonly PHP class (PHP 8.2+). It carries no business logic. Construction from a raw bridge payload is isolated to a named constructor `fromBridgePayload(array $payload)` so the rest of the codebase never touches raw array keys.

### Events

All events live in `src/Events/`. Each event is a plain PHP class implementing no interface beyond Laravel's convention of being an event. Events are dispatched by `FirebasePushManager` only; no other class dispatches them. This keeps event dispatch centralised and easy to audit.

### Token Repository

Two concrete token repository implementations are shipped:

- `src/Repositories/NativeSessionTokenRepository.php` — stores the token in NativePHP's key-value native session store. The default.
- `src/Repositories/CacheTokenRepository.php` — stores the token in the configured Laravel cache store. Suitable for web-first apps that also have a mobile client.

The active implementation is resolved by `FirebasePushServiceProvider` based on the `token_driver` config key.

### Artisan Commands

`src/Commands/` contains thin Artisan command classes. Commands delegate all logic to `FirebasePushManager`; they contain no business logic themselves.

### Facades

`src/Facades/FirebasePush.php` is a standard Laravel facade pointing to the `FirebasePushManager` binding. No logic lives in the facade class itself.

---

## NativePHP Bridge

NativePHP Mobile exposes a bidirectional bridge between PHP (running on-device via the NativePHP runtime) and native platform code. The bridge has two directions:

**Outbound (PHP → Native)**
PHP code calls a bridge method by name, optionally passing a JSON-serialisable payload. The NativePHP runtime routes the call to a registered native handler on the correct platform.

**Inbound (Native → PHP)**
The native platform code dispatches a named bridge event carrying a JSON payload. The NativePHP runtime delivers this event to the registered PHP listener.

### Package Bridge Contract

The package registers the following bridge event names it listens for:

| Bridge Event Name | Trigger |
|---|---|
| `firebase-push.token-received` | FCM SDK delivers a registration token |
| `firebase-push.notification-received` | Notification arrives in foreground |
| `firebase-push.notification-tapped` | User taps a notification |
| `firebase-push.permission-granted` | OS permission prompt accepted |
| `firebase-push.permission-denied` | OS permission prompt rejected |
| `firebase-push.token-revoked` | Token invalidated |

The package registers the following outbound bridge call names:

| Bridge Call Name | Purpose |
|---|---|
| `firebase-push.request-permission` | Invoke OS permission prompt |
| `firebase-push.get-token` | Request current token from FCM SDK |
| `firebase-push.revoke-token` | Instruct FCM SDK to delete the token |

All payload keys are `camelCase` strings. All timestamps are ISO 8601 UTC strings. The bridge payload schema for each event is defined in `src/Bridge/Payloads/` as readonly PHP classes used purely for documentation and deserialization validation.

---

## Android Architecture

The Android layer integrates the Firebase Android SDK (specifically `firebase-messaging`) within the NativePHP Mobile Android host application.

### Components

**FirebasePushPlugin**
The top-level NativePHP plugin class registered with the NativePHP Android plugin system. It owns the lifecycle of all FCM interactions and is the only class allowed to call NativePHP's outbound bridge dispatch.

**PushNotificationService**
Extends `FirebaseMessagingService`. Handles:
- `onNewToken(token: String)` — emits `firebase-push.token-received` through the bridge.
- `onMessageReceived(message: RemoteMessage)` — maps the FCM `RemoteMessage` to the bridge payload schema and emits `firebase-push.notification-received` or stores the payload for later retrieval when the app was in the background.

**NotificationChannelManager**
Creates and configures Android `NotificationChannel` objects using the channel configuration forwarded from the PHP config layer through the bridge at app startup.

**PermissionHandler**
Manages the `POST_NOTIFICATIONS` runtime permission request/response cycle (required on Android 13+). Emits `firebase-push.permission-granted` or `firebase-push.permission-denied` after the user responds.

**BridgeCallHandler**
Receives inbound bridge calls from PHP (`firebase-push.request-permission`, `firebase-push.get-token`, `firebase-push.revoke-token`) and routes them to the appropriate Android component.

### Configuration Injection

At build time, NativePHP copies `google-services.json` from the PHP project root into the Android build directory. The Android Firebase SDK locates this file automatically by convention — no manual wiring is required.

---

## iOS Architecture

The iOS layer integrates the Firebase iOS SDK (`FirebaseMessaging`) within the NativePHP Mobile iOS host application.

### Components

**FirebasePushPlugin**
The top-level NativePHP plugin class registered with the NativePHP iOS plugin system. Mirrors the Android design: it owns FCM lifecycle and is the only class allowed to dispatch bridge events.

**AppDelegateExtension**
Hooks into the NativePHP app delegate lifecycle to:
- Call `FirebaseApp.configure()` at launch using the bundled `GoogleService-Info.plist`.
- Register for remote notifications (`UIApplication.registerForRemoteNotifications()`).
- Forward APNs device tokens to the FCM SDK via `Messaging.messaging().apnsToken`.

**MessagingDelegate**
Implements `MessagingDelegate`. Handles:
- `messaging(_:didReceiveRegistrationToken:)` — emits `firebase-push.token-received`.
- `messaging(_:didReceive:)` — processes foreground notifications and emits `firebase-push.notification-received`.

**UNUserNotificationCenterDelegate**
Implements `UNUserNotificationCenterDelegate`. Handles:
- `userNotificationCenter(_:willPresent:withCompletionHandler:)` — allows foreground notification display and emits `firebase-push.notification-received`.
- `userNotificationCenter(_:didReceive:withCompletionHandler:)` — emits `firebase-push.notification-tapped` when the user taps a notification.

**PermissionHandler**
Wraps `UNUserNotificationCenter.requestAuthorization(options:)`. Emits `firebase-push.permission-granted` or `firebase-push.permission-denied`.

**BridgeCallHandler**
Receives inbound bridge calls from PHP and routes them to the appropriate iOS component.

### Configuration Injection

At build time, NativePHP copies `GoogleService-Info.plist` from the PHP project root into the iOS app bundle. The Firebase SDK locates this file automatically by convention.

---

## Communication Flow

### Token Registration (Happy Path)

```
App Launch
    │
    ▼
[Android/iOS] FCM SDK initialises
    │
    ▼
[Android/iOS] SDK delivers token via onNewToken / didReceiveRegistrationToken
    │
    ▼
[Android/iOS] BridgeDispatcher emits firebase-push.token-received { token, refreshed }
    │
    ▼
[NativePHP Bridge] Routes event to PHP runtime
    │
    ▼
[PHP] FirebasePushManager receives event
    │
    ├── Persists token via TokenRepository
    ├── Fires TokenReceived Laravel event
    └── Invokes registered onTokenReceived callbacks
```

### Foreground Notification (Happy Path)

```
FCM Server sends notification
    │
    ▼
[Android/iOS] App is in foreground — FCM SDK delivers to service/delegate
    │
    ▼
[Android/iOS] Maps RemoteMessage → bridge payload
    │
    ▼
[Android/iOS] BridgeDispatcher emits firebase-push.notification-received { ... }
    │
    ▼
[NativePHP Bridge] Routes event to PHP runtime
    │
    ▼
[PHP] FirebasePushManager constructs PushNotification from payload
    │
    ├── Fires NotificationReceived Laravel event
    └── Invokes registered onNotificationReceived callbacks
```

### Permission Request Flow

```
PHP: FirebasePush::requestPermission()
    │
    ▼
[PHP] FirebasePushManager calls BridgeDispatcher.call('firebase-push.request-permission')
    │
    ▼
[NativePHP Bridge] Routes call to platform
    │
    ▼
[Android/iOS] PermissionHandler shows OS prompt
    │
    ▼
User responds
    │
    ├── Granted → emit firebase-push.permission-granted
    └── Denied  → emit firebase-push.permission-denied
         │
         ▼
    [PHP] FirebasePushManager fires PermissionGranted or PermissionDenied event
```

---

## Directory Layout

```
nativephp-firebase-push/
│
├── config/
│   └── firebase-push.php              — publishable package configuration
│
├── docs/
│   ├── SPEC.md
│   ├── ARCHITECTURE.md
│   ├── ROADMAP.md
│   └── CONTRIBUTING.md
│
├── src/
│   ├── Bridge/
│   │   ├── BridgeDispatcher.php       — concrete NativePHP bridge dispatcher
│   │   └── Payloads/                  — bridge payload schema classes (readonly)
│   │       ├── TokenReceivedPayload.php
│   │       ├── NotificationPayload.php
│   │       └── PermissionPayload.php
│   │
│   ├── Commands/
│   │   ├── TokenCommand.php
│   │   └── TestNotificationCommand.php
│   │
│   ├── Contracts/
│   │   ├── FirebasePushManager.php
│   │   ├── TokenRepository.php
│   │   └── BridgeDispatcher.php
│   │
│   ├── Data/
│   │   └── PushNotification.php       — immutable notification value object
│   │
│   ├── Events/
│   │   ├── TokenReceived.php
│   │   ├── TokenRevoked.php
│   │   ├── NotificationReceived.php
│   │   ├── NotificationTapped.php
│   │   ├── PermissionGranted.php
│   │   └── PermissionDenied.php
│   │
│   ├── Facades/
│   │   └── FirebasePush.php
│   │
│   ├── Repositories/
│   │   ├── NativeSessionTokenRepository.php
│   │   └── CacheTokenRepository.php
│   │
│   ├── FirebasePushManager.php        — main implementation
│   └── FirebasePushServiceProvider.php
│
├── tests/
│   ├── Unit/
│   │   ├── Data/
│   │   ├── Events/
│   │   ├── Repositories/
│   │   └── Bridge/
│   └── Feature/
│       ├── TokenRegistrationTest.php
│       ├── NotificationReceiptTest.php
│       ├── PermissionTest.php
│       └── FacadeTest.php
│
├── CLAUDE.md
├── LICENSE
├── README.md
├── composer.json
└── phpstan.neon
```

---

## Key Architectural Decisions

**No static state.** All state (token, callbacks) lives on the `FirebasePushManager` singleton, which is managed by the Laravel container. This makes the manager fully testable via container swapping.

**Events are not the callbacks.** The facade `onNotificationReceived` callbacks and the Laravel events serve different audiences. Callbacks are for inline application logic in service providers. Events are for decoupled listeners. Both are always fired; neither is optional when `dispatch_events` is true.

**Platform code is read-only from PHP's perspective.** PHP never pushes configuration into the platform layer at runtime except through explicit bridge calls. Platform components are self-contained — they do not poll PHP.

**Payload schema is versioned.** The bridge payload shape is defined in `src/Bridge/Payloads/` with a `version` field. Future breaking changes to the payload shape will increment the version and maintain a backward-compatible deserializer for one major version.
