# Contributing — NativePHP Firebase Push

Thank you for investing time in this project. NativePHP Firebase Push aims to be the community standard for FCM push notifications in NativePHP Mobile applications, and that quality comes from the contributors who care enough to hold the bar high.

Please read this guide before opening a pull request. It exists to make reviews faster and to keep the codebase coherent as it grows.

---

## Ground Rules

- **Be respectful.** Review the code, not the person.
- **Be specific.** Vague comments are not actionable. Vague code is not mergeable.
- **Prefer discussion before large changes.** If you intend to change the public API, add a new integration, or restructure the architecture, open an issue first. Building on top of a rejected design is frustrating for everyone.
- **One concern per pull request.** A PR that fixes a bug, adds a feature, and reformats the codebase will wait in review while all three are evaluated. Split them.

---

## Setting Up a Development Environment

### Requirements

- PHP 8.2 or 8.3
- Composer 2.x
- A physical Android device or emulator (API 26+) for integration work
- A physical iOS device or simulator (iOS 16+) for integration work
- A Firebase project with `google-services.json` (Android) and `GoogleService-Info.plist` (iOS) — keep these out of version control

### Installation

```bash
git clone https://github.com/your-org/nativephp-firebase-push.git
cd nativephp-firebase-push
composer install
```

### Running Tests

```bash
# All tests
./vendor/bin/pest

# With coverage
./vendor/bin/pest --coverage --min=90

# Static analysis
./vendor/bin/phpstan analyse
```

Tests must pass and coverage must remain at or above 90% before a PR is ready for review.

---

## Coding Standards

### PHP

- **PSR-12** formatting enforced by PHP-CS-Fixer. Run `composer lint` before committing. The CI pipeline will fail on formatting violations.
- **PHP 8.2 minimum.** Use `readonly` classes, named arguments, first-class callable syntax, and intersection types where they improve clarity. Do not use features not available in 8.2.
- **Strict types.** Every PHP file must begin with `declare(strict_types=1);`.
- **No mixed types in public API.** Public method signatures must have complete type coverage. `mixed` is not allowed in public contracts.
- **Immutability by default.** Data objects (`PushNotification`, payload classes) must be `readonly`. Nothing on these classes should mutate after construction.
- **No static state outside the container.** Do not use static properties or static caches. All state lives on objects managed by the Laravel container.
- **No magic.** Do not use `__get`, `__set`, `__call`, or `__callStatic` outside of the `FirebasePush` facade, where it is Laravel convention.
- **No global helpers.** Do not add global PHP helper functions. The facade provides the public entry point.

### Naming

- Classes: `PascalCase`.
- Methods and properties: `camelCase`.
- Constants: `UPPER_SNAKE_CASE`.
- Configuration keys: `snake_case`.
- Bridge event names: `kebab-case` with the `firebase-push.` namespace prefix.
- Test methods: descriptive snake_case, written as sentences (e.g., `it_dispatches_token_received_event_when_token_arrives`).

### Architecture Rules

- Every public behaviour must be expressed as an interface in `src/Contracts/` before it is implemented.
- Classes in `src/Events/` must never dispatch events themselves. Only `FirebasePushManager` dispatches events.
- Classes in `src/Data/` must never depend on Laravel, Illuminate, or NativePHP internals.
- Classes in `src/Commands/` must contain no business logic — delegate everything to the manager.
- The `FirebasePushManager` may depend on contracts, never on concrete implementations directly.

---

## Comments and Documentation

- Write no comments unless the reason for the code is not obvious from reading it. A comment that says what the code does is noise.
- PHPDoc is required for all public methods in `src/Contracts/`. It is optional elsewhere.
- Every public method that can throw must declare `@throws` in its PHPDoc.
- Do not write TODO comments in committed code. Open a GitHub issue instead.

---

## Commit Messages

