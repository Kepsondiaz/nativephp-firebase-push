# NativePHP Firebase Push

A **free, open-source (MIT) alternative to the paid [`nativephp/mobile-firebase`](https://nativephp.com/plugins/nativephp/mobile-firebase) plugin** — Firebase Cloud Messaging (FCM) push notifications for [NativePHP Mobile](https://nativephp.com/mobile) apps, with a Laravel-native developer experience.

> ⚠️ **Status: early development (v0.1).** Token registration works today. Notification receiving, taps/deep links, and server-side sending are on the roadmap. Not yet production-ready.

## Why this exists

NativePHP and its base `nativephp/mobile` package are free (MIT), but they ship only the **token and permission** primitives for push. The fuller Firebase integration — receiving notifications, tap handling, deep linking, data-only background messages, and server-side sending — is sold as the commercial `nativephp/mobile-firebase` plugin ($99 one-time, or bundled in NativePHP Ultra).

This package aims to provide that same feature set, **free**, layered on top of the free base.

| Package | License | Provides |
|---|---|---|
| `nativephp/mobile` | MIT (free) | FCM/APNs token + permission primitives |
| `nativephp/mobile-firebase` | Proprietary (paid) | Full receiving, deep links, data messages, server sending |
| **`kepson/nativephp-firebase-push`** | **MIT (free)** | A free equivalent of the paid plugin, built on the free base |

It **requires** `nativephp/mobile` — it complements NativePHP Mobile, it does not replace it.

## Requirements

- PHP 8.2+
- Laravel 11 / 12
- NativePHP Mobile v3+
- A Firebase project (`google-services.json` for Android, `GoogleService-Info.plist` for iOS)

## Installation

```bash
composer require kepson/nativephp-firebase-push
```

The service provider is auto-discovered. Publish the config with:

```bash
php artisan vendor:publish --tag=firebase-push-config
```

## Usage (available today)

```php
use Kepson\NativePhpFirebasePush\Facades\FirebasePush;

// Current FCM registration token, or null
$token = FirebasePush::token();

// Whether notification permission is granted (no prompt)
FirebasePush::isPermissionGranted();

// Trigger the OS permission prompt + push enrollment
FirebasePush::requestPermission();

// React to token acquisition / refresh
FirebasePush::onTokenReceived(function (string $token, bool $refreshed) {
    // send $token to your backend
});
```

You can also read the current token from the CLI:

```bash
php artisan firebase-push:token
```

Methods for capabilities not yet delivered (`revokeToken`, notification/permission callbacks) throw `Kepson\NativePhpFirebasePush\Exceptions\FeatureNotSupported` until the corresponding milestone lands — see [`docs/ROADMAP.md`](docs/ROADMAP.md).

## Documentation

- [`docs/SPEC.md`](docs/SPEC.md) — public API, events, configuration
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — layered design and the NativePHP adapter
- [`docs/ROADMAP.md`](docs/ROADMAP.md) — milestones and parity targets
- [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md) — how to contribute

## License

MIT.
