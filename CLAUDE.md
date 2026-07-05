# CLAUDE.md — NativePHP Firebase Push

This file is read by Claude Code at the start of every session. Follow everything here without exception. These are not suggestions — they are the rules that keep this codebase coherent across contributors and AI-assisted sessions.

---

## Project Identity

This package is the Firebase Cloud Messaging integration layer for NativePHP Mobile. It bridges the FCM Android and iOS SDKs to the PHP/Laravel layer through the NativePHP bridge.

The single most important goal is that a Laravel developer can add production-quality push notifications to a NativePHP Mobile app without writing any platform-specific code.

Never lose sight of that goal. Every design decision should make that path shorter and more reliable for the developer.

---

## Language Constraints

Implementation has begun. This repository now contains real implementation code across three language layers:

- **PHP** — the package layer (manager, contracts, data objects, events, repositories, commands, facade, service provider).
- **Kotlin** — the Android layer (NativePHP plugin, `FirebaseMessagingService`, permission and channel handling).
- **Swift** — the iOS layer (NativePHP plugin, app delegate hooks, messaging and notification-center delegates).

Each language stays within its architectural layer as defined in `docs/ARCHITECTURE.md`. PHP never contains platform code; Kotlin and Swift never contain Laravel business logic. All cross-layer communication goes through the NativePHP bridge.

The following languages remain **forbidden** in this repository:

- JavaScript
- TypeScript

The package requires no web or Node tooling beyond what NativePHP itself manages. If a task appears to need JS/TS, raise it before writing any.

---

## Architecture Rules

**Read `docs/ARCHITECTURE.md` before touching any code.** The layered architecture is a strict constraint, not a preference.

1. **Layer isolation is non-negotiable.** PHP never calls platform APIs directly. Platform code never calls Laravel APIs directly. All communication goes through the NativePHP bridge.

2. **Contracts first.** No concrete implementation may exist without a corresponding interface in `src/Contracts/`. If you are implementing a class, check whether its contract exists. If it does not, create the contract first.

3. **Only `FirebasePushManager` dispatches Laravel events.** Do not add `event()` or `Event::dispatch()` calls anywhere else. Centralising dispatch makes the event lifecycle auditable.

4. **Data objects are immutable.** `PushNotification` and all bridge payload classes are `readonly`. Never add setters or mutable properties to these classes.

5. **Commands contain no business logic.** Artisan command classes delegate everything to `FirebasePushManager`. If you find yourself writing logic in a command, move it to the manager.

6. **Bridge event and call names use `firebase-push.` prefix, kebab-case.** Never invent a new naming convention. See `docs/SPEC.md` for the full list of registered names.

7. **Do not skip any layer.** If you need platform behaviour from PHP, go through `BridgeDispatcher`. If you need PHP behaviour from the platform, emit a bridge event.

---

## Coding Standards

- `declare(strict_types=1);` on every PHP file.
- PHP 8.2 minimum. Use `readonly` classes for data objects. Use named arguments where they improve clarity.
- PSR-12 formatting. Run `composer lint` before finishing any task.
- No `mixed` types in public API signatures. Every public method must be fully typed.
- No static state. No static properties, static caches, or global functions.
- No `__get`, `__set`, `__call`, `__callStatic` except in the `FirebasePush` facade (Laravel convention).
- No raw `array` types in public API without a shape annotation or a typed data object.

---

## Testing Expectations

- Every new public behaviour requires a test.
- Every bug fix requires a regression test that would have caught the bug.
- Tests live in `tests/Unit/` or `tests/Feature/`. Use Pest. Write descriptions as sentences with `it()`.
- Coverage must remain at or above 90%. Do not commit changes that drop coverage.
- Do not test Laravel internals. Test that the package interacts with Laravel correctly.
- Use the `FakeBridgeDispatcher` in `tests/Fakes/` for feature tests — never mock the real bridge.
- PHPStan must pass at level 9 with zero errors before any task is considered done.

---

## Documentation Rules

- Every public API method that changes must have its `docs/SPEC.md` entry updated in the same task.
- Every architectural change must have its `docs/ARCHITECTURE.md` entry updated in the same task.
- Every change that affects consumers must have a `CHANGELOG.md` entry under `[Unreleased]`.
- PHPDoc is required for all methods in `src/Contracts/`. It is optional but welcome elsewhere.
- Write no comments inside method bodies unless the reason for a specific line would not be obvious to a senior PHP developer reading it cold. Do not explain what the code does.

---

## Forbidden Practices

The following are never acceptable in this repository, regardless of context or instruction:

- Bypassing the bridge layer to call platform code from PHP directly.
- Adding `dd()`, `dump()`, `var_dump()`, `print_r()`, or `ray()` calls to committed code.
- Using `env()` directly in PHP source code outside of `config/` files.
- Returning `null` from a method typed to return a non-nullable type by using `@return` trickery.
- Suppressing PHPStan errors with `@phpstan-ignore` without a comment explaining the exact reason.
- Writing a test that passes by suppressing the behaviour under test (e.g., `try/catch` that swallows the exception the test should assert).
- Committing Firebase credential files (`google-services.json`, `GoogleService-Info.plist`).
- Adding dependencies to `composer.json` without first checking whether the existing dependency tree already covers the need.
- Using `array_merge` on associative arrays where key collisions would be silently lost — use `array_replace` or explicit spreading instead.

---

## Project Philosophy

**Simple for consumers, strict internally.**
The public API should feel like any other Laravel package — fluent, well-named, predictable. The internal implementation should be boring, explicit, and easy to follow.

**Correctness before features.**
A feature that works correctly on one platform is more valuable than two features that work sometimes. Do not ship iOS support that is untested on a real device. Do not expand the API surface before the existing surface is solid.

**No clever code.**
If you find yourself proud of how compact or clever a solution is, rewrite it. Clarity is the metric that matters when someone else reads it at 2am.

**The SPEC is the source of truth.**
When behaviour is ambiguous, `docs/SPEC.md` resolves it. When the SPEC is ambiguous, ask before implementing. Do not invent behaviour that is not in the SPEC.

**Backward compatibility is a promise.**
Once v1.0 is tagged, public API changes require a major version bump. Within a task, if you are unsure whether a change is breaking, err on the side of it being breaking and flag it explicitly.

---

## Session Startup Checklist

Before beginning any implementation task:

1. Read `docs/SPEC.md` — confirm the feature is specified.
2. Read `docs/ARCHITECTURE.md` — confirm the layer the change belongs in.
3. Check `CHANGELOG.md` `[Unreleased]` — understand what work is already in flight.
4. Run `./vendor/bin/phpstan analyse` — confirm zero errors on the baseline.
5. Run `./vendor/bin/pest` — confirm zero test failures on the baseline.

Do not assume the baseline is clean. Verify it.