This project follows **Conventional Commits** (https://www.conventionalcommits.org).

### Format

```
<type>(<scope>): <short description>

[optional body]

[optional footer]
```

### Types

| Type | When to use |
|---|---|
| `feat` | A new feature or capability |
| `fix` | A bug fix |
| `docs` | Documentation changes only |
| `test` | Adding or updating tests, no production code changes |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `chore` | Build process, dependency updates, tooling |
| `perf` | Performance improvement |

### Scopes

Use the package area affected: `android`, `ios`, `bridge`, `events`, `commands`, `config`, `docs`, `ci`.

### Rules

- The short description is imperative mood, lowercase, no period: `add token refresh support`, not `Added token refresh support.`
- Keep the subject line under 72 characters.
- The body explains *why*, not *what*. The diff shows what changed.
- Breaking changes must include `BREAKING CHANGE:` in the footer with a description of what breaks and how to migrate.

### Examples

```
feat(android): emit token-refresh bridge event on onNewToken

fix(bridge): prevent duplicate token-received events on cold start

docs(contributing): add commit message scope table

test(events): cover TokenRevoked dispatch path

feat(ios)!: require iOS 16 minimum

BREAKING CHANGE: dropped iOS 15 support to align with NativePHP Mobile
minimum requirements introduced in NativePHP v3.2.
```

---

## Branching Strategy

### Branches

| Branch | Purpose |
|---|---|
| `main` | Stable, always releasable. Protected. Direct pushes blocked. |
| `develop` | Integration branch. All feature branches merge here first. |
| `feature/<name>` | New features and non-trivial changes. Branch from `develop`. |
| `fix/<name>` | Bug fixes. Branch from the affected release branch (or `develop` if unreleased). |
| `docs/<name>` | Documentation-only changes. Branch from `develop`. |
| `release/v<semver>` | Release preparation. Branch from `develop`. |
| `hotfix/<name>` | Critical production fixes. Branch from `main`, merged to `main` and `develop`. |

### Workflow

1. Branch from `develop` (or `main` for hotfixes).
2. Work in small, focused commits.
3. Open a PR targeting `develop`.
4. Ensure CI passes.
5. Request review from at least one maintainer.
6. Squash-merge after approval, unless the commit history is intentionally structured (multi-commit features with clear, atomic commits).
7. Delete the branch after merge.

---

## Testing

### Philosophy

Tests are first-class citizens. A feature without tests is not complete. A bug fix without a regression test is not fixed — it will recur.

### Test Types

**Unit tests** (`tests/Unit/`) cover individual classes in isolation. Dependencies are replaced with fakes or stubs defined in the test file. Unit tests must run in under 100ms total.

**Feature tests** (`tests/Feature/`) cover the integration between package components — typically the bridge listener → manager → event dispatch chain. These tests use a fake bridge dispatcher (shipped in `tests/Fakes/`) that simulates native events without requiring a real device.

**No real device tests in CI.** Device/emulator tests are manual pre-release checks. Document them in the release checklist; do not add them to the automated suite.

### Coverage

- Minimum 90% line coverage enforced by CI via `--min=90`.
- Dropping below 90% blocks merge.
- Increasing coverage is always welcome, but do not write tests purely for coverage — test the behaviour, not the lines.

### Conventions

- Use Pest (https://pestphp.com) for all tests.
- Use `it()` for test descriptions written as sentences.
- Use `dataset()` / `with()` for parametrised cases.
- Use `beforeEach()` for shared setup, never static fixtures shared across test files.
- Do not test framework behaviour (Laravel's own event dispatching, Artisan routing). Test that the package calls the framework correctly.

---

## Pull Request Process

### Before Opening

- Run `composer lint` and fix any violations.
- Run `./vendor/bin/phpstan analyse` and fix any errors.
- Run `./vendor/bin/pest --coverage --min=90` and confirm it passes.
- Update `CHANGELOG.md` under `[Unreleased]` with a brief description of your change.
- If you changed the public API, update the relevant section of `docs/SPEC.md`.
- If you changed the architecture, update `docs/ARCHITECTURE.md`.

### PR Description Template

```
## What

[One paragraph describing what this PR changes.]

## Why

[One paragraph describing why this change is needed. Link to the relevant issue.]

## Testing

[Describe how you tested this change. Include platform (Android/iOS) and OS version if applicable.]

## Checklist

- [ ] Tests pass
- [ ] Coverage ≥ 90%
- [ ] PHPStan level 9, zero errors
- [ ] CHANGELOG.md updated
- [ ] Docs updated (if applicable)
```

### Review Expectations

- Maintainers aim to respond to PRs within 72 hours.
- One approving review from a maintainer is required to merge.
- Reviewers will not merge PRs that fail CI.
- Reviewers will leave specific, actionable comments. Respond to every comment, either with a code change or a clear explanation of why the change was not made.

---

## Release Process

Releases follow **Semantic Versioning** (https://semver.org).

| Change | Version increment |
|---|---|
| Backward-incompatible public API change | Major (`1.0.0` → `2.0.0`) |
| New backward-compatible functionality | Minor (`1.0.0` → `1.1.0`) |
| Backward-compatible bug fix | Patch (`1.0.0` → `1.0.1`) |

### Steps

1. Create a `release/vX.Y.Z` branch from `develop`.
2. Update `CHANGELOG.md`: move everything under `[Unreleased]` to a new `[X.Y.Z] — YYYY-MM-DD` section.
3. Update the version constant in `src/FirebasePushServiceProvider.php` (if present).
4. Open a PR from `release/vX.Y.Z` → `main`.
5. After approval and merge, tag `main` with `vX.Y.Z` and push the tag.
6. Merge `main` back into `develop` to keep histories aligned.
7. Create a GitHub Release from the tag, copying the `CHANGELOG.md` section as the release notes.
8. Packagist will auto-update via the GitHub webhook.

### Hotfixes

1. Branch `hotfix/<name>` from `main`.
2. Fix, test, and update `CHANGELOG.md`.
3. PR → `main`. After merge, tag the patch version.
4. PR `main` → `develop` to carry the fix forward.

---

## Reporting Security Issues

Do not open a public GitHub issue for security vulnerabilities. Email the maintainers directly at the address listed in `SECURITY.md`. We aim to respond within 48 hours and will coordinate a fix and disclosure timeline with you.
