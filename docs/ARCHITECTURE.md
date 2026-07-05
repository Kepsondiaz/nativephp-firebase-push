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

- Holds a reference to the `TokenRepository`, the `BridgeDispatcher`, and the Laravel event dispatcher.
- Provides all public facade methods (`token()`, `requestPermission()`, `isPermissionGranted()`, `revokeToken()`, and the `on*` callback registrars).
- Stores registered callbacks and invokes them alongside the corresponding Laravel event.
- Exposes `handleNativeToken()`, the internal seam the service provider's `TokenGenerated` listener calls; it is the only place the package dispatches events.

Methods whose native capability is not yet exposed by NativePHP Mobile throw `Exceptions\FeatureNotSupported` (see the Roadmap for scheduling).

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

NativePHP Mobile v3 already owns the platform (Android FCM / iOS APNs) push integration and exposes it to PHP. This package does **not** reimplement the native SDK integration or invent a custom bridge; it **adapts** over what NativePHP Mobile provides. This is a deliberate decision recorded during v0.1 implementation after inspecting the actual `nativephp/mobile` dependency.

NativePHP Mobile exposes push over two mechanisms:

**Outbound (PHP → Native), synchronous**
`Native\Mobile\PushNotifications` provides synchronous calls that return a value directly:

| NativePHP call | Purpose |
|---|---|
| `PushNotifications::getToken()` | Return the current FCM (Android) / APNs (iOS) token, or null |
| `PushNotifications::checkPermission()` | Return the permission status string without prompting |
| `PushNotifications::enroll()` | Request permission and enroll for push notifications |

**Inbound (Native → PHP), asynchronous**
The native runtime dispatches a standard Laravel event, `Native\Mobile\Events\PushNotification\TokenGenerated`, carrying `(string $token, ?string $id)` when a token is generated.

### Package Bridge Contract

`Contracts\BridgeDispatcher` is the seam the package adapts over. Its sole concrete implementation, `Bridge\NativePushBridge`, is the only class in the package that references `Native\Mobile` directly.

| `BridgeDispatcher` method | Delegates to |
|---|---|
| `getToken(): ?string` | `PushNotifications::getToken()` |
| `permissionStatus(): ?string` | `PushNotifications::checkPermission()` |
| `requestPermission(): void` | `PushNotifications::enroll()` |

Inbound token delivery is **not** modelled on the contract. The service provider registers a listener for the native `TokenGenerated` event and forwards it to `FirebasePushManager::handleNativeToken()`, keeping event dispatch centralised in the manager.

Because NativePHP owns the native layer, the package ships **no Kotlin or Swift** for token registration. Custom native code would only be required for behaviours NativePHP does not yet expose (e.g. foreground notification / tap delivery), and is deferred to the milestone that needs it.

---

## Platform Layer (owned by NativePHP Mobile)

The Android (FCM) and iOS (APNs → FCM) SDK integration, permission prompts, token generation, and build-time placement of `google-services.json` / `GoogleService-Info.plist` are all handled by NativePHP Mobile. This package consumes them through `NativePushBridge` and the `TokenGenerated` event.

Capabilities NativePHP Mobile does **not** currently expose to PHP — and which this package therefore cannot yet deliver — include foreground `NotificationReceived` / `NotificationTapped` events, permission-result events, and token revocation. The corresponding public methods exist on the contract but fail loudly with `Exceptions\FeatureNotSupported` until the underlying capability is available. See `docs/ROADMAP.md` for when each is scheduled.

---

## Communication Flow

### Token Registration (Happy Path)

```
App Launch
    │
    ▼
[NativePHP Mobile] Native FCM/APNs SDK initialises and generates a token
    │
    ▼
[NativePHP Mobile] Dispatches Native\Mobile ...\TokenGenerated { token, id }
    │
    ▼
[PHP] Service provider's listener forwards to FirebasePushManager::handleNativeToken()
    │
    ├── Skips duplicates matching the stored token
    ├── Persists token via TokenRepository
    ├── Fires TokenReceived Laravel event (when dispatch_events is true)
    └── Invokes registered onTokenReceived callbacks
```

### Token Retrieval (Synchronous)

```
PHP: FirebasePush::token()
    │
    ├── Returns the persisted token when present, otherwise:
    ▼
[PHP] BridgeDispatcher::getToken() → Native\Mobile PushNotifications::getToken()
    │
    ▼
[PHP] Persists and returns the token (no event fired on passive fetch)
```

### Permission Request Flow

```
PHP: FirebasePush::requestPermission()
    │
    ▼
[PHP] BridgeDispatcher::requestPermission() → PushNotifications::enroll()
    │
    ▼
[NativePHP Mobile] Shows the OS permission prompt and enrolls for push
    │
    ▼
On success, a token is delivered asynchronously via the TokenGenerated flow above
```

### Foreground Notification & Permission-Result Flows

Not yet available: NativePHP Mobile does not currently expose foreground `NotificationReceived` / `NotificationTapped` or permission-result events to PHP. The corresponding contract methods throw `FeatureNotSupported` until the platform provides these signals. See `docs/ROADMAP.md`.

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
│   │   └── NativePushBridge.php       — adapter over Native\Mobile\PushNotifications
│   │
│   ├── Commands/
│   │   └── TokenCommand.php
│   │
│   ├── Contracts/
│   │   ├── FirebasePushManager.php
│   │   ├── TokenRepository.php
│   │   └── BridgeDispatcher.php
│   │
│   ├── Data/
│   │   └── PushNotification.php       — immutable notification value object
│   │
│   ├── Exceptions/
│   │   └── FeatureNotSupported.php    — thrown for not-yet-available API
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

**The native layer is owned by NativePHP Mobile.** This package adapts over `Native\Mobile\PushNotifications` and the `TokenGenerated` event rather than shipping its own Kotlin/Swift or a custom bridge. It reaches the native layer only through `Bridge\NativePushBridge`.

**Unavailable capabilities fail loudly.** Contract methods whose underlying native capability NativePHP does not yet expose throw `Exceptions\FeatureNotSupported` rather than silently no-op. This keeps the public API honest about what a given milestone actually delivers.

**Raw payload parsing is isolated.** Inbound payloads are mapped to the immutable `Data\PushNotification` via its `fromBridgePayload()` named constructor, so raw array keys never leak into the rest of the codebase.
