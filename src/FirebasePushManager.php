<?php

declare(strict_types=1);

namespace Kepson\NativePhpFirebasePush;

use Illuminate\Contracts\Events\Dispatcher;
use Kepson\NativePhpFirebasePush\Contracts\BridgeDispatcher;
use Kepson\NativePhpFirebasePush\Contracts\FirebasePushManager as FirebasePushManagerContract;
use Kepson\NativePhpFirebasePush\Contracts\TokenRepository;
use Kepson\NativePhpFirebasePush\Data\PushNotification;
use Kepson\NativePhpFirebasePush\Events\NotificationReceived;
use Kepson\NativePhpFirebasePush\Events\NotificationTapped;
use Kepson\NativePhpFirebasePush\Events\PermissionDenied;
use Kepson\NativePhpFirebasePush\Events\PermissionGranted;
use Kepson\NativePhpFirebasePush\Events\TokenReceived;
use Kepson\NativePhpFirebasePush\Exceptions\FeatureNotSupported;

/**
 * Single concrete implementation of the package's public API and the only class
 * permitted to dispatch the package's Laravel events.
 *
 * Implemented so far: token registration and foreground/tap notification
 * delivery. Revocation and permission-result callbacks arrive in later
 * milestones and currently fail loudly rather than pretend to work.
 */
final class FirebasePushManager implements FirebasePushManagerContract
{
    /**
     * Native permission statuses that count as "granted".
     */
    private const GRANTED_STATUSES = ['granted', 'provisional', 'ephemeral'];

    /** @var list<callable(string, bool): void> */
    private array $onTokenReceived = [];

    /** @var list<callable(PushNotification): void> */
    private array $onNotificationReceived = [];

    /** @var list<callable(PushNotification): void> */
    private array $onNotificationTapped = [];

    /** @var list<callable(): void> */
    private array $onPermissionGranted = [];

    /** @var list<callable(): void> */
    private array $onPermissionDenied = [];

    public function __construct(
        private readonly TokenRepository $tokens,
        private readonly BridgeDispatcher $bridge,
        private readonly Dispatcher $events,
        private readonly bool $dispatchEvents,
    ) {}

    public function token(): ?string
    {
        $stored = $this->tokens->get();

        if ($stored !== null) {
            return $stored;
        }

        $token = $this->bridge->getToken();

        if ($token !== null) {
            $this->tokens->store($token);
        }

        return $token;
    }

    public function requestPermission(): void
    {
        $this->bridge->requestPermission();
    }

    public function isPermissionGranted(): bool
    {
        return in_array($this->bridge->permissionStatus(), self::GRANTED_STATUSES, true);
    }

    public function revokeToken(): void
    {
        throw FeatureNotSupported::method(
            'revokeToken',
            'NativePHP Mobile exposes no token deletion API yet',
        );
    }

    public function onTokenReceived(callable $callback): void
    {
        $this->onTokenReceived[] = $callback;
    }

    public function onTokenRevoked(callable $callback): void
    {
        throw FeatureNotSupported::method('onTokenRevoked', 'token revocation arrives in a later milestone');
    }

    public function onNotificationReceived(callable $callback): void
    {
        $this->onNotificationReceived[] = $callback;
    }

    public function onNotificationTapped(callable $callback): void
    {
        $this->onNotificationTapped[] = $callback;
    }

    public function onPermissionGranted(callable $callback): void
    {
        $this->onPermissionGranted[] = $callback;
    }

    public function onPermissionDenied(callable $callback): void
    {
        $this->onPermissionDenied[] = $callback;
    }

    /**
     * Handle a token delivered by the native TokenGenerated event. Persists the
     * token, fires TokenReceived (when event dispatch is enabled) and invokes
     * registered onTokenReceived callbacks. Deliveries that match the currently
     * stored token are ignored to prevent duplicate events on cold start.
     *
     * Called by the service provider's TokenGenerated listener — not part of
     * the consumer-facing contract.
     */
    public function handleNativeToken(string $token): void
    {
        $previous = $this->tokens->get();

        if ($previous === $token) {
            return;
        }

        $refreshed = $previous !== null;

        $this->tokens->store($token);

        if ($this->dispatchEvents) {
            $this->events->dispatch(new TokenReceived($token, $refreshed));
        }

        foreach ($this->onTokenReceived as $callback) {
            $callback($token, $refreshed);
        }
    }

    /**
     * Handle a foreground notification delivered by the native layer. Builds a
     * PushNotification, dispatches NotificationReceived (when event dispatch is
     * enabled) and invokes registered onNotificationReceived callbacks.
     *
     * Called by the service provider's native listener — not part of the
     * consumer-facing contract.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleNativeNotificationReceived(array $payload): void
    {
        $notification = PushNotification::fromBridgePayload(
            array_replace($payload, ['foreground' => true, 'tapped' => false]),
        );

        if ($this->dispatchEvents) {
            $this->events->dispatch(new NotificationReceived($notification));
        }

        foreach ($this->onNotificationReceived as $callback) {
            $callback($notification);
        }
    }

    /**
     * Handle a notification tap delivered by the native layer (cold start or
     * resume). Builds a PushNotification, dispatches NotificationTapped (when
     * event dispatch is enabled) and invokes registered onNotificationTapped
     * callbacks.
     *
     * Called by the service provider's native listener — not part of the
     * consumer-facing contract.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleNativeNotificationTapped(array $payload): void
    {
        $notification = PushNotification::fromBridgePayload(
            array_replace($payload, ['tapped' => true]),
        );

        if ($this->dispatchEvents) {
            $this->events->dispatch(new NotificationTapped($notification));
        }

        foreach ($this->onNotificationTapped as $callback) {
            $callback($notification);
        }
    }

    /**
     * Handle a permission-prompt result delivered by the native layer. Dispatches
     * PermissionGranted or PermissionDenied (when event dispatch is enabled) and
     * invokes the matching callbacks. An undecided status (e.g. not_determined)
     * dispatches nothing.
     *
     * Called by the service provider's native listener — not part of the
     * consumer-facing contract.
     */
    public function handleNativePermissionResult(string $status): void
    {
        if (in_array($status, self::GRANTED_STATUSES, true)) {
            if ($this->dispatchEvents) {
                $this->events->dispatch(new PermissionGranted);
            }

            foreach ($this->onPermissionGranted as $callback) {
                $callback();
            }

            return;
        }

        if ($status === 'denied') {
            if ($this->dispatchEvents) {
                $this->events->dispatch(new PermissionDenied);
            }

            foreach ($this->onPermissionDenied as $callback) {
                $callback();
            }
        }
    }
}
